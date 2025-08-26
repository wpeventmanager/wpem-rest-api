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
        // Check if matchmaking is enabled
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response(array(
                'code'    => 403,
                'status'  => 'Disabled',
                'message' => 'Matchmaking functionality is not enabled.',
                'data'    => null
            ), 403);
        }
        $auth_check = $this->wpem_check_authorized_user();
		if ($auth_check) {
			return self::prepare_error_for_response(405);
		} else {
            $taxonomy = sanitize_text_field($request->get_param('taxonomy'));

            if (!taxonomy_exists($taxonomy)) {
                return new WP_REST_Response(array(
                    'code'    => 400,
                    'status'  => 'Bad Request',
                    'message' => 'Invalid taxonomy.',
                    'data'    => null
                ), 400);
            }

            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ));

            if (is_wp_error($terms)) {
                return new WP_REST_Response(array(
                    'code'    => 500,
                    'status'  => 'Server Error',
                    'message' => 'Failed to fetch terms.',
                    'data'    => null
                ), 500);
            }

            $term_list = array_map(array($this, 'format_term_data'), $terms);

            return new WP_REST_Response(array(
                'code'    => 200,
                'status'  => 'OK',
                'message' => 'Taxonomy terms retrieved successfully.',
                'data'    => $term_list
            ), 200);
        }
    }

    private function format_term_data($term) {
        return array(
            'id'    => $term->term_id,
            'name'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            'slug'  => $term->slug,
        );
    }
}

new WPEM_REST_Taxonomy_List_Controller();
