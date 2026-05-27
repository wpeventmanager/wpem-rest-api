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
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for meetings.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_collection_params(),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_meeting'),
                    'permission_callback' => array($this, 'permission_check'),
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
                    'callback' => array($this, 'get_item'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_item'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'permission_check'),
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
                    'callback' => array($this, 'update_participant_status'),
                    'permission_callback' => array($this, 'permission_check'),
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
                    'callback' => array($this, 'cancel_item'),
                    'permission_callback' => array($this, 'permission_check'),
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
                    'callback' => array($this, 'get_available_slots'),
                    'permission_callback' => array($this, 'permission_check'),
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
                    'callback' => array($this, 'update_available_slots'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => array(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/organizer',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_meeting_list_for_organizer'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_collection_params(),
                ),
                'schema' => array($this, 'get_public_item_schema'),
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
    public function permission_check($request)
    {
        $auth_check = $this->wpem_check_authorized_user();
        if ($auth_check) {
            return $auth_check; // Standardized error already sent
        }
        return true;
    }
    
    /**
     * Format a DB row as API response data.
     */
    protected function format_meeting_row($row)
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
    public function get_items($request)
    {
        global $wpdb;
        // Get current user ID
        $user_id = wpem_rest_get_current_user_id();
        $partner_id = (int) $request->get_param('partner_id');
        $event_id = (int) $request->get_param('event_id');
        $status = sanitize_text_field($request->get_param('status'));
        $search = sanitize_text_field($request->get_param('search'));
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $offset = ($page - 1) * $per_page;
        $params = array();

        // --- Determine current user's role tier ---
        // Use user_can() against an explicit WP_User object rather than current_user_can(),
        // because current_user_can() relies on WordPress's global current-user state which
        // is not reliably populated during JWT/token-authenticated REST API requests.
        $current_user_obj = get_userdata( $user_id );
        $is_admin         = $current_user_obj && user_can( $current_user_obj, 'manage_options' );

        // Organizer detection: this plugin has no dedicated capability for organizers.
        // The same pattern used in get_meeting_list_for_organizer() applies here —
        // a user is treated as an organizer if they have at least one published event_listing
        // where they are the post author.
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

            // Organizer: scope to their own published events only.
            if ( $event_id ) {
                // Requested a specific event — must belong to this organizer.
                if ( ! in_array( $event_id, $organizer_event_ids, true ) ) {
                    // Event doesn't belong to this organizer — return a clear error message.
                    $organizer_name   = $current_user_obj ? $current_user_obj->display_name : '';
                    $response_data    = self::prepare_error_for_response( 403 );
                    $response_data['message'] = sprintf(
                        /* translators: %s: organizer display name */
                        __( 'This event is not published by you (%s).', 'wpem-rest-api' ),
                        $organizer_name
                    );
                    return wp_send_json( $response_data );
                }
                $where_sql = 'WHERE event_id = %d';
                $params[]  = $event_id;
            } else {
                // No specific event — show meetings for ALL organizer's events.
                if ( empty( $organizer_event_ids ) ) {
                    $response_data = self::prepare_error_for_response( 200 );
                    $response_data['data'] = array(
                        'total_post_count' => 0,
                        'current_page'     => $page,
                        'last_page'        => 1,
                        'total_pages'      => 1,
                        $this->rest_base   => array(),
                        'user_status'      => wpem_get_user_login_status( $user_id ),
                    );
                    return wp_send_json( $response_data );
                }
                $placeholders = implode( ',', array_fill( 0, count( $organizer_event_ids ), '%d' ) );
                $where_sql    = "WHERE event_id IN ($placeholders)";
                $params       = array_merge( $params, $organizer_event_ids );
            }

        } else {

            // Regular user: filter by event_id if provided, then further restrict to own meetings.
            if ( $event_id ) {
                $where_sql = 'WHERE event_id = %d';
                $params[]  = $event_id;
            } else {
                $where_sql = 'WHERE 1=1';
            }
        }

        // --- Per-role meeting visibility filter ---
        $filter_sql = '';

        if ( $is_admin ) {

            // Admin sees every meeting — no extra filter.
            $filter_sql = '';

        } elseif ( $is_organizer ) {

            // Organizer sees all meetings of their events (scoped by WHERE above) —
            // no extra per-user participant filter needed.
            if ( $partner_id ) {
                // Optional: narrow down to a specific participant within organizer's events.
                $filter_sql  = ' AND (
                    user_id = %d
                    OR participant_ids LIKE %s
                )';
                $params[] = $partner_id;
                $params[] = '%' . $wpdb->esc_like( 'i:' . $partner_id ) . '%';
            }

        } else {

            // Regular user: only their own meetings (host or participant).
            if ( $partner_id ) {
                $filter_sql  = ' AND (
                    user_id = %d
                    OR participant_ids LIKE %s
                )';
                $params[] = $partner_id;
                $params[] = '%' . $wpdb->esc_like( 'i:' . $partner_id ) . '%';
            } else {
                $filter_sql  = ' AND (
                    user_id = %d
                    OR participant_ids LIKE %s
                )';
                $params[] = $user_id;
                $params[] = '%' . $wpdb->esc_like( 'i:' . $user_id ) . '%';
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
        $sql_count = "SELECT COUNT(*) FROM {$this->table} {$where_sql} {$filter_sql} {$status_filter} {$search_filter}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_count = $wpdb->prepare($sql_count, ...$params);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var($sql_count);
        
        $sql_rows = "SELECT * FROM {$this->table} {$where_sql} {$filter_sql} {$status_filter} {$search_filter} ORDER BY meeting_date ASC, meeting_start_time ASC LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_rows = $wpdb->prepare($sql_rows, array_merge($params, [$per_page, $offset]));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results($sql_rows, ARRAY_A);

        // Format rows
        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = $this->format_meeting_row($row);
        }

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = array(
            'total_post_count' => $total,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $per_page)),
            'total_pages' => (int) max(1, ceil($total / $per_page)),
            $this->rest_base => $items,
            'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
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
    public function get_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$row) {
            return self::prepare_error_for_response(404);
        }
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function create_meeting($request)
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
         * check participant availability
         */
        $check_user_ids = array_unique(array_merge([$user_id], $participants));
        foreach ($check_user_ids as $check_user_id) {

            // Host meetings
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $host_conflict = $wpdb->get_var($wpdb->prepare( "SELECT id FROM {$this->table} WHERE meeting_date = %s AND user_id = %d AND ( (meeting_start_time < %s AND meeting_end_time > %s) ) LIMIT 1", $meeting_date, $check_user_id, $end_time, $start_time));

            if ($host_conflict) {
                return new WP_REST_Response([
                    'code'    => 409,
                    'status'  => 'ERROR',
                    'message' => __('participant not available on this time', 'wpem-rest-api'),
                ], 409);
            }

            // Participant meetings
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $participant_conflicts = $wpdb->get_results($wpdb->prepare("SELECT id, participant_ids FROM {$this->table} WHERE meeting_date = %s AND ((meeting_start_time < %s AND meeting_end_time > %s))", $meeting_date, $end_time, $start_time), ARRAY_A);

            if (!empty($participant_conflicts)) {
                foreach ($participant_conflicts as $row) {
                    $participant_ids = maybe_unserialize($row['participant_ids']);
                    if (is_array($participant_ids) && array_key_exists($check_user_id, $participant_ids)) {
                        return new WP_REST_Response([
                            'code'    => 409,
                            'status'  => 'ERROR',
                            'message' => __('participant not available on this time', 'wpem-rest-api'),
                        ], 409);
                    }
                }
            }
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
            $available_table_id = $wpdb->get_var($wpdb->prepare( "SELECT t.id FROM {$tables_table} t INNER JOIN {$rooms_table} r ON r.id = t.room_id WHERE r.event_id = %d AND t.table_capacity >= %d AND t.id NOT IN ( SELECT b.table_id FROM {$table_bookings_table} b WHERE b.booked_date = %s AND b.start_time < %s AND b.end_time > %s ) ORDER BY t.id ASC LIMIT 1", $event_id, $total_persons_requested, $meeting_date, $end_time, $start_time));

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
            'data'    => $this->format_meeting_row($row),
        ], 200);
    }

    /**
     * Update an existing matchmaking meeting.
     * PUT/PATCH /matchmaking-meetings/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.0
     */
    public function update_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }

        $fields = array();
        $formats = array();

        if (null !== ($val = $request->get_param('meeting_date'))) {
            $fields['meeting_date'] = sanitize_text_field($val);
            $formats[] = '%s';
        }
        if (null !== ($val = $request->get_param('meeting_start'))) {
            $fields['meeting_start_time'] = gmdate('H:i', strtotime($val));
            $formats[] = '%s';
        }
        if (null !== ($val = $request->get_param('meeting_end'))) {
            $fields['meeting_end_time'] = gmdate('H:i', strtotime($val));
            $formats[] = '%s';
        }
        if (null !== ($val = $request->get_param('message'))) {
            $fields['message'] = sanitize_textarea_field($val);
            $formats[] = '%s';
        }
        if (null !== ($val = $request->get_param('meeting_status'))) {
            $fields['meeting_status'] = (int) $val;
            $formats[] = '%d';
        }
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($this->table, $fields, array('id' => $meeting_id), $formats, array('%d'));
        if ($updated === false) {
            return self::prepare_error_for_response(500);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function update_participant_status($request)
    {
        global $wpdb;
        $meeting_id = (int) $request['id'];
        $user_id = (int) wpem_rest_get_current_user_id();
        $status = $request['status'];

        if ($status != 0 && $status != 1 && $status != -1) {
            return self::prepare_error_for_response(400);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $this->table,
            array(
                'participant_ids' => maybe_serialize($participant_data),
                'meeting_status' => $meeting_status,
            ),
            array('id' => $meeting_id),
            array('%s', '%d'),
            array('%d')
        );

        if ($updated === false) {
            return self::prepare_error_for_response(500);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $meeting_id), ARRAY_A);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function cancel_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
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
            return self::prepare_error_for_response(500);
        }

        // Fetch meeting
        $table = esc_sql($this->table);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $meeting_id));
        //send mail to all participants        
        if ( class_exists( 'WP_Event_Manager_Registrations_MatchMaking' ) ) {
            $registration_instance = new WP_Event_Manager_Registrations_MatchMaking();
        
            $registration_instance->wpem_send_cancel_meeting_email(
                $user_id,
                $participant_ids,
                $meeting
            );
        }
        // $registration_instance = new WP_Event_Manager_Registrations_MatchMaking();
        // $registration_instance->wpem_send_cancel_meeting_email($user_id, $participant_ids, $meeting);
        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $this->format_meeting_row($row);
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
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
    public function delete_item($request)
    {
        global $wpdb;
        $user_id = wpem_rest_get_current_user_id();
        $meeting_id = (int) $request['id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d", $meeting_id, $user_id), ARRAY_A);
        if (!$row) {
            return self::prepare_error_for_response(404);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($this->table, array('id' => $meeting_id), array('%d'));
        if (!$deleted) {
            return self::prepare_error_for_response(500);
        }

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = array('id' => $meeting_id);
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

    /**
     * JSON Schema for a meeting item
     * @return array
     */
    public function get_item_schema()
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
        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Collection params (pagination + filters)
     * 
     */
    public function get_collection_params()
    {
        $params = parent::get_collection_params();
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
    public function get_available_slots($request)
    {

        $user_id = $request->get_param('user_ids') ?: wpem_rest_get_current_user_id();
        if ($user_id == wpem_rest_get_current_user_id()) {
            // Fetch default slots for user (helper aligns with existing implementation)
            $slots = wpem_get_default_meeting_slots_for_matchmaking_participants($user_id);

            // Availability flag (_available_for_meeting); default to 1 if not set
            $meta = get_user_meta($user_id, '_available_for_meeting', true);
            $meeting_available = ($meta !== '' && $meta !== null) ? ((int) $meta === 0 ? 0 : 1) : 1;

            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'available_for_meeting' => $meeting_available,
                'slots' => $slots,
                'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
            );
            return wp_send_json($response_data);
        } else {

            $date = $request->get_param('date') ? sanitize_text_field($request->get_param('date')) : '';
            $user_ids = $request->get_param('user_ids');
            if ( empty($date) || empty($user_ids) ) {
                return self::prepare_error_for_response(404);
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
            $response_data = self::prepare_error_for_response(200);
            $response_data['data'] = array(
                'slots' => $slots,
                'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
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
    public function update_available_slots($request)
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

        return self::prepare_error_for_response(200);
    }

    /**
     * Retrieves a specific matchmaking meeting by ID.
     * GET /matchmaking-meetings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.2.0
     */
    public function get_meeting_list_for_organizer($request)
    {
        global $wpdb;
        // Get current user ID
        $user_id = wpem_rest_get_current_user_id();
        $event_id = (int) $request->get_param('event_id');
        $status = sanitize_text_field($request->get_param('status'));
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $offset = ($page - 1) * $per_page;

        $params = [];

        // --- Determine role tier (same logic as get_items) ---
        $current_user_obj = get_userdata( $user_id );
        $is_admin         = $current_user_obj && user_can( $current_user_obj, 'manage_options' );

        // --- Get events published by this user as author ---
        $organizer_events = [];
        if ( ! $is_admin ) {
            $organizer_events = get_posts([
                'post_type'      => 'event_listing',
                'post_status'    => 'publish',
                'author'         => $user_id,
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);
        }
        $is_organizer = ! $is_admin && ! empty( $organizer_events );

        // --- Build WHERE clause based on role ---
        if ( $is_admin ) {

            // Admin: see all meetings, optionally filtered by event_id.
            if ( $event_id ) {
                $where_sql = "WHERE event_id = %d";
                $params[]  = $event_id;
            } else {
                $where_sql = "WHERE 1=1";
            }

        } elseif ( $is_organizer ) {

            if ( $event_id ) {
                // Requested a specific event — must belong to this organizer.
                if ( ! in_array( $event_id, $organizer_events, true ) ) {
                    $organizer_name           = $current_user_obj ? $current_user_obj->display_name : '';
                    $response_data            = self::prepare_error_for_response( 403 );
                    $response_data['message'] = sprintf(
                        /* translators: %s: organizer display name */
                        __( 'This event is not published by you (%s).', 'wpem-rest-api' ),
                        $organizer_name
                    );
                    return wp_send_json( $response_data );
                }
                $where_sql = "WHERE event_id = %d";
                $params[]  = $event_id;
            } else {
                // No event_id — show meetings for ALL organizer's published events.
                $placeholders = implode( ',', array_fill( 0, count( $organizer_events ), '%d' ) );
                $where_sql    = "WHERE event_id IN ($placeholders)";
                $params       = array_merge( $params, $organizer_events );
            }

        } else {

            // Regular user (customer): show only their own meetings (host or participant).
            if ( $event_id ) {
                $where_sql = "WHERE event_id = %d";
                $params[]  = $event_id;
            } else {
                $where_sql = "WHERE 1=1";
            }
            $where_sql .= " AND ( user_id = %d OR participant_ids LIKE %s )";
            $params[]   = $user_id;
            $params[]   = '%' . $wpdb->esc_like( 'i:' . $user_id ) . '%';
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

        // --- SQL queries ---
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query parts use prepared placeholders.
        $sql_count = "SELECT COUNT(*) FROM {$this->table} {$where_sql}{$status_filter}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query parts use prepared placeholders.
        $sql_rows = "SELECT * FROM {$this->table} {$where_sql}{$status_filter}
                    ORDER BY meeting_date ASC, meeting_start_time ASC
                    LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_count = $wpdb->prepare($sql_count, ...$params);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var($sql_count);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql_rows = $wpdb->prepare($sql_rows, array_merge($params, [$per_page, $offset]));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results($sql_rows, ARRAY_A);

        // Format rows
        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = $this->format_meeting_row($row);
        }

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = [
            'total_post_count' => $total,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $per_page)),
            'total_pages' => (int) max(1, ceil($total / $per_page)),
            $this->rest_base => $items,
            'user_status' => wpem_get_user_login_status($user_id)
        ];

        return wp_send_json($response_data);
    }

}

new WPEM_REST_Matchmaking_Meetings_Controller();