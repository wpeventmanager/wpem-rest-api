<?php
/**
 * Abstract Rest CRUD Controller Class
 *
 * @class   WPEM_REST_CRUD_Controller
 * @version 1.0.0
 */
if( !defined('ABSPATH') ) {
    exit;
}

/**
 * WPEM_REST_CRUD_Controller class.
 *
 * @extends WPEM_REST_Posts_Controller
 */
abstract class WPEM_REST_CRUD_Controller extends WPEM_REST_Posts_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * If object is hierarchical.
     *
     * @var bool
     */
    protected $hierarchical = false;

    /**
     * Get object.
     *
     * @param  int $id Object ID.
     * @return object Post Data object or WP_Error object.
     */
    protected function get_object( $id ) {
        // translators: %s: Class method name.
        return self::prepare_error_for_response(405);
    }

    /**
     * Check if a given request has access to read an item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_item_permissions_check( $request ) {
        $object = $this->get_object( (int) $request['id'] );
		if( !is_wp_error( $object ) && $object ) {
			$object_id = $object->ID;
			if( $object->post_type === 'product' ) {
				$object_id = $object->get_id();
			}

			if( 0 == $object_id && !wpem_rest_api_check_post_permissions( $this->post_type, 'read', $object_id ) ) {
                return self::prepare_error_for_response(203);
			}
			return true;
		} else {
			// pass actual error to response
			return $object;
		}
    }

    /**
     * Check if a given request has access to update an item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function update_item_permissions_check( $request ) {
        $object = $this->get_object( (int) $request['id'] );
	    if( !is_wp_error( $object ) && $object ) {
	        if( $object && 0 !== $object->ID && ! wpem_rest_api_check_post_permissions( $this->post_type, 'edit', $object->ID ) ) {
                return self::prepare_error_for_response(504);
	        }
	        return true;
        } else {
			// pass actual error to response
		    return $object;
        }
    }

    /**
     * Check if a given request has access to delete an item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        $object = $this->get_object( (int) $request['id'] );
	    if( !is_wp_error( $object ) && $object ) {
		    if( $object && 0 !== $object->ID && ! wpem_rest_api_check_post_permissions( $this->post_type, 'delete', $object->ID ) ) {
                return self::prepare_error_for_response(412);
		    }
		    return true;
	    } else {
		    // pass actual error to response
		    return $object;
	    }
    }

    /**
     * Get object permalink.
     *
     * @param  object $object Object.
     * @return string
     */
    protected function get_permalink( $object ) {
        return '';
    }

    /**
     * Prepares the object for the REST response.
     *
     * @since  3.0.0
     * @param  Post data       $object  Object data.
     * @param  WP_REST_Request $request Request object.
     * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
     */
    protected function prepare_object_for_response( $object, $request ) {
        // translators: %s: Class method name.
        return self::prepare_error_for_response(405);
    }

    /**
     * Prepares one object for create or update operation.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request  Request object.
     * @param  bool            $creating If is creating a new object.
     * @return WP_Error|Post Data The prepared item, or WP_Error object on failure.
     */
    protected function prepare_object_for_database( $request, $creating = false ) {
        // translators: %s: Class method name.
        return self::prepare_error_for_response(405);
    }

    /**
     * Get a single item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_item( $request ) {
        $object = $this->get_object( (int) $request['id'] );
	    if( !$object || 0 === $object->ID ) {
            return parent::prepare_error_for_response(404);
        }

        $data     = $this->prepare_object_for_response($object, $request);
        $response = rest_ensure_response($data);

        if( $this->public ) {
            $response->link_header( 'alternate', $this->get_permalink( $object ), array( 'type' => 'text/html' ) );
        }

        $response_data = self::prepare_error_for_response( 200 );
        $response_data['data'] = array(
            $this->rest_base => $response,
        );
        return wp_send_json($response_data);
    }

    /**
     * Save an object data.
     *
     * @since  3.0.0
     * @param  WP_REST_Request $request  Full details about the request.
     * @param  bool            $creating If is creating a new object.
     * @return Post Data|WP_Error
     */
    protected function save_object( $request, $creating = false ) {
        try {
            $object = $this->prepare_object_for_database( $request, $creating );

            if( is_wp_error( $object ) ) {
                return $object;
            }
            return $this->get_object( $object->ID );
        } catch( Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ));
        }
    }

    /**
     * Create a single item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function create_item( $request ) {
        if( !empty( $request['id'] ) ) {
            /* translators: %s: post type */
            return parent::prepare_error_for_response(400);
        }

        $object = $this->save_object($request, true);

        if( is_wp_error( $object ) ) {
            return $object;
        }

        try {
            $this->update_additional_fields_for_object( $object, $request );
            /**
             * Fires after a single object is created or updated via the REST API.
             *
             * @param WP_REST_Request $request   Request object.
             * @param boolean         $creating  True when creating object, false when updating.
             */
            do_action( "wpem_rest_insert_{$this->post_type}_object", $object, $request, true );
        } catch( Exception $e ) {
            wp_delete_post( $object->ID );
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }

        $request->set_param( 'context', 'edit' );
        $response = $this->prepare_object_for_response( $object, $request );
        $response = rest_ensure_response( $response );
        $response->set_status( 201 );
        $response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $object->ID ) ) );

        return $response;
    }

    /**
     * Update a single post.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function update_item( $request ) {
        $object = $this->get_object( (int) $request['id'] );

        if( !$object || 0 === $object->ID ) {
            return parent::prepare_error_for_response(400);
        }

        $object = $this->save_object($request, false);

        if( is_wp_error( $object ) ) {
            return $object;
        }

        try {
            $this->update_additional_fields_for_object( $object, $request );
            /**
             * Fires after a single object is created or updated via the REST API.
             *
             * @param Post Data         $object    Inserted object.
             * @param WP_REST_Request $request   Request object.
             * @param boolean         $creating  True when creating object, false when updating.
             */
            do_action("wpem_rest_insert_{$this->post_type}_object", $object, $request, false );
        } catch ( Exception $e ) {
            return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
        }

        $request->set_param( 'context', 'edit' );
        $response = $this->prepare_object_for_response( $object, $request );
        return rest_ensure_response( $response );
    }

    /**
     * Prepare objects query.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_objects_query( $request ) {
        $args                        = array();
        $args['offset']              = $request['offset'];
        $args['order']               = $request['order'];
        $args['orderby']             = $request['orderby'];
        $args['paged']               = $request['page'];
        $args['author']              = $request['author'];
        $args['post__in']            = $request['include'];
        $args['post__not_in']        = $request['exclude'];
        $args['posts_per_page']      = $request['per_page'];
        $args['name']                = $request['slug'];
        $args['post_parent__in']     = $request['parent'];
        $args['post_parent__not_in'] = $request['parent_exclude'];
        $args['s']                   = $request['search'];

        if( 'date' === $args['orderby'] ) {
            $args['orderby'] = 'date ID';
        }

        $args['date_query'] = array();
        // Set before into date query. Date query must be specified as an array of an array.
        if( isset( $request['before'] ) ) {
            $args['date_query'][0]['before'] = $request['before'];
        }

        // Set after into date query. Date query must be specified as an array of an array.
        if( isset( $request['after'] ) ) {
            $args['date_query'][0]['after'] = $request['after'];
        }

        // Force the post_type argument, since it's not a user input variable.
        $args['post_type'] = $this->post_type;

        /**
         * Filter the query arguments for a request.
         *
         * Enables adding extra arguments or setting defaults for a post
         * collection request.
         *
         * @param array           $args    Key value array of query var to query value.
         * @param WP_REST_Request $request The request used.
         */
        $args = apply_filters( "wpem_rest_{$this->post_type}_object_query", $args, $request );

        return $this->prepare_items_query( $args, $request );
    }

    /**
     * Determine the allowed query_vars for a get_items() response and
     * prepare for WP_Query.
     *
     * @param  array           $prepared_args Prepared arguments.
     * @param  WP_REST_Request $request       Request object.
     * @return array          $query_args
     */
    protected function prepare_items_query( $prepared_args = array(), $request = null ) {

        $valid_vars = array_flip( $this->get_allowed_query_vars() );
        $query_args = array();
        foreach ( $valid_vars as $var => $index ) {
            if( isset( $prepared_args[ $var ] ) ) {
                /**
                 * Filter the query_vars used in `get_items` for the constructed query.
                 *
                 * The dynamic portion of the hook name, $var, refers to the query_var key.
                 *
                 * @param mixed $prepared_args[ $var ] The query_var value.
                 */
                $query_args[ $var ] = apply_filters( "wpem_rest_query_var-{$var}", $prepared_args[ $var ] );
            }
        }
        $query_args['ignore_sticky_posts'] = true;

        if( 'include' === $query_args['orderby'] ) {
            $query_args['orderby'] = 'post__in';
        } elseif ( 'id' === $query_args['orderby'] ) {
            $query_args['orderby'] = 'ID'; // ID must be capitalized.
        } elseif ( 'slug' === $query_args['orderby'] ) {
            $query_args['orderby'] = 'name';
        }
        return $query_args;
    }

    /**
     * Get objects.
     *
     * @since  3.0.0
     * @param  array $query_args Query args.
     * @return array
     */
    protected function get_objects( $query_args ) {
        $query  = new WP_Query();
        $result = $query->query( $query_args );

        $total_posts = $query->found_posts;
        if( $total_posts < 1 ) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset( $query_args['paged'] );
            $count_query = new WP_Query();
            $count_query->query( $query_args );
            $total_posts = $count_query->found_posts;
        }

        return array(
            'objects' => array_filter( array_map( array( $this, 'get_object' ), $result ) ),
            'total'   => (int) $total_posts,
            'pages'   => (int) ceil( $total_posts / (int) $query->query_vars['posts_per_page'] ),
        );
    }

    /**
     * Get a collection of posts.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items( $request ) {
        $auth_check = $this->wpem_check_authorized_user();
        if($auth_check){
            return self::prepare_error_for_response(405);
        } else {
            $query_args    = $this->prepare_objects_query( $request );
            $query_results = $this->get_objects( $query_args );

            $objects = array();
            foreach ( $query_results['objects'] as $object ) {

                if( !isset( $object->ID ) ) {
                    $object_id = $object->get_id();
                } else {
                    $object_id = $object->ID;
                }

                if( !wpem_rest_api_check_post_permissions( $this->post_type, 'read', $object_id ) ) {
                    continue;
                }

                $data = $this->prepare_object_for_response( $object, $request );
                $objects[] = $this->prepare_response_for_collection( $data );
            }

            $page = isset($query_args['paged']) ? (int) $query_args['paged']: 1;
                
            $total_pages = ceil($query_results['total'] / $query_args['posts_per_page']);
            $response_data = self::prepare_error_for_response( 200 );
            $response_data['data'] = array(
                'total_post_count' => isset($query_results['total']) ? $query_results['total'] : null,
                'current_page' => $page,
                'last_page' => max(1, $total_pages),
                'total_pages' => $total_pages,
                $this->rest_base => $objects,
            );
            return wp_send_json($response_data);
        }
    }

    /**
     * Delete a single item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error|Array
     */
    public function delete_item( $request ) {
        $force  = isset( $request["force"] ) && (bool) $request['force'];

        $object = $this->get_object( (int) $request['id']);
        $result = false;

        if( !$object || 0 === $object->ID ) {
            return parent::prepare_error_for_response(404);
        }

        $supports_trash = EMPTY_TRASH_DAYS > 0;

        /**
         * Filter whether an object is trashable.
         *
         * Return false to disable trash support for the object.
         *
         * @param boolean $supports_trash Whether the object type support trashing.
         * @param Post Data $object         The object being considered for trashing support.
         */
        $supports_trash = apply_filters( "wpem_rest_{$this->post_type}_object_trashable", $supports_trash, $object );

        if( !wpem_rest_api_check_post_permissions( $this->post_type, 'delete', $object->ID ) ) {
			return parent::prepare_error_for_response(412);
        }

        $request->set_param( 'context', 'edit' );
        $response = $this->prepare_object_for_response( $object, $request );

        // If we're forcing, then delete permanently.
        if( $force ) {
            wp_delete_post( $object->ID, true );
            //$result = 0 === $object->ID;
            $result = 1;
        } else {
            // If we don't support trashing for this type, error out.
            if( !$supports_trash ) {
			    return parent::prepare_error_for_response(412);
            } else {
	            if ($object->post_status === 'trash') {
                    return self::prepare_error_for_response(410);
                }
	            wp_trash_post($object->ID);
	            $result = 1;
            }
        }


        if( !$result ) {
            return parent::prepare_error_for_response(500);
        }

        /**
         * Fires after a single object is deleted or trashed via the REST API.
         *
         * @param Post Data          $object   The deleted or trashed object.
         * @param WP_REST_Response $response The response data.
         * @param WP_REST_Request  $request  The request sent to the API.
         */
        do_action( "wpem_rest_delete_{$this->post_type}_object", $object, $response, $request );
        return parent::prepare_error_for_response(200);
    }

    /**
     * Prepare links for the request.
     *
     * @param  Post Data       $object  Object data.
     * @param  WP_REST_Request $request Request object.
     * @return array                   Links for the given post.
     */
    protected function prepare_links( $object, $request ) {
        $links = array(
            'self' => array(
                'href' => rest_url( sprintf('/%s/%s/%d', $this->namespace, $this->rest_base, $object->ID ) ),
            ),
            'collection' => array(
                'href' => rest_url( sprintf('/%s/%s', $this->namespace, $this->rest_base ) ),
            ),
        );

        return $links;
    }

    /**
     * Get the query params for collections of attachments.
     *
     * @return array
     */
    public function get_collection_params() {
        $params                       = array();
        $params['context']            = $this->get_context_param();
        $params['context']['default'] = 'view';

        $params['page'] = array(
            'description'        => __( 'Current page of the collection.', 'wpem-rest-api' ),
            'type'               => 'integer',
            'default'            => 1,
            'sanitize_callback'  => 'absint',
            'validate_callback'  => 'rest_validate_request_arg',
            'minimum'            => 1,
        );
        $params['per_page'] = array(
            'description'        => __( 'Maximum number of items to be returned in result set.', 'wpem-rest-api' ),
            'type'               => 'integer',
            'default'            => 10,
            'minimum'            => 1,
            'maximum'            => 100,
            'sanitize_callback'  => 'absint',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['search'] = array(
            'description'        => __( 'Limit results to those matching a string.', 'wpem-rest-api' ),
            'type'               => 'string',
            'sanitize_callback'  => 'sanitize_text_field',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['after'] = array(
            'description'        => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'wpem-rest-api' ),
            'type'               => 'string',
            'format'             => 'date-time',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['before'] = array(
            'description'        => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'wpem-rest-api' ),
            'type'               => 'string',
            'format'             => 'date-time',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['exclude'] = array(
            'description'       => __( 'Ensure result set excludes specific IDs.', 'wpem-rest-api' ),
            'type'              => 'array',
            'items'             => array(
                'type'          => 'integer',
             ),
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );
        $params['include'] = array(
            'description'       => __( 'Limit result set to specific ids.', 'wpem-rest-api' ),
            'type'              => 'array',
            'items'             => array(
                'type'          => 'integer',
            ),
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );
        $params['offset'] = array(
            'description'        => __( 'Offset the result set by a specific number of items.', 'wpem-rest-api' ),
            'type'               => 'integer',
            'sanitize_callback'  => 'absint',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['order'] = array(
            'description'        => __( 'Order sort attribute ascending or descending.', 'wpem-rest-api' ),
            'type'               => 'string',
            'default'            => 'desc',
            'enum'               => array( 'asc', 'desc' ),
            'validate_callback'  => 'rest_validate_request_arg',
        );
        $params['orderby'] = array(
            'description'        => __( 'Sort collection by object attribute.', 'wpem-rest-api' ),
            'type'               => 'string',
            'default'            => 'date',
            'enum'               => array(
                'date',
                'id',
                'include',
                'title',
                'slug',
            ),
            'validate_callback'  => 'rest_validate_request_arg',
        );

        if ($this->hierarchical ) {
            $params['parent'] = array(
                'description'       => __( 'Limit result set to those of particular parent IDs.', 'wpem-rest-api' ),
                'type'              => 'array',
                'items'             => array(
                    'type'          => 'integer',
                ),
                'sanitize_callback' => 'wp_parse_id_list',
                'default'           => array(),
            );
            $params['parent_exclude'] = array(
                'description'       => __( 'Limit result set to all items except those of a particular parent ID.', 'wpem-rest-api' ),
                'type'              => 'array',
                'items'             => array(
                    'type'          => 'integer',
                ),
                'sanitize_callback' => 'wp_parse_id_list',
                'default'           => array(),
            );
        }

        /**
         * Filter collection parameters for the posts controller.
         *
         * The dynamic part of the filter `$this->post_type` refers to the post
         * type slug for the controller.
         *
         * This filter registers the collection parameter, but does not map the
         * collection parameter to an internal WP_Query parameter. Use the
         * `rest_{$this->post_type}_query` filter to set WP_Query parameters.
         *
         * @param array        $query_params JSON Schema-formatted collection parameters.
         * @param WP_Post_Type $post_type    Post type object.
         */
        return apply_filters( "rest_{$this->post_type}_collection_params", $params, $this->post_type );
    }

    /**
     * Function to check authorization and return user data
     * @since 1.0.1
     */
    public function wpem_check_authorized_user() {
        // Get the authorization header
        global $wpdb;
        $headers = getallheaders();
        $token = '';

        // First try standard header
        if (isset($headers['Authorization'])) {
            $token = trim(str_replace('Bearer', '', $headers['Authorization']));
        } 
        // Try for some server environments
        elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = trim(str_replace('Bearer', '', $_SERVER['HTTP_AUTHORIZATION']));
        }
        // NGINX or fastcgi_pass may use this
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $token = trim(str_replace('Bearer', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        }
        if(empty($token)) {
            return WPEM_REST_CRUD_Controller::prepare_error_for_response(405);
        }

        $user_data = self::wpem_validate_jwt_token($token);
        if (!$user_data) {
            return self::prepare_error_for_response(405);
        }
        $user = get_userdata($user_data['id']);

        if($user){ 
            $user_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpem_rest_api_keys WHERE user_id = $user->ID "));
            if($user_info){ 
                $date_expires = date('Y-m-d', strtotime($user_info->date_expires));
                if( $user_info->permissions == 'write'){
                    return self::prepare_error_for_response(203);
                } else if( $date_expires < date('Y-m-d') ){
                    return self::prepare_error_for_response(503);
                } else {
                    return false;
                }
            } else {
                return self::prepare_error_for_response(405);
            }
        } else {
            return self::prepare_error_for_response(405);
        }
    }
    
    /**
     * This function is used to verify token sent in api header
     * @since 1.0.9
     */
    public static function wpem_validate_jwt_token($token) {
        // Extract the JWT parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($encodedHeader, $encodedPayload, $encodedSignature) = $parts;
    
        $payload_json = wpem_base64url_decode($encodedPayload);
        $payload = json_decode($payload_json, true);
    
        // Re-generate signature
        $signature = hash_hmac('sha256', "$encodedHeader.$encodedPayload", JWT_SECRET_KEY, true);
        $expected_signature = wpem_base64url_encode(hash_hmac('sha256', "$encodedHeader.$encodedPayload", JWT_SECRET_KEY, true));

        if ($expected_signature === $encodedSignature) {
            $payload = json_decode(wpem_base64url_decode($encodedPayload), true);
            return $payload['user'];
        }
        return false;
    }

    /**
     * Prepares the error for the REST response.
     *
     * @since  1.0.1
     * @param  Post data       $object  Object data.
     * @param  WP_REST_Request $request Request object.
     * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
     */
    public static function prepare_error_for_response( $code, $data = array()) {
        $error_info = wpem_response_default_status();

        // Filter the array to find the element where 'code' matches the provided $code
        $filtered_error = array_filter($error_info, function($error) use ($code) {
            return (string) $error['code'] === (string) $code;
        });

        // If $filtered_error is not empty, return the first matched array
        if (!empty($filtered_error)) {
            $error = array_shift($filtered_error);  // Get the first matched array
            if( $code == 200 )
                return $error;
            else
                if (!empty($data)){
                    $error['data'] = $data;
                }
                return wp_send_json($error);
        } else {
            return null;  // Or handle the case where code 400 is not found
        }
    }
    
}
