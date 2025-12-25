<?php
/**
 * REST API Settings controller (Event Controller style)
 *
 * Provides an endpoint to retrieve/update settings for the current user.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * Route base: /wp-json/wpem/settings
 * Methods: GET (retrieve), POST (update)
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Settings_Controller extends WPEM_REST_CRUD_Controller {
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
    protected $rest_base = 'settings';

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

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
           array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_settings'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                )
            )
        );
    }

    /**
     * GET /settings
     * Retrieve settings for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_settings($request) {
        $user_id  = wpem_rest_get_current_user_id();
		$print_badge_mode = get_user_meta($user_id, 'wpem_print_badge_mode', true) ? get_user_meta($user_id, 'wpem_print_badge_mode', true)  : 0;

        $settings = [
            'wpem_print_badge_mode' => (int)$print_badge_mode, 
        ];

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $settings;
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

    /**
     * update user profile settings
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response $response The response object.
     * @since 1.1.0
     */
    public function update_settings($request) {
        $user_id = wpem_rest_get_current_user_id();
        $wpem_print_badge_mode = $request->get_param('wpem_print_badge_mode') ? 1 : 0;

		update_user_meta($user_id, 'wpem_print_badge_mode', (int) $wpem_print_badge_mode);

        return self::prepare_error_for_response(200);
    }
}

new WPEM_REST_Settings_Controller();
