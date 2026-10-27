<?php
/**
 * REST API Matchmaking Settings controller (Event Controller style)
 *
 * Provides an endpoint to retrieve/update matchmaking settings for the current user.
 * Structured similarly to the Events controller's route/permission/response style.
 *
 * Route base: /wp-json/wpem/matchmaking-settings
 * Methods: GET (retrieve), POST (update)
 *
 * @since 1.1.4
 */

defined('ABSPATH') || exit;

class WPEM_REST_Matchmaking_Settings_Controller extends WPEM_REST_CRUD_Controller
{
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base for matchmaking settings endpoints.
     *
     * @var string
     */
    protected $rest_base = 'matchmaking-settings';

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
                    'callback' => array($this, 'wpem_get_settings'),
                    'permission_callback' => array($this, 'wpem_permission_check'),
                    'args' => array(),
                )
            )
        );

        register_rest_route(
            $this->namespace,
            '/taxonomy-list',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'wpem_get_taxonomy_terms'),
                'permission_callback' => array($this, 'wpem_permission_check'),
                'args' => array(
                    'taxonomy' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Taxonomy name (e.g., category, post_tag, custom_taxonomy).',
                    )
                ),
            )
        );
    }

    /**
     * GET /matchmaking-settings
     * Retrieve matchmaking settings for the current user. If event_id is provided,
     * include participation for that event; otherwise include all events.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|Array
     */
    public function wpem_get_settings($request)
    {
        $settings = [
            'request_mode' => get_option('wpem_meeting_request_mode'),
            'scheduling_mode' => get_option('wpem_meeting_scheduling_mode'),
            'attendee_limit' => get_option('wpem_meeting_attendee_limit'),
            'meeting_expiration' => get_option('wpem_meeting_expiration'),
            'is_matchmaking_site_feature_enabled' => get_option('enable_matchmaking'),
            'participant_activation' => get_option('participant_activation'),
            'meeting_delete_permission' => get_option('wpem_meeting_delete_policy'),
            'datepicker_date_format' => $this->wpem_get_datepicker_format_data(),
        ];

        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = $settings;
        // $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

    /**
     * Get the datepicker date format saved in WP Event Manager settings.
     *
     * The core plugin saves only an index (0-11) in the option
     * "event_manager_datepicker_format". The real formats are read from the
     * core plugin (WP_Event_Manager_Date_Time) so they always stay in sync,
     * including any change made through its filters.
     *
     * @return array|null
     */
    private function wpem_get_datepicker_format_data()
    {
        if (!class_exists('WP_Event_Manager_Date_Time')) {
            return null;
        }

        // jQuery UI datepicker format, e.g. "yy.mm.dd".
        $datepicker_format = WP_Event_Manager_Date_Time::get_datepicker_format();
        // PHP date() format for the same index, e.g. "Y.m.d".
        $php_format = WP_Event_Manager_Date_Time::get_view_date_format_from_datepicker_date_format($datepicker_format);

        $data = array(
            'value'             => absint(get_option('event_manager_datepicker_format', 0)),
            'datepicker_format' => $datepicker_format,
            'php_format'        => $php_format,
            'example'           => gmdate($php_format, strtotime('January 15 ' . gmdate('Y'))),
        );

        // Moment.js / Day.js style format, e.g. "YYYY.MM.DD" (available in newer core versions).
        if (method_exists('WP_Event_Manager_Date_Time', 'wpem_convert_php_to_moment_format')) {
            $data['moment_format'] = WP_Event_Manager_Date_Time::wpem_convert_php_to_moment_format($php_format);
        }

        return $data;
    }

    /**
     * Get the list of terms for a given taxonomy.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response $response The response object.
     * @since 1.1.0
     */
    public function wpem_get_taxonomy_terms($request)
    {
        $taxonomy = sanitize_text_field($request->get_param('taxonomy'));

        if (!taxonomy_exists($taxonomy)) {
            return self::wpem_prepare_error_for_response(400);
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            return self::wpem_prepare_error_for_response(500);
        }

        $term_list = array_map(array($this, 'wpem_format_term_data'), $terms);

        $response_data = self::wpem_prepare_error_for_response(200);
        $response_data['data'] = array(
            'terms' => $term_list,
            // 'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id()),
        );
        return wp_send_json($response_data);
    }

    /**
     * This function formats the term data.
     * 
     * @param $term
     * @return array
     */
    private function wpem_format_term_data($term)
    {
        return array(
            'id' => $term->term_id,
            'name' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            'slug' => $term->slug,
        );
    }
}

new WPEM_REST_Matchmaking_Settings_Controller();
