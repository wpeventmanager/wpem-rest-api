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
                'senderId'   => array('required' => true, 'type' => 'integer'),
                'receiverId' => array('required' => true, 'type' => 'integer'),
                'message'    => array('required' => true, 'type' => 'string'),
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
    }

    public function handle_send_message($request) {
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
        $message     = sanitize_textarea_field($request->get_param('message'));

        $sender_user   = get_user_by('id', $sender_id);
        $receiver_user = get_user_by('id', $receiver_id);
        if (!$sender_user || !$receiver_user) {
            return new WP_REST_Response([
                'code'    => 404,
                'status'  => 'Not Found',
                'message' => 'Sender or Receiver not found.',
                'data'    => null
            ], 404);
        }
		// Check message_notification in wp_wpem_matchmaking_users table
		$table_users = $wpdb->prefix . 'wpem_matchmaking_users';

		$sender_notify = $wpdb->get_var(
			$wpdb->prepare("SELECT message_notification FROM $table_users WHERE user_id = %d", $sender_id)
		);

		$receiver_notify = $wpdb->get_var(
			$wpdb->prepare("SELECT message_notification FROM $table_users WHERE user_id = %d", $receiver_id)
		);

		if ($sender_notify != 1 || $receiver_notify != 1) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Forbidden',
				'message' => 'Both sender and receiver must have message notifications enabled to send messages.',
			], 403);
		}

        $first_name = get_user_meta($sender_id, 'first_name', true);
        $last_name  = get_user_meta($sender_id, 'last_name', true);

        // Determine parent_id (new conversation for the day = parent_id 1)
        $table = $wpdb->prefix . 'wpem_matchmaking_users_messages';
        $today = date('Y-m-d');

        $first_message_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM $table 
			 WHERE (sender_id = %d AND receiver_id = %d)
				OR (sender_id = %d AND receiver_id = %d)
			 ORDER BY created_at ASC LIMIT 1",
			$sender_id, $receiver_id,
			$receiver_id, $sender_id
		));

		$parent_id = $first_message_id ? $first_message_id : 0;

        $inserted = $wpdb->insert($table, array(
            'parent_id'   => $parent_id,
            'sender_id'   => $sender_id,
            'receiver_id' => $receiver_id,
            'message'     => $message,
            'created_at'  => current_time('mysql')
        ), array('%d', '%d', '%d', '%s', '%s'));

        if (!$inserted) {
            return new WP_REST_Response([
                'code'    => 500,
                'status'  => 'Error',
                'message' => 'Failed to save message.',
                'data'    => $wpdb->last_error
            ], 500);
        }

        wp_mail(
            $receiver_user->user_email,
            'New Message from ' . $sender_user->display_name,
            "You have received a new message:\n\n" . $message . "\n\nFrom: " . $sender_user->display_name,
            array('Content-Type: text/plain; charset=UTF-8')
        );

        return new WP_REST_Response([
            'code'    => 200,
            'status'  => 'OK',
            'message' => 'Message sent and saved successfully.',
            'data'    => array(
                'id'         => $wpdb->insert_id,
                'parent_id'  => $parent_id,
                'sender_id'  => $sender_id,
                'receiver_id'=> $receiver_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'message'    => $message,
                'created_at' => current_time('mysql'),
            )
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
			 ORDER BY created_at ASC
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
}

new WPEM_REST_Send_Message_Controller();
