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

class WPEM_REST_Matchmaking_Profile_Controller extends WPEM_REST_CRUD_Controller
{
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
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'wpem_register_routes'), 10);
    }

    /**
     * Register profile routes in the event-controller format.
     */
    public function wpem_register_routes()
    {
        // GET - Retrieve single or all matchmaking profiles
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_user_profile_data'),
                    'permission_callback' => array($this, 'wpem_get_user_profile_permission_check'),
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
                    'callback' => array($this, 'wpem_update_matchmaking_profile'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
                )
            )
        );

        // POST - Upload a user file (profile photo)
        register_rest_route(
            $this->namespace,
            '/upload-user-file',
            array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'wpem_upload_user_file'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(
                        'user_id' => array(
                            'required' => true,
                            'type' => 'integer',
                        ),
                    ),
                ),
            )
        );

        // Retrieve/Update matchmaking profile settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-approval',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'wpem_approve_matchmaking_profile'),
                'permission_callback' => array($this, 'wpem_permission_check'),
                // 'args'     => array(
                //     'registration_id' => array(
                //         'required' => true,
                //         'type'     => 'integer',
                //     ),
                //     'profile_status' => array(
                //         'required' => true,
                //         'type'     => 'integer',
                //     )
                // ),
            ),
        );

        // Retrieve/Update matchmaking profile settings
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'wpem_get_matchmaking_profile_settings'),
                'permission_callback' => array($this, 'wpem_permission_check'),
                'args' => array(),
            ),
        );
        register_rest_route(
            $this->namespace,
            '/matchmaking-profile-settings',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'wpem_update_matchmaking_profile_settings'),
                'permission_callback' => array($this, 'wpem_permission_check'),
                'args' => array(
                    'user_id' => array(
                        'required' => false,
                        'type' => 'integer',
                    ),
                    'is_user_matchmaking_profile_enabled' => array(
                        'required' => false,
                        'type' => 'integer',
                    ),
                    'message_notification' => array(
                        'required' => false,
                        'type' => 'integer',
                    ),
                    'meeting_request_mode' => array(
                        'required' => false,
                        'type' => 'string',
                    ),
                    'event_participation' => array(
                        'required' => false,
                        'type' => 'array',
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
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_wpem_matchmaking_filter_users'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(
                        'profession' => array('required' => false, 'type' => 'string'),
                        'company_name' => array('required' => false, 'type' => 'string'),
                        // Accept either 'country[]=a&country[]=b' OR the shorter
                        // 'country=a,b,c' comma-separated form. wpem_sanitize_array_or_csv()
                        // normalizes both into a plain array before the callback runs.
                        'country' => array(
                            'required' => false,
                            'type' => array('array', 'string'),
                            'sanitize_callback' => array($this, 'wpem_sanitize_array_or_csv'),
                            'validate_callback' => '__return_true',
                        ),
                        'city' => array('required' => false, 'type' => 'string'),
                        'experience' => array('required' => false),
                        'skills' => array(
                            'required' => false,
                            'type' => array('array', 'string'),
                            'sanitize_callback' => array($this, 'wpem_sanitize_array_or_csv'),
                            'validate_callback' => '__return_true',
                        ),
                        'interests' => array(
                            'required' => false,
                            'type' => array('array', 'string'),
                            'sanitize_callback' => array($this, 'wpem_sanitize_array_or_csv'),
                            'validate_callback' => '__return_true',
                        ),
                        'event_id' => array('required' => false, 'type' => 'integer'),
                        'search' => array('required' => false, 'type' => 'string'),
                        'is_bookmarked' => array(
                            'required' => false,
                            'type' => 'boolean',
                            'sanitize_callback' => 'rest_sanitize_boolean',
                        ),
                        // match the currently authenticated user's own skills/interests/profession
                        // - 'exact'   => participant must match ALL of the current user's set skills/interests/profession
                        // - 'partial' => participant must match AT LEAST ONE of them
                        'your_matches' => array('required' => false, 'type' => 'string', 'enum' => array('exact', 'partial'),),
                        'per_page' => array('required' => false, 'type' => 'integer', 'default' => 5),
                        'page' => array('required' => false, 'type' => 'integer', 'default' => 1),
                    ),
                ),
            )
        );

        // POST - Toggle a matchmaking participant bookmark.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/bookmark',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'wpem_toggle_matchmaking_bookmark'),
                'permission_callback' => array($this, 'wpem_permission_check'),
                'args' => array(
                    'participant_user_id' => array(
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                    ),
                ),
            )
        );

        // Alias endpoint for legacy path and POST method
        // get current user's registered events, match by current user's emailid
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/events',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_wpem_matchmaking_user_events'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                )
            )
        );
    }

    /**
     * Sanitize callback for query params that accept multiple values.
     * Normalizes both accepted input styles into a plain, trimmed array:
     *   - repeated bracket params: country[]=india&country[]=germany
     *   - a single comma-separated string: country=india,germany,new-jersey
     *
     * @param mixed $value Raw param value from the request.
     * @return array
     */
    public function wpem_sanitize_array_or_csv($value)
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && $value !== '') {
            $items = explode(',', $value);
        } else {
            return array();
        }

        $items = array_map(function ($item) {
            return sanitize_text_field(trim((string) $item));
        }, $items);

        return array_values(array_filter($items, function ($item) {
            return $item !== '';
        }));
    }
    
    /**
     * Permission callback: ensure matchmaking is enabled and user is authorized.
     *
     * Note: This follows the plugin's pattern of returning the standardized
     * error payload via wpem_prepare_error_for_response on failure.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error True if allowed, or sends JSON error.
     */
    public function wpem_permission_check($request)
    {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return $auth_check; // Standardized error already sent
        }
        return true;
    }

    /**
     * Toggle a matchmaking participant in the authenticated user's bookmarks.
     *
     * @param WP_REST_Request $request Request containing participant_user_id.
     * @return array
     */
    public function wpem_toggle_matchmaking_bookmark(WP_REST_Request $request)
    {
        $current_user_id = absint(wpem_rest_get_current_user_id());
        $participant_user_id = absint($request->get_param('participant_user_id'));

        if (!$participant_user_id || !get_userdata($participant_user_id)) {
            return self::wpem_prepare_error_for_response(404);
        }

        if ($current_user_id === $participant_user_id) {
            return self::wpem_prepare_error_for_response(508);
        }

        $bookmarked_users = array_map(
            'absint',
            (array) get_user_meta($current_user_id, '_matchmaking_bookmarked_users', true)
        );
        $bookmark_index = array_search($participant_user_id, $bookmarked_users, true);

        if ($bookmark_index !== false) {
            unset($bookmarked_users[$bookmark_index]);
            $status = 0;
        } else {
            $bookmarked_users[] = $participant_user_id;
            $status = 1;
        }

        $bookmarked_users = array_values($bookmarked_users);
        update_user_meta($current_user_id, '_matchmaking_bookmarked_users', $bookmarked_users);

        $response = self::wpem_prepare_error_for_response(200);
        $response['message'] = $status === 1 ? 'User bookmarked successfully.' : 'User removed from bookmarks.';

        return $response;
    }

    public function wpem_get_user_profile_permission_check( $request ) {

        // Authentication
        $auth_check = $this->wpem_check_authorized_user();

        if ( $auth_check ) {
            return $auth_check;
        }

        $current_user = (int) wpem_rest_get_current_user_id();
        $is_admin  = user_can( $current_user, 'manage_options' );
        $requested_user = absint( $request->get_param( 'user_id' ) );

        $user_status = wpem_get_user_login_status($current_user);
        $is_organizer = isset( $user_status['is_organizer'] ) ? (int) $user_status['is_organizer'] : 0;

        if ( empty( $requested_user ) || ($current_user === $requested_user) || $is_organizer == 1 || $is_admin ) {
            return true;
        }
        
        return self::wpem_prepare_error_for_response(414);
    }

    public function wpem_approve_profile_permission_check( $request ) {

        // Authentication
        $auth_check = $this->wpem_check_authorized_user();

        if ( $auth_check ) {
            return $auth_check;
        }

        // Authorization
        global $wpdb;
        $current_user = wpem_rest_get_current_user_id();
        $requested_user = absint( $request->get_param( 'user_id' ) );
        $table_name = esc_sql($wpdb->prefix . 'wpem_rest_api_keys');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $user_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id = %d", $current_user));
        $is_user_organizer = ($user_info) ? 1 : 0;

        if ( (!empty($is_user_organizer)) && (user_can($current_user, 'manage_options') || user_can($current_user, 'manage_organizers') || user_can($current_user, 'customer') || get_option( 'default_role' )) ) {
            return true;
        }

        return self::wpem_prepare_error_for_response( 403 );
    }

    public function wpem_search_profiles_permission_check( $request ) {

        $auth_check = $this->wpem_check_authorized_user();

        if ( $auth_check ) {
            return $auth_check;
        }

        $current_user_id = wpem_rest_get_current_user_id();

        // User must have an active matchmaking profile.
        $matchmaking_enabled = get_user_meta(
            $current_user_id,
            '_matchmaking_profile',
            true
        );

        if ( ! empty( $matchmaking_enabled ) ) {
            return true;
        }

        return self::wpem_prepare_error_for_response( 403 );
    }
    
    /**
     * GET /matchmaking-profile
     * Retrieve the matchmaking profile of a user, grouped by the "Tab Group"
     * configured in Event Registrations > Registration Form.
     *
     *  - other_fields                   : every remaining value that is NOT a registration form field
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function wpem_get_user_profile_data($request)
    {
        $user_id = (int) $request->get_param('user_id') ? (int) $request->get_param('user_id') : wpem_rest_get_current_user_id();
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return self::wpem_prepare_error_for_response(404);
        }

        $user_meta = get_user_meta($user_id);
        $form_fields = $this->wpem_get_profile_form_fields();

        // Tabs (about / organization / settings) - same source the admin "Tab Group" dropdown uses.
        $sections = array(
            'about' => array(),
            'organization' => array(),
            'settings' => array(),
        );
        if (function_exists('wpem_get_matchmaking_profile_tabs')) {
            foreach (array_keys((array) wpem_get_matchmaking_profile_tabs()) as $tab_key) {
                if (!isset($sections[$tab_key])) {
                    $sections[$tab_key] = array();
                }
            }
        }

        // Group registration form fields by tab group.
        $order = 0;
        foreach ($form_fields as $field_key => $config) {
            if (!is_array($config)) {
                continue;
            }
            $field_key = (string) $field_key;

            // Empty / unknown tab group falls back to "about" (same as the admin dropdown shows).
            $tab = isset($config['tabgroup']) ? (string) $config['tabgroup'] : '';
            if ($tab === '' || !isset($sections[$tab])) {
                $tab = 'about';
            }

            $priority = (isset($config['priority']) && is_numeric($config['priority'])) ? (int) $config['priority'] : $order;

            $field_type = !empty($config['type']) ? (string) $config['type'] : 'text';

            $entry = array(
                'label' => isset($config['label']) ? (string) $config['label'] : '',
                'type' => $field_type,
                'description' => $this->wpem_blank_to_null(isset($config['description']) ? $config['description'] : ''),
                'placeholder' => $this->wpem_blank_to_null(isset($config['placeholder']) ? $config['placeholder'] : ''),
            );

            // Select type fields also return their selectable options.
            if (in_array($field_type, array('select', 'multiselect'), true)) {
                $entry['options'] = $this->wpem_get_profile_field_options($config);
            }

            // Term fields return the taxonomy selected in the form (e.g. event_registration_professions).
            if (in_array($field_type, array('term-select', 'term-multiselect'), true)) {
                $entry['taxonomy'] = !empty($config['taxonomy']) ? (string) $config['taxonomy'] : null;
            }

            // File fields return whether multiple files are allowed.
            if ('file' === $field_type) {
                $entry['multiple_files'] = !empty($config['multiple']) ? 1 : 0;
            }

            // "Add to Registration Form" / "Add to User Profile Form" checkboxes.
            // Attendee name / last name / email are locked (always checked) in the admin screen.
            $is_locked = in_array($field_key, array('attendee_name', 'attendee_last_name', 'attendee_email'), true);

            $entry += array(
                'metakey' => '_' . $field_key,
                'value' => $this->wpem_get_profile_field_value($user_id, $user, $field_key, $config, $user_meta),
                'priority' => $priority,
                'validation' => $this->wpem_get_profile_field_validation($config),
                'add_to_registration_form' => ($is_locked || !empty($config['add_to_form'])) ? 1 : 0,
                'add_to_user_profile_form' => ($is_locked || !empty($config['matchmaking_field'])) ? 1 : 0,
                '_order' => $order,
            );
            $sections[$tab][] = $entry;
            $order++;
        }

        // Sort every tab by the priority saved in the registration form (ties keep form order).
        $data = array();
        foreach ($sections as $tab => $items) {
            usort($items, function ($a, $b) {
                if ($a['priority'] === $b['priority']) {
                    return $a['_order'] - $b['_order'];
                }
                return $a['priority'] - $b['priority'];
            });
            foreach ($items as $i => $item) {
                unset($items[$i]['_order']);
            }
            if ('organization' === $tab) {
                $has_value = false;
                $has_saved_logo = !empty($this->wpem_get_saved_organization_logo_urls($user_id));
                foreach ($items as $item) {
                    // The default organization logo is only a placeholder, it is not "filled in" data.
                    if ('_organization_logo' === $item['metakey'] && !$has_saved_logo) {
                        continue;
                    }
                    if (!($item['value'] === null || $item['value'] === array())) {
                        $has_value = true;
                        break;
                    }
                }
                if (!$has_value) {
                    // $data['organization'] = null; // nothing filled in the organization tab
                    continue;
                }
            }
            $data[('settings' === $tab) ? 'setting' : $tab] = array('fields' => array_values($items));
        }

        // Everything that is not a registration form field.
        $other = array(
            'user_id' => $user_id,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        );

        // Photo / logo are normally form fields; only expose them here when the admin removed them from the form.
        if (!isset($form_fields['profile_photo'])) {
            $other['profile_photo'] = $this->wpem_get_profile_photo_value($user_id);
        }
        if (!isset($form_fields['organization_logo'])) {
            $other['organization_logo'] = $this->wpem_get_organization_logo_value($user_id);
        }

        $other['profile_photo_status'] = get_user_meta($user_id, '_profile_photo', true) ? true : false;
        $other['organization_logo_status'] = !empty($this->wpem_get_saved_organization_logo_urls($user_id));

        if (!isset($form_fields['is_user_matchmaking_profile_enabled'])) {
            $other['is_user_matchmaking_profile_enabled'] = get_user_meta($user_id, '_matchmaking_profile', true) ? (int) get_user_meta($user_id, '_matchmaking_profile', true) : 0;
        }
        if (!isset($form_fields['approve_profile_status'])) {
            $other['approve_profile_status'] = get_user_meta($user_id, '_approve_profile_status', true) ? (int) get_user_meta($user_id, '_approve_profile_status', true) : 0;
        }
        if (!isset($form_fields['wpem_meeting_request_mode'])) {
            $other['wpem_meeting_request_mode'] = get_user_meta($user_id, '_wpem_meeting_request_mode', true) ? get_user_meta($user_id, '_wpem_meeting_request_mode', true) : 'approval';
        }
        if (!isset($form_fields['available_for_meeting'])) {
            $meta = get_user_meta($user_id, '_available_for_meeting', true);
            $other['available_for_meeting'] = ($meta !== '' && $meta !== null) ? ((int) $meta === 0 ? 0 : 1) : 1;
        }

        $other['organization_default_logo'] = $this->wpem_get_default_organization_logo();

        $data['other_fields'] = $other;

        // Build response
        $response = self::wpem_prepare_error_for_response(200);
        $response['data'] = $data;
        return wp_send_json($response);
    }

    /**
     * Registration form fields exactly as the admin "Registration Form" screen shows them.
     *
     * get_wpem_user_matchmaking_profile_fields() merges saved fields into the defaults with
     * array_replace_recursive(), which cannot remove an entry from the default "rules" array
     * (e.g. admin un-selects "From Name" but it still comes back). The admin screen uses a
     * shallow per-field merge, so the same is done here.
     *
     * @return array
     */
    private function wpem_get_profile_form_fields()
    {
        $defaults = wpem_get_registration_default_form_fields();
        $saved = get_option('event_registration_form_fields', array());
        $defaults = is_array($defaults) ? $defaults : array();
        $saved = is_array($saved) ? $saved : array();

        $fields = array();
        foreach ($defaults as $key => $default_config) {
            if (isset($saved[$key]) && is_array($saved[$key])) {
                $fields[$key] = array_merge($default_config, $saved[$key]);
        } else {
                $fields[$key] = $default_config;
            }
        }
        // Custom fields added by the admin (not part of the defaults).
        foreach ($saved as $key => $saved_config) {
            if (!isset($fields[$key]) && is_array($saved_config)) {
                $fields[$key] = $saved_config;
            }
        }

        // "options" are resolved the way the plugin does for the profile form (recursive merge), so the
        // full country list of default fields is kept when the admin saves the form.
        $merged = array_replace_recursive($defaults, $saved);
        foreach ($fields as $key => $config) {
            if (isset($merged[$key]['options']) && is_array($merged[$key]['options'])) {
                $fields[$key]['options'] = $merged[$key]['options'];
            }
        }

        return $fields;
    }

    /**
     * Options of a select / multiselect field as an ordered list: [ {value, label}, ... ].
     *
     * @param array $config Field config.
     * @return array
     */
    private function wpem_get_profile_field_options($config)
    {
        $options = (!empty($config['options']) && is_array($config['options'])) ? $config['options'] : array();

        $list = array();
        foreach ((array) $options as $value => $label) {
            if ((string) $value === '' || !is_scalar($label)) {
                continue; // skip the empty "Select ..." placeholder option
            }
            $list[] = array(
                'value' => (string) $value,
                'label' => wp_specialchars_decode(stripslashes((string) $label), ENT_QUOTES),
            );
        }
        return $list;
    }

    /**
     * Blank (empty string / null / empty array) => null, everything else untouched.
     */
    private function wpem_blank_to_null($value)
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }
        if (is_array($value) && empty($value)) {
            return null;
        }
        return $value === null ? null : $value;
    }

    /**
     * Validation rules of a field, in registration-form format ("required" first).
     * Slugs use hyphens (from_name => from-name).
     *
     * @param array $config Field config.
     * @return array
     */
    private function wpem_get_profile_field_validation($config)
    {
        $rules = array();
        if (!empty($config['required'])) {
            $rules[] = 'required';
        }
        if (!empty($config['rules'])) {
            foreach ((array) $config['rules'] as $rule) {
                if (is_scalar($rule) && (string) $rule !== '') {
                    $rules[] = str_replace('_', '-', strtolower((string) $rule));
                }
            }
        }
        return array_values(array_unique($rules));
    }

    private function wpem_get_profile_photo_value($user_id)
    {
        return get_wpem_user_profile_photo($user_id) ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
    }

    /**
     * Default organization logo (used when the user has not set one).
     */
    private function wpem_get_default_organization_logo()
    {
        return EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/organisation-icon.jpg';
    }

    /**
     * Organization logo URLs really saved by the user (default placeholder is ignored).
     */
    private function wpem_get_saved_organization_logo_urls($user_id)
    {
        $urls = $this->wpem_get_file_urls(get_user_meta($user_id, '_organization_logo', true));
        return array_values(array_filter($urls, function ($url) {
            return basename(wp_parse_url($url, PHP_URL_PATH) ?: $url) !== 'organisation-icon.jpg';
        }));
    }

    /**
     * Organization logo (single file field): saved logo, otherwise the default logo.
     */
    private function wpem_get_organization_logo_value($user_id)
    {
        $urls = $this->wpem_get_saved_organization_logo_urls($user_id);
        return !empty($urls) ? reset($urls) : $this->wpem_get_default_organization_logo();
    }

    /**
     * Stored value of one registration form field for a user (null when blank).
     */
    private function wpem_get_profile_field_value($user_id, $user, $field_key, $config, $user_meta)
    {
        $type = !empty($config['type']) ? $config['type'] : 'text';

        // File field that allows multiple files: always a list of URLs ([] when there is none).
        if ('file' === $type && !empty($config['multiple'])) {
            $meta_key = '_' . $field_key;
            $urls = $this->wpem_get_file_urls(isset($user_meta[$meta_key][0]) ? $user_meta[$meta_key][0] : '');
            // No photo saved: return the default photo (as a one item list) like the single file field does.
            if (empty($urls) && 'profile_photo' === $field_key) {
                $urls = array(EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png');
            }
            // No organization logo saved: return the default logo (as a one item list).
            if ('organization_logo' === $field_key) {
                $urls = $this->wpem_get_saved_organization_logo_urls($user_id);
                if (empty($urls)) {
                    $urls = array($this->wpem_get_default_organization_logo());
                }
            }
            return $urls;
        }

        // Photo / logo keep their placeholder image fallback (same as before).
        if ('profile_photo' === $field_key) {
            return $this->wpem_get_profile_photo_value($user_id);
        }
        if ('organization_logo' === $field_key) {
            return $this->wpem_get_organization_logo_value($user_id);
        }

        $meta_key = '_' . $field_key;
        $raw_value = isset($user_meta[$meta_key][0]) ? maybe_unserialize($user_meta[$meta_key][0]) : '';

        // Attendee name / email are not saved as user meta - fall back to the WP user data.
        if (($raw_value === '' || $raw_value === null)) {
            if ('attendee_name' === $field_key) {
                $raw_value = $user->first_name;
            } elseif ('attendee_last_name' === $field_key) {
                $raw_value = $user->last_name;
            } elseif ('attendee_email' === $field_key) {
                $raw_value = $user->user_email;
            }
        }

        switch ($type) {
            case 'checkbox':
            case 'radio':
                return !empty($raw_value) ? 1 : 0;

            case 'multiselect':
            case 'checkbox_multi':
            case 'term-multiselect':
            case 'term-checkbox':
                $arr = is_array($raw_value) ? $raw_value : (($raw_value === '' || $raw_value === null) ? array() : array($raw_value));
                $value = array_values(array_filter(array_map('sanitize_text_field', array_filter($arr, 'is_scalar')), function ($v) {
                    return $v !== '';
                }));
                if (empty($value)) {
                    // term-multiselect values are stored as an array, so blank => [] (not null).
                    return ('term-multiselect' === $type) ? array() : null;
                }
                return $value;

            case 'url':
            case 'file':
                if (is_array($raw_value)) {
                    $raw_value = reset($raw_value); // handle WP file upload meta
                }
                $value = is_scalar($raw_value) ? esc_url_raw($raw_value) : '';
                return $value === '' ? null : $value;

            case 'textarea':
                $value = is_scalar($raw_value) ? sanitize_textarea_field($raw_value) : '';
                return $value === '' ? null : $value;

            case 'date':
                // Return the date in the "Date Format" selected in Event Manager > Settings > Date & Time Format.
                $date_format = WP_Event_Manager_Date_Time::get_event_manager_view_date_format();
                $value = is_scalar($raw_value) ? sanitize_text_field($raw_value) : '';
                if ($value === '') {
                    return null;
                }
                $timestamp = strtotime($value);
                return $timestamp ? date_i18n($date_format, $timestamp) : $value;

            default: // text, email, number, select, term-select, ...
                $value = is_scalar($raw_value) ? sanitize_text_field($raw_value) : '';
                return $value === '' ? null : $value;
        }
    }

    /**
     * PUT/PATCH /attendee-profile/update
     * Update matchmaking profile for the given user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function wpem_update_matchmaking_profile($request)
    {
        $user_id = (int) wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Same field definitions the GET API uses (admin Registration Form).
        $fields = $this->wpem_get_profile_form_fields();
        $files = $request->get_file_params();
        $files = is_array($files) ? $files : array();

        $errors = array();       // meta key => message
        $updates = array();      // meta key => sanitized value
        $uploads = array();      // meta key => upload job
        $known_meta_keys = array();

        // ---------- 1) Read + validate (nothing is written here) ----------
        foreach ($fields as $field_key => $config) {
            if (!is_array($config)) {
                continue;
            }
            $field_key = (string) $field_key;
            $meta_key = '_' . $field_key;
            $known_meta_keys[] = $meta_key;
            $type = !empty($config['type']) ? (string) $config['type'] : 'text';
            $label = !empty($config['label']) ? (string) $config['label'] : $field_key;

            // Meta key first, plain field key only as backward compatibility.
            $has_param = false;
            $raw = null;
            foreach (array($meta_key, $field_key) as $param_name) {
                $param_value = $request->get_param($param_name);
                if ($param_value !== null) {
                    $raw = $param_value;
                    $has_param = true;
                    break;
                }
            }

            // ----- file fields -----
            if ('file' === $type) {
                $new_files = array();
                foreach (array($meta_key, $field_key) as $param_name) {
                    if (isset($files[$param_name])) {
                        $new_files = $this->wpem_normalize_uploaded_files($files[$param_name]);
                        break;
                    }
                }
                if (!$has_param && empty($new_files)) {
                    continue; // nothing sent for this field
                }

                $multiple = !empty($config['multiple']);
                if (!$multiple && count($new_files) > 1) {
                    $errors[$meta_key] = sprintf('Only one file can be uploaded in %s field.', $label);
                    continue;
                }

                // Existing file URLs the client wants to keep (text param). Only URLs already stored are accepted.
                $keep = array();
                if ($has_param) {
                    $tokens = is_array($raw) ? $raw : array($raw);
                    $current_urls = $this->wpem_get_file_urls(get_user_meta($user_id, $meta_key, true));
                    $invalid_token = false;
                    foreach ($tokens as $token) {
                        if (!is_scalar($token)) {
                            $invalid_token = true;
                            break;
                        }
                        $token = trim((string) $token);
                        if ($token === '') {
                            continue;
                        }
                        $token = esc_url_raw($token);
                        if ($token !== '' && in_array($token, $current_urls, true)) {
                            $keep[] = $token;
                        } else {
                            $invalid_token = true;
                            break;
                        }
                    }
                    if ($invalid_token) {
                        $errors[$meta_key] = sprintf('%s: send files as multipart/form-data. Only URLs of already uploaded files can be sent as text.', $label);
                        continue;
                    }
                }

                if (!$multiple && count($new_files) === 0 && count($keep) > 1) {
                    $errors[$meta_key] = sprintf('Only one file can be uploaded in %s field.', $label);
                    continue;
                }

                if (empty($new_files) && empty($keep) && $this->wpem_profile_field_is_required($config)) {
                    $errors[$meta_key] = sprintf('%s is a required field.', $label);
                    continue;
                }

                $uploads[$meta_key] = array(
                    'label' => $label,
                    'files' => $new_files,
                    'keep' => $keep,
                    'multiple' => $multiple,
                    'mimes' => (!empty($config['allowed_mime_types']) && is_array($config['allowed_mime_types'])) ? $config['allowed_mime_types'] : null,
                );
                continue;
            }

            if (!$has_param) {
                continue; // skip if not provided
            }

            $result = $this->wpem_validate_profile_field_value($raw, $type, $config, $label);
            if (is_wp_error($result)) {
                $errors[$meta_key] = $result->get_error_message();
                continue;
            }
            $updates[$meta_key] = $result;
        }

        if (!empty($errors)) {
            return $this->wpem_send_profile_update_error($errors);
        }

        // ---------- 2) Upload files (still nothing saved in DB) ----------
        $final_files = array();
        $uploaded_paths = array();
        if (!empty($uploads)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            foreach ($uploads as $meta_key => $job) {
                $urls = array();
                foreach ($job['files'] as $file) {
                    $overrides = array('test_form' => false);
                    if (!empty($job['mimes'])) {
                        $overrides['mimes'] = $job['mimes'];
                    }
                    $movefile = wp_handle_upload($file, $overrides);
                    if (empty($movefile['url']) || !empty($movefile['error'])) {
                        foreach ($uploaded_paths as $path) {
                            wp_delete_file($path); // roll back files uploaded earlier in this request
                        }
                        $message = !empty($movefile['error']) ? (string) $movefile['error'] : 'File upload failed.';
                        return $this->wpem_send_profile_update_error(array($meta_key => sprintf('%s: %s', $job['label'], $message)));
                    }
                    $uploaded_paths[] = $movefile['file'];
                    $urls[] = esc_url_raw($movefile['url']);
                }

                if ($job['multiple']) {
                    $final_files[$meta_key] = array_values(array_unique(array_merge($job['keep'], $urls)));
            } else {
                    // single file: a new upload replaces the old one
                    $final_files[$meta_key] = !empty($urls) ? $urls[0] : (!empty($job['keep']) ? $job['keep'][0] : '');
                }
            }
        }

        // ---------- 3) Save ----------
        foreach ($updates as $meta_key => $value) {
            update_user_meta($user_id, $meta_key, $value);
        }
        foreach ($final_files as $meta_key => $value) {
            update_user_meta($user_id, $meta_key, $value);
        }

        // Update core user fields (first name, last name, display name)
        if ($request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($request->get_param('first_name')));
        }
        if ($request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($request->get_param('last_name')));
        }
        if ($request->get_param('display_name')) {
            wp_update_user(
                array(
                    'ID' => $user_id,
                    'display_name' => sanitize_text_field($request->get_param('display_name')),
                )
            );
        }

        $response = self::wpem_prepare_error_for_response(200);

        // Tell the client about meta keys that are not in the Registration Form (typos like "skills_" or old names).
        $reserved = array('_method', '_wpnonce', '_locale', '_envelope', '_embed', '_fields', '_jsonp');
        $ignored = array();
        foreach (array_keys((array) $request->get_params()) as $param_name) {
            $param_name = (string) $param_name;
            if ($param_name !== '' && '_' === $param_name[0] && !in_array($param_name, $known_meta_keys, true) && !in_array($param_name, $reserved, true)) {
                $ignored[] = $param_name;
            }
        }
        if (!empty($ignored)) {
            $response['data'] = array('ignored_fields' => $ignored);
        }

        return $response;
    }

    /**
     * Sends a 400 validation error. "message" is the first error, "data.errors" has every error (meta key => message).
     */
    private function wpem_send_profile_update_error($errors)
    {
        $message = reset($errors);
        return wp_send_json(
            array(
                'code' => 400,
                'status' => 'Bad request',
                'message' => $message,
                'data' => array('errors' => $errors),
            )
        );
    }

    /**
     * Is the field required (Required flag or "required" validation rule)?
     */
    private function wpem_profile_field_is_required($config)
    {
        if (!empty($config['required'])) {
            return true;
        }
        return !empty($config['rules']) && in_array('required', (array) $config['rules'], true);
    }

    /**
     * Flat list of uploaded files from a $_FILES entry (single file or file[] style), empty slots skipped.
     */
    private function wpem_normalize_uploaded_files($entry)
    {
        $out = array();
        if (!is_array($entry) || !isset($entry['name'])) {
            return $out;
        }
        if (is_array($entry['name'])) {
            foreach ($entry['name'] as $i => $name) {
                if (is_array($name)) {
                    continue;
                }
                $error = isset($entry['error'][$i]) ? (int) $entry['error'][$i] : UPLOAD_ERR_OK;
                if ($error === UPLOAD_ERR_NO_FILE || (string) $name === '') {
                    continue;
                }
                $out[] = array(
                    'name' => $name,
                    'type' => isset($entry['type'][$i]) ? $entry['type'][$i] : '',
                    'tmp_name' => isset($entry['tmp_name'][$i]) ? $entry['tmp_name'][$i] : '',
                    'error' => $error,
                    'size' => isset($entry['size'][$i]) ? $entry['size'][$i] : 0,
                );
            }
            } else {
            $error = isset($entry['error']) ? (int) $entry['error'] : UPLOAD_ERR_OK;
            if ($error !== UPLOAD_ERR_NO_FILE && (string) $entry['name'] !== '') {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /**
     * File URLs stored in user meta (string, array or serialized array) as a clean list.
     */
    private function wpem_get_file_urls($raw)
    {
        $raw = maybe_unserialize($raw);
        $urls = array();
        foreach ((array) $raw as $item) {
            $item = maybe_unserialize($item);
            foreach ((array) $item as $url) {
                if (is_string($url)) {
                    $url = esc_url_raw($url);
                    if ($url !== '') {
                        $urls[] = $url;
                    }
                }
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * Comma separated string or array => clean list of strings (null when the input is not usable).
     */
    private function wpem_to_value_list($raw)
    {
        $items = is_array($raw) ? $raw : array($raw);
        $list = array();
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                return null;
            }
            foreach (explode(',', (string) $item) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $list[] = $part;
                }
            }
        }
        return array_values(array_unique($list));
    }

    /**
     * Slug of a term from a slug (or term id), false when it does not exist in the taxonomy.
     */
    private function wpem_resolve_term_slug($token, $taxonomy)
    {
        $term = get_term_by('slug', $token, $taxonomy);
        if (!$term) {
            $term = get_term_by('slug', sanitize_title($token), $taxonomy);
        }
        if (!$term && ctype_digit((string) $token)) {
            $term = get_term_by('id', (int) $token, $taxonomy);
        }
        return ($term && !is_wp_error($term)) ? $term->slug : false;
    }

    /**
     * Validate + sanitize one (non-file) field value.
     *
     * @return mixed|WP_Error Sanitized value to store, or WP_Error with the message for the client.
     */
    private function wpem_validate_profile_field_value($raw, $type, $config, $label)
    {
        $required = $this->wpem_profile_field_is_required($config);
        $required_error = new WP_Error('required', sprintf('%s is a required field.', $label));
        $taxonomy = !empty($config['taxonomy']) ? (string) $config['taxonomy'] : '';
        $use_taxonomy = ($taxonomy !== '' && taxonomy_exists($taxonomy));

        // Valid option values of select type fields (empty "Select ..." option ignored).
        $option_keys = array();
        if (!empty($config['options']) && is_array($config['options'])) {
            foreach (array_keys($config['options']) as $option_key) {
                if ((string) $option_key !== '') {
                    $option_keys[] = (string) $option_key;
            }
        }
        }

        switch ($type) {
            // ---- many values: stored as array ----
            case 'term-multiselect':
            case 'term-checkbox':
            case 'multiselect':
            case 'checkbox_multi':
            case 'checkbox_group':
                $list = $this->wpem_to_value_list($raw);
                if ($list === null) {
                    return new WP_Error('invalid', sprintf('%s: invalid value. Pass comma separated values, e.g. value1,value2.', $label));
                }
                if (empty($list)) {
                    return $required ? $required_error : array();
                }

                $is_term = in_array($type, array('term-multiselect', 'term-checkbox'), true);
                $values = array();
                $invalid = array();
                foreach ($list as $token) {
                    if ($is_term && $use_taxonomy) {
                        $slug = $this->wpem_resolve_term_slug($token, $taxonomy);
                        if ($slug === false) {
                            $invalid[] = $token;
                        } else {
                            $values[] = $slug;
                        }
                    } elseif (!$is_term && !empty($option_keys)) {
                        if (in_array($token, $option_keys, true)) {
                            $values[] = $token;
                        } else {
                            $invalid[] = $token;
                        }
                    } else {
                        $values[] = sanitize_text_field($token);
                    }
                }
                if (!empty($invalid)) {
                    return new WP_Error('invalid', sprintf('%s: invalid value(s): %s.', $label, implode(', ', $invalid)));
                }
                return array_values(array_unique($values));

            // ---- exactly one value ----
            case 'term-select':
            case 'select':
                $multiple_error = new WP_Error('multiple', sprintf('You can not pass multiple values in %s field.', $label));

                if (is_array($raw)) {
                    $parts = array();
                    foreach ($raw as $item) {
                        if (!is_scalar($item)) {
                            return new WP_Error('invalid', sprintf('%s: invalid value.', $label));
                        }
                        if (trim((string) $item) !== '') {
                            $parts[] = trim((string) $item);
                        }
                    }
                    if (count($parts) > 1) {
                        return $multiple_error;
                    }
                    $value = isset($parts[0]) ? $parts[0] : '';
                } elseif (is_scalar($raw)) {
                    $value = trim((string) $raw);
                } else {
                    return new WP_Error('invalid', sprintf('%s: invalid value.', $label));
                }

                if ($value === '') {
                    return $required ? $required_error : '';
                }

                if ('term-select' === $type) {
                    if (strpos($value, ',') !== false) {
                        return $multiple_error;
                    }
                    if ($use_taxonomy) {
                        $slug = $this->wpem_resolve_term_slug($value, $taxonomy);
                        if ($slug === false) {
                            return new WP_Error('invalid', sprintf('%s: invalid value "%s".', $label, $value));
                        }
                        return $slug;
                    }
                    return sanitize_text_field($value);
                }

                // select
                if (!empty($option_keys) && !in_array($value, $option_keys, true)) {
                    if (strpos($value, ',') !== false) {
                        return $multiple_error;
                    }
                    $message = sprintf('%s: invalid value "%s".', $label, $value);
                    if (count($option_keys) <= 20) {
                        $message .= ' Allowed values: ' . implode(', ', $option_keys) . '.';
                    }
                    return new WP_Error('invalid', $message);
                }
                return sanitize_text_field($value);

            // ---- on / off ----
            case 'checkbox':
                if (is_array($raw) || (!is_scalar($raw) && $raw !== null)) {
                    return new WP_Error('invalid', sprintf('%s: value must be 1 or 0.', $label));
                }
                $flag = is_bool($raw) ? ($raw ? '1' : '0') : strtolower(trim((string) $raw));
                if (in_array($flag, array('1', 'true', 'yes', 'on'), true)) {
                    return 1;
                }
                if (in_array($flag, array('0', 'false', 'no', 'off', ''), true)) {
                    return 0;
                }
                return new WP_Error('invalid', sprintf('%s: value must be 1 or 0.', $label));
        }

        // ---- single scalar value (text, textarea, number, email, url, date, ...) ----
        if (!is_scalar($raw)) {
            return new WP_Error('invalid', sprintf('%s: invalid value.', $label));
        }
        $raw = (string) $raw;

        switch ($type) {
            case 'textarea':
                $value = wp_kses_post(trim($raw));
                break;
            case 'email':
                $value = sanitize_email(trim($raw));
                if ($value === '' && trim($raw) !== '') {
                    return new WP_Error('invalid', sprintf('%s: Please provide a valid email address', $label));
                }
                break;
            case 'url':
                $value = esc_url_raw(trim($raw));
                if ($value === '' && trim($raw) !== '') {
                    return new WP_Error('invalid', sprintf('%s: Please provide a valid URL.', $label));
                }
                break;
            default:
                $value = sanitize_text_field($raw);
                break;
        }

        if ($value === '') {
            return $required ? $required_error : '';
        }

        if ('email' === $type && !is_email($value)) {
            return new WP_Error('invalid', sprintf('%s: Please provide a valid email address', $label));
        }
        if ('number' === $type && !is_numeric($value)) {
            return new WP_Error('invalid', sprintf('%s: Please enter a number.', $label));
        }

        // Validation rules chosen in Registration Form > Validation / Rules (same checks as the registration form).
        if (!empty($config['rules'])) {
            foreach ((array) $config['rules'] as $rule) {
                switch ($rule) {
                    case 'email':
                    case 'from_email':
                        if (!is_email($value)) {
                            return new WP_Error('invalid', sprintf('%s: Please provide a valid email address', $label));
                        }
                        break;
                    case 'alphabet':
                        if (!ctype_alpha(str_replace(' ', '', $value))) {
                            return new WP_Error('invalid', sprintf('%s: Please enter only alphabets.', $label));
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            return new WP_Error('invalid', sprintf('%s: Please enter a number.', $label));
                        }
                        break;
                }
            }
        }

        return $value;
    }

    /**
     * POST /upload-user-file
     * Upload a file for the user, storing it as profile photo.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function wpem_upload_user_file($request)
    {
        $user_id = (int) wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return self::wpem_prepare_error_for_response(404);
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
        if (empty($_FILES['file'])) {
            return self::wpem_prepare_error_for_response(400, array('message' => 'No file uploaded.'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = map_deep($_FILES['file'], 'wp_kses_post'); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
        $upload_overrides = array('test_form' => false); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
        $movefile = wp_handle_upload($file, $upload_overrides);
        if (!isset($movefile['url'])) {
            return self::wpem_prepare_error_for_response(500, array('message' => 'File upload failed.'));
        }

        $file_url = esc_url_raw($movefile['url']);
        update_user_meta($user_id, '_profile_photo', $file_url);

        $response = self::wpem_prepare_error_for_response(200);
        $response['message'] = 'File uploaded and stored successfully.';
        $response['data'] = array(
            'profile_photo' => $file_url,
            'meta_updated' => true,
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
    public function wpem_get_matchmaking_profile_settings($request)
    {
        $user_id = wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);

        // Build user event participation settings
        $user_event_participation = array();

        // Get registrations made with the user's own e-mail address (not post_author).
        // Cancelled / trashed registrations are ignored.
        $user_registrations = $this->wpem_get_user_registration_ids_by_email($user_id);

        foreach ($user_registrations as $registration_id) {
            $parent_event_id = (int) get_post_field('post_parent', $registration_id);
            if (!$parent_event_id) {
                continue;
            }

            // Skip events that no longer exist.
            $parent_status = get_post_status($parent_event_id);
            if (!$parent_status || 'trash' === $parent_status) {
                continue;
            }

            $create_matchmaking = (int) get_post_meta($registration_id, '_create_matchmaking', true);

            // One entry per event; enabled if any of its registrations is enabled.
            if (isset($user_event_participation[$parent_event_id])) {
                if ($create_matchmaking) {
                    $user_event_participation[$parent_event_id]['create_matchmaking'] = 1;
                }
                continue;
            }

            $user_event_participation[$parent_event_id] = array(
                'event_id' => $parent_event_id,
                'event_title' => get_the_title($parent_event_id),
                'event_banner' => get_the_post_thumbnail_url($parent_event_id, 'thumbnail'),
                'create_matchmaking' => $create_matchmaking,
            );
        }
        $user_event_participation = array_values($user_event_participation);
        $timezone_settings = get_user_meta($user_id, '_timezone_settings', true) ? get_user_meta($user_id, '_timezone_settings', true) : 'default';
        $settings = array(
            'is_user_matchmaking_profile_enabled' => (int) get_user_meta($user_id, '_matchmaking_profile', true),
            'message_notification' => (int) get_user_meta($user_id, '_message_notification', true),
            'event_participation' => $user_event_participation,
            'meeting_request_mode' => get_user_meta($user_id, '_wpem_meeting_request_mode', true) ?: 'approval',
            'timezone_settings' => $timezone_settings,
        );

        if ($timezone_settings === 'custom') {
            $settings['custom_timezone'] = get_user_meta($user_id, '_custom_timezone', true);

            $timezone_values = array();
            if (preg_match_all('/<option\b[^>]*\bvalue="([^"]+)"/', wp_timezone_choice('', get_user_locale()), $matches)) {
                foreach ($matches[1] as $value) {
                    $value = html_entity_decode($value);
                    if ($value === 'UTC') {
                        $value = 'UTC/' . $value;
                    } elseif (preg_match('/^UTC[+-]/', $value)) {
                        $value = 'Manual Offsets/' . $value;
                    }
                    $timezone_values[] = $value;
                }
            }
            $settings['custom_timezone_offset'] = $timezone_values;
        }
        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = $settings;
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function wpem_update_matchmaking_profile_settings($request)
    {
        $user_id = wpem_rest_get_current_user_id();
        $user = get_user_by('id', $user_id);

        // Validate that at least one valid parameter is provided. 
        $valid_params = array( 'is_user_matchmaking_profile_enabled', 'message_notification', 'meeting_request_mode', 'timezone_settings', 'event_participation', );
        $params = $request->get_params();
        foreach ($params as $param => $value) {
            // custom_timezone is allowed only when timezone_settings is custom.
            if ($param === 'custom_timezone') {
                if ($request->get_param('timezone_settings') !== 'custom') {
                    return new WP_REST_Response(
                        array(
                            'code'    => 400,
                            'status'  => 'invalid data',
                            'message' => 'Not Require custom_timezon key.',
                        ),
                        400
                    );
                }
                continue;
            }
            if (!in_array($param, $valid_params, true)) {
                return new WP_REST_Response(
                    array(
                        'code'    => 400,
                        'status'  => 'invalid data',
                        'message' => 'Please Enter Valid Data.',
                    ),
                    400
                );
            }
        }
        $has_valid_data = false;
        foreach ($valid_params as $param) {
            if (!is_null($request->get_param($param))) {
                $has_valid_data = true;
                break;
            }
        }
        if (!$has_valid_data) {
            return new WP_REST_Response(
                array(
                    'code'    => 400,
                    'status'  => 'invalid data',
                    'message' => 'Please Enter Valid Data.',
                ),
                400
            );
        }

        // Update user meta values
        if (!is_null($request->get_param('is_user_matchmaking_profile_enabled'))) {
            $enable_matchmaking = $request->get_param('is_user_matchmaking_profile_enabled');
            if (!in_array($enable_matchmaking, array(0, 1, '0', '1'), true)) {
                return new WP_REST_Response(
                    array(
                        'code'    => 400,
                        'status'  => 'invalid data',
                        'message' => 'Please Enter Valid Value.',
                    ),
                    400
                );
            }
            update_user_meta($user_id, '_matchmaking_profile', (int) $enable_matchmaking);
        }
        if (!is_null($request->get_param('message_notification'))) {
            $message_notification = $request->get_param('message_notification');
            if (!in_array($message_notification, array(0, 1, '0', '1'), true)) {
                return new WP_REST_Response(
                    array(
                        'code'    => 400,
                        'status'  => 'invalid data',
                        'message' => 'Please Enter Valid Data.',
                    ),
                    400
                );
            }
            update_user_meta($user_id, '_message_notification', (int) $message_notification);
        }
        if (!is_null($request->get_param('meeting_request_mode'))) {
            update_user_meta($user_id, '_wpem_meeting_request_mode', sanitize_text_field($request->get_param('meeting_request_mode')));
        }
        if (!is_null($request->get_param('timezone_settings'))) {
            $timezone_settings = $request->get_param('timezone_settings');
            if ($timezone_settings === 'custom') {
                $tzlist = DateTimeZone::listIdentifiers();
                $timezone = $request->get_param('custom_timezone');

                if ($timezone && in_array($timezone, $tzlist, true)) {
                    update_user_meta($user_id, '_custom_timezone', sanitize_text_field($timezone));
                } else {
                    self::wpem_prepare_error_for_response(400);
                }
            } else {
                delete_user_meta($user_id, '_custom_timezone');
            }
            update_user_meta($user_id, '_timezone_settings', sanitize_text_field($request->get_param('timezone_settings')));
        }
        // Update event participation settings
        $event_participation = $request->get_param('event_participation');
        if (is_array($event_participation)) {
            foreach ($event_participation as $event) {
                if (!isset($event['event_id'])) {
                    continue;
                }
                $eid = (int) $event['event_id'];
                $value = isset($event['create_matchmaking']) ? (int) $event['create_matchmaking'] : 0;

                // Only registrations made with the user's own e-mail address (not post_author)
                $registration_post_ids = $this->wpem_get_user_registration_ids_by_email($user_id, $eid);

                foreach ($registration_post_ids as $registration_post_id) {
                    update_post_meta($registration_post_id, '_create_matchmaking', $value);
                }
            }
        }
        return self::wpem_prepare_error_for_response(200);
    }

    /**
     * Approve matchmaking profile by organizer.
     * Params/validation aligned with matchmaking-settings controller.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.1.3
     */
    public function wpem_approve_matchmaking_profile($request)
    {
        global $wpdb;
        $current_user = absint(wpem_rest_get_current_user_id());
        $is_admin  = user_can( $current_user, 'manage_options' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $user_info = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpem_rest_api_keys WHERE user_id = %d",$current_user));
        if ($user_info) {
            if((gmdate( 'Y-m-d', strtotime( $user_info->date_expires )) < gmdate( 'Y-m-d' ))) {
                if ( !$is_admin ) {
                    return self::wpem_prepare_error_for_response(403);
                }
            }else{
                if($this->wpem_user_has_permission($current_user, 'read')) {
                    return self::wpem_prepare_error_for_response(403);
                }
            }
        }else{
            if ( !$is_admin ) {
                return self::wpem_prepare_error_for_response(403);
            }
        }
        
        $params = $request->get_json_params();
        $registration_id = isset($params['registration_id']) ? trim($params['registration_id']) : 0;
        $profile_status = isset($params['profile_status']) ? $params['profile_status'] : 0;
        // Update user meta values
        if (empty($registration_id) || $registration_id <= 0) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Get user_id and email from the current registration
        $user_id = get_post_field('post_author', $registration_id);

        // Update custom matchmaking table
        if (!empty($user_id)) {
            update_user_meta($user_id, '_approve_profile_status', $profile_status);
        }
        // Find all registrations with same user_id OR same email
        $args = [
            'post_type' => 'event_registration',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'author' => absint($user_id), // Now uses post_author instead of meta
        ];

        $registrations = get_posts($args);

        foreach ($registrations as $reg) {
            update_post_meta($reg->ID, '_attendee_approved', $profile_status);
        }

        return self::wpem_prepare_error_for_response(200);
    }

    /**
     * Filter matchmaking users (combined logic from wpem_matchmaking_filter_users)
     * Route: POST /wp-json/wpem/matchmaking-profile/filter
     * @since 1.1.4
     */
    public function wpem_get_wpem_matchmaking_filter_users(WP_REST_Request $request)
    {
        $filters = $request->get_params();
        // Cast to int: wpem_rest_get_current_user_id() is not guaranteed to return an int
        // (e.g. it can come back as a numeric string depending on the auth method used),
        // and wpem_get_all_matchmaking_participants() excludes "yourself" from the results
        // using a STRICT (===) comparison against integer user IDs. Without this cast, that
        // self-exclusion silently fails and your own profile leaks into the results.
        $current_user = (int) wpem_rest_get_current_user_id();

        if (!empty($filters['event_id'])) {
            $requested_event_id = absint($filters['event_id']);

            if (!wpem_user_has_matchmaking_for_event($current_user, $requested_event_id)) {
                return new WP_REST_Response(
                    array(
                        'code'    => 404,
                        'status'  => 'Not found',
                        'message' => 'Matchmaking is not enabled for this event.',
                    ),
                    404
                );
            }
        }

        $countries = wpem_get_all_countries();
        // Same field definitions as the GET /matchmaking-profile API (admin Registration Form).
        $fields = $this->wpem_get_profile_form_fields();
        $filtered_users = [];

        $your_matches_mode = !empty($filters['your_matches']) ? strtolower(sanitize_text_field($filters['your_matches'])) : '';
        $is_your_matches = in_array($your_matches_mode, array('exact', 'partial'), true);

        if ($is_your_matches) {
            // "Your Matches" mode: resolve the event scope EXACTLY like the [match_making]
            // "Your Matches" tab does (wpem_get_match_making_filter_user's filters_type branch):
            // 1) an explicit 'event_id' request param always wins (keeps existing API behaviour),
            // 2) otherwise fall back to the event stored on the user's own matchmaking profile
            //    (user meta '_wpem_matchmaking_profile'),
            // 3) otherwise pass '' so wpem_get_all_matchmaking_participants() falls back to
            //    ALL events the current user is registered for (its own default behaviour) —
            //    note this is intentionally NOT limited to registrations with
            //    '_create_matchmaking' = 1, same as the website.
            if (!empty($filters['event_id'])) {
                $event_id = absint($filters['event_id']);
            } else {
                $stored_filters = get_user_meta($current_user, '_wpem_matchmaking_profile', true);
                $event_id = !empty($stored_filters['event_id']) ? sanitize_text_field($stored_filters['event_id']) : '';
            }

            $users = wpem_get_all_matchmaking_participants($current_user, $event_id);

            foreach ($users as $user) {
                $uid = (int) $user['user_id'];
                $filtered_users[$uid] = $this->wpem_build_matchmaking_profile($uid, $user, $fields);
            }

            if (empty($filtered_users)) {
                return self::wpem_prepare_error_for_response(404);
            }
        } else {
            // Default (no event_id / no your_matches) mirrors the [match_making] "All" tab
            // (wpem_render_matchmaking_participants_list), which calls
            // wpem_get_all_matchmaking_participants(get_current_user_id()) with NO event
            // filter at all — i.e. ALL events the user is registered for, ANY status, with
            // NO '_create_matchmaking' restriction. The previous version of this method built
            // its own narrower event list (only registrations with '_create_matchmaking' = 1
            // and status in new/confirmed/archived), which is why it returned fewer users
            // than the website. An explicit 'event_id' request param still scopes to that
            // single event, same as before.
            $event_id = !empty($filters['event_id']) ? absint($filters['event_id']) : '';

            $users = wpem_get_all_matchmaking_participants($current_user, $event_id);

            // When an event is requested, keep only participants who have matchmaking
            // enabled (_create_matchmaking = 1) for THAT event - same rule as the website
            // event filter (wpem_get_match_making_filter_user), via the shared helper.
            if (!empty($event_id)) {
                $users = array_values(array_filter($users, function ($u) use ($event_id) {
                    return wpem_user_has_matchmaking_for_event($u['user_id'], $event_id);
                }));
            }

            foreach ($users as $user) {
                $uid = (int) $user['user_id'];
                $filtered_users[$uid] = $this->wpem_build_matchmaking_profile($uid, $user, $fields);
            }

            if (empty($filtered_users)) {
                return self::wpem_prepare_error_for_response(404);
            }
        }

        $users = array_values($filtered_users);

        if (empty($users)) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Step 3: Apply filters
        // "Your Matches" mode ('your_matches' = 'exact' | 'partial') takes over filtering completely,
        // exactly like the [match_making] "Your Matches" tab does: it ignores the free-text/taxonomy
        // filters below and instead compares every candidate against the CURRENTLY AUTHENTICATED
        // user's own saved skills/interests/profession.
        $your_matches_mode = !empty($filters['your_matches']) ? strtolower(sanitize_text_field($filters['your_matches'])) : '';

        if (in_array($your_matches_mode, array('exact', 'partial'), true)) {

            // Current (authenticated) user's own matchmaking profile values
            $own_skills = get_user_meta($current_user, '_skills', true);
            $own_skills = is_array($own_skills) ? $own_skills : (array) maybe_unserialize($own_skills);
            $own_skills_slugs = array_map('sanitize_title', $own_skills);

            $own_interests = get_user_meta($current_user, '_interests', true);
            $own_interests = is_array($own_interests) ? $own_interests : (array) maybe_unserialize($own_interests);
            $own_interests_slugs = array_map('sanitize_title', $own_interests);

            $own_profession = get_user_meta($current_user, '_profession', true);
            if (is_array($own_profession)) {
                $own_profession = implode('-', $own_profession);
            }
            $own_profession_slug = sanitize_title($own_profession);

            $final_users = [];
            foreach ($users as $user) {
                $user_skills_slugs = array_map('sanitize_title', (array) maybe_unserialize($user['skills'] ?? ''));
                $user_interests_slugs = array_map('sanitize_title', (array) maybe_unserialize($user['interests'] ?? ''));

                $user_profession_raw = $user['profession'] ?? '';
                if (is_array($user_profession_raw)) {
                    $user_profession_raw = implode('-', $user_profession_raw);
                }
                $user_profession_slug = sanitize_title($user_profession_raw);

                if ($your_matches_mode === 'exact') {
                    // Exact match: participant must match ALL of the current user's
                    // set skills AND interests AND profession.
                    $match = true;

                    if (!empty($own_skills_slugs)) {
                        $skills_match = count(array_intersect($own_skills_slugs, $user_skills_slugs)) === count($own_skills_slugs);
                        if (!$skills_match) {
                            $match = false;
                        }
                    }

                    if (!empty($own_interests_slugs)) {
                        $interests_match = count(array_intersect($own_interests_slugs, $user_interests_slugs)) === count($own_interests_slugs);
                        if (!$interests_match) {
                            $match = false;
                        }
                    }

                    if (!empty($own_profession_slug) && $user_profession_slug !== $own_profession_slug) {
                        $match = false;
                    }
                } else {
                    // Partial match: participant must match AT LEAST ONE of
                    // profession OR skills OR interests.
                    $has_profession_match = empty($own_profession_slug) || ($user_profession_slug === $own_profession_slug);
                    $has_skill_match = empty($own_skills_slugs) || !empty(array_intersect($own_skills_slugs, $user_skills_slugs));
                    $has_interest_match = empty($own_interests_slugs) || !empty(array_intersect($own_interests_slugs, $user_interests_slugs));

                    $match = $has_profession_match || $has_skill_match || $has_interest_match;
                }

                if ($match) {
                    $final_users[] = $user;
                }
            }
        } else {
            // Default filtering (search, profession, company_name, country, city, experience, skills, interests)
            $final_users = [];
            foreach ($users as $user) {
                // Search (name, profession, company, country, city, skills, interests)
                if (!empty($filters['search'])) {
                    $search = strtolower($filters['search']);
                    $haystack = strtolower(
                        ($user['display_name'] ?? '') . ' ' .
                        ($user['_profession'] ?? '') . ' ' .
                        ($user['_company_name'] ?? '') . ' ' .
                        ($countries[$user['_country']] ?? $user['country']) . ' ' .
                        ($user['_city'] ?? '') . ' ' .
                        implode(' ', (array) maybe_unserialize($user['skills'])) . ' ' .
                        implode(' ', (array) maybe_unserialize($user['interests']))
                    );
                    if (strpos($haystack, $search) === false)
                        continue;

                }

                // Profession
                if (!empty($filters['profession']) && strtolower($filters['profession']) !== strtolower($user['profession'] ?? '')) {
                    continue;
                }

                // Company
                if (!empty($filters['company_name']) && strtolower($filters['company_name']) !== strtolower($user['company_name'] ?? '')) {
                    continue;
                }

                // Country
                if (!empty($filters['country'])) {
                    $selected_countries = array_map('strtolower', (array) $filters['country']);
                    $user_country = strtolower($user['country'] ?? '');
                    $user_country_name = strtolower($countries[$user_country] ?? $user_country);

                    if (!in_array($user_country, $selected_countries, true) && !in_array($user_country_name, $selected_countries, true)) {
                        continue;
                    }
                }

                // City
                if (!empty($filters['city']) && strtolower($filters['city']) !== strtolower($user['city'] ?? '')) {
                    continue;
                }

                // Experience
                if (!empty($filters['experience']) && is_array($filters['experience'])) {
                    $user_exp = (int) ($user['experience'] ?? 0);
                    if ($user_exp < ($filters['experience']['min'] ?? 0) || $user_exp > ($filters['experience']['max'] ?? PHP_INT_MAX)) {
                        continue;
                    }
                }

                // Skills
                if (!empty($filters['skills'])) {
                    $user_skills = array_map('sanitize_title', (array) maybe_unserialize($user['skills']));
                    if (empty(array_intersect($filters['skills'], $user_skills))) {
                        continue;
                    }
                }

                // Interests
                if (!empty($filters['interests'])) {
                    $user_interests = array_map('sanitize_title', (array) maybe_unserialize($user['interests']));
                    if (empty(array_intersect($filters['interests'], $user_interests))) {
                        continue;
                    }
                }

                $final_users[] = $user;
            }
        }

        $bookmarked_user_ids = array_map(
            'absint',
            (array) get_user_meta($current_user, '_matchmaking_bookmarked_users', true)
        );
        if (array_key_exists('is_bookmarked', $filters)) {
            $bookmark_filter = rest_sanitize_boolean($filters['is_bookmarked']);
            $final_users = array_values(array_filter($final_users, function ($user) use ($bookmarked_user_ids, $bookmark_filter) {
                $is_bookmarked = in_array((int) $user['user_id'], $bookmarked_user_ids, true);
                return $is_bookmarked === $bookmark_filter;
            }));
        }

        if (empty($final_users)) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Step 4: Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : 5;
        $offset = ($page - 1) * $per_page;
        $paged_users = array_slice($final_users, $offset, $per_page);
        foreach ($paged_users as &$paged_user) {
            $paged_user = $this->wpem_format_profile_fields_with_label($paged_user, $fields);
            $paged_user['is_bookmarked'] = in_array((int) $paged_user['user_id'], $bookmarked_user_ids, true);
        }
        unset($paged_user);

        $response = self::wpem_prepare_error_for_response(200);
        $response['data'] = [
            'total' => count($final_users),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil(count($final_users) / $per_page),
            'users' => $paged_users,
        ];

        return wp_send_json($response);
    }

    /**
     * Moves the registration-form fields of a built profile into one "profile_fields" list.
     *
     * Rules:
     *  - Only fields with "Add to User Profile Form" checked are listed (attendee first name,
     *    last name and email are locked/always checked in the admin screen, so they are always included).
     *  - Only fields of the "About" and "Organization" tabs go in "profile_fields"
     *    (an empty / unknown tab group falls back to "about", same as the admin screen).
     *  - A field without a value is left out completely.
     *  - Each item is { label, metakey, type, value } (+ "taxonomy" for term-select / term-multiselect).
     *  - The two "Settings" tab fields (create_matchmaking, message_notification) are NOT in
     *    "profile_fields"; they are returned as plain top level keys instead.
     *
     * @param array $profile Flat profile from wpem_build_matchmaking_profile().
     * @param array $fields  Registration form field definitions.
     * @return array
     */
    private function wpem_format_profile_fields_with_label($profile, $fields)
    {
        $profile_fields = array();
        $locked_fields = array('attendee_name', 'attendee_last_name', 'attendee_email');
        $setting_keys = array('create_matchmaking', 'message_notification');
        $setting_values = array();

        foreach ($fields as $field_key => $field_config) {
            if (!array_key_exists($field_key, $profile)) {
                continue;
            }

            $value = $profile[$field_key];
            unset($profile[$field_key]);

            // Settings tab: the two settings fields are returned at the top level below.
            $tab = (is_array($field_config) && !empty($field_config['tabgroup'])) ? (string) $field_config['tabgroup'] : 'about';
            if ('settings' === $tab) {
                if (in_array($field_key, $setting_keys, true)) {
                    $setting_values[$field_key] = $value;
                }
                continue;
            }

            // Only fields with "Add to User Profile Form" checked.
            $is_locked = in_array($field_key, $locked_fields, true);
            if (!$is_locked && empty($field_config['matchmaking_field'])) {
                continue;
            }

            // Only "About" and "Organization" tabs (unknown tab group => about).
            if (!in_array($tab, array('about', 'organization'), true)) {
                if (function_exists('wpem_get_matchmaking_profile_tabs') && array_key_exists($tab, (array) wpem_get_matchmaking_profile_tabs())) {
                    continue; // some other custom tab
                }
            }

            // No value => do not show the field in the response at all.
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                continue;
            }

            $label = isset($field_config['label']) && is_scalar($field_config['label'])
                ? wp_specialchars_decode(stripslashes((string) $field_config['label']), ENT_QUOTES)
                : $field_key;

            $field_type = !empty($field_config['type']) ? (string) $field_config['type'] : 'text';

            $item = array(
                'label' => $label,
                'metakey' => '_' . $field_key,
                'type' => $field_type,
                'value' => $value,
            );

            // Term fields also return the taxonomy used by the field.
            if (in_array($field_type, array('term-select', 'term-multiselect'), true)) {
                $item['taxonomy'] = !empty($field_config['taxonomy']) ? (string) $field_config['taxonomy'] : null;
            }

            $profile_fields[] = $item;
        }

        // Settings fields (top level keys, outside profile_fields).
        $user_id = isset($profile['user_id']) ? (int) $profile['user_id'] : 0;
        if (array_key_exists('message_notification', $setting_values)) {
            // Same default as the plugin: no saved value => 1 (enabled).
            $saved = $user_id ? get_user_meta($user_id, '_message_notification', true) : '';
            $setting_values['message_notification'] = ($saved === '' || $saved === null) ? 1 : (int) $setting_values['message_notification'];
        }

        // Keep the base info first, then profile_fields, then the settings, then the remaining keys.
        $base_keys = array('user_id', 'display_name', 'email', 'first_name', 'last_name');
        $result = array();
        foreach ($base_keys as $key) {
            if (array_key_exists($key, $profile)) {
                $result[$key] = $profile[$key];
                unset($profile[$key]);
            }
        }
        $result['profile_fields'] = $profile_fields;
        foreach ($setting_keys as $key) {
            if (array_key_exists($key, $setting_values)) {
                $result[$key] = (int) $setting_values[$key];
            }
        }

        return $result + $profile;
    }

    /**
     * Builds the response-ready matchmaking profile array for a single participant.
     * Extracted from wpem_get_wpem_matchmaking_filter_users() so it can be reused by
     * both the default search flow and the "your_matches" flow.
     *
     * @param int   $uid    User ID.
     * @param array $user   Raw participant row from wpem_get_all_matchmaking_participants().
     * @param array $fields Dynamic matchmaking profile field definitions.
     * @return array
     */
    private function wpem_build_matchmaking_profile($uid, $user, $fields)
    {
        $user_meta = get_user_meta($uid);
        $wp_user = get_userdata($uid);

        // Base info
        $profile = [
            'user_id' => $uid,
            'display_name' => $user['display_name'],
            'email' => $user['user_email'],
            'first_name' => $user_meta['first_name'][0] ?? '',
            'last_name' => $user_meta['last_name'][0] ?? '',
        ];

        // Dynamic fields: values are read by the SAME function the GET /matchmaking-profile API uses,
        // so both APIs always return the same value (single file => URL, multiple files => array of URLs,
        // photo / logo placeholder fallback, attendee name / email fallback, ...).
        foreach ($fields as $field_key => $field_config) {
            if (!is_array($field_config)) {
                continue;
            }
            $value = $this->wpem_get_profile_field_value($uid, $wp_user, (string) $field_key, $field_config, $user_meta);
            if ($field_key === 'profession' && ($value === '0' || $value === 0)) {
                $value = null;
            }
            $profile[$field_key] = $value;
        }

        // Matchmaking / meeting meta
        $profile['matchmaking_profile'] = (int) get_user_meta($uid, '_matchmaking_profile', true);
        if (get_option('participant_activation') === 'manual')
            $profile['approve_profile_status'] = (int) get_user_meta($uid, '_approve_profile_status', true);
        else {
            $profile_status = get_user_meta($uid, '_approve_profile_status', true);
            $profile['approve_profile_status'] = ($profile_status !== '' && $profile_status !== null) ? ((int) $profile_status === 0 ? 0 : 1) : 1;
        }
        $profile['wpem_meeting_request_mode'] = get_user_meta($uid, '_wpem_meeting_request_mode', true) ?: 'approval';
        $meta = get_user_meta($uid, '_available_for_meeting', true);
        $profile['available_for_meeting'] = ($meta !== '' && $meta !== null) ? ((int) $meta === 0 ? 0 : 1) : 1;

        return $profile;
    }

    /**
     * Get the registrations that belong to a user *by e-mail address*.
     *
     * A registration is the user's own only when its attendee e-mail (_attendee_email)
     * equals the user's account e-mail. post_author is NOT used, because the author may
     * be the logged-in person who registered somebody else with a different e-mail.
     * Cancelled / trashed registrations are ignored.
     * Same rule as the Registrations add-on (wpem_get_user_registration_ids_by_email).
     *
     * @param int $user_id  User ID.
     * @param int $event_id Optional. Limit to one event (registration post_parent).
     * @return int[] Registration post IDs.
     */
    private function wpem_get_user_registration_ids_by_email($user_id, $event_id = 0)
    {
        $user = get_userdata((int) $user_id);
        if (!$user || empty($user->user_email)) {
            return array();
        }

        $statuses = function_exists('wpem_get_event_registration_statuses')
            ? array_keys(wpem_get_event_registration_statuses())
            : array('new', 'confirmed', 'waiting', 'archived', 'cancelled');
        $statuses = array_values(array_diff($statuses, array('cancelled', 'trash')));

        $args = array(
            'post_type'      => 'event_registration',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key'     => '_attendee_email',
                    'value'   => $user->user_email,
                    'compare' => '=',
                ),
            ),
        );

        if ($event_id) {
            $args['post_parent'] = (int) $event_id;
        }

        return get_posts($args);
    }

    /**
     * This function is used to get event list for which current loggedin user has registered
     * (only registrations made with the user's own e-mail address)
     * @since 1.3.0
     */
    public function wpem_get_wpem_matchmaking_user_events($request)
    {
        $user_id = wpem_rest_get_current_user_id();

        // Registrations made with the user's own e-mail address (not post_author)
        $registrations = $this->wpem_get_user_registration_ids_by_email($user_id);

        // Collect unique parent event IDs
        $event_ids = array();
        foreach ($registrations as $registration_id) {
            $parent_id = (int) wp_get_post_parent_id($registration_id);
            if ($parent_id > 0) {
                $post_type = get_post_field('post_type', $parent_id);
                if ($post_type === 'event_listing') {
                    $event_ids[$parent_id] = true; // unique map
                }
            }
        }

        $all_event_ids = array_keys($event_ids);

        // Get all events that actually exist (any status: publish, cancelled, expired, etc.)
        $all_existing_events = get_posts(array(
            'post_type' => 'event_listing',
            'post_status' => array('publish', 'expired'),
            'post__in' => $all_event_ids,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ));

        $total = count($all_existing_events);

        // Pagination
        $per_page = max(1, (int) $request->get_param('per_page') ?: 10);
        $page = max(1, (int) $request->get_param('page') ?: 1);
        $offset = ($page - 1) * $per_page;
        $event_posts = array_slice($all_existing_events, $offset, $per_page);

        // Build response items
        $events = array();
        foreach ($event_posts as $event_post) {
            $event_id = $event_post->ID;
            $images = function_exists('get_event_banner') ? get_event_banner($event_post) : wpem_get_event_banner($event_post);

            // get venue QR code
            $meta_data = get_post_meta($event_post->ID);

            $event_venue_ids = array();
            $is_online = get_post_meta($event_id, '_event_online', true);
            if ($is_online === 'no') {
                $event_venue_ids = get_post_meta($event_id, '_event_venue_ids', true);
            }

            $venues = array();

            if (!empty($event_venue_ids)) {
                if (!is_array($event_venue_ids)) {
                    $event_venue_ids = array($event_venue_ids);
                }

                foreach ($event_venue_ids as $event_venue_id) {
                    $venue = get_post($event_venue_id);
                    if ($venue) {
                        $venue_qrcode = get_post_meta($event_venue_id, '_venue_qrcode', true);

                        $venue_data = array(
                            'id'   => $venue->ID,
                            'name' => $venue->post_title,
                        );

                        if (!empty($venue_qrcode)) {
                            $venue_data['QRCode'] = $venue_qrcode;
                        }
                        $venues[] = $venue_data;
                    }
                }
            }

            // location logic
            $geo_location   = get_post_meta($event_id, 'geolocation_formatted_address', true);
            $event_location = get_post_meta($event_id, '_event_location', true);

            // if geolocation_formatted_address not found or empty,
            // then add _event_location value inside metadata only
            if ( !metadata_exists('post', $event_id, 'geolocation_formatted_address') || empty($geo_location)) {
                $meta_data['geolocation_formatted_address'] = array($event_location);
                $geo_location = $event_location;
            }

            $colors_plugin_active = is_plugin_active( 'wp-event-manager-colors/wp-event-manager-colors.php' );
            $cancelled_event_color = $colors_plugin_active ? get_option( 'event_manager_event_cancelled_color', '#ffe5e5' ) : '#ffe5e5';
            $featured_event_color = $colors_plugin_active ? get_option( 'event_manager_event_featured_color', '#ffffe4' ) : '#ffffe4';

            $date_time_separator = get_option( 'event_manager_date_time_format_separator', '@' );

            $date_format = WP_Event_Manager_Date_Time::get_event_manager_view_date_format();
            $time_format = WP_Event_Manager_Date_Time::get_timepicker_format();
            $startdate_timestamp = strtotime( get_post_meta($event_id, '_event_start_date', true) );
            $enddate_timestamp = strtotime( get_post_meta($event_id, '_event_end_date', true) );
            $starttime_timestamp = strtotime( get_post_meta($event_id, '_event_start_time', true) );
            $endtime_timestamp = strtotime( get_post_meta($event_id, '_event_end_time', true) );
            $event_online = get_post_meta( $event_id, '_event_online', true ) ?: 'no';
            $event_start_date = date_i18n( $date_format, $startdate_timestamp );
            $event_end_date = date_i18n( $date_format, $enddate_timestamp );
            $event_start_time = date_i18n( $time_format, $starttime_timestamp );
            $event_end_time = date_i18n( $time_format, $endtime_timestamp );

            $event_data = array(
                'event_id' => $event_id,
                'title' => $event_post->post_title,
                'status' => $event_post->post_status,
                'description' => wpautop(do_shortcode($event_post->post_content)),
                '_event_start_date' => $event_start_date,
                '_event_start_time' => $event_start_time,
                '_event_end_date' => $event_end_date,
                '_event_end_time' => $event_end_time,
                '_event_online' => $event_online,
                'location' => get_post_meta($event_id, '_event_location', true),
                'banner' => $images,
                '_cancelled' => get_post_meta($event_id, '_cancelled', true),
                'cancelled_event_color' => $cancelled_event_color,
                '_featured' => get_post_meta($event_id, '_featured', true),
                'featured_event_color' => $featured_event_color,
                'date_time_separator' => $date_time_separator,
                'meta_data' => array(
                    'geolocation_formatted_address' => $meta_data['geolocation_formatted_address'],
                ),
            );

            // add venue inside event
            if (!empty($venues)) {
                $event_data['venue'] = $venues;
            }
            $events[] = $event_data;
        }

        $total_pages = (int) ceil($total / $per_page);

        $response = self::wpem_prepare_error_for_response(200);
        $response['data'] = array(
            'total_post_count' => $total,
            'current_page' => $page,
            'last_page' => max(1, $total_pages),
            'total_pages' => $total_pages,
            'events' => $events,
        );
        return wp_send_json($response);
    }
}

new WPEM_REST_Matchmaking_Profile_Controller();