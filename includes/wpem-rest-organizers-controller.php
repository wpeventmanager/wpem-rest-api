<?php
/**
 * REST API Organizers controller
 *
 * Handles requests to the /organizers endpoint.
 *
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * REST API Organizers controller class.
 *
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_Organizers_Controller extends WPEM_REST_CRUD_Controller
{

    /**
     * Endpoint namespace.
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base.
     * @var string
     */
    protected $rest_base = 'organizers';

    /**
     * Post type.
     * @var string
     */
    protected $post_type = 'event_organizer';

    /**
     * If object is hierarchical.
     * @var bool
     */
    protected $hierarchical = true;

    /**
     * Initialize organizer actions.
     */
    public function __construct()
    {
        add_action("wpem_rest_insert_{$this->post_type}_object", array($this, 'clear_transients'));
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    /**
     * Register the routes for organizers.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_item'),
                    'permission_callback' => array($this, 'create_item_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
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
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                    'args'                => array(
                        'context' => $this->get_context_param(array('default' => 'view')),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_item'),
                    'permission_callback' => array($this, 'update_item_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'delete_item_permissions_check'),
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

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/batch',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'batch_items'),
                    'permission_callback' => array($this, 'batch_items_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                'schema' => array($this, 'get_public_batch_schema'),
            )
        );
    }

    /**
     * Get object.
     *
     * @param int $id Object ID.
     * @return WP_Post|null
     */
    protected function get_object($id)
    {
        $post = get_post($id);
        if ( $post && $post->post_type === $this->post_type ) {
            return $post;
        }
        return null;
    }

    /**
     * Prepare a single organizer output for response.
     *
     * @param WP_Post         $object  Object data.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_object_for_response($object, $request)
    {
        $context = 'view';
        if (
            is_user_logged_in() &&
            current_user_can('edit_posts') &&
            !empty($request['context'])
        ) {
            $context = sanitize_text_field($request['context']);
        }
        $data = $this->get_organizer_data($object, $context);

        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);
        $response = rest_ensure_response($data);
        if (is_user_logged_in()) {
            $response->add_links($this->prepare_links($object, $request));
        }

        return apply_filters("wpem_rest_prepare_{$this->post_type}_object", $response, $object, $request);
    }

    /**
     * Prepare objects query.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_objects_query($request)
    {
        $args = parent::prepare_objects_query($request);

        // Set post_status.
        $args['post_status'] = $request['status'];
        $args['post_type']   = $this->post_type;

        return $args;
    }

    /**
     * Get organizer data.
     *
     * @param WP_Post $organizer Organizer post object.
     * @param string  $context   Request context: 'view' or 'edit'.
     * @return array
     */
    protected function get_organizer_data($organizer, $context = 'view')
    {
        // Get description safely from post content.
        $description = $organizer->post_content;
        if ( 'view' === $context ) {
            $description = wpautop( do_shortcode( $description ) );
        }

        // Get the featured image safely.
        $image_src = '';
        $thumbnail_id = get_post_thumbnail_id( $organizer->ID );
        if ( $thumbnail_id ) {
            $image_data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
            if ( $image_data ) {
                $image_src = $image_data[0];
            }
        }

        $data = array(
            'id'            => $organizer->ID,
            'name'          => $organizer->post_title,
            'slug'          => $organizer->post_name,
            'permalink'     => get_permalink($organizer->ID),
            'date_created'  => get_the_date('', $organizer),
            'date_modified' => get_the_modified_date('', $organizer),
            'status'        => $organizer->post_status,
            'featured'      => (bool) get_post_meta($organizer->ID, '_featured', true),
            'description'   => $description,
            'image'         => $image_src,
            'meta_data'     => get_post_meta($organizer->ID),
        );

        return $data;
    }

    /**
     * Prepare a single organizer output for response (legacy method).
     *
     * @param  WP_Post         $post    Post object.
     * @param  WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_item_for_response($post, $request)
    {
        $organizer = get_post($post);
        $context   = 'view';
        if (
            is_user_logged_in() &&
            current_user_can('edit_posts') &&
            !empty($request['context'])
        ) {
            $context = sanitize_text_field($request['context']);
        }
        $data = $this->get_organizer_data($organizer, $context);
        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($organizer, $request));

        return apply_filters("wpem_rest_prepare_{$this->post_type}", $response, $post, $request);
    }

    /**
     * Prepare links for the request.
     *
     * @param WP_Post         $object  Object data.
     * @param WP_REST_Request $request Request object.
     * @return array Links for the given post.
     */
    protected function prepare_links($object, $request)
    {
        $links = array(
            'self' => array(
                'href' => rest_url(sprintf('/%s/%s/%d', $this->namespace, $this->rest_base, $object->ID)),
            ),
            'collection' => array(
                'href' => rest_url(sprintf('/%s/%s', $this->namespace, $this->rest_base)),
            ),
        );

        if ($object->post_parent) {
            $links['up'] = array(
                'href' => rest_url(sprintf('/%s/%s/%d', $this->namespace, $this->rest_base, $object->post_parent)),
            );
        }
        return $links;
    }

    /**
     * Prepare a single organizer for create or update.
     *
     * @param WP_REST_Request $request  Request object.
     * @param bool            $creating If is creating a new object.
     * @return WP_Error|WP_Post
     */
    protected function prepare_object_for_database($request, $creating = false)
    {
        $id = isset($request['id']) ? absint($request['id']) : 0;

        if ( $creating ) {
            $post_data = array(
                'post_title'   => isset($request['name'])        ? sanitize_text_field($request['name'])  : '',
                'post_content' => isset($request['description']) ? wp_kses_post($request['description'])  : '',
                'post_status'  => isset($request['status'])      ? sanitize_key($request['status'])        : 'publish',
                'post_type'    => $this->post_type,
                'post_author'  => wpem_rest_get_current_user_id(),
            );

            $inserted_id = wp_insert_post($post_data, true);
            if ( is_wp_error($inserted_id) ) {
                return $inserted_id;
            }
            $post = get_post($inserted_id);
        } else {
            $post = get_post($id);
            if ( ! $post || $post->post_type !== $this->post_type ) {
                return new WP_Error('wpem_rest_invalid_id', __('Invalid organizer ID.', 'wpem-rest-api'), array('status' => 404));
            }
            $post_data = array('ID' => $id);
            if ( isset($request['name']) )        $post_data['post_title']   = sanitize_text_field($request['name']);
            if ( isset($request['description']) ) $post_data['post_content'] = wp_kses_post($request['description']);
            if ( isset($request['status']) )      $post_data['post_status']  = sanitize_key($request['status']);
            wp_update_post($post_data);
            $post = get_post($id);
        }

        return apply_filters("wpem_rest_pre_insert_{$this->post_type}_object", $post, $request, $creating);
    }

    /**
     * Clear caches here so in sync with any new variations/children.
     *
     * @param WP_Post $object Object data.
     */
    public function clear_transients($object)
    {
        // call wpem clear transient here
    }

    /**
     * Delete a single item.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item($request)
    {
        $force  = (bool) $request['force'];
        $object = $this->get_object((int) $request['id']);

        if (!$object || 0 === $object->ID) {
            return parent::prepare_error_for_response(404);
        }

        if (!wpem_rest_api_check_post_permissions($this->post_type, 'delete', $object->ID)) {
            return new WP_Error(
                "wpem_rest_user_cannot_delete_{$this->post_type}",
                sprintf(__('Sorry, you are not allowed to delete %s.', 'wpem-rest-api'), $this->post_type),
                array('status' => rest_authorization_required_code())
            );
        }

        $request->set_param('context', 'edit');
        $response = $this->prepare_object_for_response($object, $request);

        if ($force) {
            wp_delete_post($object->ID, true);
        } else {
            if ($object->post_status === 'trash') {
                return parent::prepare_error_for_response(410);
            }
            wp_trash_post($object->ID);
        }

        do_action("wpem_rest_delete_{$this->post_type}_object", $object, $response, $request);

        return $response;
    }

    /**
     * Get the Organizer's schema, conforming to JSON Schema.
     *
     * @return array
     */
    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->post_type,
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'wpem-rest-api'),
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'name' => array(
                    'description' => __('Organizer name.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'slug' => array(
                    'description' => __('Organizer slug.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'permalink' => array(
                    'description' => __('Organizer URL.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'date_created' => array(
                    'description' => __("The date the organizer was created, in the site's timezone.", 'wpem-rest-api'),
                    'type'        => 'date-time',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'date_modified' => array(
                    'description' => __("The date the organizer was last modified, in the site's timezone.", 'wpem-rest-api'),
                    'type'        => 'date-time',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'status' => array(
                    'description' => __('Organizer status (post status).', 'wpem-rest-api'),
                    'type'        => 'string',
                    'default'     => 'publish',
                    'enum'        => array_merge(array_keys(get_post_statuses()), array('future')),
                    'context'     => array('view', 'edit'),
                ),
                'featured' => array(
                    'description' => __('Featured organizer.', 'wpem-rest-api'),
                    'type'        => 'boolean',
                    'default'     => false,
                    'context'     => array('view', 'edit'),
                ),
                'description' => array(
                    'description' => __('Organizer description.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                ),
                'image' => array(
                    'description' => __('Organizer featured image URL.', 'wpem-rest-api'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => array('view', 'edit'),
                ),
                'meta_data' => array(
                    'description' => __('Meta data.', 'wpem-rest-api'),
                    'type'        => 'array',
                    'context'     => array('view', 'edit'),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'key'   => array('type' => 'string', 'context' => array('view', 'edit')),
                            'value' => array('type' => 'string', 'context' => array('view', 'edit')),
                        ),
                    ),
                ),
            ),
        );
        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Get the query params for collections of organizers.
     * @return array
     */
    public function get_collection_params()
    {
        $params = parent::get_collection_params();

        $params['orderby']['enum'] = array_merge($params['orderby']['enum'], array('menu_order'));

        $params['slug'] = array(
            'description'       => __('Limit result set to organizers with a specific slug.', 'wpem-rest-api'),
            'type'              => 'string',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['status'] = array(
            'default'           => 'any',
            'description'       => __('Limit result set to organizers assigned a specific status.', 'wpem-rest-api'),
            'type'              => 'string',
            'enum'              => array_merge(array('any', 'future'), array_keys(get_post_statuses())),
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => 'rest_validate_request_arg',
        );

        return $params;
    }
}

new WPEM_REST_Organizers_Controller();