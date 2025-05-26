<?php

defined('ABSPATH') || exit;

/**
 * REST API Send Message controller class.
 */
class WPEM_REST_Send_Message_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'send-message';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'handle_send_message'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'senderId' => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                    'receiverId' => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                    'message' => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                    'eventId' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                ),
            )
        );
    }

    public function handle_send_message($request) {
		
		 // Check if matchmaking is enabled
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response(array(
                'code'    => 403,
                'status'  => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data'    => null
            ), 403);
        }
		
        global $wpdb;

        $sender_id   = intval($request->get_param('senderId'));
        $receiver_id = intval($request->get_param('receiverId'));
        $message     = sanitize_textarea_field($request->get_param('message'));
        $event_id    = intval($request->get_param('eventId'));

        // Validate users
        $sender_user   = get_user_by('id', $sender_id);
        $receiver_user = get_user_by('id', $receiver_id);

        if (!$sender_user) {
            return new WP_REST_Response(['success' => 0, 'message' => 'Sender not found.'], 404);
        }

        if (!$receiver_user) {
            return new WP_REST_Response(['success' => 0, 'message' => 'Receiver not found.'], 404);
        }

        $first_name = get_user_meta($sender_id, 'first_name', true);
        $last_name  = get_user_meta($sender_id, 'last_name', true);

        $table = $wpdb->prefix . 'wpem_matchmaking_users_messages';
        $today = date('Y-m-d');

        // Check if the user already sent a message to the same participant today
        $existing_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE sender_id = %d AND receiver_id = %d 
             AND DATE(created_at) = %s",
            $sender_id, $receiver_id, $today
        ));

        $parent_id = ($existing_count == 0) ? 1 : 0;

        // Insert message
        $inserted = $wpdb->insert(
            $table,
            array(
                'event_id'    => $event_id,
                'parent_id'   => $parent_id,
                'sender_id'   => $sender_id,
                'receiver_id' => $receiver_id,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'message'     => $message,
                'created_at'  => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            return new WP_REST_Response([
                'success' => 0,
                'message' => 'Failed to save message.',
                'error'   => $wpdb->last_error
            ], 500);
        }

        // Send email
        $to      = $receiver_user->user_email;
        $subject = 'New Message from ' . $sender_user->display_name;
        $body    = "You have received a new message:\n\n" . $message . "\n\nFrom: " . $sender_user->display_name;
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $body, $headers);

        return new WP_REST_Response(array(
            'success' => 1,
            'message' => 'Message sent and saved successfully.',
            'data'    => array(
                'id'          => $wpdb->insert_id,
                'event_id'    => $event_id,
                'parent_id'   => $parent_id,
                'sender_id'   => $sender_id,
                'receiver_id' => $receiver_id,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'message'     => $message,
                'created_at'  => current_time('mysql'),
            )
        ), 200);
    }
}

new WPEM_REST_Send_Message_Controller();
