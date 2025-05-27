<?php
defined('ABSPATH') || exit;

/**
 * REST API Attendee Profile GET controller class.
 */
class WPEM_REST_Attendee_Profile_Controller {
    protected $namespace = 'wpem';
    protected $rest_base = 'attendee-profile';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_attendee_profile'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'attendeeId' => array(
                        'required' => false,
                        'type' => 'integer',
                    )
                ),
            )
        );
    }

    public function get_attendee_profile($request) {
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response(array(
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data' => null
            ), 403);
        }

        global $wpdb;
        $attendee_id = $request->get_param('attendeeId');
        $table = $wpdb->prefix . 'wpem_matchmaking_users';

        if ($attendee_id) {
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $attendee_id), ARRAY_A);
            if (!$data) {
                return new WP_REST_Response(array(
                    'code' => 404,
                    'status' => 'Not Found',
                    'message' => 'Attendee not found.',
                    'data' => null
                ), 404);
            }
            $profile = $this->format_profile_data($data);
            return new WP_REST_Response(array(
                'code' => 200,
                'status' => 'OK',
                'message' => 'Profile retrieved successfully.',
                'data' => $profile
            ), 200);
        } else {
            $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            $profiles = array_map(array($this, 'format_profile_data'), $results);
            return new WP_REST_Response(array(
                'code' => 200,
                'status' => 'OK',
                'message' => 'All profiles retrieved successfully.',
                'data' => $profiles
            ), 200);
        }
    }

    private function format_profile_data($data) {
        $user = get_userdata($data['user_id']);

        return array(
            'attendeeId'              => $data['user_id'],
            'firstName'               => $user ? get_user_meta($user->ID, 'first_name', true) : '',
            'lastName'                => $user ? get_user_meta($user->ID, 'last_name', true) : '',
            'email'                   => $user ? $user->user_email : '',
            'profilePhoto'            => $data['profile_photo'],
            'profession'              => $data['profession'],
            'experience'              => $data['experience'],
            'companyName'             => $data['company_name'],
            'country'                 => $data['country'],
            'city'                    => $data['city'],
            'about'                   => $data['about'],
            'skills'                  => maybe_unserialize($data['skills']),
            'interests'               => maybe_unserialize($data['interests']),
            'organizationName'        => $data['organization_name'],
            'organizationLogo'        => $data['organization_logo'],
            'organizationCity'        => $data['organization_city'],
            'organizationCountry'     => $data['organization_country'],
            'organizationDescription' => $data['organization_description'],
            'messageNotification'     => $data['message_notification'],
            'approveProfileStatus'    => $data['approve_profile_status'],
        );
    }
}


/**
 * REST API Attendee Profile UPDATE controller class.
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
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_attendee_profile'),
                'permission_callback' => '__return_true',
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
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response([
                'code' => 403,
                'status' => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.'
            ], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpem_matchmaking_users';
        $user_id = $request->get_param('user_id');

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_REST_Response(['code' => 404, 'status' => 'Not Found', 'message' => 'User not found.'], 404);
        }

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        if (!$existing) {
            return new WP_REST_Response(['code' => 404, 'status' => 'Not Found', 'message' => 'Profile not found.'], 404);
        }

        $custom_fields = [
            'profile_photo', 'profession', 'experience', 'company_name', 'country',
            'city', 'about', 'skills', 'interests', 'organization_name', 'organization_logo',
            'organization_city', 'organization_country', 'organization_description',
            'message_notification', 'approve_profile_status',
        ];

        $custom_data = [];
        foreach ($custom_fields as $field) {
            if ($request->get_param($field) !== null) {
                $custom_data[$field] = sanitize_text_field($request->get_param($field));
            }
        }

        if (!empty($custom_data)) {
            $updated = $wpdb->update($table, $custom_data, ['user_id' => $user_id]);
            if ($updated === false) {
                return new WP_REST_Response(['code' => 500, 'status' => 'Error', 'message' => 'Failed to update profile.'], 500);
            }
        }

        if ($first = $request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($first));
        }
        if ($last = $request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($last));
        }
        if ($email = $request->get_param('email')) {
            $email = sanitize_email($email);
            $email_exists = email_exists($email);
            if ($email_exists && $email_exists != $user_id) {
                return new WP_REST_Response(['code' => 400, 'status' => 'Error', 'message' => 'Email already in use.'], 400);
            }
            $result = wp_update_user(['ID' => $user_id, 'user_email' => $email]);
            if (is_wp_error($result)) {
                return new WP_REST_Response(['code' => 500, 'status' => 'Error', 'message' => $result->get_error_message()], 500);
            }
        }

        return new WP_REST_Response(['code' => 200, 'status' => 'OK', 'message' => 'Profile updated successfully.'], 200);
    }
}

// Initialize both controllers
new WPEM_REST_Attendee_Profile_Controller();
new WPEM_REST_Attendee_Profile_Update_Controller();
