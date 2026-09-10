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
        add_action('rest_api_init', array($this, 'wpem_register_routes'), 10);
    }

    /**
     * Register matchmaking settings routes (event-controller style structure).
     */
    public function wpem_register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/events',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_user_registered_events'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
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
    public function wpem_get_user_registered_events( $request ) 
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();

        // pass event_id params when you want only ticket data of specific event
        $requested_event_id = absint( $request->get_param( 'event_id' ) );

        $args = array(
            'post_type'      => 'event_registration',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'author'         => $user_id,
        );
        $query = new WP_Query( $args );

        $per_page = (int) $request->get_param('per_page');
        $page = (int) $request->get_param('page');
        if ($per_page <= 0) {
            $per_page = 10;
        }
        if ($page <= 0) {
            $page = 1;
        }

        $event_search   = $request->get_param('event_search');
        $event_search   = is_string($event_search) ? trim($event_search) : '';
        $ticket_search   = $request->get_param('ticket_search');
        $ticket_search   = is_string($ticket_search) ? trim($ticket_search) : '';

        $event_data       = array();
        $processed_orders = array();
        $ticket_data = array();

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $registration_id ) {
                $order_id = absint( get_post_meta( $registration_id, '_order_id', true ) );
                if ( empty( $order_id ) || isset( $processed_orders[ $order_id ] ) ) {
                    continue;
                }

                $processed_orders[ $order_id ] = true;
                // Get ALL registrations belonging to this order.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, 	WordPress.DB.DirectDatabaseQuery.NoCaching
                $registration_ids = $wpdb->get_col($wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_order_id', $order_id));

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $order_date = $wpdb->get_var($wpdb->prepare( "SELECT DATE(post_date) FROM {$wpdb->posts} WHERE ID = %d", $order_id));

                foreach ( $registration_ids as $registration_id ) {
                    $event_id = wp_get_post_parent_id( $registration_id );
                    if ( empty( $event_id ) ) {
                        continue;
                    }

                    if ( $requested_event_id && absint( $event_id ) !== $requested_event_id ) {
                        continue;
                    }

                    $event_post = get_post( $event_id );
                    if ( ! $event_post || 'event_listing' !== $event_post->post_type ) {
                        continue;
                    }

                    // Only process event data when event_id not passed
                    if ( ! $requested_event_id ) {
                        if (!empty($event_search)) {
                            $event_title = get_the_title( $event_id ) !== null ? get_the_title( $event_id ) : '';

                            $haystack = strtolower($event_title);
                            $search_term = strtolower($event_search);

                            if (strpos($haystack, $search_term) === false) {
                                continue;
                            }
                        }
                            $event_data[ $event_id ] = array(
                                'event_id'      => $event_id,
                                'event_title'   => get_the_title( $event_id ),
                                'event_date'    => wpem_get_event_start_date( $event_id ),
                                'event_time'    => wpem_get_event_start_time( $event_id ),
                                'thumbnail'     => wpem_get_event_thumbnail( $event_id, 'thumbnail' )
                            );
                        }

                    // only process when event_id params passed
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

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $get_payment_status = $wpdb->get_var($wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $order_id));
                    if($get_payment_status === 'wc-completed') {
                        $payment_status = 'paid';
                    } else {
                        $payment_status = 'unpaid';
                    }

                    if ( $requested_event_id ) {
                        if (!empty($ticket_search)) {
                            $s_order_id = isset($order_id) ? $order_id : '';
                            $attendee_name = $first_name !== null ? $first_name : '';
                            $attendee_email = $email !== null ? $email : '';

                            $haystack = strtolower($s_order_id . ' ' . $attendee_name . '' . $attendee_email);
                            $search_term = strtolower($ticket_search);

                            if (strpos($haystack, $search_term) === false) {
                                continue;
                            }
                        }

                        // get seat number
                        $seatnumber = maybe_unserialize( get_post_meta( $registration_id, '_seats_details', true ) );

                        $ticket_data[] = array(
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
            }

            wp_reset_postdata();
            $event_data = array_values( $event_data );
        }

        $response_data = self::wpem_prepare_error_for_response( 200 );
        if ( $requested_event_id ) {
            $total_data = count($ticket_data);
            $total_pages = $total_data > 0 ? (int) ceil($total_data / $per_page) : 0;
            $offset = ($page - 1) * $per_page;
            $paged_ticket_data = array_slice($ticket_data, $offset, $per_page);
        $response_data['data'] = array(
                'total' => $total_data,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'event_id' => $event_id,
                'ticket_data' => array_values($paged_ticket_data),
                'user_status' => wpem_get_user_login_status( $user_id ),
            );
        } else {
            $total_data = count($event_data);
            $total_pages = $total_data > 0 ? (int) ceil($total_data / $per_page) : 0;
            $offset = ($page - 1) * $per_page;
            $paged_event_data = array_slice($event_data, $offset, $per_page);
            $response_data['data'] = array(
                'total' => $total_data,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'event_data'  => array_values($paged_event_data),
            'user_status' => wpem_get_user_login_status( $user_id ),
        );
        }

        return rest_ensure_response( $response_data );
    }
}

new WPEM_REST_Ticket_Controller();