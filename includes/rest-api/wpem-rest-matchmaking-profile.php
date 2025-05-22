<?php
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
        global $wpdb;
        $attendee_id = $request->get_param('attendeeId');
        $table = $wpdb->prefix . 'wpem_matchmaking_users';

        if ($attendee_id) {
            // Fetch single attendee
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $attendee_id), ARRAY_A);

            if (!$data) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Attendee not found.'
                ), 404);
            }

            $profile = $this->format_profile_data($data);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Profile retrieved successfully.',
                'data'    => $profile
            ), 200);
        } else {
            // Fetch all attendees
            $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

            $profiles = array_map(array($this, 'format_profile_data'), $results);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'All attendee profiles retrieved successfully.',
                'data'    => $profiles
            ), 200);
        }
    }

    private function format_profile_data($data) {
        return array(
            'attendeeId'             => $data['user_id'],
            'profilePhoto'           => $data['profile_photo'],
            'profession'             => $data['profession'],
            'experience'             => $data['experience'],
            'companyName'            => $data['company_name'],
            'country'                => $data['country'],
            'city'                   => $data['city'],
            'about'                  => $data['about'],
            'skills'                 => $data['skills'],
            'interests'              => $data['interests'],
            'organizationName'       => $data['organization_name'],
            'organizationLogo'       => $data['organization_logo'],
            'organizationCity'       => $data['organization_city'],
            'organizationCountry'    => $data['organization_country'],
            'organizationDescription'=> $data['organization_description'],
            'messageNotification'    => $data['message_notification'],
            'approveProfileStatus'   => $data['approve_profile_status'],
        );
    }
}

new WPEM_REST_Attendee_Profile_Controller();
?>