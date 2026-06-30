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

class WPEM_REST_Ticket_Controller extends WPEM_REST_CRUD_Controller
{
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
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register matchmaking settings routes (event-controller style structure).
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/events',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_user_registered_events'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => array(),
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
    public function get_user_registered_events( $request ) 
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $args = array(
            'post_type'      => 'event_registration',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'author'         => $user_id,
        );
        $query = new WP_Query( $args );

        $event_data       = array();
        $processed_orders = array();

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $registration_id ) {
                $order_id = absint( get_post_meta( $registration_id, '_order_id', true ) );
                if ( empty( $order_id ) || isset( $processed_orders[ $order_id ] ) ) {
                    continue;
                }

                $processed_orders[ $order_id ] = true;
                // Get ALL registrations belonging to this order.
                $registration_ids = $wpdb->get_col($wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_order_id', $order_id));

                $order_date = $wpdb->get_var($wpdb->prepare( "SELECT DATE(post_date) FROM {$wpdb->posts} WHERE ID = %d", $order_id));

                foreach ( $registration_ids as $registration_id ) {
                    $event_id = wp_get_post_parent_id( $registration_id );
                    if ( empty( $event_id ) ) {
                        continue;
                    }

                    $event_post = get_post( $event_id );
                    if ( ! $event_post || 'event_listing' !== $event_post->post_type ) {
                        continue;
                    }

                    if ( ! isset( $event_data[ $event_id ] ) ) {
                        $event_data[ $event_id ] = array(
                            'event_id'      => $event_id,
                            'event_title'   => get_the_title( $event_id ),
                            'event_date'    => wpem_get_event_start_date( $event_id ),
                            'event_time'    => wpem_get_event_start_time( $event_id ),
                            'thumbnail'     => wpem_get_event_thumbnail( $event_id, 'thumbnail' ),
                            'ticket_detail' => array(),
                        );
                    }

                    $ticket_ids = get_post_meta( $registration_id, '_ticket_id', true );
                    $ticket_id = ( is_array( $ticket_ids ) && ! empty( $ticket_ids ) ) ? absint( $ticket_ids[0] ) : 0;
                    $ticket_name = $ticket_id ? get_the_title( $ticket_id ) : '';
                    $registration_author = get_post_field( 'post_author', $registration_id );
                    $first_name = get_post_meta( $registration_id, '_attendee_name', true );
                    if ( empty( $first_name ) ) {
                        $first_name = get_user_meta( $registration_author, 'first_name', true );
                    }
                    $last_name = get_post_meta( $registration_id, '_attendee_last_name', true );
                    if ( empty( $last_name ) ) {
                        $last_name = get_user_meta( $registration_author, 'last_name', true );
                    }
                    $email = get_post_meta( $registration_id, '_attendee_email', true );
                    if ( empty( $email ) ) {
                        $user  = get_user_by( 'id', $registration_author );
                        $email = $user ? $user->user_email : '';
                    }
                    $user_photo = maybe_unserialize(
                        get_post_meta( $registration_id, '_profile_photo', true )
                    );

                    if ( is_array( $user_photo ) && ! empty( $user_photo[0] ) ) {
                        $user_photo = $user_photo[0];
                    } else {
                        $user_meta_photo = maybe_unserialize(
                            get_user_meta( $registration_author, '_profile_photo', true )
                        );
                        if ( is_array( $user_meta_photo ) && ! empty( $user_meta_photo[0] ) ) {
                            $user_photo = $user_meta_photo[0];
                        } else {
                            $user_photo = get_avatar_url( $registration_author );
                        }
                    }

                    $payment_method = get_post_meta( $order_id, '_payment_method', true );
                    $order_amount = get_post_meta( $order_id, '_order_total', true );

                    // get organizer name and venue
                    $organizer_name = '';
                    $venue_name     = '';

                    $order_event_meta = maybe_unserialize( get_post_meta( $order_id, '_event_id', true ) );
                    $order_event_id = 0;
                    if ( is_array( $order_event_meta ) && ! empty( $order_event_meta[0] ) ) {
                        $order_event_id = absint( $order_event_meta[0] );
                    }
                    if ( $order_event_id ) {
                        // Venue
                        $venue_name = get_post_meta( $order_event_id, '_event_location', true );
                        if ( empty( $venue_name ) ) {
                            $venue_name = '';
                        }

                        // Organizer
                        $organizer_ids = maybe_unserialize( get_post_meta( $order_event_id, '_event_organizer_ids', true ) );
                        if ( is_array( $organizer_ids ) && ! empty( $organizer_ids[0] ) ) {
                            $organizer_id = absint( $organizer_ids[0] );
                            if ( $organizer_id ) {
                                $organizer_name = get_post_meta( $organizer_id, '_organizer_name', true );
                                if ( empty( $organizer_name ) ) {
                                    $organizer_name = '';
                                }
                            }
                        }
                    }

                    $get_payment_status = $wpdb->get_var($wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $order_id));
                    if($get_payment_status === 'wc-completed') {
                        $payment_status = 'paid';
                    } else {
                        $payment_status = 'unpaid';
                    }

                    // get seat number
                    $seatnumber = maybe_unserialize( get_post_meta( $registration_id, '_seats_details', true ) );

                    $event_data[ $event_id ]['ticket_detail'][] = array(
                        'registration_id' => absint( $registration_id ),
                        'order_id'        => $order_id,
                        'ticket_name'     => $ticket_name,
                        'first_name'      => $first_name,
                        'last_name'       => $last_name,
                        'email'           => $email,
                        'user_photo'      => $user_photo,
                        'order_date'      => wp_date( 'Y-m-d', strtotime( $order_date ) ),
                        'order_amount'    => $order_amount,
                        'payment_method'  => $payment_method,
                        'payment_status'  => $payment_status,
                        'organizer_name'  => $organizer_name,
                        'event_venue'      => $venue_name,
                        'seat_number'      => is_array( $seatnumber ) ? $seatnumber[0] : $seatnumber,
                    );
                }
            }

            wp_reset_postdata();
            $event_data = array_values( $event_data );
        }

        $response_data = self::prepare_error_for_response( 200 );
        $response_data['data'] = array(
            'event_data'  => $event_data,
            'user_status' => wpem_get_user_login_status( $user_id ),
        );

        return rest_ensure_response( $response_data );
    }
}

new WPEM_REST_Ticket_Controller();