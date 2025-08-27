<?php
defined('ABSPATH') || exit;

class WPEM_REST_Send_Message_Controller extends WPEM_REST_CRUD_Controller{

    protected $namespace = 'wpem';
    protected $rest_base = 'send-message';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for the objects of the controller.
     *
     * @since 1.1.0
     */
    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();
       register_rest_route($this->namespace, '/' . $this->rest_base, array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array($this, 'wpem_send_matchmaking_messages'),
			'args' => array(
				'senderId'   => array('required' => true),
				'receiverId' => array('required' => true),
				'message'    => array('required' => false),
				'image'      => array('required' => false),
			),
		));

        register_rest_route($this->namespace, '/get-messages', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'wpem_get_matchmaking_messages'),
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
			'callback' => array($this, 'wpem_get_matchmaking_conversation_list'),
			'args' => array(
				'user_id'   => array('required' => true, 'type' => 'integer'),
				'event_ids' => array('required' => true, 'type' => 'array', 'items' => array('type' => 'integer')),
				'paged'     => array('required' => false, 'type' => 'integer', 'default' => 1),
				'per_page'  => array('required' => false, 'type' => 'integer', 'default' => 10),
			),
		));
    }
    /**
     * Handles sending a message to a user.
     *
     * @param WP_REST_Request $request {
     *     @type int    $senderId   The ID of the user sending the message.
     *     @type int    $receiverId The ID of the user receiving the message.
     *     @type string $message    The message being sent. Optional.
     *     @type string $image      The image being sent. Optional.
     * }
     *
     * @return WP_REST_Response
	 * @since 1.1.0
     */
    public function wpem_send_matchmaking_messages($request) {
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
			], 403);
		}
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$sender_id     = intval($request->get_param('senderId'));
			$receiver_id   = intval($request->get_param('receiverId'));
			$text_message  = sanitize_textarea_field($request->get_param('message'));
			
			// Get minimal user objects just for email addresses
			$sender_user   = get_user_by('id', $sender_id);
			$receiver_user = get_user_by('id', $receiver_id);

			if (!$sender_user || !$receiver_user) {
				return new WP_REST_Response([
					'code'    => 404,
					'status'  => 'Not Found',
					'message' => 'Sender or Receiver not found.',
				], 404);
			}

			// Get other user data from meta
			$sender_first  = get_user_meta($sender_id, 'first_name', true);
			$sender_last   = get_user_meta($sender_id, 'last_name', true);
			$sender_display_name = trim("$sender_first $sender_last");

			$receiver_first = get_user_meta($receiver_id, 'first_name', true);
			$receiver_last  = get_user_meta($receiver_id, 'last_name', true);
			$receiver_display_name = trim("$receiver_first $receiver_last");

			// Get notification preferences
			$sender_notify   = get_user_meta($sender_id, '_message_notification', true);
			$receiver_notify = get_user_meta($receiver_id, '_message_notification', true);

			if ($sender_notify != 1 || $receiver_notify != 1) {
				return new WP_REST_Response([
					'code'    => 403,
					'status'  => 'Forbidden',
					'message' => 'Both sender and receiver must have message notifications enabled.',
				], 403);
			}

			$image_url = '';
			if (!empty($_FILES['image']['tmp_name'])) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$uploaded = wp_handle_upload($_FILES['image'], ['test_form' => false]);
				if (!isset($uploaded['error'])) {
					$image_url = esc_url_raw($uploaded['url']);
				}
			}
			
			$final_message = '';
			if ($text_message && $image_url) {
				$final_message = $text_message . "\n\n" . $image_url;
			} elseif ($text_message) {
				$final_message = $text_message;
			} elseif ($image_url) {
				$final_message = $image_url;
			} else {
				return new WP_REST_Response([
					'code'    => 400,
					'status'  => 'Bad Request',
					'message' => 'Either message or image is required.',
				], 400);
			}

			// Insert into DB
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

			// --- EMAIL SECTION ---
			$headers = ['Content-Type: text/html; charset=UTF-8'];

			// Build email body for receiver (your format)
			$receiver_body  = "Hello, this is a message from {$sender_first}<br><br>";
			$receiver_body .= "First Name: {$sender_first}<br>";
			$receiver_body .= "Last Name: {$sender_last}<br>";
			$receiver_body .= "Message:<br>" . nl2br(esc_html($text_message)) . "<br><br>";
			if ($image_url) {
				$receiver_body .= "<p><img src='{$image_url}' alt='Attachment' style='max-width:400px;'></p>";
			}
			$receiver_body .= "Thank you.";

			wp_mail(
				$receiver_user->user_email,
				'New Message from ' . $sender_first,
				$receiver_body,
				$headers
			);

			// Build confirmation email for sender
			$sender_body  = "Hello, this is a confirmation of your message to {$receiver_first}<br><br>";
			$sender_body .= "First Name: {$sender_first}<br>";
			$sender_body .= "Last Name: {$sender_last}<br>";
			$sender_body .= "Message:<br>" . nl2br(esc_html($text_message)) . "<br><br>";
			if ($image_url) {
				$sender_body .= "<p><img src='{$image_url}' alt='Attachment' style='max-width:400px;'></p>";
			}
			$sender_body .= "Thank you.";

			wp_mail(
				$sender_user->user_email,
				'Your Message to ' . $receiver_first,
				$sender_body,
				$headers
			);

			// --- END EMAIL SECTION ---
			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Message sent successfully.',
				'data'    => [
					'id'         => $insert_id,
					'parent_id'  => $parent_id,
					'sender_id'  => $sender_id,
					'receiver_id'=> $receiver_id,
					'message'    => $text_message ?: null,
					'image'      => $image_url ?: null,
					'created_at' => current_time('mysql'),
				]
			], 200);
		}
	}
	/**
	 * Retrieve messages between two users
	 * 
	 * Retrieves a list of messages between the sender and receiver, paginated
	 * 
	 * @param WP_REST_Request $request The request object.
	 * @since 1.1.0
	 * @return WP_REST_Response
	 */
     public function wpem_get_matchmaking_messages($request) {
		global $wpdb;

		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response(array(
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			), 403);
		}
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
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
			// Separate text and image
			foreach ($messages as &$msg) {
				// Break message into lines
				$parts = preg_split("/\n+/", trim($msg['message']));
				$text_parts = [];

				foreach ($parts as $part) {
					$part = trim($part);
					if (filter_var($part, FILTER_VALIDATE_URL) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $part)) {
						$msg['image'] = $part;
					} elseif (!empty($part)) {
						$text_parts[] = $part;
					}
				}

				// Replace message with text only
				$msg['message'] = implode(' ', $text_parts);
			}
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
					'messages'         => array_filter($messages, function ($msg) {
						unset($msg['image']);
						return $msg;
					}),
				]
			], 200);
		}
	}
	/**
	 * Retrieve a paginated list of conversation partners for a given user_id
	 * 
	 * Returns a list of users with their profile photos, names, last message, and
	 * last message time. The list is sorted by last message time in descending order.
	 * 
	 * @param WP_REST_Request $request The request object.
	 * @since 1.1.0
	 * @return WP_REST_Response
	 */
	public function wpem_get_matchmaking_conversation_list($request) {
		global $wpdb;
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response(array(
				'code'    => 403,
				'status'  => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data'    => null
			), 403);
		}
		$auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
			$user_id  = intval($request->get_param('user_id'));
			$paged    = max(1, intval($request->get_param('paged')));
			$per_page = max(1, intval($request->get_param('per_page')));

			if (empty($user_id)) {
				return new WP_REST_Response([
					'code'    => 400,
					'status'  => 'Bad Request',
					'message' => 'user_id is required.',
				], 400);
			}

			$messages_tbl = $wpdb->prefix . 'wpem_matchmaking_users_messages';

			/**
			 * Step 1: Get all unique conversation partners
			 */
			$conversation_user_ids = $wpdb->get_col($wpdb->prepare("
				SELECT DISTINCT other_user FROM (
					SELECT receiver_id AS other_user FROM $messages_tbl WHERE sender_id = %d
					UNION
					SELECT sender_id AS other_user FROM $messages_tbl WHERE receiver_id = %d
				) AS temp
				WHERE other_user != %d
			", $user_id, $user_id, $user_id));

			if (empty($conversation_user_ids)) {
				return new WP_REST_Response([
					'code'    => 200,
					'status'  => 'OK',
					'message' => 'No conversation history found.',
					'data'    => [
						'total_users'   => 0,
						'current_page'  => $paged,
						'last_page'     => 0,
						'users'         => []
					]
				], 200);
			}

			/**
			 * Step 2: Pagination
			 */
			$total_count = count($conversation_user_ids);
			$last_page   = ceil($total_count / $per_page);
			$offset      = ($paged - 1) * $per_page;
			$paginated_ids = array_slice($conversation_user_ids, $offset, $per_page);

			/**
			 * Step 3: Build conversation list with last message
			 */
			$results = [];
			foreach ($paginated_ids as $partner_id) {
				// Get last message between the two users
				$last_message_row = $wpdb->get_row($wpdb->prepare("
					SELECT message, created_at
					FROM $messages_tbl
					WHERE (sender_id = %d AND receiver_id = %d)
					   OR (sender_id = %d AND receiver_id = %d)
					ORDER BY created_at DESC
					LIMIT 1
				", $user_id, $partner_id, $partner_id, $user_id));

				// Build display name
				$display_name = get_user_meta($partner_id, 'display_name', true);
				if (empty($display_name)) {
					$first_name = get_user_meta($partner_id, 'first_name', true);
					$last_name  = get_user_meta($partner_id, 'last_name', true);
					$display_name = trim("$first_name $last_name");
				}

				$is_image = 0;
				if ($last_message_row && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $last_message_row->message)) {
					$is_image = 1;
				}
				$photo = get_wpem_user_profile_photo($partner_id);
				$results[] = [
					'user_id'       => (int) $partner_id,
					'first_name'    => get_user_meta($partner_id, 'first_name', true),
					'last_name'     => get_user_meta($partner_id, 'last_name', true),
					'display_name'  => $display_name,
					'profile_photo' => $photo,
					'profession'    => get_user_meta($partner_id, '_profession', true),
					'company_name'  => get_user_meta($partner_id, '_company_name', true),
					'last_message'  => $last_message_row ? $last_message_row->message : null,
					'message_time'  => $last_message_row ? date('Y-m-d H:i:s', strtotime($last_message_row->created_at)) : null,
					'last_message_is_image' => $is_image,
				];
			}

			return new WP_REST_Response([
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Conversation list retrieved successfully.',
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
}
new WPEM_REST_Send_Message_Controller();
