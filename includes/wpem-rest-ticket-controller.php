<?php
/**
 * REST API Ticket controller (Event Controller style)
 *
 * Provides an endpoint to retrieve ticket for the current user.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * Route base: /wp-json/wpem/contact
 * Methods: GET (retrieve), POST (update)
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Ticket_Controller extends WPEM_REST_CRUD_Controller {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base for contact endpoints.
     *
     * @var string
     */
    protected $rest_base = 'ticket';

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
            '/' . $this->rest_base.'/events',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_user_registered_events'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                )
            )
        );

    }

    /**
     * GET /ticket/events
     * Retrieve registered event for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_user_registered_events( $request ) {

        $user_id = wpem_rest_get_current_user_id();

        // get current user order id
		$results = [];

		// Query registrations
		$args = array(
			'post_type'      => 'event_registration',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'author'         => $user_id,
		);

		$query = new WP_Query( $args );
        $event_data = [];
		if ( $query->have_posts() ) {
            foreach ( $query->posts as $registration_id ) {

                $order_id = get_post_meta( $registration_id, '_order_id', true );
                if ( empty( $order_id ) ) {
                    continue;
                }

                $event_id = wp_get_post_parent_id( $registration_id );
                if ( empty( $event_id ) ) {
                    continue;
                }

                $event_post = get_post( $event_id );
                if ( ! $event_post || 'event_listing' !== $event_post->post_type ) {
                    continue;
                }

                // Initialize event if not exists
                if ( ! isset( $event_data[ $event_id ] ) ) {
                    $event_data[ $event_id ] = [
                        'event_id'    => $event_id,
                        'event_title' => get_the_title( $event_id ),
                        'event_date'  => wpem_get_event_start_date( $event_id ),
                        'event_time'  => wpem_get_event_start_time( $event_id ),
                        'thumbnail'   => wpem_get_event_thumbnail( $event_id, 'thumbnail' ),
                        'ticket_detail' => [],
                    ];
                }

                // Ticket ID
                $ticket_ids = get_post_meta( $registration_id, '_ticket_id', true );
                $ticket_id  = ( is_array( $ticket_ids ) && ! empty( $ticket_ids ) )
                    ? absint( $ticket_ids[0] )
                    : 0;

                $ticket_name = $ticket_id ? get_the_title( $ticket_id ) : '';

                // User info
                $first_name = get_post_meta( $registration_id, 'first_name', true )
                    ?: get_user_meta( $user_id, 'first_name', true );

                $last_name = get_post_meta( $registration_id, 'last_name', true )
                    ?: get_user_meta( $user_id, 'last_name', true );

                $email = get_post_meta( $registration_id, 'email', true );
                if ( empty( $email ) ) {
                    $user  = get_user_by( 'id', $user_id );
                    $email = $user ? $user->user_email : '';
                }

                $user_photo = get_post_meta( $registration_id, 'user_photo', true )
                    ?: get_user_meta( $user_id, 'user_photo', true )
                    ?: get_avatar_url( $user_id );

                // Append ticket
                $event_data[ $event_id ]['ticket_detail'][] = [
                    'registration_id' => $registration_id,
                    'order_id'        => absint( $order_id ),
                    'ticket_name'     => $ticket_name,
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'email'           => $email,
                    'user_photo'      => $user_photo,
                ];
            }
            $event_data = array_values( $event_data );
		}

        $response_data = self::prepare_error_for_response( 200 );
        $response_data['data'] = [
            'event_data'    => $event_data,
            'user_status' => wpem_get_user_login_status( $user_id ),
        ];

        return rest_ensure_response( $response_data );
    }
}

new WPEM_REST_Ticket_Controller();