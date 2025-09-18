<?php
/**
 * REST API Matchmaking Meetings controller
 *
 * Handles requests to the /matchmaking-meetings endpoint.
 *
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * REST API Matchmaking Meetings controller class.
 *
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_Matchmaking_Meetings_Controller extends WPEM_REST_CRUD_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking-meetings';

    /**
     * DB table for meetings
     * @var string
     */
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for meetings.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_meeting'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                'args'   => array(
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'wpem-rest-api'),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(
                        'force' => array(
                            'default'     => false,
                            'description' => __('Whether to bypass trash and force deletion.', 'wpem-rest-api'),
                            'type'        => 'boolean',
                        ),
                    ),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        // Update the logged-in participant's status for a meeting
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/meeting-status',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Meeting ID.', 'wpem-rest-api'),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_participant_status'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(
                        'status' => array(
                            'required'    => true,
                            'description' => __('Your participant status (-1 pending, 0 declined, 1 accepted).', 'wpem-rest-api'),
                            'type'        => 'integer',
                            'enum'        => array(-1, 0, 1),
                        ),
                    ),
                ),
            )
        );

        // Cancel a meeting (sets meeting_status = -1)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/cancel',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Meeting ID.', 'wpem-rest-api'),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'cancel_item'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        // Availability slots endpoint (for compatibility)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base. '/slots',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_available_slots'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                ),
            )
        );

        // Update availability slots endpoint (for compatibility)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base. '/slots',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_available_slots'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args'                => array(),
                ),
            )
        );
    }
    
    /**
     * Format a DB row as API response data.
     */
    protected function format_meeting_row($row) {
        // Participants map: [user_id => status]
        $host_id = (int) $row['user_id'];

        $participant_map = maybe_unserialize($row['participant_ids']);
        if (!is_array($participant_map)) {
            $participant_map = array();
        }

        // Build participants info array
        $participants_info = array();
        // Preload profession terms (slug => name)
        $profession_terms = function_exists('get_event_registration_taxonomy_list')
            ? (array) get_event_registration_taxonomy_list('event_registration_professions')
            : array();

        foreach ($participant_map as $pid => $status) {
            if($pid == $host_id) continue;
            $pid = (int) $pid;
            $status = (int) $status;
            $user = get_userdata($pid);

            // Build display name
            $display_name = '';
            if ($user && !empty($user->display_name)) {
                $display_name = $user->display_name;
            } else {
                $first_name = get_user_meta($pid, 'first_name', true);
                $last_name  = get_user_meta($pid, 'last_name', true);
                $display_name = trim($first_name . ' ' . $last_name);
            }

            // Profile photo
            $profile_photo = function_exists('get_wpem_user_profile_photo')
                ? get_wpem_user_profile_photo($pid)
                : '';
            if (empty($profile_photo) && defined('EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL')) {
                $profile_photo = EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
            }

            // Profession slug
            $profession_value = get_user_meta($pid, '_profession', true);
            $profession_slug  = $profession_value;
            if (!empty($profession_value) && !isset($profession_terms[$profession_value])) {
                // If stored as name, convert to slug
                $found_slug = array_search($profession_value, $profession_terms, true);
                if ($found_slug) {
                    $profession_slug = $found_slug;
                }
            }

            // Company name
            $company_name = get_user_meta($pid, '_company_name', true);

            $participants_info[] = array(
                'id'            => $pid,
                'status'        => $status,
                'name'          => $display_name,
                'profile_photo' => !empty($profile_photo) ? esc_url($profile_photo) : '',
                'profession'    => $profession_slug,
                'company_name'  => !empty($company_name) ? $company_name : '',
            );
        }

        // Host info
        $host = get_userdata($host_id);
        $host_name = ($host && !empty($host->display_name)) ? $host->display_name : '';
        if (empty($host_name)) {
            $fn = get_user_meta($host_id, 'first_name', true);
            $ln = get_user_meta($host_id, 'last_name', true);
            $host_name = trim($fn . ' ' . $ln);
        }
        $host_profile = function_exists('get_wpem_user_profile_photo') ? get_wpem_user_profile_photo($host_id) : '';
        if (empty($host_profile) && defined('EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL')) {
            $host_profile = EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
        }
        $host_prof_value = get_user_meta($host_id, '_profession', true);
        $host_prof_slug  = $host_prof_value;
        if (!empty($host_prof_value) && !isset($profession_terms[$host_prof_value])) {
            $found_slug = array_search($host_prof_value, $profession_terms, true);
            if ($found_slug) {
                $host_prof_slug = $found_slug;
            }
        }
        $host_company = get_user_meta($host_id, '_company_name', true);

        $host_info = array(
            'id'            => $host_id,
            'name'          => $host_name,
            'profile_photo' => !empty($host_profile) ? esc_url($host_profile) : '',
            'profession'    => $host_prof_slug,
            'company_name'  => !empty($host_company) ? $host_company : '',
        );

        // Build final payload
        return array(
            'meeting_id'     => (int) $row['id'],
            'event_id'       => isset($row['event_id']) ? (int) $row['event_id'] : 0,
            'meeting_date'   => date_i18n('l, d F Y', strtotime($row['meeting_date'])),
            'start_time'     => date_i18n('H:i', strtotime($row['meeting_start_time'])),
            'end_time'       => date_i18n('H:i', strtotime($row['meeting_end_time'])),
            'message'        => isset($row['message']) ? $row['message'] : '',
            'host_info'      => $host_info,
            'participants'   => $participants_info,
            'meeting_status' => (int) $row['meeting_status'],
        );
    }

    /**
     * Retrieves a specific matchmaking meeting by ID.
     * GET /matchmaking-meetings
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.2.0
     */
    public function get_items($request) {
        global $wpdb;
        // Get current user ID
        $user_id  = wpem_rest_get_current_user_id();
        $partner_id = (int) $request->get_param('partner_id');
        $event_id   = (int) $request->get_param('event_id');
        $page       = max(1, (int) $request->get_param('page'));
        $per_page   = max(1, min(100, (int) $request->get_param('per_page')));
        $offset   = ($page - 1) * $per_page;

        $params = array();

        // Base WHERE clause
        if ($event_id) {
            $where_sql = 'WHERE event_id = %d';
            $params[]  = $event_id;
        } else {
            $where_sql = 'WHERE 1=1';
        }

        // --- Filters ---
        if ($partner_id) {
            // Bi-directional filter
            $filter_sql = ' AND (
                (user_id = %d AND participant_ids LIKE %s)
                OR (user_id = %d AND participant_ids LIKE %s)
            )';

            $params[] = $user_id;
            $params[] = '%' . $wpdb->esc_like((string)$partner_id) . '%';
            $params[] = $partner_id;
            $params[] = '%' . $wpdb->esc_like((string)$user_id) . '%';
        } else {
            // Default user-only filter
            $filter_sql = ' AND (user_id = %d OR participant_ids LIKE %s)';
            $params[]   = $user_id;
            $params[]   = '%' . $wpdb->esc_like('i:' . $user_id) . '%';
        }

        // SQL queries
        $sql_count = "SELECT COUNT(*) FROM {$this->table} {$where_sql}{$filter_sql}";
        $sql_rows  = "SELECT * FROM {$this->table} {$where_sql}{$filter_sql} 
                    ORDER BY meeting_date ASC, meeting_start_time ASC 
                    LIMIT %d OFFSET %d";

        $sql_count = $wpdb->prepare($sql_count, $params);
        $sql_rows  = $wpdb->prepare($sql_rows, array_merge($params, [$per_page, $offset]));

        // Execute queries
        $total = (int) $wpdb->get_var($sql_count);
        $rows  = $wpdb->get_results($sql_rows, ARRAY_A);

        // Format rows
        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = $this->format_meeting_row($row);
        }

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = array(
            'total_post_count' => $total,
            'current_page'     => $page,
            'last_page'        => (int) max(1, ceil($total / $per_page)),
            'total_pages'      => (int) max(1, ceil($total / $per_page)),
            $this->rest_base   => $items,
        );
        return wp_send_json($response_data);
    }

    /**
     * GET /matchmaking-meetings/{id}
	 * 
	 * Retrieves a specific matchmaking meeting by ID.
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.2.0
     */
    public function get_item($request) {
        global $wpdb;
        $user_id  = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        return wp_send_json($response_data);
    }

    /**
	 * Create a new matchmaking meeting.
     * POST /matchmaking-meetings
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.2.0
     */
    public function create_meeting($request) {
        global $wpdb;
        $user_id      = wpem_rest_get_current_user_id();
        $event_id     = sanitize_text_field($request->get_param('event_id'));
        $meeting_date = sanitize_text_field($request->get_param('meeting_date'));
        $slot         = sanitize_text_field($request->get_param('slot'));
        $participants = (array) $request->get_param('meeting_participants');
        $message      = $request->get_param('message');

        if (!$user_id || empty($event_id) || empty($meeting_date) || empty($slot) || empty($participants) ||!is_array($participants)) {
            return self::prepare_error_for_response(400);
        }

        $participants_raw = array_filter(array_map('intval', $participants), function($pid) use ($user_id){ return $pid && $pid !== $user_id; });
        $participants_map = array_fill_keys($participants_raw, -1);

        $start_time = date('H:i', strtotime($slot));
        $end_time   = date('H:i', strtotime($slot . ' +1 hour'));

        // Availability check: host and all selected participants must be free (no accepted meetings) in this slot
        $check_ids = array_unique(array_merge(array($user_id), $participants_raw));
        if (!empty($check_ids)) {
            $in_placeholders = implode(',', array_fill(0, count($check_ids), '%d'));

            $like_clauses = array();
            $like_params  = array();
            foreach ($check_ids as $cid) {
                $like_clauses[] = 'participant_ids LIKE %s';
                $like_params[]  = '%' . $wpdb->esc_like('i:' . (int)$cid) . '%';
            }

            $availability_sql = "SELECT * FROM {$this->table} WHERE meeting_date = %s AND NOT (meeting_end_time <= %s OR meeting_start_time >= %s) AND (user_id IN ($in_placeholders) OR " . implode(' OR ', $like_clauses) . ")";
            $availability_params = array_merge(array($meeting_date, $start_time, $end_time), $check_ids, $like_params);
            $overlapping_rows = $wpdb->get_results($wpdb->prepare($availability_sql, $availability_params), ARRAY_A);

            $conflicts = array();
            if (!empty($overlapping_rows)) {
                foreach ($overlapping_rows as $row) {
                    $participant_ids = maybe_unserialize($row['participant_ids']);
                    if (!is_array($participant_ids)) { $participant_ids = array(); }
                    $overall_status = isset($row['meeting_status']) ? (int)$row['meeting_status'] : 0;

                    foreach ($check_ids as $cid) {
                        $blocks = false;
                        if ((int)$row['user_id'] === (int)$cid) {
                            // Host blocks if meeting is accepted/confirmed (overall status) or any participant accepted
                            if ($overall_status === 1) {
                                $blocks = true;
                            } else {
                                foreach ($participant_ids as $participant_slot) {
                                    if ((int)$participant_slot === 1) { $blocks = true; break; }
                                }
                            }
                        } elseif (isset($participant_ids[$cid]) && (int)$participant_ids[$cid] === 1) {
                            // Participant blocks only if they accepted
                            $blocks = true;
                        }

                        if ($blocks) {
                            if (!isset($conflicts[$cid])) { $conflicts[$cid] = array(); }
                            $conflicts[$cid][] = (int)$row['id'];
                        }
                    }
                }
            }

            if (!empty($conflicts)) {
                return new WP_REST_Response(array(
                    'code'    => 409,
                    'status'  => 'Conflict',
                    'message' => 'One or more attendees are busy in the selected time slot.',
                    'data'    => array('conflicts' => $conflicts),
                ), 409);
            }
        }

        $inserted = $wpdb->insert(
            $this->table,
            array(
                'user_id'            => $user_id,
                'participant_ids'    => serialize($participants_map),
                'meeting_date'       => $meeting_date,
                'meeting_start_time' => $start_time,
                'meeting_end_time'   => $end_time,
                'event_id'           => $event_id,
                'message'            => $message,
                'meeting_status'     => 0,
            ),
            array('%d','%s','%s','%s','%s','%s','%d')
        );
        if (!$inserted) {
            return self::prepare_error_for_response(500);
        }
        $meeting_id = $wpdb->insert_id;
        $registrations = new WP_Event_Manager_Registrations_Register();
        $registrations->send_matchmaking_meeting_emails($meeting_id, $user_id, $event_id, $participants_raw, $meeting_date, $start_time, $end_time, $message);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        return wp_send_json($response_data);
    }

    /**
	 * Update an existing matchmaking meeting.
     * PUT/PATCH /matchmaking-meetings/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.2.0
     */
    public function update_item($request) {
        global $wpdb;
        $user_id  = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }

        $fields = array();
        $formats = array();

        if (null !== ($val = $request->get_param('meeting_date'))) { $fields['meeting_date'] = sanitize_text_field($val); $formats[] = '%s'; }
        if (null !== ($val = $request->get_param('meeting_start'))) { $fields['meeting_start_time'] = date('H:i', strtotime($val)); $formats[] = '%s'; }
        if (null !== ($val = $request->get_param('meeting_end'))) { $fields['meeting_end_time'] = date('H:i', strtotime($val)); $formats[] = '%s'; }
        if (null !== ($val = $request->get_param('message'))) { $fields['message'] = sanitize_textarea_field($val); $formats[] = '%s'; }
        if (null !== ($val = $request->get_param('meeting_status'))) { $fields['meeting_status'] = (int) $val; $formats[] = '%d'; }
        if (null !== ($val = $request->get_param('participants'))) {
            if (is_array($val)) {
                // Accept both a list of user IDs or a map of user_id => status
                $participants_map = array();
                $host_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;

                $is_assoc = array_keys($val) !== range(0, count($val) - 1);
                if ($is_assoc) {
                    foreach ($val as $pid => $status) {
                        $pid = (int) $pid;
                        if ($pid <= 0 || ($host_id && $pid === $host_id)) {
                            continue;
                        }
                        $status = (int) $status;
                        if (!in_array($status, array(-1, 0, 1), true)) {
                            $status = -1;
                        }
                        $participants_map[$pid] = $status;
                    }
                } else {
                    $ids = array_filter(array_map('intval', $val));
                    $ids = array_values(array_unique($ids));
                    foreach ($ids as $pid) {
                        if ($pid <= 0 || ($host_id && $pid === $host_id)) {
                            continue;
                        }
                        $participants_map[$pid] = -1; // default pending
                    }
                }

                $fields['participant_ids'] = maybe_serialize($participants_map);
                $formats[] = '%s';
            }
        }

        if (empty($fields)) {
            return self::prepare_error_for_response(400);
        }

        $updated = $wpdb->update($this->table, $fields, array('id' => $meeting_id), $formats, array('%d'));
        if ($updated === false) {
            return self::prepare_error_for_response(500);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        return wp_send_json($response_data);
    }

    /**
     * Update your participant status on a meeting without overwriting others.
     * PUT/PATCH /matchmaking-meetings/{id}/participant-status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.1
     */
    public function update_participant_status($request) {
        global $wpdb;
        $meeting_id       = (int) $request['id'];
        $user_id  = (int) wpem_rest_get_current_user_id();
        $status   = $request['status'];

        if ($status!= 0 && $status!= 1 && $status!= -1) {
            return self::prepare_error_for_response(400);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }

        $participant_data = maybe_unserialize($row['participant_ids']);
        if (!is_array($participant_data)) {
            $participant_data = array();
        }

        if (!array_key_exists($user_id, $participant_data)) {
            return self::prepare_error_for_response(404);
        }

        // Update the status
        $participant_data[$user_id] = $status;

        // Compute overall meeting status: accepted if any participant accepted
        $meeting_status = in_array(1, $participant_data, true) ? 1 : 0;

        $updated = $wpdb->update(
            $this->table,
            array(
                'participant_ids' => maybe_serialize($participant_data),
                'meeting_status'  => $meeting_status,
            ),
            array('id' => $meeting_id),
            array('%s', '%d'),
            array('%d')
        );

        if ($updated === false) {
            return self::prepare_error_for_response(500);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        return wp_send_json($response_data);
    }

    /**
     * Cancel a meeting by setting meeting_status = -1.
     * PUT/PATCH /matchmaking-meetings/{id}/cancel
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.1
     */
    public function cancel_item($request) {
        global $wpdb;
        $user_id  = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }
        // Unserialize participant_ids safely
        $participant_ids = maybe_unserialize( $row['participant_ids']);

        if ( ! is_array( $participant_ids ) ) {
            $participant_ids = [];
        }
        // Update all participants to -1 (cancelled)
        foreach ( $participant_ids as $participant_id => $status ) {
            $participants[ $participant_id ] = -1;
        }

        // Re-serialize
        $participant_serialized = maybe_serialize( $participants );

        $updated = $wpdb->update(
            $this->table,
            array('meeting_status' => -1, 'participant_ids' => $participant_serialized),
            array('id' => $meeting_id),
            array('%d', '%s'),
            array('%d')
        );
        if ($updated === false) {
            return self::prepare_error_for_response(500);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        //send mail to all participants
        $registration_instance = new WP_Event_Manager_Registrations_Register();
        $registration_instance->wpem_send_cancel_meeting_email($user_id, $participant_ids, $row);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        return wp_send_json($response_data);
    }

    /**
	 * Delete a matchmaking meeting.
     * DELETE /matchmaking-meetings/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.2.0
     */
    public function delete_item($request) {
        global $wpdb;
        $user_id  = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }

        $deleted = $wpdb->delete($this->table, array('id' => $meeting_id), array('%d'));
        if (!$deleted) {
            return self::prepare_error_for_response(500);
        }

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = array('id' => $meeting_id);
        return wp_send_json($response_data);
    }

    /**
     * JSON Schema for a meeting item
     * @return array
     */
    public function get_item_schema() {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'matchmaking_meeting',
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'wpem-rest-api'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'event_id' => array(
                    'description' => __('Event ID.', 'wpem-rest-api'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                ),
                'host_id' => array(
                    'description' => __('Host user ID.', 'wpem-rest-api'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                ),
                'meeting_date' => array(
                    'description' => __('Meeting date (Y-m-d).', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'meeting_start' => array(
                    'description' => __('Meeting start time (H:i).', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'meeting_end' => array(
                    'description' => __('Meeting end time (H:i).', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'message' => array(
                    'description' => __('Optional message.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'participants' => array(
                    'description' => __('Map of participant user_id => status (-1 pending, 0 declined, 1 accepted).', 'wpem-rest-api'),
                    'type'        => 'object',
                    'context'     => array('view', 'edit'),
                ),
                'meeting_status' => array(
                    'description' => __('Derived overall meeting status.', 'wpem-rest-api'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                ),
            ),
        );
        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Collection params (pagination + filters)
	 * 
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        $params['user_id'] = array(
            'description'       => __('Limit result set to meetings relevant to a user (host or participant).', 'wpem-rest-api'),
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        );
        $params['event_id'] = array(
            'description'       => __('Limit result set to meetings relevant to a specific event.', 'wpem-rest-api'),
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        );
        // Keep pagination params from parent
        return $params;
    }

    /**
     * GET /get-availability-slots
     * Return availability slots + availability flag for the specified/current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function get_available_slots($request) {
       
        $user_id = $request->get_param('user_ids') ?: wpem_rest_get_current_user_id();
        if($user_id == wpem_rest_get_current_user_id()) {
            // Fetch default slots for user (helper aligns with existing implementation)
            $slots = get_wpem_default_meeting_slots_for_user($user_id);

            // Availability flag (_available_for_meeting); default to 1 if not set
            $meta               = get_user_meta($user_id, '_available_for_meeting', true);
            $meeting_available  = ($meta !== '' && $meta !== null) ? ((int) $meta === 0 ? 0 : 1) : 1;

            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'available_for_meeting' => $meeting_available,
                'slots'                 => $default_slots,
            );
            return wp_send_json($response_data);
        } else {
            // Fetch slots for other users (if needed)
            $date     = $request->get_param('date') ? sanitize_text_field($request->get_param('date')) : '';

            if (!$date && !is_array($user_id)) {
                return self::prepare_error_for_response(404);
            }
            $combined_slots = get_wpem_user_available_slots($user_id, $date);
            foreach ($combined_slots as $slot) {
                $time = $slot['time'];
                // You can decide: set "1" if slot exists OR based on is_booked
                $slots[$time] = $slot['is_booked'] ? "0" : "1"; // available=1, booked=0
            }
            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'slots' => $slots
            );
            return wp_send_json($response_data);
        }
    }

    /**
     * PUT /update-availability-slots
     * Update availability slots + availability flag for the specified/current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function update_available_slots($request) {
        $user_id = wpem_rest_get_current_user_id();
        $available_for_meeting = $request->get_param('available_for_meeting') ? 1 : 0;

		$updated_status = update_user_meta($user_id, '_available_for_meeting', (int) $available_for_meeting);

		if($available_for_meeting == 1) {
            $availability_slots = $request->get_param('availability_slots')??array();
			$updated_slot = update_user_meta($user_id, '_meeting_availability_slot', $availability_slots);
		} else {
			delete_user_meta($user_id, '_meeting_availability_slot');
		}

        return self::prepare_error_for_response(200);
    }
}

new WPEM_REST_Matchmaking_Meetings_Controller();