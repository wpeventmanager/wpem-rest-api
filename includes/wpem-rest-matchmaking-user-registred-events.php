<?php

class WPEM_REST_User_Registered_Events_Controller extends WPEM_REST_CRUD_Controller{
    protected $namespace = 'wpem';
    protected $rest_base = 'user-registered-events';

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
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'wpem_get_user_registered_events'),
                'args' => array(
                    'user_id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                        'description' => 'User ID to fetch registered events for.'
                    )
                )
            )
        );
    }

	/**
	 * Retrieve all events that a user is registered to.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.1.0
	 */
    public function wpem_get_user_registered_events($request) {
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
			$target_user_id = intval($request->get_param('user_id'));

			// Get user's email from ID
			$user_info = get_userdata($target_user_id);
			if (!$user_info) {
				return new WP_REST_Response(array(
					'code'    => 404,
					'status'  => 'ERROR',
					'message' => 'User not found.',
					'data'    => []
				), 404);
			}

			$user_email = $user_info->user_email;

			// Get all event registrations where attendee email matches
			$args = array(
				'post_type'      => 'event_registration',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_attendee_email',
						'value'   => $user_email,
						'compare' => '='
					)
				)
			);

			$query = new WP_Query($args);

			$event_ids = array();
			foreach ($query->posts as $registration_post) {
				$event_id = wp_get_post_parent_id($registration_post->ID);
				if (!empty($event_id) && !in_array($event_id, $event_ids)) {
					$event_ids[] = (int) $event_id;
				}
			}

			if (empty($event_ids)) {
				return new WP_REST_Response(array(
					'code'    => 200,
					'status'  => 'OK',
					'message' => 'No registered events found.',
					'data'    => []
				), 200);
			}

			$events = array();
			foreach ($event_ids as $event_id) {
				$post = get_post($event_id);
				if ($post && $post->post_type === 'event_listing') {
					$events[] = array(
						'event_id'    => $event_id,
						'title'       => get_the_title($event_id),
						'status'      => $post->post_status,
						'start_date'  => get_post_meta($event_id, '_event_start_date', true),
						'end_date'    => get_post_meta($event_id, '_event_end_date', true),
						'location'    => get_post_meta($event_id, '_event_location', true),
						'banner'      => get_post_meta($event_id, '_event_banner', true),
					);
				}
			}

			return new WP_REST_Response(array(
				'code'    => 200,
				'status'  => 'OK',
				'message' => 'Events retrieved successfully.',
				'data'    => $events
			), 200);
		}
	}
}

new WPEM_REST_User_Registered_Events_Controller();
