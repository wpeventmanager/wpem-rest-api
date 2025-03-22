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
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_send_message'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'senderId' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'receiverId' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'message' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );
    }

    public function handle_send_message($request) {
        $sender_id   = $request->get_param('senderId');
        $receiver_id = $request->get_param('receiverId');
        $message     = sanitize_text_field($request->get_param('message'));

        // Validate sender
        $sender_user = get_user_by('id', $sender_id);
        if (!$sender_user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Sender not found.'
            ), 404);
        }

        // Validate receiver
        $receiver_user = get_user_by('id', $receiver_id);
        if (!$receiver_user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Receiver not found.'
            ), 404);
        }

        // Build message data
        $message_data = array(
            'senderId'  => $sender_id,
            'message'   => $message,
            'timestamp' => current_time('mysql'),
        );

        // Save message in receiver's user meta
        $existing_messages = get_user_meta($receiver_id, 'user_meetings', true);
        if (!is_array($existing_messages)) {
            $existing_messages = array();
        }

        $existing_messages[] = $message_data;
        update_user_meta($receiver_id, 'user_meetings', $existing_messages);

        // Send real email to receiver
        $to      = $receiver_user->user_email;
        $subject = 'New Message from ' . $sender_user->display_name;
        $body    = "You have received a new message:\n\n" . $message . "\n\nFrom: " . $sender_user->display_name;
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($to, $subject, $body, $headers);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Message sent successfully and email delivered.',
            'data'    => $message_data
        ), 200);
    }
}

new WPEM_REST_Send_Message_Controller();