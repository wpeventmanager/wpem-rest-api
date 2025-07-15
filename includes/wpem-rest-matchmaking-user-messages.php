<?php
defined('ABSPATH') || exit;

class WPEM_REST_Send_Message_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'send-message';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();
       register_rest_route($this->namespace, '/' . $this->rest_base, array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array($this, 'handle_send_message'),
			'permission_callback' => array($auth_controller, 'check_authentication'),
			'args' => array(
				'senderId'   => array('required' => true),
				'receiverId' => array('required' => true),
				'message'    => array('required' => false),
				'image'      => array('required' => false),
			),
		));

        register_rest_route($this->namespace, '/get-messages', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_get_messages'),
            'permission_callback' => array($auth_controller, 'check_authentication'),
            'args' => array(
                'senderId'   => array('required' => true, 'type' => 'integer'),
                'receiverId' => array('required' => true, 'type' => 'integer'),
                'page'       => array('required' => false, 'type' => 'integer', 'default' => 1),
				'per_page'   => array('required' => false, 'type' => 'integer', 'default' => 20),
            ),
        ));
        // Get conversation list endpoint
		register_rest_route($this->namespace, '/get-conversation-list', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array($this, 'handle_get_conversation_list'),
			'permission_callback' => array($auth_controller, 'check_authentication'),
			'args' => array(
				'user_id'   => array('required' => true, 'type' => 'integer'),
				'event_ids' => array('required' => true, 'type' => 'array', 'items' => array('type' => 'integer')),
				'paged'     => array('required' => false, 'type' => 'integer', 'default' => 1),
				'per_page'  => array('required' => false, 'type' => 'integer', 'default' => 10),
			),
		));
    }
     public function handle_send_message($request) {
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
			], 403);
		}

		$sender_id   = intval($request->get_param('senderId'));
		$receiver_id = intval($request->get_param('receiverId'));
		$text_message = sanitize_textarea_field($request->get_param('message'));

		$sender_user = get_user_by('id', $sender_id);
		$receiver_user = get_user_by('id', $receiver_id);

		if (!$sender_user || !$receiver_user) {
			return new WP_REST_Response([
				'code'    => 404,
				'status'  => 'Not Found',
				'message' => 'Sender or Receiver not found.',
			], 404);
		}

		// Check message notifications
		$table_users = $wpdb->prefix . 'wpem_matchmaking_users';
		$sender_notify = $wpdb->get_var($wpdb->prepare("SELECT message_notification FROM $table_users WHERE user_id = %d", $sender_id));
		$receiver_notify = $wpdb->get_var($wpdb->prepare("SELECT message_notification FROM $table_users WHERE user_id = %d", $receiver_id));

		if ($sender_notify != 1 || $receiver_notify != 1) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Forbidden',
				'message' => 'Both sender and receiver must have message notifications enabled.',
			], 403);
		}

		// Handle file upload if present
		$uploaded_file_url = '';
		if (!empty($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$uploaded = wp_handle_upload($_FILES['image'], ['test_form' => false]);

			if (!isset($uploaded['error'])) {
				$uploaded_file_url = esc_url_raw($uploaded['url']);
			}
		}

		// Final message logic
		$final_message = '';
		if ($text_message && $uploaded_file_url) {
			$final_message = $text_message . "\n\n" . $uploaded_file_url;
		} elseif ($text_message) {
			$final_message = $text_message;
		} elseif ($uploaded_file_url) {
			$final_message = $uploaded_file_url;
		} else {
			return new WP_REST_Response([
				'code'    => 400,
				'status'  => 'Bad Request',
				'message' => 'Message or image is required.',
			], 400);
		}

		// Save message
		$table = $wpdb->prefix . 'wpem_matchmaking_users_messages';

		$first_message_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM $table 
			 WHERE (sender_id = %d AND receiver_id = %d)
				OR (sender_id = %d AND receiver_id = %d)
			 ORDER BY created_at ASC LIMIT 1",
			$sender_id, $receiver_id,
			$receiver_id, $sender_id
		));
		$parent_id = $first_message_id ?: 0;

		$wpdb->insert($table, [
			'parent_id'   => $parent_id,
			'sender_id'   => $sender_id,
			'receiver_id' => $receiver_id,
			'message'     => $final_message,
			'created_at'  => current_time('mysql')
		], ['%d', '%d', '%d', '%s', '%s']);

		$insert_id = $wpdb->insert_id;

		// Send email
		wp_mail(
			$receiver_user->user_email,
			'New Message from ' . $sender_user->display_name,
			$final_message,
			['Content-Type: text/plain; charset=UTF-8']
		);

		return new WP_REST_Response([
			'code'       => 200,
			'status'     => 'OK',
			'message'    => 'Message sent successfully.',
			'data'       => [
				'id'         => $insert_id,
				'parent_id'  => $parent_id,
				'sender_id'  => $sender_id,
				'receiver_id'=> $receiver_id,
				'message'    => $final_message,
				'created_at' => current_time('mysql'),
			]
		], 200);
	}

    public function handle_get_messages($request) {
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response(array(
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			), 403);
		}

		$sender_id   = intval($request->get_param('senderId'));
		$receiver_id = intval($request->get_param('receiverId'));
		$page        = max(1, intval($request->get_param('page')));
		$per_page    = max(1, intval($request->get_param('per_page')));

		if (!$sender_id || !$receiver_id) {
			return new WP_REST_Response([
				'code'    => 400,
				'status'  => 'Bad Request',
				'message' => 'senderId and receiverId are required.',
				'data'    => null
			], 400);
		}

		$offset = ($page - 1) * $per_page;
		$table = $wpdb->prefix . 'wpem_matchmaking_users_messages';

		// Get total message count
		$total_messages = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $table 
			 WHERE (sender_id = %d AND receiver_id = %d) 
				OR (sender_id = %d AND receiver_id = %d)",
			$sender_id, $receiver_id, $receiver_id, $sender_id
		));

		// Get paginated messages
		$messages = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table 
			 WHERE (sender_id = %d AND receiver_id = %d) 
				OR (sender_id = %d AND receiver_id = %d)
			 ORDER BY created_at DESC
			 LIMIT %d OFFSET %d",
			$sender_id, $receiver_id, $receiver_id, $sender_id,
			$per_page, $offset
		), ARRAY_A);

		$total_pages = ceil($total_messages / $per_page);

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Messages retrieved successfully.',
			'data'    => [
				'total_page_count' => intval($total_messages),
				'current_page'     => $page,
				'last_page'        => $total_pages,
				'total_pages'      => $total_pages,
				'messages'         => $messages,
			]
		], 200);
	}
    public function handle_get_conversation_list($request) {
		global $wpdb;

		$user_id   = intval($request->get_param('user_id'));
		$event_ids = $request->get_param('event_ids'); // expects an array
		$paged     = max(1, intval($request->get_param('paged')));
		$per_page  = max(1, intval($request->get_param('per_page')));

		if (empty($user_id) || empty($event_ids) || !is_array($event_ids)) {
			return new WP_REST_Response([
				'code'    => 400,
				'status'  => 'Bad Request',
				'message' => 'user_id and event_ids[] are required.',
			], 400);
		}

		$postmeta     = $wpdb->postmeta;
		$messages_tbl = $wpdb->prefix . 'wpem_matchmaking_users_messages';
		$profile_tbl  = $wpdb->prefix . 'wpem_matchmaking_users';

		// Step 1: Get users registered in the same events (excluding self)
		$event_placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
		$registered_user_ids = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT pm2.meta_value
			FROM $postmeta pm1
			INNER JOIN $postmeta pm2 ON pm1.post_id = pm2.post_id
			WHERE pm1.meta_key = '_event_id'
			  AND pm1.meta_value IN ($event_placeholders)
			  AND pm2.meta_key = '_attendee_user_id'
			  AND pm2.meta_value != %d
		", array_merge($event_ids, [ $user_id ])));

		if (empty($registered_user_ids)) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'No users registered in the same events.',
				'data'    => []
			], 200);
		}

		// Step 2: Get users who have messaged with current user
		$messaged_user_ids = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT user_id FROM (
				SELECT sender_id AS user_id FROM $messages_tbl WHERE receiver_id = %d
				UNION
				SELECT receiver_id AS user_id FROM $messages_tbl WHERE sender_id = %d
			) AS temp
			WHERE user_id != %d
		", $user_id, $user_id, $user_id));

		if (empty($messaged_user_ids)) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'No message history found.',
				'data'    => []
			], 200);
		}

		// Step 3: Intersect both lists
		$valid_user_ids = array_values(array_intersect($registered_user_ids, $messaged_user_ids));

		$total_count = count($valid_user_ids);
		if ($total_count === 0) {
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'No matched users found.',
				'data'    => [
					'total_users'   => 0,
					'current_page'  => $paged,
					'total_pages'   => 0,
					'users'         => []
				]
			], 200);
		}

		// Step 4: Paginate
		$offset = ($paged - 1) * $per_page;
		$paginated_ids = array_slice($valid_user_ids, $offset, $per_page);

		// Step 5: Build user info with last message
		$results = [];
		foreach ($paginated_ids as $uid) {
			// Get last message exchanged
			$last_message_row = $wpdb->get_row($wpdb->prepare("
				SELECT message, created_at
				FROM $messages_tbl
				WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)
				ORDER BY created_at DESC
				LIMIT 1
			", $user_id, $uid, $uid, $user_id));

			$results[] = [
				'user_id'       => (int) $uid,
				'first_name'    => get_user_meta($uid, 'first_name', true),
				'last_name'     => get_user_meta($uid, 'last_name', true),
				'profile_photo' => $wpdb->get_var($wpdb->prepare("SELECT profile_photo FROM $profile_tbl WHERE user_id = %d", $uid)),
				'profession'    => $wpdb->get_var($wpdb->prepare("SELECT profession FROM $profile_tbl WHERE user_id = %d", $uid)),
				'company_name'  => $wpdb->get_var($wpdb->prepare("SELECT company_name FROM $profile_tbl WHERE user_id = %d", $uid)),
				'last_message'  => $last_message_row ? $last_message_row->message : null,
				'message_time'  => $last_message_row ?  date('Y-m-d H:i:s', strtotime($last_message_row->created_at)) : null,
			];
		}

		$last_page = ceil($total_count / $per_page);

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Filtered users retrieved.',
			'data'    => [
				'total_users'   => $total_count,
				'current_page'  => $paged,
				'per_page'      => $per_page,
				'last_page'     => $last_page,
				'users'         => $results
			]
		], 200);
	}
}

new WPEM_REST_Send_Message_Controller();
