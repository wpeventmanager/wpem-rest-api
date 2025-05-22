<?php 

defined('ABSPATH') || exit;

/**
 * REST API Attendee Profile Update controller class.
 */
class WPEM_REST_Attendee_Profile_Update_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'attendee-profile';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/update',
            array(
                'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH/POST
                'callback' => array($this, 'update_attendee_profile'),
                'permission_callback' => '__return_true', // For testing, allow all (you should restrict this in production)
                'args' => array(
                    'user_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                ),
            )
        );
    }

    public function update_attendee_profile($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpem_matchmaking_users';
        $user_id = $request->get_param('user_id');

        // Check if the user exists in the table
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        if (!$existing) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Attendee not found.',
            ), 404);
        }

        // Get all possible fields from request
        $fields = array(
            'profile_photo',
            'profession',
            'experience',
            'company_name',
            'country',
            'city',
            'about',
            'skills',
            'interests',
            'organization_name',
            'organization_logo',
            'organization_city',
            'organization_country',
            'organization_description',
            'message_notification',
            'approve_profile_status',
        );

        $data = [];
        foreach ($fields as $field) {
            if ($request->get_param($field) !== null) {
                $data[$field] = sanitize_text_field($request->get_param($field));
            }
        }

        if (empty($data)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No data provided to update.',
            ), 400);
        }

        $updated = $wpdb->update($table, $data, array('user_id' => $user_id));

        if ($updated === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update attendee profile.',
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Attendee profile updated successfully.',
        ), 200);
    }
}

new WPEM_REST_Attendee_Profile_Update_Controller();


?>