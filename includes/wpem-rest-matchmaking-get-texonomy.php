<?php
class WPEM_REST_Taxonomy_List_Controller extends WPEM_REST_CRUD_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'taxonomy-list';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_taxonomy_terms'),
                'permission_callback' => array($this, 'permission_check'),
                'args'                => array(
                    'taxonomy' => array(
                        'required'    => true,
                        'type'        => 'string',
                        'description' => 'Taxonomy name (e.g., category, post_tag, custom_taxonomy).',
                    )
                ),
            )
        );
    }

    /**
     * Get the list of terms for a given taxonomy.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response $response The response object.
     * @since 1.1.0
     */
    public function get_taxonomy_terms($request) {
        $taxonomy = sanitize_text_field($request->get_param('taxonomy'));

        if (!taxonomy_exists($taxonomy)) {
            return self::prepare_error_for_response(400);
        }

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            return self::prepare_error_for_response(500);
        }

        $term_list = array_map(array($this, 'format_term_data'), $terms);

        $response_data = self::prepare_error_for_response(200);
        $response_data['data'] = $term_list;
        $response_data['data']['user_status'] = wpem_get_user_login_status(wpem_rest_get_current_user_id());
        return wp_send_json($response_data);
    }

    /**
     * This function formats the term data.
     * 
     * @param $term
     * @return array
     */
    private function format_term_data($term) {
        return array(
            'id'    => $term->term_id,
            'name'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            'slug'  => $term->slug,
        );
    }
}

new WPEM_REST_Taxonomy_List_Controller();
