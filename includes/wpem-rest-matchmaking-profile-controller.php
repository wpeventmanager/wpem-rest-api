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
                ),
            ),
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_matchmaking_profile'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                ),
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