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
class WPEM_REST_Matchmaking_Meetings_Controller extends WPEM_REST_CRUD_Controller
{

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

    public function __construct()
    {
        global $wpdb;
        $this->table = esc_sql($wpdb->prefix . 'wpem_matchmaking_users_meetings');
        add_action('rest_api_init', array($this, 'wpem_register_routes'), 10);
    }

    /**
     * Register the routes for meetings.
     */
    public function wpem_register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_items'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => $this->wpem_get_collection_params(),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'wpem_create_meeting'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'wpem-rest-api'),
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_item'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'wpem_update_item'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'wpem_delete_item'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(
                        'force' => array(
                            'default' => false,
                            'description' => __('Whether to bypass trash and force deletion.', 'wpem-rest-api'),
                            'type' => 'boolean',
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
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'wpem_update_participant_status'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(
                        'status' => array(
                            'required' => true,
                            'description' => __('Your participant status (-1 pending, 0 declined, 1 accepted).', 'wpem-rest-api'),
                            'type' => 'integer',
                            'enum' => array(-1, 0, 1),
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
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'wpem_cancel_item'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                ),
            )
        );

        // Availability slots endpoint (for compatibility)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/slots',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'wpem_get_available_slots'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
                ),
            )
        );

        // Update availability slots endpoint (for compatibility)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/slots',
            array(
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'wpem_update_available_slots'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
                ),
            )
        );
    }
    
    /**
     * Format a DB row as API response data.
     */
    protected function wpem_format_meeting_row($row)
    {
        
        global $wpdb;
        // Participants map: [user_id => status]
        $host_id = (int) $row['user_id'];

        $participant_map = maybe_unserialize($row['participant_ids']);
        if (!is_array($participant_map)) {
            $participant_map = array();
        }

        // Build participants info array
        $participants_info = array();
        // Preload profession terms (slug => name)
        $profession_terms = function_exists('wpem_get_registration_taxonomy_list')
            ? (array) wpem_get_registration_taxonomy_list('event_registration_professions')
            : array();

        foreach ($participant_map as $pid => $status) {
            if ($pid == $host_id)
                continue;
            $user = get_userdata($pid);
            if (!$user)
                continue;
            $pid = (int) $pid;

            // participant status convert int to str
            $status = (int) $status;
            if ($status == 0):
                $p_status = 'rejected';
            endif;
            if ($status == 1):
                $p_status = 'accepted';
            endif;
            if ($status == -1):
                $p_status = 'pending';
            endif;

            // Build display name
            $display_name = '';
            if ($user && !empty($user->display_name)) {
                $display_name = $user->display_name;
            } else {
                $first_name = get_user_meta($pid, 'first_name', true);
                $last_name = get_user_meta($pid, 'last_name', true);
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

            // Always convert to array
            $profession_values = is_array($profession_value)
                ? $profession_value
                : [$profession_value];

            $profession_slugs = [];

            foreach ($profession_values as $value) {
                if (empty($value)) {
                    continue;
                }

                // Default: assume slug
                $slug = $value;

                // CASE 1: Value already matches a slug key
                if (isset($profession_terms[$value])) {
                    $slug = $value;

                } else {
                    // CASE 2: Value might be a name → convert name → slug
                    $found_slug = array_search($value, $profession_terms, true);
                    if ($found_slug !== false) {
                        $slug = $found_slug;
                    }
                }

                $profession_slugs[] = $slug;
            }

            // If one value, return string — if many, return array
            $profession_slug = count($profession_slugs) === 1
                ? $profession_slugs[0]
                : $profession_slugs;

            // Company name
            $company_name = get_user_meta($pid, '_company_name', true);

            $participants_info[] = array(
                'id' => $pid,
                'participant_status' => $p_status,
                'name' => $display_name,
                'profile_photo' => !empty($profile_photo) ? esc_url($profile_photo) : '',
                'profession' => $profession_slug,
                'company_name' => !empty($company_name) ? $company_name : '',
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

        // Normalize to array
        $host_prof_values = is_array($host_prof_value)
            ? $host_prof_value
            : [$host_prof_value];

        $host_prof_slugs = [];

        foreach ($host_prof_values as $value) {

            if (empty($value)) {
                continue;
            }

            // Case 1: Already a slug
            if (isset($profession_terms[$value])) {
                $host_prof_slugs[] = $value;
                continue;
            }

            // Case 2: Stored as name — convert to slug
            $found_slug = array_search($value, $profession_terms, true);

            if ($found_slug !== false) {
                $host_prof_slugs[] = $found_slug;
            } else {
                // fallback
                $host_prof_slugs[] = $value;
            }
        }

        $host_prof_slug = count($host_prof_slugs) === 1
            ? $host_prof_slugs[0]
            : $host_prof_slugs;

        $host_company = get_user_meta($host_id, '_company_name', true);

        $host_info = array(
            'id' => $host_id,
            'name' => $host_name,
            'profile_photo' => !empty($host_profile) ? esc_url($host_profile) : '',
            'profession' => $host_prof_slug,
            'company_name' => !empty($host_company) ? $host_company : '',
        );

        $event_title = get_the_title($row['event_id']);

        // meeting status convert int to str
        $current_date = current_time('Y-m-d');
        $meeting_date = gmdate('Y-m-d', strtotime($row['meeting_date']));
        $m_status = (int) $row['meeting_status'];
        if ($m_status == -1) {
            $m_status_str = 'cancel';
        } elseif ($meeting_date >= $current_date) {
            $m_status_str = 'upcoming';
        } elseif ($meeting_date < $current_date) {
            $m_status_str = 'past';
        }

        // room & table info
        $table_booking_table_name = esc_sql( $wpdb->prefix . 'wpem_matchmaking_table_bookings' );
        $table_room_name          = esc_sql( $wpdb->prefix . 'wpem_matchmaking_rooms' );
        $table_name               = esc_sql( $wpdb->prefix . 'wpem_matchmaking_tables' );

        // default values
        $room  = '';
        $floor = '';
        $table = '';
        
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table names are safely generated using the WordPress table prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_booked_datas = $wpdb->get_results($wpdb->prepare( "SELECT * FROM {$table_booking_table_name} WHERE meeting_id = %d ORDER BY id DESC", $row['id'] ), ARRAY_A );

        foreach($table_booked_datas as $table_booked_data) {
            $table_id = $table_booked_data['table_id'];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table_info = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $table_id ), ARRAY_A );
            $table = !empty($table_info['table_name']) ? $table_info['table_name'] : '';

            $room_id = $table_info['room_id'];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $room_info = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$table_room_name} WHERE id = %d", $room_id ), ARRAY_A );
            $room = !empty($room_info['room_name']) ? $room_info['room_name'] : '';
            $floor = !empty($room_info['floor']) ? $room_info['floor'] : '';
        }
        // phpcs:enable
        // Build final payload
        return array(
            'meeting_id' => (int) $row['id'],
            'event_id' => isset($row['event_id']) ? (int) $row['event_id'] : 0,
            'event_title' => $event_title,
            'meeting_date' => date_i18n('l, d F Y', strtotime($row['meeting_date'])),
            'start_time' => date_i18n('H:i', strtotime($row['meeting_start_time'])),
            'end_time' => date_i18n('H:i', strtotime($row['meeting_end_time'])),
            'message' => isset($row['message']) ? $row['message'] : '',
            'host_info' => $host_info,
            'participants' => $participants_info,
            'meeting_status' => $m_status_str,
            'room' => $room,
            'floor' => $floor,
            'table' => $table,
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
    public function wpem_get_items($request)
    {
        global $wpdb;
        $user_id      = wpem_rest_get_current_user_id();
        $partner_id   = (int) $request->get_param('partner_id');
        $meeting_date = sanitize_text_field($request->get_param('meeting_date'));
        $event_id     = (int) $request->get_param('event_id');
        $status       = sanitize_text_field($request->get_param('status'));
        $search       = sanitize_text_field($request->get_param('search'));
        $own_meeting  = (int) $request->get_param('own_meeting'); // NEW
        $page         = max(1, (int) $request->get_param('page'));
        $per_page     = max(1, min(100, (int) $request->get_param('per_page')));
        $offset       = ($page - 1) * $per_page;
        $params       = array();

        // --- Determine current user's role tier ---
        // Use user_can() against an explicit WP_User object rather than current_user_can(),
        // because current_user_can() relies on WordPress's global current-user state which
        // is not reliably populated during JWT/token-authenticated REST API requests.
        $current_user_obj = get_userdata( $user_id );
        $is_admin         = $current_user_obj && user_can( $current_user_obj, 'manage_options' );

        $organizer_event_ids = array();
        if ( ! $is_admin ) {
            $organizer_event_ids = get_posts( array(
                'post_type'      => 'event_listing',
                'post_status'    => 'publish',
                'author'         => $user_id,
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ) );
        }
        $is_organizer = ! $is_admin && ! empty( $organizer_event_ids );

        // Base WHERE clause
        if ( $is_admin ) {
            // Admin: filter by event_id if provided, otherwise all events.
            if ( $event_id ) {
                $where_sql  = 'WHERE event_id = %d';
                $params[]   = $event_id;
            } else {
                $where_sql = 'WHERE 1=1';
            }
        } elseif ( $is_organizer ) {
            if ($own_meeting === 1) {
                // Organizer wants ONLY their own meetings (host/participant),
                // regardless of which event it belongs to.
                if ($event_id) {
                    $where_sql = 'WHERE event_id = %d';
                    $params[]  = $event_id;
                } else {
                    $where_sql = 'WHERE 1=1';
                }

                $filter_sql = ' AND ( user_id = %d OR participant_ids LIKE %s )';
                $params[]   = $user_id;
                $params[]   = '%' . $wpdb->esc_like('i:' . $user_id) . '%';

                if ($partner_id) {
                    $filter_sql .= ' AND ( user_id = %d OR participant_ids LIKE %s )';
                    $params[]    = $partner_id;
                    $params[]    = '%' . $wpdb->esc_like('i:' . $partner_id) . '%';
                }

            } else {
                // Default organizer behavior — scoped to their own published events.
                if ($event_id) {
                    if (! in_array($event_id, $organizer_event_ids, true)) {
                        $organizer_name = $current_user_obj ? $current_user_obj->display_name : '';
                        $response_data  = self::wpem_prepare_error_for_response(403);
                        $response_data['message'] = sprintf(
                            /* translators: %s: organizer display name */
                            __('This event is not published by you (%s).', 'wpem-rest-api'),
                            $organizer_name
                        );
                        return wp_send_json($response_data);
                    }
                    $where_sql = 'WHERE event_id = %d';
                    $params[]  = $event_id;
                } else {
                    if (empty($organizer_event_ids)) {
                        $response_data = self::wpem_prepare_error_for_response(200);
                        $response_data['data'] = array(
                            'total_post_count' => 0,
                            'current_page'     => $page,
                            'last_page'        => 1,
                            'total_pages'      => 1,
                            $this->rest_base   => array(),
                            // 'user_status'      => wpem_get_user_login_status($user_id),
                        );
                        return wp_send_json($response_data);
                    }
                    $placeholders = implode(',', array_fill(0, count($organizer_event_ids), '%d'));
                    $where_sql    = "WHERE event_id IN ($placeholders)";
                    $params       = array_merge($params, $organizer_event_ids);
                }

                // Narrowing to a specific partner is safe here — organizer already
                // has full read access to every meeting within their own events.
                if ($partner_id) {
                    $filter_sql = ' AND ( user_id = %d OR participant_ids LIKE %s )';
                    $params[]   = $partner_id;
                    $params[]   = '%' . $wpdb->esc_like('i:' . $partner_id) . '%';
                }
            }

        } else {

            // Regular user: filter by event_id if provided, then further restrict to own meetings.
            if ( $event_id ) {
                $where_sql = 'WHERE event_id = %d';
                $params[]  = $event_id;
            } else {
                $where_sql = 'WHERE 1=1';
            }

            if ($partner_id) {
                // FIX (IDOR): logged-in user must ALWAYS be part of the meeting.
                // partner_id only narrows further to meetings shared with that partner.
                $filter_sql = ' AND (
                    ( user_id = %d OR participant_ids LIKE %s )
                    AND
                    ( user_id = %d OR participant_ids LIKE %s )
                )';
                $params[] = $user_id;
                $params[] = '%' . $wpdb->esc_like('i:' . $user_id) . '%';
                $params[] = $partner_id;
                $params[] = '%' . $wpdb->esc_like('i:' . $partner_id) . '%';
            } else {
                $filter_sql = ' AND ( user_id = %d OR participant_ids LIKE %s )';
                $params[]   = $user_id;
                $params[]   = '%' . $wpdb->esc_like('i:' . $user_id) . '%';
            }
        }

        // Status filter
        $current_date = current_time('Y-m-d');
        $status_filter = '';
        if ($status === 'cancelled') {
            $status_filter = ' AND meeting_status = -1';
        }
        if ($status === 'pending') {
            $status_filter = ' AND meeting_status = -2';
        }
        if ($status === 'accepted') {
            $status_filter = ' AND meeting_status = 1';
        }
        if ($status === 'rejected') {
            $status_filter = ' AND meeting_status = 0';
        }
        if ($status === 'upcoming') {
            $status_filter = $wpdb->prepare(' AND meeting_date >= %s AND meeting_status != -1', $current_date);
        }
        if ($status === 'past') {
            $status_filter = $wpdb->prepare(' AND meeting_date < %s AND meeting_status != -1', $current_date);
        }
        $date_filter = '';
        if (!empty($meeting_date)) {
            // $date_filter = $wpdb->prepare(' AND meeting_date = %s', $meeting_date);
            $date_filter = $wpdb->prepare(' AND DATE(meeting_date) = %s', $meeting_date);
        }
        
        $search_filter = '';
        if (!empty($search)) {
            $search_filter = " AND EXISTS (
                SELECT 1
                FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = {$this->table}.event_id
                AND pm.meta_key = '_event_title'
                AND pm.meta_value LIKE %s
            )";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        // SQL queries
        $sql_count = "SELECT COUNT(*) FROM {$this->table} {$where_sql} {$filter_sql} {$status_filter} {$date_filter} {$search_filter}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_count = $wpdb->prepare($sql_count, ...$params);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var($sql_count);
        
        $sql_rows = "SELECT * FROM {$this->table} {$where_sql} {$filter_sql} {$status_filter} {$date_filter} {$search_filter} ORDER BY meeting_date DESC, meeting_start_time ASC LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_rows = $wpdb->prepare($sql_rows, array_merge($params, [$per_page, $offset]));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results($sql_rows, ARRAY_A);

        // Format rows
        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = $this->wpem_format_meeting_row($row);
        }

        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = array(
            'total_post_count' => $total,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $per_page)),
            'total_pages' => (int) max(1, ceil($total / $per_page)),
            $this->rest_base => $items,
            // 'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
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
    public function wpem_get_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$row) {
            return self::wpem_prepare_error_for_response(404);
        }
        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = $this->wpem_format_meeting_row($row);
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function wpem_create_meeting($request)
    {
        global $wpdb;
        $user_id       = wpem_rest_get_current_user_id();
        $event_id      = intval($request->get_param('event_id'));
        $meeting_date  = sanitize_text_field($request->get_param('meeting_date'));
        $slot          = sanitize_text_field($request->get_param('slot'));
        $participants  = (array) $request->get_param('meeting_participants');
        $message       = sanitize_textarea_field($request->get_param('message'));

        if ( !$user_id || !$event_id || empty($meeting_date) || empty($slot) || empty($participants) ) {
            return new WP_REST_Response([
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => 'Missing required fields.',
            ], 400);
        }

        // Remove host from participants if passed accidentally
        $participants = array_filter(array_map('intval', $participants), function ($pid) use ($user_id) {
            return $pid && $pid !== $user_id;
        });

        if (empty($participants)) {
            return new WP_REST_Response([
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => 'Invalid participants.',
            ], 400);
        }

        $current_date = current_time('Y-m-d');
        $current_time = current_time('H:i:s');
        if($current_date > $meeting_date || ($meeting_date === $current_date && $current_time > $slot)) {
            return new WP_REST_Response([
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => 'Please select future date and time.',
            ], 400);
        }

        /**
         * Time
         */
        $start_time = gmdate('H:i:s', strtotime($slot));
        $end_time   = gmdate('H:i:s', strtotime($slot . ' +1 hour'));

        /*
         * check table capacity
         */
        $total_persons_requested = 1 + count($participants);
        $tables_table = esc_sql(WPEM_MATCHMAKING_TABLES_TABLE);
        $rooms_table  = esc_sql(WPEM_MATCHMAKING_ROOMS_TABLE);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table names are safely generated using the WordPress table prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $max_table_capacity = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(t.table_capacity) FROM {$tables_table} t INNER JOIN {$rooms_table} r ON r.id = t.room_id WHERE r.event_id = %d", $event_id));
        if ($max_table_capacity > 0 && $total_persons_requested > $max_table_capacity) {
            return new WP_REST_Response([
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => sprintf(
                    /* translators: 1: Total requested persons, 2: Maximum available table capacity. */
                    __(
                        'Cannot create meeting for %1$d persons. The largest available table for this event holds %2$d persons. Please reduce the number of participants.',
                        'wpem-rest-api'
                    ),
                    $total_persons_requested,
                    $max_table_capacity
                ),
            ], 400);
        }

        /**
         * check participant availability (slots + overlapping meetings)
         */
        if ( ! $this->wpem_check_meeting_time_availability( $user_id, $participants, $meeting_date, $start_time, $end_time ) ) {
            return new WP_REST_Response([
                'code'    => 409,
                'status'  => 'ERROR',
                'message' => __('Participant not available on this time, check availability of participants first.', 'wpem-rest-api'),
            ], 409);
        }

        /**
         * check table availability
         */
        $table_bookings_table = esc_sql(WPEM_MATCHMAKING_TABLE_BOOKINGS_TABLE);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $event_room_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$rooms_table} WHERE event_id = %d", $event_id));
        $available_table_id = null;
        if ($event_room_count > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $available_table_id = $wpdb->get_var($wpdb->prepare( "SELECT t.id FROM {$tables_table} t INNER JOIN {$rooms_table} r ON r.id = t.room_id WHERE r.event_id = %d AND t.table_capacity >= %d AND t.id NOT IN ( SELECT b.table_id FROM {$table_bookings_table} b WHERE b.booked_date = %s AND b.start_time < %s AND b.end_time > %s ) ORDER BY t.table_capacity ASC, t.id ASC LIMIT 1", $event_id, $total_persons_requested, $meeting_date, $end_time, $start_time));

            if (!$available_table_id) {
                return new WP_REST_Response([
                    'code'    => 409,
                    'status'  => 'ERROR',
                    'message' => __('table not available on this time', 'wpem-rest-api'),
                ], 409);
            }
        }

        /**
         * participant status
         */
        $participant_status_array = [];
        foreach ($participants as $pid) {
            $meeting_request_mode = get_user_meta($pid, '_wpem_meeting_request_mode', true);
            $is_auto_mode = strtolower($meeting_request_mode) === 'automatic';
            $participant_status_array[$pid] = $is_auto_mode ? 1 : -1;
        }

        /**
         * insert meeting
         */
        $insert_data = [
            'user_id'             => $user_id,
            'event_id'            => $event_id,
            'participant_ids'     => maybe_serialize($participant_status_array),
            'meeting_date'        => $meeting_date,
            'meeting_start_time'  => gmdate('H:i', strtotime($start_time)),
            'meeting_end_time'    => gmdate('H:i', strtotime($end_time)),
            'message'             => $message,
            'meeting_status'      => 0,
            'created_at'          => current_time('mysql'),
        ];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        if (!$inserted) {
            return new WP_REST_Response([
                'code'    => 500,
                'status'  => 'ERROR',
                'message' => 'Failed to create meeting.',
            ], 500);
        }
        $meeting_id = $wpdb->insert_id;

        /**
         * Assign table only when at least one participant has accepted
         * (automatic-mode participants are accepted at creation time).
         * Otherwise the table is assigned later, on first acceptance.
         */
        if ( $available_table_id && $this->wpem_has_accepted_participant( $participant_status_array ) ) {
            $this->wpem_book_meeting_table( $meeting_id, $available_table_id, $meeting_date, $start_time, $end_time );
        }

        /**
         * send mail
         */
        WP_Event_Manager_Registrations_MatchMaking::send_matchmaking_meeting_emails(
            $meeting_id,
            $user_id,
            $event_id,
            $participants,
            $meeting_date,
            gmdate('H:i', strtotime($start_time)),
            gmdate('H:i', strtotime($end_time)),
            $message
        );

        /**
         * response
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $meeting_id
            ),
            ARRAY_A
        );
        // phpcs:enable
        return new WP_REST_Response([
            'code'    => 200,
            'status'  => 'OK',
            'message' => 'Meeting created successfully.',
            'data'    => $this->wpem_format_meeting_row($row),
        ], 200);
    }

    /**
     * True when at least one participant has accepted (status 1).
     *
     * @param array $participants_map [user_id => status]
     * @return bool
     */
    protected function wpem_has_accepted_participant( $participants_map )
    {
        foreach ( (array) $participants_map as $status ) {
            if ( (int) $status === 1 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does the meetings table have a table_id column?
     *
     * @return bool
     */
    protected function wpem_meeting_table_id_column_exists()
    {
        global $wpdb;
        static $exists = null;
        if ( null === $exists ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $exists = (bool) $wpdb->get_var( "SHOW COLUMNS FROM {$this->table} LIKE 'table_id'" );
        }
        return $exists;
    }

    /**
     * Table currently booked for a meeting (0 when none).
     *
     * @param int $meeting_id
     * @return int
     */
    protected function wpem_get_meeting_table_id( $meeting_id )
    {
        global $wpdb;
        $bookings = esc_sql( WPEM_MATCHMAKING_TABLE_BOOKINGS_TABLE );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT table_id FROM {$bookings} WHERE meeting_id = %d ORDER BY id DESC LIMIT 1", (int) $meeting_id ) );
    }

    /**
     * Release the table booked for a meeting so it can be used by another meeting
     * on the same date/time.
     *
     * @param int $meeting_id
     */
    protected function wpem_release_meeting_table( $meeting_id )
    {
        global $wpdb;
        $meeting_id = (int) $meeting_id;
        $bookings   = esc_sql( WPEM_MATCHMAKING_TABLE_BOOKINGS_TABLE );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $bookings, array( 'meeting_id' => $meeting_id ), array( '%d' ) );

        if ( $this->wpem_meeting_table_id_column_exists() ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET table_id = NULL WHERE id = %d", $meeting_id ) );
        }
    }

    /**
     * Book a table for a meeting (replaces any previous booking of that meeting).
     *
     * @param int    $meeting_id
     * @param int    $table_id
     * @param string $date  Y-m-d
     * @param string $start H:i[:s]
     * @param string $end   H:i[:s]
     */
    protected function wpem_book_meeting_table( $meeting_id, $table_id, $date, $start, $end )
    {
        global $wpdb;
        $meeting_id = (int) $meeting_id;
        $table_id   = (int) $table_id;
        $bookings   = esc_sql( WPEM_MATCHMAKING_TABLE_BOOKINGS_TABLE );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $bookings, array( 'meeting_id' => $meeting_id ), array( '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $bookings,
            array(
                'meeting_id'  => $meeting_id,
                'table_id'    => $table_id,
                'booked_date' => $date,
                'start_time'  => gmdate( 'H:i', strtotime( $start ) ),
                'end_time'    => gmdate( 'H:i', strtotime( $end ) ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        if ( $this->wpem_meeting_table_id_column_exists() ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $this->table, array( 'table_id' => $table_id ), array( 'id' => $meeting_id ), array( '%d' ), array( '%d' ) );
        }
    }

    /**
     * Check that every participant is available on the given date and slot.
     *
     * A participant is unavailable when:
     *  - they switched off "available for meeting" (_available_for_meeting = 0), or
     *  - the requested slot is not in their availability slots for that date, or
     *  - the requested slot is already booked.
     */
    public function wpem_check_meeting_time_availability( $host_id, $participants, $meeting_date, $start_time, $end_time, $exclude_meeting_id = 0 )
    {
        global $wpdb;

        $requested = gmdate( 'H:i', strtotime( $start_time ) );

        // 1. Availability slots (participants only)
        foreach ( (array) $participants as $uid ) {
            $uid = (int) $uid;
            if ( ! $uid ) {
                continue;
            }

            $flag = get_user_meta( $uid, '_available_for_meeting', true );
            if ( $flag !== '' && $flag !== null && (int) $flag === 0 ) {
                return false;
            }

            $user_slots = function_exists( 'wpem_get_participants_available_meeting_slots' )
                ? wpem_get_participants_available_meeting_slots( array( $uid ), $meeting_date )
                : array();

            // Only check that the slot EXISTS in the user's availability.
            // "is_booked" is intentionally ignored here, because the helper may
            // flag slots as booked even when the user rejected that meeting.
            // Real conflicts are handled (status-aware) in step 2 below.
            $found = false;
            if ( is_array( $user_slots ) ) {
                foreach ( $user_slots as $user_slot ) {
                    if ( empty( $user_slot['time'] ) ) {
                        continue;
                    }
                    if ( gmdate( 'H:i', strtotime( $user_slot['time'] ) ) === $requested ) {
                        $found = true;
                        break;
                    }
                }
            }

            if ( ! $found ) {
                return false;
            }
        }

        // 2. Overlapping ACTIVE meetings (host + participants)
        $check_user_ids = array_map( 'intval', array_unique( array_merge( array( $host_id ), (array) $participants ) ) );
        $participant_only_ids = array_map( 'intval', (array) $participants );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $overlaps = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, participant_ids FROM {$this->table}
            WHERE id != %d AND meeting_date = %s
            AND meeting_status != -1
            AND meeting_start_time < %s AND meeting_end_time > %s",
            $exclude_meeting_id, $meeting_date, $end_time, $start_time
        ), ARRAY_A );

        foreach ( (array) $overlaps as $row ) {
            $pids = maybe_unserialize( $row['participant_ids'] );
            $pids = is_array( $pids ) ? $pids : array();

            // A meeting where every participant rejected (status 0) is dead -> ignore it.
            $has_active_participant = false;
            foreach ( $pids as $status ) {
                if ( (int) $status !== 0 ) {
                    $has_active_participant = true;
                    break;
                }
            }
            if ( ! $has_active_participant ) {
                continue;
            }

            // Someone is the host of an overlapping active meeting
            if ( in_array( (int) $row['user_id'], $check_user_ids, true ) ) {
                return false;
            }

            // Someone is a participant of an overlapping meeting
            // (only counts if they did NOT reject it)
                foreach ( $check_user_ids as $cid ) {
                if ( array_key_exists( $cid, $pids ) && (int) $pids[ $cid ] !== 0 ) {
                        return false;
                }
            }
        }

        return true;
    }

    /**
     * Update an existing matchmaking meeting.
     * PUT/PATCH /matchmaking-meetings/{id}
     *
     * Performs the same table-capacity, participant-availability, and
     * table-availability checks that wpem_create_meeting() does.  When the
     * date/time or participants change the old table booking is released
     * and a fresh one is created.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.0
     */
    public function wpem_update_item($request)
    {
        global $wpdb;

        $user_id    = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id ), ARRAY_A );
        if ( ! $row ) {
            return self::wpem_prepare_error_for_response( 404 );
        }

        // ----------------------------------------------------------------
        // 1. Collect incoming values (fall back to existing row values).
        // ----------------------------------------------------------------
        $new_date  = null !== $request->get_param( 'meeting_date' )
            ? sanitize_text_field( $request->get_param( 'meeting_date' ) )
            : $row['meeting_date'];

        $new_slot  = $request->get_param( 'slot' );  // optional convenience param (same as create)
        if ( null !== $new_slot ) {
            $new_start = gmdate( 'H:i:s', strtotime( sanitize_text_field( $new_slot ) ) );
            $new_end   = gmdate( 'H:i:s', strtotime( sanitize_text_field( $new_slot ) . ' +1 hour' ) );
        } else {
            $new_start = null !== $request->get_param( 'meeting_start' )
                ? gmdate( 'H:i:s', strtotime( $request->get_param( 'meeting_start' ) ) )
                : $row['meeting_start_time'];
            $new_end   = null !== $request->get_param( 'meeting_end' )
                ? gmdate( 'H:i:s', strtotime( $request->get_param( 'meeting_end' ) ) )
                : $row['meeting_end_time'];
        }

        // Resolve new participants list.
        $host_id          = (int) $row['user_id'];
        $existing_pmap    = maybe_unserialize( $row['participant_ids'] );
        if ( ! is_array( $existing_pmap ) ) {
            $existing_pmap = array();
        }

        $participants_map = $existing_pmap; // default: keep existing
        $participants_changed = false;

        if ( null !== ( $val = $request->get_param( 'participants' ) ) && is_array( $val ) ) {
            $participants_map     = array();
            $participants_changed = true;

            $is_assoc = array_keys( $val ) !== range( 0, count( $val ) - 1 );
            if ( $is_assoc ) {
                foreach ( $val as $pid => $status ) {
                    $pid = (int) $pid;
                    if ( $pid <= 0 || $pid === $host_id ) {
                        continue;
                    }
                    $status = (int) $status;
                    if ( ! in_array( $status, array( -1, 0, 1 ), true ) ) {
                        $status = -1;
                    }
                    $participants_map[ $pid ] = $status;
                }
            } else {
                $ids = array_values( array_unique( array_filter( array_map( 'intval', $val ) ) ) );
                foreach ( $ids as $pid ) {
                    if ( $pid <= 0 || $pid === $host_id ) {
                        continue;
                    }
                    // Preserve existing status; new participants follow their request mode (same as create).
                    if ( isset( $existing_pmap[ $pid ] ) ) {
                        $participants_map[ $pid ] = $existing_pmap[ $pid ];
                    } else {
                        $mode = get_user_meta( $pid, '_wpem_meeting_request_mode', true );
                        $participants_map[ $pid ] = ( strtolower( (string) $mode ) === 'automatic' ) ? 1 : -1;
                    }
                }
            }
        }

        // Determine whether time-sensitive checks are needed.
        $date_time_changed = (
            gmdate( 'Y-m-d', strtotime( $new_date ) ) !== gmdate( 'Y-m-d', strtotime( $row['meeting_date'] ) )
            || gmdate( 'H:i', strtotime( $new_start ) ) !== gmdate( 'H:i', strtotime( $row['meeting_start_time'] ) )
            || gmdate( 'H:i', strtotime( $new_end ) )   !== gmdate( 'H:i', strtotime( $row['meeting_end_time'] ) )
        );
        $schedule_changed = ( $date_time_changed || $participants_changed );

        // ----------------------------------------------------------------
        // 1b. Basic validations (same as create meeting).
        // ----------------------------------------------------------------
        if ( $participants_changed && empty( $participants_map ) ) {
            return new WP_REST_Response( array(
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => 'Invalid participants.',
            ), 400 );
        }

        if ( false === strtotime( $new_date ) || false === strtotime( $new_start ) || false === strtotime( $new_end ) ) {
            return new WP_REST_Response( array(
                'code'    => 400,
                'status'  => 'ERROR',
                'message' => 'Invalid date or time.',
            ), 400 );
        }

        // Future date/time is required only when the date/time is being changed.
        if ( $date_time_changed ) {
            $current_date = current_time( 'Y-m-d' );
            $current_time = current_time( 'H:i:s' );
            if ( $current_date > $new_date || ( $new_date === $current_date && $current_time > $new_start ) ) {
                return new WP_REST_Response( array(
                    'code'    => 400,
                    'status'  => 'ERROR',
                    'message' => 'Please select future date and time.',
                ), 400 );
            }
        }

        // ----------------------------------------------------------------
        // 2. Only run availability/capacity checks when schedule changes.
        // ----------------------------------------------------------------
        if ( $schedule_changed ) {

            $event_id            = (int) $row['event_id'];
            $participant_ids     = array_keys( $participants_map );
            $total_persons       = 1 + count( $participant_ids ); // host + participants

            $tables_table        = esc_sql( WPEM_MATCHMAKING_TABLES_TABLE );
            $rooms_table         = esc_sql( WPEM_MATCHMAKING_ROOMS_TABLE );
            $table_bookings_table = esc_sql( WPEM_MATCHMAKING_TABLE_BOOKINGS_TABLE );

            // -- 2a. Table capacity check ----------------------------------
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $max_table_capacity = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT MAX(t.table_capacity)
                     FROM {$tables_table} t
                     INNER JOIN {$rooms_table} r ON r.id = t.room_id
                     WHERE r.event_id = %d",
                    $event_id
                )
            );

            if ( $max_table_capacity > 0 && $total_persons > $max_table_capacity ) {
                return new WP_REST_Response( array(
                    'code'    => 400,
                    'status'  => 'ERROR',
                    'message' => sprintf(
                        /* translators: 1: Total requested persons, 2: Maximum available table capacity. */
                        __( 'Cannot update meeting for %1$d persons. The largest available table for this event holds %2$d persons. Please reduce the number of participants.', 'wpem-rest-api' ),
                        $total_persons,
                        $max_table_capacity
                    ),
                ), 400 );
            }

            // phpcs:enable

            // -- 2b. Participant availability check (slots + overlapping meetings) ---
            // Excludes this meeting itself; cancelled meetings never block.
            if ( ! $this->wpem_check_meeting_time_availability( $host_id, $participant_ids, $new_date, $new_start, $new_end, $meeting_id ) ) {
                return new WP_REST_Response( array(
                    'code'    => 409,
                    'status'  => 'ERROR',
                    'message' => __( 'Participant not available on this time, check availability of participants first.', 'wpem-rest-api' ),
                ), 409 );
            }

            // -- 2c. Table availability check -----------------------------
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $event_room_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$rooms_table} WHERE event_id = %d", $event_id ) );

            $new_table_id = null;
            if ( $event_room_count > 0 ) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $new_table_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT t.id
                         FROM {$tables_table} t
                         INNER JOIN {$rooms_table} r ON r.id = t.room_id
                         WHERE r.event_id = %d
                           AND t.table_capacity >= %d
                           AND t.id NOT IN (
                               SELECT b.table_id
                               FROM {$table_bookings_table} b
                               WHERE b.booked_date = %s
                                 AND b.start_time  < %s
                                 AND b.end_time    > %s
                                 AND b.meeting_id != %d
                           )
                         ORDER BY t.table_capacity ASC, t.id ASC
                         LIMIT 1",
                        $event_id,
                        $total_persons,
                        $new_date,
                        $new_end,
                        $new_start,
                        $meeting_id   // exclude the current meeting's own booking
                    )
                );
                // phpcs:enable

                if ( ! $new_table_id ) {
                    return new WP_REST_Response( array(
                        'code'    => 409,
                        'status'  => 'ERROR',
                        'message' => __( 'table not available on this time', 'wpem-rest-api' ),
                    ), 409 );
                }
            }

            // -- 2d. Assign table only if at least one participant accepted ---
            // No acceptance yet => make sure no table is held for this meeting.
            if ( $this->wpem_has_accepted_participant( $participants_map ) ) {
                if ( ! is_null( $new_table_id ) ) {
                    $this->wpem_book_meeting_table( $meeting_id, $new_table_id, $new_date, $new_start, $new_end );
                }
            } else {
                $this->wpem_release_meeting_table( $meeting_id );
            }

        } // end if $schedule_changed

        // ----------------------------------------------------------------
        // 3. Build the UPDATE fields array.
        // ----------------------------------------------------------------
        $fields  = array();
        $formats = array();

        // Date / time.
        if ( $new_date !== $row['meeting_date'] ) {
            $fields['meeting_date'] = $new_date;
            $formats[]              = '%s';
        }
        $new_start_hm = gmdate( 'H:i', strtotime( $new_start ) );
        $new_end_hm   = gmdate( 'H:i', strtotime( $new_end ) );
        if ( $new_start_hm !== gmdate( 'H:i', strtotime( $row['meeting_start_time'] ) ) ) {
            $fields['meeting_start_time'] = $new_start_hm;
            $formats[]                    = '%s';
        }
        if ( $new_end_hm !== gmdate( 'H:i', strtotime( $row['meeting_end_time'] ) ) ) {
            $fields['meeting_end_time'] = $new_end_hm;
            $formats[]                  = '%s';
        }

        // Participants.
        if ( $participants_changed ) {
            $fields['participant_ids'] = maybe_serialize( $participants_map );
            $formats[]                 = '%s';
        }

        // Message.
        if ( null !== ( $val = $request->get_param( 'message' ) ) ) {
            $fields['message'] = sanitize_textarea_field( $val );
            $formats[]         = '%s';
        }

        // Meeting status (only allow explicit override).
        if ( null !== ( $val = $request->get_param( 'meeting_status' ) ) ) {
            $fields['meeting_status'] = (int) $val;
            $formats[]                = '%d';
        }

        if ( empty( $fields ) ) {
            // Nothing actually changed — return current data.
            $response_data         = self::wpem_prepare_error_for_response( 200 );
            $response_data['data'] = $this->wpem_format_meeting_row( $row );
            // $response_data['data']['user_status'] = wpem_get_user_login_status( wpem_rest_get_current_user_id() );
            return wp_send_json( $response_data );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update( $this->table, $fields, array( 'id' => $meeting_id ), $formats, array( '%d' ) );
        if ( $updated === false ) {
            return self::wpem_prepare_error_for_response( 500 );
        }

        // If the meeting was cancelled via this endpoint, free its table too.
        if ( isset( $fields['meeting_status'] ) && (int) $fields['meeting_status'] === -1 ) {
            $this->wpem_release_meeting_table( $meeting_id );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $meeting_id ), ARRAY_A );

        $response_data         = self::wpem_prepare_error_for_response( 200 );
        $response_data['data'] = $this->wpem_format_meeting_row( $row );
        // $response_data['data']['user_status'] = wpem_get_user_login_status( wpem_rest_get_current_user_id() );
        return wp_send_json( $response_data );
    }

    /**
     * Update your participant status on a meeting without overwriting others.
     * PUT/PATCH /matchmaking-meetings/{id}/participant-status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.1
     */
    public function wpem_update_participant_status($request)
    {
        global $wpdb;
        $meeting_id = (int) $request['id'];
        $user_id = (int) wpem_rest_get_current_user_id();
        $status = $request['status'];

        if ($status != 0 && $status != 1 && $status != -1) {
            return self::wpem_prepare_error_for_response(400);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        if (!$row) {
            return self::wpem_prepare_error_for_response(404);
        }

        $participant_data = maybe_unserialize($row['participant_ids']);
        if (!is_array($participant_data)) {
            $participant_data = array();
        }

        if (!array_key_exists($user_id, $participant_data)) {
            return self::wpem_prepare_error_for_response(404);
        }

        // Update the status
        $participant_data[$user_id] = $status;

        // Compute overall meeting status: accepted if any participant accepted
        $meeting_status = in_array(1, $participant_data, true) ? 1 : 0;

        // --- Table auto-allocation on first acceptance (mirrors web-side logic) ---
        $table_id = $this->wpem_get_meeting_table_id( $meeting_id );

        // No participant accepted (anymore) => the meeting must not hold a table.
        if ( ! $this->wpem_has_accepted_participant( $participant_data ) ) {
            $this->wpem_release_meeting_table( $meeting_id );
            $table_id = 0;
        }

        if ( (int) $status === 1 && empty( $table_id ) ) {
            // host + all participants
            $total_persons = 1 + count( $participant_data );

            if ( class_exists( 'WP_Event_Manager_Registrations_MatchMaking' ) ) {
                $matchmaking = new WP_Event_Manager_Registrations_MatchMaking();
                $allocated_table_id = $matchmaking->wpem_allocate_meeting_table(
                    (int) $row['event_id'],
                    $total_persons,
                    $meeting_id,
                    $row['meeting_date'],
                    $row['meeting_start_time'],
                    $row['meeting_end_time']
                );

                if ( $allocated_table_id ) {
                    $table_id = $allocated_table_id;
                }
            }
        }

        // Build update payload
        $update_data    = array(
            'participant_ids' => maybe_serialize( $participant_data ),
            'meeting_status'  => $meeting_status,
        );
        $update_formats = array( '%s', '%d' );

        if ( ! empty( $table_id ) && $this->wpem_meeting_table_id_column_exists() ) {
            $update_data['table_id'] = $table_id;
            $update_formats[]        = '%d';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $this->table,
            $update_data,
            array( 'id' => $meeting_id ),
            $update_formats,
            array( '%d' )
        );

        if ($updated === false) {
            return self::wpem_prepare_error_for_response(500);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = $this->wpem_format_meeting_row($row);
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function wpem_cancel_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::wpem_prepare_error_for_response(404);
        }
        // // Unserialize participant_ids safely
        $participant_ids = maybe_unserialize($row['participant_ids']);

        if (!is_array($participant_ids)) {
            $participant_ids = [];
        }
        // // Update all participants to -1 (cancelled)
        // foreach ($participant_ids as $participant_id => $status) {
        //     $participants[$participant_id] = -1;
        // }

        // // Re-serialize
        // $participant_serialized = maybe_serialize($participants);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $updated = $wpdb->update(
            $this->table,
            array('meeting_status' => -1),
            array('id' => $meeting_id),
            array('%d', '%s'),
            array('%d')
        );
        if ($updated === false) {
            return self::wpem_prepare_error_for_response(500);
        }

        // Release the assigned table so it can be booked by another meeting
        $this->wpem_release_meeting_table($meeting_id);

        // Fetch meeting

        $fresh_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $meeting   = $fresh_row ? (object) $fresh_row : null;

        // 4. Send cancel mail to participants
        if ( $meeting && class_exists( 'WP_Event_Manager_Registrations_MatchMaking' ) ) {
            $registration_instance = new WP_Event_Manager_Registrations_MatchMaking();
            $registration_instance->wpem_send_cancel_meeting_email(
                $user_id,
                $participant_ids,
                $meeting
            );
        }
        // $registration_instance = new WP_Event_Manager_Registrations_MatchMaking();
        // $registration_instance->wpem_send_cancel_meeting_email($user_id, $participant_ids, $meeting);
        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = $this->wpem_format_meeting_row($fresh_row ? $fresh_row : $row);
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function wpem_delete_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::wpem_prepare_error_for_response(404);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($this->table, array('id' => $meeting_id), array('%d'));
        if (!$deleted) {
            return self::wpem_prepare_error_for_response(500);
        }

        // Release the assigned table so it can be booked by another meeting
        $this->wpem_release_meeting_table($meeting_id);

        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = array('id' => $meeting_id);
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

    /**
     * JSON Schema for a meeting item
     * @return array
     */
    public function wpem_get_item_schema()
    {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'matchmaking_meeting',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'wpem-rest-api'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'event_id' => array(
                    'description' => __('Event ID.', 'wpem-rest-api'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                ),
                'host_id' => array(
                    'description' => __('Host user ID.', 'wpem-rest-api'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                ),
                'meeting_date' => array(
                    'description' => __('Meeting date (Y-m-d).', 'wpem-rest-api'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'meeting_start' => array(
                    'description' => __('Meeting start time (H:i).', 'wpem-rest-api'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'meeting_end' => array(
                    'description' => __('Meeting end time (H:i).', 'wpem-rest-api'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'message' => array(
                    'description' => __('Optional message.', 'wpem-rest-api'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'participants' => array(
                    'description' => __('Map of participant user_id => status (-1 pending, 0 declined, 1 accepted).', 'wpem-rest-api'),
                    'type' => 'object',
                    'context' => array('view', 'edit'),
                ),
                'meeting_status' => array(
                    'description' => __('Derived overall meeting status.', 'wpem-rest-api'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                ),
            ),
        );
        return $this->wpem_add_additional_fields_schema($schema);
    }

    /**
     * Collection params (pagination + filters)
     * 
     */
    public function wpem_get_collection_params()
    {
        $params = parent::wpem_get_collection_params();
        $params['user_id'] = array(
            'description' => __('Limit result set to meetings relevant to a user (host or participant).', 'wpem-rest-api'),
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        );
        $params['event_id'] = array(
            'description' => __('Limit result set to meetings relevant to a specific event.', 'wpem-rest-api'),
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        );
        $params['status'] = array(
            'description' => __('Limit result set to meetings with a specific status (pending, accepted, rejected).', 'wpem-rest-api'),
            'type' => 'string',
            'enum' => array('pending', 'accepted', 'rejected'),
            'sanitize_callback' => 'sanitize_text_field',
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
    public function wpem_get_available_slots($request)
    {

        $user_id = $request->get_param('user_ids') ?: wpem_rest_get_current_user_id();
        if ($user_id == wpem_rest_get_current_user_id()) {
            // Fetch default slots for user (helper aligns with existing implementation)
            $slots = wpem_get_default_meeting_slots_for_matchmaking_participants($user_id);

            // Availability flag (_available_for_meeting); default to 1 if not set
            $meta = get_user_meta($user_id, '_available_for_meeting', true);
            $meeting_available = ($meta !== '' && $meta !== null) ? ((int) $meta === 0 ? 0 : 1) : 1;

            $response_data = self::wpem_prepare_error_for_response(200);
            $response_data['data'] = array(
                'available_for_meeting' => $meeting_available,
                'slots' => $slots,
                // 'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
            );
            return wp_send_json($response_data);
        } else {

            $date = $request->get_param('date') ? sanitize_text_field($request->get_param('date')) : '';
            $user_ids = $request->get_param('user_ids');
            if ( empty($date) || empty($user_ids) ) {
                return self::wpem_prepare_error_for_response(404);
            }

            if ( is_array($user_ids) && isset($user_ids[0]) ) {
                $user_ids = trim($user_ids[0], '[]');
                $user_ids = array_map('intval', explode(',', $user_ids));
            }

            // Get slots
            $combined_slots = wpem_get_participants_available_meeting_slots($user_ids, $date);
            $slots = array();
            foreach ($combined_slots as $slot) {
                // Skip booked slots
                if ( ! empty($slot['is_booked']) ) {
                    continue;
                }
                $time = $slot['time'];
                $slots[$time] = "1";
            }
            $response_data = self::wpem_prepare_error_for_response(200);
            $response_data['data'] = array(
                'slots' => $slots,
                // 'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
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
    public function wpem_update_available_slots($request)
    {
        $user_id = wpem_rest_get_current_user_id();
        $available_for_meeting = $request->get_param('available_for_meeting') ? 1 : 0;

        $updated_status = update_user_meta($user_id, '_available_for_meeting', (int) $available_for_meeting);

        if ($available_for_meeting == 1) {
            $availability_slots = $request->get_param('availability_slots') ?? array();
            $updated_slot = update_user_meta($user_id, '_meeting_availability_slot', $availability_slots);
        } else {
            delete_user_meta($user_id, '_meeting_availability_slot');
        }

        return self::wpem_prepare_error_for_response(200);
    }
}

new WPEM_REST_Matchmaking_Meetings_Controller();