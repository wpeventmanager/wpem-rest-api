<?php
/**
 * REST API Contact controller (Event Controller style)
 *
 * Provides an endpoint to retrieve/update contact for the current user.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * Route base: /wp-json/wpem/contact
 * Methods: GET (retrieve), POST (update)
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Contact_Controller extends WPEM_REST_CRUD_Controller {
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
    protected $rest_base = 'contact';

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
                    'callback'            => array($this, 'get_contacts'),
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
                    'callback'            => array($this, 'add_contact'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                )
            )
        );
    }

    /**
     * GET /contact
     * Retrieve contact for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_contacts( $request ) {

        $user_id = wpem_rest_get_current_user_id();

        // Get contact IDs
        $user_contacts = get_user_meta( $user_id, 'user_contacts', true );

        if ( ! is_array( $user_contacts ) || empty( $user_contacts ) ) {
            $user_contacts = [];
        }

        $contacts_data = [];

        foreach ( $user_contacts as $contact_id ) {

            $contact_id = absint( $contact_id );
            if ( empty( $contact_id ) ) {
                continue;
            }

            $user = get_user_by( 'id', $contact_id );
            if ( ! $user ) {
                continue;
            }

			$photo = get_wpem_user_profile_photo($user->ID) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
            $profession = get_user_meta($user->ID, '_profession', true) ?: '';
            if (!empty($profession)) {
                $term = get_term_by('name', $profession, 'event_registration_professions');
                if (!$term) {
                    $term = get_term_by('slug', $profession, 'event_registration_professions');
                }
                $profession_slug = $term ? $term->slug : $profession;
            } else {
                $profession_slug = '';
            }

            $contacts_data[] = [
                'user_id'    => $user->ID,
                'first_name' => get_user_meta( $user->ID, 'first_name', true ),
                'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
                'email'      => $user->user_email,
                'profile_photo' => $photo,
                'profession' => $profession_slug,
                'experience' => get_user_meta($user->ID, '_experience', true) ?: '',
                'company_name' => get_user_meta($user->ID, '_company_name', true) ?: '',
                'country' => get_user_meta($user->ID, '_country', true) ?: '',
                'city' => get_user_meta($user->ID, '_city', true) ?: '',
                'about' => get_user_meta($user->ID, '_about', true) ?: '',		
            ];
        }

        $response_data = self::prepare_error_for_response( 200 );
        $response_data['data'] = [
            'contacts'    => $contacts_data,
            'user_status' => wpem_get_user_login_status( $user_id ),
        ];

        return rest_ensure_response( $response_data );
    }

    /**
     * add contact for the current user.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response $response The response object.
     * @since 1.1.0
     */
    public function add_contact($request) {
        $user_id = wpem_rest_get_current_user_id();
        $contact_id = $request->get_param('contact_id') ?? 0;
        if(!empty($contact_id) && $contact_id > 0) {
            // check user is exist or not
            $contact_user = get_user_by( 'id', $contact_id );
            if ( ! $contact_user ) {
                return self::prepare_error_for_response(400);
            }
            if ( $user_id === $contact_id ) {
                return self::prepare_error_for_response(400);
            }

            // Get existing contacts
            $contacts = get_user_meta( $user_id, 'user_contacts', true );

            if ( ! is_array( $contacts ) ) {
                $contacts = [];
            }

            // Prevent duplicates
            if ( in_array( $contact_id, $contacts, true ) ) {
                return self::prepare_error_for_response( 411 );
            }

            // Add contact
            $contacts[] = $contact_id;
            update_user_meta( $user_id, 'user_contacts', $contacts );

            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'contact_id' => $contact_id,
                'user_status' => wpem_get_user_login_status($user_id),
            );
            return wp_send_json($response_data);

        } else {
            return self::prepare_error_for_response(400);
        }
    }
}

new WPEM_REST_Contact_Controller();