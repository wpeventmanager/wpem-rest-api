<?php
defined('ABSPATH') || exit;

/**
 * REST API Attendee Profile controller class.
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
        $attendee_id = $request->get_param('attendeeId');

        if ($attendee_id) {
            // Fetch single attendee
            $user = get_userdata($attendee_id);
            if (!$user) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Attendee not found.'
                ), 404);
            }

            $profile = $this->get_attendee_data($user);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Profile retrieved successfully.',
                'data'    => $profile
            ), 200);
        } else {
            // Fetch all attendees
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC'
            ));

            $profiles = array_map(array($this, 'get_attendee_data'), $users);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'All attendee profiles retrieved successfully.',
                'data'    => $profiles
            ), 200);
        }
    }

    private function get_attendee_data($user) {
        return array(
            'attendeeId'  => $user->ID,
            'firstName'   => get_user_meta($user->ID, 'first_name', true),
            'lastName'    => get_user_meta($user->ID, 'last_name', true),
            'email'       => $user->user_email,
            'photo'       => get_avatar_url($user->ID),
            'occupation'  => get_user_meta($user->ID, 'occupation', true),
            'experience'  => get_user_meta($user->ID, 'experience', true),
            'companyName' => get_user_meta($user->ID, 'company_name', true),
            'country'     => get_user_meta($user->ID, 'country', true),
            'city'        => get_user_meta($user->ID, 'city', true),
            'about'       => get_user_meta($user->ID, 'about', true),
            'skills'      => get_user_meta($user->ID, 'skills', true),
            'interests'   => get_user_meta($user->ID, 'interests', true),
        );
    }
}

new WPEM_REST_Attendee_Profile_Controller();