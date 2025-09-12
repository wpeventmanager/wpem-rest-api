<?php
/**
 * REST API Matchmaking Profile controller (Event Controller style)
 *
 * Provides endpoints to retrieve/update matchmaking user profiles and upload files.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Matchmaking_Profile_Controller extends WPEM_REST_CRUD_Controller {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base for profile endpoints.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking-profile';

    /**
     * Initialize routes.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register profile routes in the event-controller format.
     */
    public function register_routes() {
        // GET - Retrieve single or all matchmaking profiles
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_user_profile_data'),
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
                    'callback'            => array($this, 'update_matchmaking_profile'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                )
            )
        );

        // POST - Upload a user file (profile photo)
        register_rest_route(
            $this->namespace,
            '/upload-user-file',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'upload_user_file'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(
                        'user_id' => array(
                            'required' => true,
                            'type'     => 'integer',
                        ),
                    ),
                ),
            )
        );

        // Retrieve/Update matchmaking profile settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_matchmaking_profile_settings'),
                'permission_callback' => array($this, 'permission_check'),
                'args'     => array(),
            ),
        );
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_matchmaking_profile_settings'),
                'permission_callback' => array($this, 'permission_check'),
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

        // POST - Filter matchmaking users (same as wpem_matchmaking_filter_users)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/search',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_wpem_matchmaking_filter_users'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(
                        'profession'    => array('required' => false, 'type' => 'string'),
                        'company_name'  => array('required' => false, 'type' => 'string'),
                        'country'       => array('required' => false, 'type' => 'array'),
                        'city'          => array('required' => false, 'type' => 'string'),
                        'experience'    => array('required' => false),
                        'skills'        => array('required' => false, 'type' => 'array'),
                        'interests'     => array('required' => false, 'type' => 'array'),
                        'event_id'      => array('required' => false, 'type' => 'integer'),
                        'search'        => array('required' => false, 'type' => 'string'),
                        'per_page'      => array('required' => false, 'type' => 'integer', 'default' => 5),
                        'page'          => array('required' => false, 'type' => 'integer', 'default' => 1),
                    ),
                ),
            )
        );

        // Alias endpoint for legacy path and POST method
        register_rest_route(
             $this->namespace,
            '/' . $this->rest_base . '/events', 
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_wpem_matchmaking_user_events'),
                    'permission_callback' => array($this, 'permission_check'),
                )
            )
        );
    }

    /**
     * GET /attendee-profile
     * Retrieve matchmaking profile(s). If attendeeId provided, returns single profile; otherwise returns all.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_user_profile_data($request) {
        $user_id = (int) $request->get_param('user_id') ? (int) $request->get_param('user_id') : wpem_rest_get_current_user_id();
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return self::prepare_error_for_response(404);
        }

        $user_meta = get_user_meta($user_id);
        // Base info
        $profile = array(
            'user_id'      => $user_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
        );

        // Fetch dynamic fields
        $fields = get_wpem_user_matchmaking_profile_fields();
        foreach ($fields as $field_key => $field_config) {
            $raw_value = isset($user_meta["_".$field_key][0]) ? maybe_unserialize($user_meta["_".$field_key][0]) : '';

            $value = null;
            $type  = isset($field_config['type']) ? $field_config['type'] : 'text';
            switch ($type) {
                case 'text':
                case 'email':
                case 'number':
                case 'textarea':
                    $value = sanitize_text_field($raw_value);
                    break;
                case 'select':
                case 'term-select':
                    $value = sanitize_text_field($raw_value);
                    break;

                case 'checkbox':
                case 'radio':
                    $value = !empty($raw_value) ? 1 : 0;
                    break;

                case 'multiselect':
                case 'checkbox_multi':
                    $arr = is_array($raw_value) ? $raw_value : (array) $raw_value;
                    $value = array_map('sanitize_text_field', $arr);
                    break;

                case 'url':
                case 'file':
                    if (is_array($raw_value)) {
                        $raw_value = reset($raw_value); // handle WP file upload meta
                    }
                    $value = esc_url_raw($raw_value);
                    break;

                case 'term-multiselect':
                case 'term-checkbox':
                    $value = $raw_value;
                    break;

                default:
                    $value = sanitize_text_field(is_scalar($raw_value) ? $raw_value : '');
                    break;
            }

            $profile[$field_key] = $value;
        }

        // Add profile photo separately
        $profile['profile_photo'] = get_wpem_user_profile_photo($user_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

        // Add organization logo separately
        $organization_logo = get_user_meta($user_id, '_organization_logo', true);
        $organization_logo = maybe_unserialize($organization_logo);
        if (is_array($organization_logo)) {
            $organization_logo = reset($organization_logo);
        }
        $profile['organization_logo'] = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';

        // Build response
        $response = self::prepare_error_for_response(200);
        $response['data'] = $profile;
        return wp_send_json($response);
    }

    /**
     * PUT/PATCH /attendee-profile/update
     * Update matchmaking profile for the given user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function update_matchmaking_profile($request) {
        $user_id = (int) wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return self::prepare_error_for_response(404);
        }

        // Get all defined profile fields
        $fields = get_wpem_user_matchmaking_profile_fields();

        foreach ($fields as $field_key => $field_config) {
            if ($request->get_param($field_key) === null) {
                continue; // skip if not provided
            }

            $value = $request->get_param($field_key);
            $type  = isset($field_config['type']) ? $field_config['type'] : 'text';
           
            if (str_starts_with($field_key, '_')) 
                $field_key = $field_key;
            else
                $field_key = '_'.$field_key;

            // Save value
            if ($value !== '' && !(is_array($value) && empty($value))) {
                update_user_meta($user_id, $field_key, $value);
            } else {
                update_user_meta($user_id, $field_key, '');
            }
        }

        // Update core user fields (first name, last name, email)
        if ($request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($request->get_param('first_name')));
        }
        if ($request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($request->get_param('last_name')));
        }
       
        return self::prepare_error_for_response(200);
    }

    /**
     * POST /upload-user-file
     * Upload a file for the user, storing it as profile photo.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function upload_user_file($request) {
        $user_id = (int) wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return self::prepare_error_for_response(404);
        }

        if (empty($_FILES['file'])) {
            return self::prepare_error_for_response(400, array('message' => 'No file uploaded.'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = $_FILES['file'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        if (!isset($movefile['url'])) {
            return self::prepare_error_for_response(500, array('message' => 'File upload failed.'));
        }

        $file_url = esc_url_raw($movefile['url']);
        update_user_meta($user_id, '_profile_photo', $file_url);

        $response = self::prepare_error_for_response(200);
        $response['message'] = 'File uploaded and stored successfully.';
        $response['data'] = array(
            'profile_photo' => $file_url,
            'meta_updated'  => true,
        );
        return wp_send_json($response);
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
        $user_id  = wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);

        // Build user event participation settings
        $user_event_participation = array();
       
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
            $user_event_participation[$parent_event_id] = array(
                'event_id'           => $parent_event_id,
                'create_matchmaking' => $create_matchmaking,
            );
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
    
    /**
     * Filter matchmaking users (combined logic from wpem_matchmaking_filter_users)
     * Route: POST /wp-json/wpem/matchmaking-profile/filter
     * @since 1.1.4
     */
    public function get_wpem_matchmaking_filter_users( WP_REST_Request $request ) {
        $filters   = $request->get_params();
        $current_user = wpem_rest_get_current_user_id();

        // Step 1: Get event_ids (passed or derived from user registrations)
        $event_ids = [];
        if (!empty($filters['event_id'])) {
            $event_ids[] = absint($filters['event_id']);
        } else {
            $registration_post_ids = get_posts([
                'post_type'      => 'event_registration',
                'post_status'    => ['new', 'confirmed', 'archived'],
                'author'         => $current_user,
                'numberposts'    => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => '_create_matchmaking',
                        'value'   => '1',
                        'compare' => '='
                    ]
                ],
            ]);

            foreach ($registration_post_ids as $reg_id) {
                $parent_id = wp_get_post_parent_id($reg_id);
                if ($parent_id) {
                    $event_ids[] = $parent_id;
                }
            }
            $event_ids = array_unique($event_ids);
        }

        if (empty($event_ids)) {
            return self::prepare_error_for_response(404);
        }

        // Step 2: Collect attendees with matchmaking
        $countries = wpem_get_all_countries();
        $filtered_users = [];

        foreach ($event_ids as $eid) {
            $users = wpem_get_all_match_making_attendees($current_user, $eid);

            foreach ($users as $u) {
                $uid = $u['user_id'] ?? $u['ID'];

                $regs = get_posts([
                    'post_type'      => 'event_registration',
                    'post_status'    => ['new', 'confirmed', 'archived'],
                    'post_parent'    => $eid,
                    'author'         => $uid,
                    'meta_query'     => [
                        [
                            'key'     => '_create_matchmaking',
                            'value'   => '1',
                            'compare' => '='
                        ]
                    ],
                    'fields' => 'ids'
                ]);

                if (!empty($regs)) {
                    $filtered_users[$uid] = $u;
                }
            }
        }

        $users = array_values($filtered_users);

        if (empty($users)) {
            return self::prepare_error_for_response(404);
        }

        // Step 3: Apply filters
        $final_users = [];

        foreach ($users as $user) {
            // Search (name, profession, company, country, city, skills, interests)
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $haystack = strtolower(
                    ($user['display_name'] ?? '') . ' ' .
                    ($user['_profession'] ?? '') . ' ' .
                    ($user['_company_name'] ?? '') . ' ' .
                    ($countries[$user['_country']] ?? $user['_country']) . ' ' .
                    ($user['_city'] ?? '') . ' ' .
                    implode(' ', (array) maybe_unserialize($user['_skills'])) . ' ' .
                    implode(' ', (array) maybe_unserialize($user['_interests']))
                );
                if (strpos($haystack, $search) === false) continue;
            }

            // Profession
            if (!empty($filters['profession']) && strtolower($filters['profession']) !== strtolower($user['_profession'] ?? '')) {
                continue;
            }

            // Company
            if (!empty($filters['company_name']) && strtolower($filters['company_name']) !== strtolower($user['_company_name'] ?? '')) {
                continue;
            }

            // Country
            if (!empty($filters['country'])) {
                $selected_countries = array_map('strtolower', (array) $filters['country']);
                $user_country = strtolower($user['_country'] ?? '');
                $user_country_name = strtolower($countries[$user_country] ?? $user_country);

                if (!in_array($user_country, $selected_countries, true) && !in_array($user_country_name, $selected_countries, true)) {
                    continue;
                }
            }

            // City
            if (!empty($filters['city']) && strtolower($filters['city']) !== strtolower($user['_city'] ?? '')) {
                continue;
            }

            // Experience
            if (!empty($filters['experience']) && is_array($filters['experience'])) {
                $user_exp = (int) ($user['_experience'] ?? 0);
                if ($user_exp < ($filters['experience']['min'] ?? 0) || $user_exp > ($filters['experience']['max'] ?? PHP_INT_MAX)) {
                    continue;
                }
            }

            // Skills
            if (!empty($filters['skills'])) {
                $user_skills = array_map('sanitize_title', (array) maybe_unserialize($user['_skills']));
                if (empty(array_intersect($filters['skills'], $user_skills))) {
                    continue;
                }
            }

            // Interests
            if (!empty($filters['interests'])) {
                $user_interests = array_map('sanitize_title', (array) maybe_unserialize($user['_interests']));
                if (empty(array_intersect($filters['interests'], $user_interests))) {
                    continue;
                }
            }

            $final_users[] = $user;
        }

        if (empty($final_users)) {
            return self::prepare_error_for_response(404);
        }

        // Step 4: Pagination
        $page     = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : 5;
        $offset   = ($page - 1) * $per_page;
        $paged_users = array_slice($final_users, $offset, $per_page);

        $response = self::prepare_error_for_response(200);
        $response['data'] = [
            'total'       => count($final_users),
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => ceil(count($final_users) / $per_page),
            'users'       => $paged_users,
        ];

        return wp_send_json($response);
    }

    /**
     * This function is used to get event list for which current loggedin user has registered
     * @since 1.3.0
     */
    public function get_wpem_matchmaking_user_events(){
        
    }
}

new WPEM_REST_Matchmaking_Profile_Controller();