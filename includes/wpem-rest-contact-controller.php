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

class WPEM_REST_Contact_Controller extends WPEM_REST_CRUD_Controller
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
    protected $rest_base = 'contact';

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
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_contacts'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(
                        'search' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'default'           => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'per_page' => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'default'           => 10,
                            'sanitize_callback' => 'absint',
                        ),
                        'page' => array(
                            'type'              => 'integer',
                            'required'          => false,
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                )
            )
        );
        
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'wpem_delete_contact'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
                )
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'wpem_add_contact'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
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
    public function wpem_get_contacts($request)
    {

        $user_id = wpem_rest_get_current_user_id();

        // Get contact IDs
        $user_contacts = get_user_meta($user_id, 'user_contacts', true);

        if (!is_array($user_contacts) || empty($user_contacts)) {
            $user_contacts = [];
        }

        // Pagination / search params
        $search   = $request->get_param('search');
        $search   = is_string($search) ? trim($search) : '';
        $per_page = (int) $request->get_param('per_page');
        $page     = (int) $request->get_param('page');

        if ($per_page <= 0) {
            $per_page = 10;
        }
        if ($page <= 0) {
            $page = 1;
        }

        $contacts_data = [];

        foreach ($user_contacts as $contact_id) {

            $contact_id = absint($contact_id);
            if (empty($contact_id)) {
                continue;
            }

            $user = get_user_by('id', $contact_id);
            if (!$user) {
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

            $first_name   = get_user_meta($user->ID, 'first_name', true) ?: get_user_meta($user->ID, '_attendee_name', true) ?: '';
            $last_name   = get_user_meta($user->ID, 'last_name', true) ?: get_user_meta($user->ID, '_attendee_last_name', true) ?: '';
            $company_name = get_user_meta($user->ID, '_company_name', true) ?: '';

            // Apply search filter (case-insensitive, matches name/email/company)
            if (!empty($search)) {
                $haystack = strtolower($first_name . ' ' . $last_name . ' ' . $user->user_email . ' ' . $company_name);
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }

            $contacts_data[] = [
                'user_id' => $user->ID,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $user->user_email,
                'profile_photo' => $photo,
                'profession' => $profession_slug,
                'experience' => get_user_meta($user->ID, '_experience', true) ?: '',
                'company_name' => $company_name,
                'country' => get_user_meta($user->ID, '_country', true) ?: '',
                'city' => get_user_meta($user->ID, '_city', true) ?: '',
                'about' => get_user_meta($user->ID, '_about', true) ?: '',
            ];
        }

        // Totals based on filtered (search-applied) result set
        $total_contacts = count($contacts_data);
        $total_pages    = $total_contacts > 0 ? (int) ceil($total_contacts / $per_page) : 0;

        // Slice for the requested page
        $offset = ($page - 1) * $per_page;
        $paged_contacts = array_slice($contacts_data, $offset, $per_page);

        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = [
            'total' => $total_contacts,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'contacts' => array_values($paged_contacts),
            'user_status' => wpem_get_user_login_status($user_id),
        ];

        return rest_ensure_response($response_data);
    }

    /**
     * add contact for the current user.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response $response The response object.
     * @since 1.1.0
     */
    public function wpem_add_contact($request)
    {
        $user_id = wpem_rest_get_current_user_id();
        $contact_id = $request->get_param('contact_id') ?? 0;
        if (!empty($contact_id) && $contact_id > 0) {
            // check user is exist or not
            $contact_user = get_user_by('id', $contact_id);
            if (!$contact_user) {
                return self::wpem_prepare_error_for_response(400);
            }
            if ($user_id === $contact_id) {
                return new WP_REST_Response(
                    array(
                        'code'    => 400,
                        'status'  => 'Bad request',
                        'message' => __('You can not scan your own QR code.', 'wpem-rest-api'),
                    ),
                    400
                );
            }

            // Get existing contacts
            $contacts = get_user_meta($user_id, 'user_contacts', true);

            if (!is_array($contacts)) {
                $contacts = [];
            }

            // Prevent duplicates
            if (in_array($contact_id, $contacts, true)) {
                return new WP_REST_Response(
                    array(
                        'code'    => 411,
                        'status'  => 'error',
                        'message' => __('Your QRcode already scanned.', 'wpem-rest-api'),
                    ),
                    400
                );
            }

            // Add contact
            $contacts[] = $contact_id;
            update_user_meta($user_id, 'user_contacts', $contacts);

            $response_data = self::wpem_prepare_error_for_response(200);
            $response_data['data'] = array(
                'contact_id' => $contact_id,
                'user_status' => wpem_get_user_login_status($user_id),
            );
            return wp_send_json($response_data);

        } else {
            return new WP_REST_Response(
                array(
                    'code'    => 400,
                    'status'  => 'Bad request',
                    'message' => __('You can not scan your own QR code.', 'wpem-rest-api'),
                ),
                400
            );
        }
    }
    
    /**
     * Delete contact for current user.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function wpem_delete_contact($request){
        $user_id    = wpem_rest_get_current_user_id();
        $contact_id = absint($request['id']);

        // Validate contact ID
        if (empty($contact_id)) {
            return self::wpem_prepare_error_for_response(400);
        }

        // Get contacts
        $contacts = get_user_meta($user_id, 'user_contacts', true);

        if (!is_array($contacts)) {
            $contacts = array();
        }

        // Normalize all IDs to integers
        $contacts = array_map('intval', $contacts);

        // Check if contact exists
        if (!in_array($contact_id, $contacts, true)) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Remove contact
        $contacts = array_values(
            array_filter(
                $contacts,
                function ($id) use ($contact_id) {
                    return $id !== $contact_id;
                }
            )
        );

        // Update user meta
        update_user_meta($user_id, 'user_contacts', $contacts);

        // Response
        $response_data = self::wpem_prepare_error_for_response(200);

        $response_data['data'] = array(
            'contact_id' => $contact_id,
            'message'    => 'Contact deleted successfully.',
            'user_status' => wpem_get_user_login_status($user_id),
        );

        return rest_ensure_response($response_data);
    }
}

new WPEM_REST_Contact_Controller();