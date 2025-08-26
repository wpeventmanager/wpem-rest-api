<?php
defined('ABSPATH') || exit;

class WPEM_REST_Attendee_Settings_Controller extends WPEM_REST_CRUD_Controller{
    protected $namespace = 'wpem';

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

        // GET - Retrieve settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-attendee-settings',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_matchmaking_attendee_settings'),
                'args' => array(
                    'user_id' => array(
                        'required' => false,
                        'type'     => 'integer'
                    ),
                    'event_id' => array(
                        'required' => false,
                        'type'     => 'integer'
                    )
                ),
            )
        );

        // POST - Update settings
        register_rest_route(
            $this->namespace,
            '/update-matchmaking-attendee-settings',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_matchmaking_attendee_settings'),
                'args' => array(
                    'user_id' => array(
                        'required' => false,
                        'type'     => 'integer'
                    ),
                    'enable_matchmaking' => array(
                        'required' => false,
                        'type'     => 'integer'
                    ),
                    'message_notification' => array(
                        'required' => false,
                        'type'     => 'integer'
                    ),
                    'meeting_request_mode' => array(
                        'required' => false,
                        'type'     => 'string'
                    ),
                    'event_participation' => array(
                        'required' => false,
                        'type'     => 'array'
                    )
                ),
            )
        );
    }

    /**
     * Retrieve attendee settings for a given user, including:
     * - `enable_matchmaking` (int): Whether matchmaking is enabled for this user.
     * - `message_notification` (int): Whether this user wants to receive notifications for new messages.
     * - `event_participation` (array of objects): Array of objects with `event_id` and `create_matchmaking` properties, 
     *   indicating whether the user has enabled matchmaking for each event they are registered for.
     * - `meeting_request_mode` (string): The meeting request mode for this user, either 'approval' or 'auto_accept'.
     *
     * @param WP_REST_Request $request The request that was sent to the API.
     * @return WP_REST_Response Response object that contains the settings.
     * @since 1.1.0
     */
    public function get_matchmaking_attendee_settings($request) {
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
            $user_id  = $request->get_param('user_id') ?: get_current_user_id();
            $event_id = (int) $request->get_param('event_id');

            $user = get_user_by('id', $user_id);
            if (!$user) {
                return new WP_REST_Response(array(
                    'code'    => 404,
                    'status'  => 'Not Found',
                    'message' => 'User not found.',
                    'data'    => null
                ), 404);
            }

            $user_event_participation = [];

            if ($event_id) {
                // Get registrations for specific event
                $registration_post_ids = get_posts([
                    'post_type'      => 'event_registration',
                    'posts_per_page' => -1,
                    'post_status'    => 'any',
                    'author'         => $user_id,
                    'post_parent'    => $event_id,
                    'fields'         => 'ids',
                ]);

                if (!empty($registration_post_ids)) {
                    $create_matchmaking = (int) get_post_meta($registration_post_ids[0], '_create_matchmaking', true);
                    $user_event_participation[] = [
                        'event_id'           => $event_id,
                        'create_matchmaking' => $create_matchmaking
                    ];
                }
            } else {
                // Get all registrations for this user
                $user_registrations = get_posts([
                    'post_type'      => 'event_registration',
                    'posts_per_page' => -1,
                    'post_status'    => 'any',
                    'author'         => $user_id,
                    'fields'         => 'ids',
                ]);

                foreach ($user_registrations as $registration_id) {
                    $parent_event_id = (int) get_post_field('post_parent', $registration_id);
                    if (!$parent_event_id) {
                        continue;
                    }
                    $create_matchmaking = (int) get_post_meta($registration_id, '_create_matchmaking', true);
                    $user_event_participation[] = [
                        'event_id'           => $parent_event_id,
                        'create_matchmaking' => $create_matchmaking
                    ];
                }
            }

            $settings = array(
                'enable_matchmaking'  => (int) get_user_meta($user_id, '_matchmaking_profile', true)[0],
                'message_notification' => (int) get_user_meta($user_id, '_message_notification', true),
                'event_participation'  => $user_event_participation,
                'meeting_request_mode' => get_user_meta($user_id, '_wpem_meeting_request_mode', true) ?: 'approval'
            );

            return new WP_REST_Response(array(
                'code'    => 200,
                'status'  => 'OK',
                'message' => 'Settings retrieved successfully.',
                'data'    => $settings
            ), 200);
        }
    }

    /**
     * Update matchmaking attendee settings.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.1.0
     */
    public function update_matchmaking_attendee_settings($request) {
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
            $user_id = $request->get_param('user_id') ?: get_current_user_id();
            $user = get_user_by('id', $user_id);

            if (!$user) {
                return new WP_REST_Response(array(
                    'code'    => 404,
                    'status'  => 'Not Found',
                    'message' => 'User not found.',
                    'data'    => null
                ), 404);
            }

            // Update user meta
            if (!is_null($request->get_param('enable_matchmaking'))) {
                update_user_meta($user_id, '_matchmaking_profile', (int) $request->get_param('enable_matchmaking'));
            }
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

                    $registration_post_ids = get_posts([
                        'post_type'      => 'event_registration',
                        'posts_per_page' => -1,
                        'post_status'    => 'any',
                        'author'         => $user_id,
                        'post_parent'    => $eid,
                        'fields'         => 'ids',
                    ]);

                    foreach ($registration_post_ids as $registration_post_id) {
                        update_post_meta($registration_post_id, '_create_matchmaking', $value);
                    }
                }
            }

            return new WP_REST_Response(array(
                'code'    => 200,
                'status'  => 'OK',
                'message' => 'Settings updated successfully.',
            ), 200);
        }
    }
}

new WPEM_REST_Attendee_Settings_Controller();
