<?php
/**
 * REST API Matchmaking Profile Settings controller
 *
 * Provides endpoints to retrieve and update matchmaking profile settings.
 * Mirrors coding structure of event controller and reuses params/validation from matchmaking-settings.
 *
 * @since 1.1.3
 */

defined('ABSPATH') || exit;

class WPEM_REST_Matchmaking_Profile_Settings_Controller extends WPEM_REST_CRUD_Controller {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Initialize routes.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for matchmaking profile settings.
     *
     * @since 1.1.3
     */
    public function register_routes() {
        // GET - Retrieve profile settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_matchmaking_profile_settings'),
                'args'     => array(
                    'user_id' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                    'event_id' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                ),
            )
        );

        // POST - Update profile settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_matchmaking_profile_settings'),
                'args'     => array(
                    'user_id' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                    'enable_matchmaking' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                    'message_notification' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                    'meeting_request_mode' => array(
                        'required' => false,
                        'type'     => 'string',
                    ),
                    'event_participation' => array(
                        'required' => false,
                        'type'     => 'array',
                    ),
                ),
            )
        );
    }

    /**
     * Retrieve matchmaking profile settings for a given user.
     * Params/validation aligned with matchmaking-settings controller.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.1.3
     */
    public function get_matchmaking_profile_settings($request) {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return self::prepare_error_for_response(405);
        }

        $user_id  = wpem_rest_get_current_user_id();
        $event_id = (int) $request->get_param('event_id');

        $user = get_user_by('id', $user_id);

        // Build user event participation settings
        $user_event_participation = array();
        if ($event_id) {
            // Get registrations for a specific event
            $registration_post_ids = get_posts(array(
                'post_type'      => 'event_registration',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'author'         => $user_id,
                'post_parent'    => $event_id,
                'fields'         => 'ids',
            ));

            if (!empty($registration_post_ids)) {
                $create_matchmaking = (int) get_post_meta($registration_post_ids[0], '_create_matchmaking', true);
                $user_event_participation[] = array(
                    'event_id'           => (int) $event_id,
                    'create_matchmaking' => $create_matchmaking,
                );
            }
        } else {
            // Get all registrations for this user
            $user_registrations = get_posts(array(
                'post_type'      => 'event_registration',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'author'         => $user_id,
                'fields'         => 'ids',
            ));

            foreach ($user_registrations as $registration_id) {
                $parent_event_id = (int) get_post_field('post_parent', $registration_id);
                if (!$parent_event_id) {
                    continue;
                }
                $create_matchmaking = (int) get_post_meta($registration_id, '_create_matchmaking', true);
                $user_event_participation[] = array(
                    'event_id'           => $parent_event_id,
                    'create_matchmaking' => $create_matchmaking,
                );
            }
        }

        $settings = array(
            'enable_matchmaking'   => (int) get_user_meta($user_id, '_matchmaking_profile', true),
            'message_notification' => (int) get_user_meta($user_id, '_message_notification', true),
            'event_participation'  => $user_event_participation,
            'meeting_request_mode' => get_user_meta($user_id, '_wpem_meeting_request_mode', true) ?: 'approval',
        );

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $settings;
        return wp_send_json($response_data);
    }

    /**
     * Update matchmaking profile settings.
     * Params/validation aligned with matchmaking-settings controller.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.1.3
     */
    public function update_matchmaking_profile_settings($request) {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return self::prepare_error_for_response(405);
        }

        $user_id  = wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);

        // Update user meta values
        if (!is_null($request->get_param('message_notification'))) {
            update_user_meta($user_id, '_message_notification', (int) $request->get_param('message_notification'));
        }
        if (!is_null($request->get_param('meeting_request_mode'))) {
            update_user_meta($user_id, '_wpem_meeting_request_mode', sanitize_text_field($request->get_param('meeting_request_mode')));
        }

        // Update event participation settings
        $event_participation = $request->get_param('event_participation');
        if (is_array($event_participation)) {
            foreach ($event_participation as $event) {
                if (!isset($event['event_id'])) {
                    continue;
                }
                $eid   = (int) $event['event_id'];
                $value = isset($event['create_matchmaking']) ? (int) $event['create_matchmaking'] : 0;

                $registration_post_ids = get_posts(array(
                    'post_type'      => 'event_registration',
                    'posts_per_page' => -1,
                    'post_status'    => 'any',
                    'author'         => $user_id,
                    'post_parent'    => $eid,
                    'fields'         => 'ids',
                ));

                foreach ($registration_post_ids as $registration_post_id) {
                    update_post_meta($registration_post_id, '_create_matchmaking', $value);
                }
            }
        }

        return self::prepare_error_for_response(200);
    }
}

new WPEM_REST_Matchmaking_Profile_Settings_Controller();
