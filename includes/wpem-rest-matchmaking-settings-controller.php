<?php
/**
 * REST API Matchmaking Settings controller (Event Controller style)
 *
 * Provides an endpoint to retrieve/update matchmaking settings for the current user.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * Route base: /wp-json/wpem/matchmaking-settings
 * Methods: GET (retrieve), POST (update)
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Matchmaking_Settings_Controller extends WPEM_REST_CRUD_Controller {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base for matchmaking settings endpoints.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking-settings';

    /**
     * Initialize routes.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register matchmaking settings routes (event-controller style structure).
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_settings'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                )
            )
        );
    }

    /**
     * GET /matchmaking-settings
     * Retrieve matchmaking settings for the current user. If event_id is provided,
     * include participation for that event; otherwise include all events.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_settings($request) {
        $settings = [
            'request_mode'     => get_option('wpem_meeting_request_mode'), 
            'scheduling_mode'     => get_option('wpem_meeting_scheduling_mode'),
            'attendee_limit'   => get_option('wpem_meeting_attendee_limit'),
            'meeting_expiration' => get_option('wpem_meeting_expiration'),
            'enable_matchmaking' => get_option('enable_matchmaking'),
            'participant_activation' => get_option('participant_activation'),
        ];

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $settings;
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

}

new WPEM_REST_Matchmaking_Settings_Controller();
