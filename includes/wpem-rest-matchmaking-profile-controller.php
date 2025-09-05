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
    protected $rest_base = 'user-profile';

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
                    'args'                => array(
                        'user_id' => array(
                            'required' => false,
                            'type'     => 'integer',
                        ),
                    ),
                ),
            ),
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_matchmaking_profile'),
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
     * Build a normalized profile array for the given user id.
     *
     * @param int $user_id
     * @param array $countries
     * @return array|null
     */
    protected function build_profile_payload($user_id, $countries) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }

        $user_meta = get_user_meta($user_id);
        $photo = get_wpem_user_profile_photo($user_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

        $organization_logo = get_user_meta($user_id, '_organization_logo', true);
        $organization_logo = maybe_unserialize($organization_logo);
        if (is_array($organization_logo)) {
            $organization_logo = reset($organization_logo);
        }
        $organization_logo = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';

        // Country normalization (value may be code or name)
        $country_value = isset($user_meta['_country'][0]) ? sanitize_text_field($user_meta['_country'][0]) : '';
        $country_code = '';
        if ($country_value) {
            if (isset($countries[$country_value])) {
                $country_code = $country_value;
            } else {
                $country_code = array_search($country_value, $countries);
            }
        }

        // Organization country normalization
        $org_country_value = isset($user_meta['_organization_country'][0]) ? sanitize_text_field($user_meta['_organization_country'][0]) : '';
        $org_country_code = '';
        if ($org_country_value) {
            if (isset($countries[$org_country_value])) {
                $org_country_code = $org_country_value;
            } else {
                $org_country_code = array_search($org_country_value, $countries);
            }
        }

        // Meeting availability (default 1)
        $meta_avail = get_user_meta($user_id, '_available_for_meeting', true);
        $meeting_available = ($meta_avail !== '' && $meta_avail !== null) ? ((int)$meta_avail === 0 ? 0 : 1) : 1;

        // Profession normalization to slug
        $professions = get_event_registration_taxonomy_list('event_registration_professions');
        $profession_value = isset($user_meta['_profession'][0]) ? sanitize_text_field($user_meta['_profession'][0]) : '';
        $profession_slug = $profession_value;
        if ($profession_value && !isset($professions[$profession_value])) {
            $found_slug = array_search($profession_value, $professions);
            if ($found_slug) {
                $profession_slug = $found_slug;
            }
        }

        // Skills to slug array, then serialize (to match existing storage style)
        $skills_slugs = array();
        $skills_arr = isset($user_meta['_skills'][0]) ? maybe_unserialize($user_meta['_skills'][0]) : array();
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

        // Interests to slug array, then serialize
        $interests_slugs = array();
        $interests_arr = isset($user_meta['_interests'][0]) ? maybe_unserialize($user_meta['_interests'][0]) : array();
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

        return array(
            'user_id'                    => $user_id,
            'display_name'               => $user->display_name,
            'first_name'                 => isset($user_meta['first_name'][0]) ? sanitize_text_field($user_meta['first_name'][0]) : '',
            'last_name'                  => isset($user_meta['last_name'][0]) ? sanitize_text_field($user_meta['last_name'][0]) : '',
            'email'                      => $user->user_email,
            'matchmaking_profile'        => isset($user_meta['_matchmaking_profile'][0]) ? (int)$user_meta['_matchmaking_profile'][0] : 0,
            'profile_photo'              => $photo,
            'profession'                 => $profession_slug,
            'experience'                 => isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0,
            'company_name'               => isset($user_meta['_company_name'][0]) ? sanitize_text_field($user_meta['_company_name'][0]) : '',
            'country'                    => $country_code,
            'city'                       => isset($user_meta['_city'][0]) ? sanitize_text_field($user_meta['_city'][0]) : '',
            'about'                      => isset($user_meta['_about'][0]) ? sanitize_textarea_field($user_meta['_about'][0]) : '',
            'skills'                     => $skills_serialized,
            'interests'                  => $interests_serialized,
            'message_notification'       => isset($user_meta['_message_notification'][0]) ? (int)$user_meta['_message_notification'][0] : 0,
            'organization_name'          => isset($user_meta['_organization_name'][0]) ? sanitize_text_field($user_meta['_organization_name'][0]) : '',
            'organization_logo'          => $organization_logo,
            'organization_country'       => $org_country_code,
            'organization_city'          => isset($user_meta['_organization_city'][0]) ? sanitize_text_field($user_meta['_organization_city'][0]) : '',
            'organization_description'   => isset($user_meta['_organization_description'][0]) ? sanitize_textarea_field($user_meta['_organization_description'][0]) : '',
            'organization_website'       => isset($user_meta['_organization_website'][0]) ? sanitize_text_field($user_meta['_organization_website'][0]) : '',
            'approve_profile_status'     => isset($user_meta['_approve_profile_status'][0]) ? (int)$user_meta['_approve_profile_status'][0] : 0,
            'wpem_meeting_request_mode'  => isset($user_meta['_wpem_meeting_request_mode'][0]) ? $user_meta['_wpem_meeting_request_mode'][0] : 'approval',
            'available_for_meeting'      => (int)$meeting_available,
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

        // Inline payload build (avoid dependency on helper that used undefined var)
        $user_meta = get_user_meta($user_id);
        $photo =  get_wpem_user_profile_photo($user_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

        $countries = wpem_get_all_countries();
        $organization_logo = get_user_meta($user_id, '_organization_logo', true);
        $organization_logo = maybe_unserialize($organization_logo);
        if (is_array($organization_logo)) {
            $organization_logo = reset($organization_logo);
        }
        $organization_logo = $organization_logo ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';

        $country_value = isset($user_meta['_country'][0]) ? sanitize_text_field($user_meta['_country'][0]) : '';
        $country_code = '';
        if ($country_value) {
            if (isset($countries[$country_value])) {
                $country_code = $country_value;
            } else {
                $country_code = array_search($country_value, $countries);
            }
        }

        $org_country_value = isset($user_meta['_organization_country'][0]) ? sanitize_text_field($user_meta['_organization_country'][0]) : '';
        $org_country_code = '';
        if ($org_country_value) {
            if (isset($countries[$org_country_value])) {
                $org_country_code = $org_country_value;
            } else {
                $org_country_code = array_search($org_country_value, $countries);
            }
        }

        $meta = get_user_meta($user_id, '_available_for_meeting', true);
        $meeting_available = ($meta !== '' && $meta !== null) ? ((int)$meta === 0 ? 0 : 1) : 1;

        $professions = get_event_registration_taxonomy_list('event_registration_professions');
        $profession_value = isset($user_meta['_profession'][0]) ? sanitize_text_field($user_meta['_profession'][0]) : '';
        $profession_slug = $profession_value;
        if ($profession_value && !isset($professions[$profession_value])) {
            $found_slug = array_search($profession_value, $professions);
            if ($found_slug) {
                $profession_slug = $found_slug;
            }
        }

        $skills_slugs = array();
        $skills_arr = isset($user_meta['_skills'][0]) ? maybe_unserialize($user_meta['_skills'][0]) : array();
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

        $interests_slugs = array();
        $interests_arr = isset($user_meta['_interests'][0]) ? maybe_unserialize($user_meta['_interests'][0]) : array();
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

        $profile = array(
            'user_id'                    => $user_id,
            'display_name'               => $user->display_name,
            'first_name'                 => isset($user_meta['first_name'][0]) ? sanitize_text_field($user_meta['first_name'][0]) : '',
            'last_name'                  => isset($user_meta['last_name'][0]) ? sanitize_text_field($user_meta['last_name'][0]) : '',
            'email'                      => $user->user_email,
            'matchmaking_profile'        => isset($user_meta['_matchmaking_profile'][0]) ? (int)$user_meta['_matchmaking_profile'][0] : 0,
            'profile_photo'              => $photo,
            'profession'                 => $profession_slug,
            'experience'                 => isset($user_meta['_experience'][0]) ? (float)$user_meta['_experience'][0] : 0,
            'company_name'               => isset($user_meta['_company_name'][0]) ? sanitize_text_field($user_meta['_company_name'][0]) : '',
            'country'                    => $country_code,
            'city'                       => isset($user_meta['_city'][0]) ? sanitize_text_field($user_meta['_city'][0]) : '',
            'about'                      => isset($user_meta['_about'][0]) ? sanitize_textarea_field($user_meta['_about'][0]) : '',
            'skills'                     => $skills_serialized,
            'interests'                  => $interests_serialized,
            'message_notification'       => isset($user_meta['_message_notification'][0]) ? (int)$user_meta['_message_notification'][0] : 0,
            'organization_name'          => isset($user_meta['_organization_name'][0]) ? sanitize_text_field($user_meta['_organization_name'][0]) : '',
            'organization_logo'          => $organization_logo,
            'organization_country'       => $org_country_code,
            'organization_city'          => isset($user_meta['_organization_city'][0]) ? sanitize_text_field($user_meta['_organization_city'][0]) : '',
            'organization_description'   => isset($user_meta['_organization_description'][0]) ? sanitize_textarea_field($user_meta['_organization_description'][0]) : '',
            'organization_website'       => isset($user_meta['_organization_website'][0]) ? sanitize_text_field($user_meta['_organization_website'][0]) : '',
            'approve_profile_status'     => isset($user_meta['_approve_profile_status'][0]) ? (int)$user_meta['_approve_profile_status'][0] : 0,
            'wpem_meeting_request_mode'  => isset($user_meta['_wpem_meeting_request_mode'][0]) ? $user_meta['_wpem_meeting_request_mode'][0] : 'approval',
            'available_for_meeting'      => (int)$meeting_available,
        );

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

        // Allowed meta fields to update
        $meta_fields = array(
            'profession', 'experience', 'company_name', 'country', 'city', 'about', 'skills', 'interests',
            'organization_name', 'organization_logo', 'organization_city', 'organization_country',
            'organization_description', 'organization_website', 'message_notification', 'matchmaking_profile'
        );

        foreach ($meta_fields as $field) {
            if ($request->get_param($field) !== null) {
                $value = $request->get_param($field);

                if (in_array($field, array('skills', 'interests'), true)) {
                    if (!is_array($value)) { $value = array($value); }
                    $value = array_values(array_filter($value, function($v) { return $v !== null && $v !== ''; }));
                    if (!empty($value)) {
                        update_user_meta($user_id, '_' . $field, $value);
                    } else {
                        update_user_meta($user_id, '_' . $field, '');
                    }
                } else {
                    if (is_array($value)) {
                        $value = array_values(array_filter($value, function($v) { return $v !== null && $v !== ''; }));
                    }
                    if ($value !== '' && !(is_array($value) && empty($value))) {
                        update_user_meta($user_id, '_' . $field, $value);
                    } else {
                        update_user_meta($user_id, '_' . $field, '');
                    }
                }
            }
        }

        // profile_photo upload
        if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['profile_photo'], $upload_overrides);
            if (isset($movefile['url'])) {
                update_user_meta($user_id, '_profile_photo', esc_url_raw($movefile['url']));
            } else {
                return self::prepare_error_for_response(500);
            }
        } elseif ($request->get_param('profile_photo')) {
            update_user_meta($user_id, '_profile_photo', esc_url_raw($request->get_param('profile_photo')));
        } elseif (isset($_FILES['profile_photo']) && isset($_FILES['profile_photo']['full_path']) && $_FILES['profile_photo']['full_path'] === '') {
            update_user_meta($user_id, '_profile_photo', EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png');
        }

        // organization_logo upload
        if (!empty($_FILES['organization_logo']) && $_FILES['organization_logo']['error'] === UPLOAD_ERR_OK) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['organization_logo'], $upload_overrides);
            if (isset($movefile['url'])) {
                update_user_meta($user_id, '_organization_logo', esc_url_raw($movefile['url']));
            } else {
                return self::prepare_error_for_response(500);
            }
        } elseif ($request->get_param('organization_logo')) {
            update_user_meta($user_id, '_organization_logo', esc_url_raw($request->get_param('organization_logo')));
        } elseif (isset($_FILES['organization_logo']) && isset($_FILES['organization_logo']['full_path']) && $_FILES['organization_logo']['full_path'] === '') {
            update_user_meta($user_id, '_organization_logo', EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg');
        }

        // Update core user fields
        if ($request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($request->get_param('first_name')));
        }
        if ($request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($request->get_param('last_name')));
        }
        if ($request->get_param('email')) {
            $email = sanitize_email($request->get_param('email'));
            $email_exists = email_exists($email);
            if ($email_exists && (int)$email_exists !== $user_id) {
                return self::prepare_error_for_response(400, array('message' => 'Email already in use.'));
            }
            $result = wp_update_user(array('ID' => $user_id, 'user_email' => $email));
            if (is_wp_error($result)) {
                return self::prepare_error_for_response(500, array('message' => $result->get_error_message()));
            }
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

new WPEM_REST_Matchmaking_Profile_Controller();
