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
                    'callback'            => array($this, 'wpem_matchmaking_filter_users'),
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
            '/' . $this->rest_base . '/filter',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'wpem_matchmaking_filter_users'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'wpem_matchmaking_filter_users'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );
    }

    /**
     * Permission callback: ensure matchmaking is enabled and user is authorized.
     *
     * Note: This follows the plugin's pattern of returning the standardized
     * error payload via prepare_error_for_response on failure.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error True if allowed, or sends JSON error.
     */
    public function permission_check($request) {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return $auth_check; // Standardized error already sent
        }
        return true;
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
    public function wpem_matchmaking_filter_users($request) {
        global $wpdb;
       
        $event_id = intval($request->get_param('event_id'));
        $user_id  = wpem_rest_get_current_user_id();

        // Step 1: Validate registration and collect attendees
        $registered_user_ids = [];
        if ($event_id && $user_id) {
            $registration_query = new WP_Query([
                'post_type'      => 'event_registration',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post_author'    => $user_id,
            ]);

            $has_registration = false;
            foreach ($registration_query->posts as $registration_id) {
                if (wp_get_post_parent_id($registration_id) == $event_id) {
                    $has_registration = true;
                    break;
                }
            }

            if (!$has_registration) {
                return self::prepare_error_for_response(404);
            }

            // Collect all registered users for this event
            $attendee_query = new WP_Query([
                'post_type'      => 'event_registration',
                'posts_per_page' => -1,
                'fields'         => 'ids'
            ]);

            foreach ($attendee_query->posts as $registration_id) {
                if (wp_get_post_parent_id($registration_id) == $event_id) {
                    $uid = intval(get_post_field('post_author', $registration_id));
                    if ($uid && !in_array($uid, $registered_user_ids)) {
                        $registered_user_ids[] = $uid;
                    }
                }
            }
        } elseif ($user_id) {
            // No event_id passed: choose the most recent event that has other attendees
            $user_regs = new WP_Query([
                'post_type'      => 'event_registration',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post_status'    => 'any',
                'post_author'    => $user_id,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            $user_event_ids_ordered = [];
            foreach ($user_regs->posts as $reg_id) {
                $parent_event = (int) wp_get_post_parent_id($reg_id);
                if ($parent_event) {
                    $user_event_ids_ordered[] = $parent_event;
                }
            }
            // Preserve order by date (DESC), but remove duplicates
            $user_event_ids_ordered = array_values(array_unique($user_event_ids_ordered));

            if (!empty($user_event_ids_ordered)) {
                // Preload all registrations, filter by event per iteration
                $attendee_query = new WP_Query([
                    'post_type'      => 'event_registration',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'post_status'    => 'any',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ]);

                foreach ($user_event_ids_ordered as $candidate_event_id) {
                    $candidate_user_ids = [];
                    foreach ($attendee_query->posts as $registration_id) {
                        if ((int) wp_get_post_parent_id($registration_id) === (int) $candidate_event_id) {
                            $uid = intval(get_post_field('post_author', $registration_id));
                            if ($uid && $uid !== $user_id && !in_array($uid, $candidate_user_ids, true)) {
                                $candidate_user_ids[] = $uid;
                            }
                        }
                    }
                    if (!empty($candidate_user_ids)) {
                        $registered_user_ids = $candidate_user_ids;
                        break;
                    }
                }
            }
        }

        if (empty($registered_user_ids)) {
            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'total_post_count' => 0,
                'current_page'     => max(1, (int) $request->get_param('page')),
                'last_page'        => 0,
                'total_pages'      => 0,
                'users'            => array(),
            );
            return wp_send_json($response_data);
        }

        // Step 2: Build user data
        $profession_terms = get_event_registration_taxonomy_list('event_registration_professions'); // [slug => name]
        $skills_terms     = get_event_registration_taxonomy_list('event_registration_skills');
        $interests_terms  = get_event_registration_taxonomy_list('event_registration_interests');

        $users_data = [];
        foreach ($registered_user_ids as $uid) {
            if ($uid == $user_id) continue;
            if (!get_user_meta($uid, '_matchmaking_profile', true)) continue;

            $photo = get_wpem_user_profile_photo($uid) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

            // Normalize organization logo
            $organization_logo = get_user_meta($uid, '_organization_logo', true);
            $organization_logo = maybe_unserialize($organization_logo);
            if (is_array($organization_logo)) {
                $organization_logo = reset($organization_logo);
            }
            $organization_logo = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';

            // Profession
            $profession_value = get_user_meta($uid, '_profession', true);
            $profession_slug  = $profession_value;
            if ($profession_value && !isset($profession_terms[$profession_value])) {
                $found_slug = array_search($profession_value, $profession_terms);
                if ($found_slug) {
                    $profession_slug = $found_slug;
                }
            }

            // Skills -> normalized to slugs, keep serialized string to mirror existing API structure
            $skills_slugs = [];
            $skills_arr = maybe_unserialize(get_user_meta($uid, '_skills', true));
            if (is_array($skills_arr)) {
                foreach ($skills_arr as $skill) {
                    $term = get_term_by('slug', $skill, 'event_registration_skills');
                    if (!$term) { $term = get_term_by('name', $skill, 'event_registration_skills'); }
                    if (!$term) { $term = get_term_by('id', $skill, 'event_registration_skills'); }
                    if ($term) { $skills_slugs[] = $term->slug; }
                }
            }
            $skills_slugs = array_filter($skills_slugs);
            $skills_serialized = serialize($skills_slugs);

            // Interests -> normalized to slugs, keep serialized string to mirror existing API structure
            $interests_slugs = [];
            $interests_arr = maybe_unserialize(get_user_meta($uid, '_interests', true));
            if (is_array($interests_arr)) {
                foreach ($interests_arr as $interest) {
                    $term = get_term_by('slug', $interest, 'event_registration_interests');
                    if (!$term) { $term = get_term_by('name', $interest, 'event_registration_interests'); }
                    if (!$term) { $term = get_term_by('id', $interest, 'event_registration_interests'); }
                    if ($term) { $interests_slugs[] = $term->slug; }
                }
            }
            $interests_slugs = array_filter($interests_slugs);
            $interests_serialized = serialize($interests_slugs);

            $countries = wpem_get_all_countries();
            $country_value = get_user_meta($uid, '_country', true);
            $country_code = '';
            if ($country_value) {
                if (isset($countries[$country_value])) {
                    $country_code = $country_value;
                } else {
                    $country_code = array_search($country_value, $countries);
                }
            }
            $org_country_value = get_user_meta($uid, '_organization_country', true);
            $org_country_code = '';
            if ($org_country_value) {
                if (isset($countries[$org_country_value])) {
                    $org_country_code = $org_country_value;
                } else {
                    $org_country_code = array_search($org_country_value, $countries);
                }
            }

            $users_data[] = [
                'user_id'                => $uid,
                'display_name'           => get_the_author_meta('display_name', $uid),
                'first_name'             => get_user_meta($uid, 'first_name', true),
                'last_name'              => get_user_meta($uid, 'last_name', true),
                'email'                  => get_userdata($uid)->user_email,
                'matchmaking_profile'    => get_user_meta($uid, '_matchmaking_profile', true),
                'profile_photo'          => $photo,
                'profession'             => $profession_slug,
                'experience'             => get_user_meta($uid, '_experience', true),
                'company_name'           => get_user_meta($uid, '_company_name', true),
                'country'                => $country_code,
                'city'                   => get_user_meta($uid, '_city', true),
                'about'                  => get_user_meta($uid, '_about', true),
                'skills'                 => $skills_serialized,
                'interests'              => $interests_serialized,
                'message_notification'   => get_user_meta($uid, '_message_notification', true),
                'organization_name'      => get_user_meta($uid, '_organization_name', true),
                'organization_logo'      => $organization_logo,
                'organization_country'   => $org_country_code,
                'organization_city'      => get_user_meta($uid, '_organization_city', true),
                'organization_description'=> get_user_meta($uid, '_organization_description', true),
                'organization_website'   => get_user_meta($uid, '_organization_website', true),
                'available_for_meeting'  => get_user_meta($uid, '_available_for_meeting', true),
                'approve_profile_status' => get_user_meta($uid, '_approve_profile_status', true),
            ];
        }

        // Step 3: Apply filters
        $profession = sanitize_text_field($request->get_param('profession'));
        $country    = $request->get_param('country');
        $skills     = $request->get_param('skills');
        $interests  = $request->get_param('interests');
        $search     = sanitize_text_field($request->get_param('search'));

        $filtered_users = array_filter($users_data, function($user) use ($profession, $country, $skills, $interests, $search) {
            if ($profession && strtolower($user['profession']) !== strtolower($profession)) {
                return false;
            }
            if (!empty($country) && is_array($country) && !in_array($user['country'], $country)) {
                return false;
            }
            if (!empty($skills) && is_array($skills)) {
                // Unserialize stored skills for comparison
                $user_skills = @unserialize($user['skills']);
                if (!is_array($user_skills) || !array_intersect($skills, $user_skills)) {
                    return false;
                }
            }
            if (!empty($interests) && is_array($interests)) {
                $user_interests = @unserialize($user['interests']);
                if (!is_array($user_interests) || !array_intersect($interests, $user_interests)) {
                    return false;
                }
            }
            if ($search) {
                $haystack_parts = [];
                foreach ($user as $key => $val) {
                    if (is_array($val)) {
                        $haystack_parts = array_merge($haystack_parts, $val);
                    } else {
                        $haystack_parts[] = $val;
                    }
                }
                $haystack = strtolower(implode(' ', $haystack_parts));
                if (strpos($haystack, strtolower($search)) === false) {
                    return false;
                }
            }
            return true;
        });

        // Step 4: Pagination
        $per_page = max(1, (int) $request->get_param('per_page'));
        $page     = max(1, (int) $request->get_param('page'));
        $total    = count($filtered_users);
        $offset   = ($page - 1) * $per_page;
        $paged_users = array_slice($filtered_users, $offset, $per_page);

        $response_data = self::prepare_error_for_response( 200 );
        $response_data['data'] = array(
            'total_post_count' => $total,
            'current_page'     => $page,
            'last_page'        => ceil($total / $per_page),
            'total_pages'      => ceil($total / $per_page),
            'users'            => array_values($paged_users),
        );
        return wp_send_json($response_data);
    }
}

new WPEM_REST_Matchmaking_Profile_Controller();