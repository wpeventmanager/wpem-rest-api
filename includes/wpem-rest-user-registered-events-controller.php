<?php
/**
 * REST API: User Registered Events Controller
 *
 * Handles requests to the /user-registered-events endpoint and returns events
 * the authenticated user is registered for.
 *
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * User Registered Events Controller
 *
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_User_Registered_Events extends WPEM_REST_CRUD_Controller {

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
    protected $rest_base = 'user-registered-event-list';

    /**
     * Post type we are ultimately returning.
     *
     * @var string
     */
    protected $post_type = 'event_listing';

    /**
     * If object is hierarchical.
     *
     * @var bool
     */
    protected $hierarchical = true;

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for user registered events.
     */
    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($auth_controller, 'check_authentication'),
                    'args'                => $this->get_collection_params() + array(
                        'user_id' => array(
                            'description'       => __('Optional user ID. Defaults to authenticated user.', 'wpem-rest-api'),
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    /**
     * Get the query params for collections.
     *
     * @return array
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();
        // We only need pagination + search for this endpoint. Others can remain as provided by parent.
        return $params;
    }

    /**
     * Handle GET /user-registered-events
     *
     * Returns paginated event summaries for events the target user is registered for.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|array
     */
    public function get_items($request) {
        $auth_user_id = $this->get_authenticated_user_id();
        if (!$auth_user_id) {
            return self::prepare_error_for_response(401);
        }

        $requested_user_id = absint($request->get_param('user_id'));
        $target_user_id    = $requested_user_id ?: $auth_user_id;

        // Restrict fetching other users' data unless explicitly allowed (no elevated capability here by JWT design)
        if ($requested_user_id && $requested_user_id !== $auth_user_id) {
            return self::prepare_error_for_response(403);
        }

        // Query all registrations authored by the target user and collect unique parent event IDs
        $registration_query = new WP_Query(array(
            'post_type'      => 'event_registration',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'author'         => $target_user_id,
            'no_found_rows'  => true,
        ));

        $event_ids_map = array();
        foreach ((array)$registration_query->posts as $registration_id) {
            $parent_id = (int) wp_get_post_parent_id($registration_id);
            if ($parent_id > 0) {
                $event_post = get_post($parent_id);
                if ($event_post && $event_post->post_type === $this->post_type) {
                    $event_ids_map[$parent_id] = true;
                }
            }
        }

        $all_event_ids = array_keys($event_ids_map);
        $total         = count($all_event_ids);

        // Pagination
        $per_page = max(1, (int) $request->get_param('per_page'));
        $page     = max(1, (int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        $paged_event_ids = array_slice($all_event_ids, $offset, $per_page);

        // Build response items
        $events = array();
        foreach ($paged_event_ids as $event_id) {
            $event_post = get_post($event_id);
            if ($event_post) {
                $events[] = $this->format_event_summary($event_post);
            }
        }

        $total_pages = (int) ceil($total / $per_page);

        $response = self::prepare_error_for_response(200);
        $response['data'] = array(
            'total_post_count' => $total,
            'current_page'     => $page,
            'last_page'        => max(1, $total_pages),
            'total_pages'      => $total_pages,
            'events'           => array_values($events),
        );
        return wp_send_json($response);
    }

    /**
     * Format a concise event payload (summary) similar to event controller style.
     *
     * @param WP_Post $event
     * @return array
     */
    protected function format_event_summary(WP_Post $event) {
        $meta_start = get_post_meta($event->ID, '_event_start_date', true);
        $meta_end   = get_post_meta($event->ID, '_event_end_date', true);
        $location   = get_post_meta($event->ID, '_event_location', true);

        // Use existing helper if available
        $images = function_exists('get_event_banner') ? get_event_banner($event) : array();

        return array(
            'event_id'     => $event->ID,
            'title'        => $event->post_title,
            'status'      => $event->post_status,
            'start_date'  => get_post_meta($event->ID, '_event_start_date', true),
            'end_date'    => get_post_meta($event->ID, '_event_end_date', true),
            'location'    => get_post_meta($event->ID, '_event_location', true),
            'banner'      => $images
        );
    }

    /**
     * Resolve authenticated user ID from Bearer token using shared helper.
     *
     * @return int
     */
    protected function get_authenticated_user_id() {
        $headers = function_exists('getallheaders') ? getallheaders() : array();
        $auth    = '';
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!$auth || !preg_match('/Bearer\s(\S+)/', $auth, $m)) {
            return 0;
        }

        $token = trim($m[1]);
        $user  = self::wpem_validate_jwt_token($token);
        if (!$user || empty($user['id'])) {
            return 0;
        }
        return (int) $user['id'];
    }

    /**
     * Satisfy abstract methods for CRUD controller; not used directly here.
     */
    protected function get_object($id) {
        $post = get_post($id);
        if ($post && $post->post_type === $this->post_type) {
            return $post;
        }
        return parent::prepare_error_for_response(404);
    }

    /**
     * Prepare object for response.
     * @param WP_Post $object
     * @param WP_REST_Request $request
     */
    protected function prepare_object_for_response($object, $request) {
        $event = $this->get_object($object->ID);
        if (is_wp_error($event)) {
            return $event;
        }
        $data = $this->format_event_summary($event);
        return rest_ensure_response($data);
    }

    /**
     * Get item schema.
     * @return array
     */
    public function get_item_schema() {
        // Schema is not essential for this listing endpoint.
        return array();
    }
}

new WPEM_REST_User_Registered_Events();
