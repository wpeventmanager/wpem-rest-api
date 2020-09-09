<?php
/**
 * REST API Events controller
 *
 * Handles requests to the /events endpoint.
 *
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API Events controller class.
 *
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_Events_Controller extends WPEM_REST_CRUD_Controller {

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
	protected $rest_base = 'events';

	/**
	 * Post type.
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
	 * Initialize event actions.
	 */
	public function __construct() {
		add_action( "wpem_rest_insert_{$this->post_type}_object", array( $this, 'clear_transients' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}

	/**
	 * Register the routes for events.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'wp-event-manager-rest-api' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'default'     => false,
							'description' => __( 'Whether to bypass trash and force deletion.', 'wp-event-manager-rest-api' ),
							'type'        => 'boolean',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'batch_items' ),
					'permission_callback' => array( $this, 'batch_items_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_batch_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_event_fields' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				)
			)
		);
	}

	/**
	 * Get object.
	 *
	 * @param int $id Object ID.
	 *
	 * @since  3.0.0
	 * @return Post Data object
	 */
	protected function get_object( $id ) {
		return get_post( $id );
	}

	/**
	 * Prepare a single event output for response.
	 *
	 * @param Post         $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @since  3.0.0
	 * @return WP_REST_Response
	 */
	public function prepare_object_for_response( $object, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->get_event_data( $object, $context );

		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $object, $request ) );

		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type,
		 * refers to object type being prepared for the response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param Post Data          $object   Object data.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( "wpem_rest_prepare_{$this->post_type}_object", $response, $object, $request );
	}

	/**
	 * Prepare objects query.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	protected function prepare_objects_query( $request ) {
		$args = parent::prepare_objects_query( $request );

		// Set post_status.
		$args['post_status'] = $request['status'];

		// Taxonomy query to filter events by type, category,
		// tag
		$tax_query = array();

		// Map between taxonomy name and arg's key.
		$taxonomies = array(
			'event_listing_category'            => 'category',
			'event_listing_type'            => 'type'
		);

		// Set tax_query for each passed arg.
		foreach ( $taxonomies as $taxonomy => $key ) {
			if ( ! empty( $request[ $key ] ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $request[ $key ],
				);
			}
		}


		// Filter by term.
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; // WPCS: slow query ok.
		}

		$args['post_type'] = $this->post_type;

		return $args;
	}


	/**
	 * Get taxonomy terms.
	 *
	 * @param Post  $event  as post instance.
	 * @param string     $taxonomy Taxonomy slug.
	 *
	 * @return array
	 */
	protected function get_taxonomy_terms( $event, $taxonomy = 'event_listing_category' ) {
		$terms = array();

		foreach ( get_the_terms( $event->ID,  $taxonomy ) as $term ) {
			$terms[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $terms;
	}

	/**
	 * Get the images of an event.
	 *
	 * @param Post.
	 *
	 * @return array
	 */
	protected function get_images( $event ) {
		$images         = array();
		$attachment_ids = array();

		//get event banner here


		// Set a placeholder image if the event has no images set.
		if ( empty( $images ) ) {
			$images[] = array(
				'id'                => 0,
				'date_created'      => wpem_rest_prepare_date_response( current_time( 'mysql' ), false ), // Default to now.
				'date_created_gmt'  => wpem_rest_prepare_date_response( current_time( 'timestamp', true ) ), // Default to now.
				'date_modified'     => wpem_rest_prepare_date_response( current_time( 'mysql' ), false ),
				'date_modified_gmt' => wpem_rest_prepare_date_response( current_time( 'timestamp', true ) ),
				'src'               => '',
				'name'              => __( 'Placeholder', 'wp-event-manager-rest-api' ),
				'alt'               => __( 'Placeholder', 'wp-event-manager-rest-api' ),
				'position'          => 0,
			);
		}

		return $images;
	}


	/**
	 * Get event data.
	 *
	 * @param $post Event instance.
	 * @param string     $context Request context.
	 *                            Options: 'view' and 'edit'.
	 *
	 * @return array
	 */
	protected function get_event_data( $event, $context = 'view' ) {
		$data = array(
				'id'                    => $event->ID,
				'name'                  => $event->post_title,
				'slug'                  => $event->post_name,
				'permalink'             => get_permalink( $event->ID ),
				'date_created'          => get_the_date('',$event),
				'date_modified'         => get_the_modified_date( '', $event),
				'status'                => $event->post_status,
				'featured'              => $event->_featured,
				
				'description'           => 'view' === $context ? wpautop( do_shortcode( get_event_description($event) ) ) : get_event_description($event ),
				
				'event_categories'            => taxonomy_exists( 'event_listing_category' ) ? get_the_terms($event->ID,'event_listing_category') : ''   ,
				'event_types'            => taxonomy_exists( 'event_listing_type' ) ? get_the_terms($event->ID,'event_listing_type') : '',
				'event_tags'                  => taxonomy_exists( 'event_listing_tag' ) ? get_the_terms($event->ID,'event_listing_tag') : '',
				'images'                => get_event_banner( $event ),
				//'menu_order'            => '',
				'meta_data'             =>  get_post_meta($event->ID),
		);
		
		return apply_filters("wpem_rest_get_{$this->post_type}_data",$data, $event, $context);
	}
	
	/**
	 * Prepare a single event output for response.
	 *
	 * @param WP_Post         $post    Post object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ) {
		$event = get_post( $post );
		$data    = $this->get_event_data( $event );
		
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );
		
		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );
		
		$response->add_links( $this->prepare_links( $event, $request ) );
		
		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type, refers to post_type of the post being
		 * prepared for the response.
		 *
		 * @param WP_REST_Response   $response   The response object.
		 * @param WP_Post            $post       Post object.
		 * @param WP_REST_Request    $request    Request object.
		 */
		return apply_filters( "wpem_rest_prepare_{$this->post_type}", $response, $post, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param post         $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array                   Links for the given post.
	 */
	protected function prepare_links( $object, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $object->ID ) ),  // @codingStandardsIgnoreLine.
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),  // @codingStandardsIgnoreLine.
			),
		);

		if ( $object->post_parent  ) {
			$links['up'] = array(
				'href' => rest_url( sprintf( '/%s/events/%d', $this->namespace, $object->post_parent  ) ),  // @codingStandardsIgnoreLine.
			);
		}

		return $links;
	}

	/**
	 * Prepare a single event for create or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param bool            $creating If is creating a new object.
	 *
	 * @return WP_Error | Post
	 */
	protected function prepare_object_for_database( $request, $creating = false ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$event = get_post( $id );
		}
		else{
				if(!empty($request['event_title']) && isset($request['event_id'])  && $request['event_id'] == 0 ){
					$_POST = $request;

					//we are inserting new event means if there is any already created event cookies we need to remvoe it
					if(isset($_COOKIE['wp-event-manager-submitting-event-id']) )
					    unset( $_COOKIE['wp-event-manager-submitting-event-id'] );				
					if(isset($_COOKIE['wp-event-manager-submitting-event-key']) )
					    unset( $_COOKIE['wp-event-manager-submitting-event-key'] );


					$GLOBALS['event_manager']->forms->get_form( 'submit-event', array() );
					$form_submit_event_instance = call_user_func( array( 'WP_Event_Manager_Form_Submit_Event', 'instance' ) );
					$event_fields =	$form_submit_event_instance->merge_with_custom_fields('frontend');

					//submit current event with $_POST values
					$form_submit_event_instance ->submit_handler();
					/**
					* Preview step will move event status if approval required then  pending otherwise publish
					*/
					$form_submit_event_instance->preview_handler();

					//we don't need done status it will be managed by response of the current request
					if(!$form_submit_event_instance->get_event_id() ){

						$validation_errors =  $this->get_errors();
						foreach ( $validation_errors as $error ) {
							echo  $error;
						}
						return;
					}
					$event = get_post( $form_submit_event_instance->get_event_id());
				}
				else{
					return;
				}

		}

		/**
		 * Filters an object before it is inserted via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`,
		 * refers to the object type slug.
		 *
		 * @param Post         $event  Object object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating If is creating a new object.
		 */
		return apply_filters( "wpem_rest_pre_insert_{$this->post_type}_object", $event, $request, $creating );
	}

	/**
	 * Set event images.
	 *
	 * @param  $event Event instance.
	 * @param array      $images  Images data.
	 *
	 * @throws WC_REST_Exception REST API exceptions.
	 * @return $event object
	 */
	protected function set_event_images( $event, $images ) {
		$images = is_array( $images ) ? array_filter( $images ) : array();

		if ( ! empty( $images ) ) {
			$gallery_positions = array();

			foreach ( $images as $index => $image ) {
				$attachment_id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;

				if ( 0 === $attachment_id && isset( $image['src'] ) ) {
					$upload = wpem_rest_upload_image_from_url( esc_url_raw( $image['src'] ) );

					if ( is_wp_error( $upload ) ) {
						if ( ! apply_filters( 'wpem_rest_suppress_image_upload_error', false, $upload, $event->get_id(), $images ) ) {
							throw new Exception( 'wpem_event_image_upload_error', $upload->get_error_message(), 400 );
						} else {
							continue;
						}
					}

					$attachment_id = wpem_rest_set_uploaded_image_as_attachment( $upload, $event->ID );
				}

				if ( ! wp_attachment_is_image( $attachment_id ) ) {
					/* translators: %s: attachment id */
					throw new Exception( 'wpem_event_invalid_image_id', sprintf( __( '#%s is an invalid image ID.', 'wp-event-manager-rest-api' ), $attachment_id ), 400 );
				}

				$gallery_positions[ $attachment_id ] = absint( isset( $image['position'] ) ? $image['position'] : $index );

				// Set the image alt if present.
				if ( ! empty( $image['alt'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', wc_clean( $image['alt'] ) );
				}

				// Set the image name if present.
				if ( ! empty( $image['name'] ) ) {
					wp_update_post(
						array(
							'ID'         => $attachment_id,
							'post_title' => $image['name'],
						)
					);
				}

				// Set the image source if present, for future reference.
				if ( ! empty( $image['src'] ) ) {
					update_post_meta( $attachment_id, '_wpem_attachment_source', esc_url_raw( $image['src'] ) );
				}
			}

			// Sort images and get IDs in correct order.
			asort( $gallery_positions );

			// Get gallery in correct order.
			$gallery = array_keys( $gallery_positions );

			// Featured image is in position 0.
			$image_id = array_shift( $gallery );

			// Set images.
			$event->set_image_id( $image_id );
			$event->set_gallery_image_ids( $gallery );
		} else {
			$event->set_image_id( '' );
			$event->set_gallery_image_ids( array() );
		}

		return $event;
	}


	/**
	 * Save taxonomy terms.
	 *
	 * @param Event $event instance.
	 * @param array      $terms    Terms data.
	 * @param string     $taxonomy Taxonomy name.
	 *
	 * @return Event object
	 */
	protected function save_taxonomy_terms( $event, $terms, $taxonomy = 'cat' ) {
		$term_ids = wp_list_pluck( $terms, 'id' );

		if ( 'event_listing_category' === $taxonomy ) {
			$event->set_category_ids( $term_ids );
		} elseif ( 'event_listing_type' === $taxonomy ) {
			$event->set_type_ids( $term_ids );
		} elseif ( 'tag' === $taxonomy ) {
			$event->set_tag_ids( $term_ids );
		}

		return $event;
	}

	

	/**
	 * Clear caches here so in sync with any new variations/children.
	 *
	 * @param WC_Data $object Object data.
	 */
	public function clear_transients( $object ) {
		//call wpem clear transient here
	}

	/**
	 * Delete a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id     = (int) $request['id'];
		$force  = (bool) $request['force'];
		$object = $this->get_object( (int) $request['id'] );
		$result = false;

		if ( ! $object || 0 === $object->ID ) {
			return new WP_Error(
				"wpem_rest_{$this->post_type}_invalid_id",
				__( 'Invalid ID.', 'wp-event-manager-rest-api' ),
				array(
					'status' => 404,
				)
			);
		}

		

		$supports_trash = EMPTY_TRASH_DAYS > 0 && is_callable( array( $object, 'get_status' ) );

		/**
		 * Filter whether an object is trashable.
		 *
		 * Return false to disable trash support for the object.
		 *
		 * @param boolean $supports_trash Whether the object type support trashing.
		 * @param WC_Data $object         The object being considered for trashing support.
		 */
		$supports_trash = apply_filters( "wpem_rest_{$this->post_type}_object_trashable", $supports_trash, $object );

		if ( ! wpem_rest_check_post_permissions( $this->post_type, 'delete', $object->ID ) ) {
			return new WP_Error(
				"wpem_rest_user_cannot_delete_{$this->post_type}",
				/* translators: %s: post type */
				sprintf( __( 'Sorry, you are not allowed to delete %s.', 'wp-event-manager-rest-api' ), $this->post_type ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_object_for_response( $object, $request );

		// If we're forcing, then delete permanently.
		if ( $force ) {	

			wp_delete_post( $object->ID, true );
			//$result = 0 === $object->ID;
			$result = 1;
		} else {
			// If we don't support trashing for this type, error out.
			if ( ! $supports_trash ) {
				return new WP_Error(
					'wpem_rest_trash_not_supported',
					/* translators: %s: post type */
					sprintf( __( 'The %s does not support trashing.', 'wp-event-manager-rest-api' ), $this->post_type ),
					array(
						'status' => 501,
					)
				);
			}

			// Otherwise, only trash if we haven't already.
			if ( is_callable( array( $object, 'get_status' ) ) ) {
				if ( 'trash' === $object->get_status() ) {
					return new WP_Error(
						'wpem_rest_already_trashed',
						/* translators: %s: post type */
						sprintf( __( 'The %s has already been deleted.', 'wp-event-manager-rest-api' ), $this->post_type ),
						array(
							'status' => 410,
						)
					);
				}

				wp_delete_post( $object->ID );
				$result = 'trash' === $object->get_status();
			}
		}

		if ( ! $result ) {
			return new WP_Error(
				'wpem_rest_cannot_delete',
				/* translators: %s: post type */
				sprintf( __( 'The %s cannot be deleted.', 'wp-event-manager-rest-api' ), $this->post_type ),
				array(
					'status' => 500,
				)
			);
		}

		/*
		// Delete parent event transients.
		if ( 0 !== $object->get_parent_id() ) {
			wpem_delete_event_transients( $object->get_parent_id() );
		}
		*/

		/**
		 * Fires after a single object is deleted or trashed via the REST API.
		 *
		 * @param Post          $object   The deleted or trashed object.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( "wpem_rest_delete_{$this->post_type}_object", $object, $response, $request );

		return $response;
	}

	/**
	 * Get the Event's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$weight_unit    = get_option( 'wpem_weight_unit' );
		$dimension_unit = get_option( 'wpem_dimension_unit' );
		$schema         = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			'properties' => array(
				'id'                    => array(
					'description' => __( 'Unique identifier for the resource.', 'wp-event-manager-rest-api' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'                  => array(
					'description' => __( 'Event name.', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'slug'                  => array(
					'description' => __( 'event slug.', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'permalink'             => array(
					'description' => __( 'event URL.', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_created'          => array(
					'description' => __( "The date the event was created, in the site's timezone.", 'wp-event-manager-rest-api' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_created_gmt'      => array(
					'description' => __( 'The date the event was created, as GMT.', 'wp-event-manager-rest-api' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_modified'         => array(
					'description' => __( "The date the event was last modified, in the site's timezone.", 'wp-event-manager-rest-api' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_modified_gmt'     => array(
					'description' => __( 'The date the event was last modified, as GMT.', 'wp-event-manager-rest-api' ),
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			
				'status'                => array(
					'description' => __( 'Event status (post status).', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'default'     => 'publish',
					'enum'        => array_merge( array_keys( get_post_statuses() ), array( 'future' ,'expired') ),
					'context'     => array( 'view', 'edit' ),
				),
				'featured'              => array(
					'description' => __( 'Featured event.', 'wp-event-manager-rest-api' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'description'           => array(
					'description' => __( 'Event description.', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'short_description'     => array(
					'description' => __( 'Event short description.', 'wp-event-manager-rest-api' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				
				'categories'            => array(
					'description' => __( 'List of categories.', 'wp-event-manager-rest-api' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array(
								'description' => __( 'Category ID.', 'wp-event-manager-rest-api' ),
								'type'        => 'integer',
								'context'     => array( 'view', 'edit' ),
							),
							'name' => array(
								'description' => __( 'Category name.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'slug' => array(
								'description' => __( 'Category slug.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
						),
					),
				),
				'tags'                  => array(
					'description' => __( 'List of tags.', 'wp-event-manager-rest-api' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array(
								'description' => __( 'Tag ID.', 'wp-event-manager-rest-api' ),
								'type'        => 'integer',
								'context'     => array( 'view', 'edit' ),
							),
							'name' => array(
								'description' => __( 'Tag name.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'slug' => array(
								'description' => __( 'Tag slug.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
						),
					),
				),
				'images'                => array(
					'description' => __( 'List of images.', 'wp-event-manager-rest-api' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'                => array(
								'description' => __( 'Image ID.', 'wp-event-manager-rest-api' ),
								'type'        => 'integer',
								'context'     => array( 'view', 'edit' ),
							),
							'date_created'      => array(
								'description' => __( "The date the image was created, in the site's timezone.", 'wp-event-manager-rest-api' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_created_gmt'  => array(
								'description' => __( 'The date the image was created, as GMT.', 'wp-event-manager-rest-api' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_modified'     => array(
								'description' => __( "The date the image was last modified, in the site's timezone.", 'wp-event-manager-rest-api' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'date_modified_gmt' => array(
								'description' => __( 'The date the image was last modified, as GMT.', 'wp-event-manager-rest-api' ),
								'type'        => 'date-time',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'src'               => array(
								'description' => __( 'Image URL.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'format'      => 'uri',
								'context'     => array( 'view', 'edit' ),
							),
							'name'              => array(
								'description' => __( 'Image name.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
							'alt'               => array(
								'description' => __( 'Image alternative text.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
							'position'          => array(
								'description' => __( 'Image position. 0 means that the image is featured.', 'wp-event-manager-rest-api' ),
								'type'        => 'integer',
								'context'     => array( 'view', 'edit' ),
							),
						),
					),
				),
				
				'meta_data'             => array(
					'description' => __( 'Meta data.', 'wp-event-manager-rest-api' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array(
								'description' => __( 'Meta ID.', 'wp-event-manager-rest-api' ),
								'type'        => 'integer',
								'context'     => array( 'view', 'edit' ),
								'readonly'    => true,
							),
							'key'   => array(
								'description' => __( 'Meta key.', 'wp-event-manager-rest-api' ),
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
							'value' => array(
								'description' => __( 'Meta value.', 'wp-event-manager-rest-api' ),
								'type'        => 'mixed',
								'context'     => array( 'view', 'edit' ),
							),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections of attachments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['orderby']['enum'] = array_merge( $params['orderby']['enum'], array( 'menu_order' ) );

		$params['slug']           = array(
			'description'       => __( 'Limit result set to events with a specific slug.', 'wp-event-manager-rest-api' ),
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['status']         = array(
			'default'           => 'any',
			'description'       => __( 'Limit result set to events assigned a specific status.', 'wp-event-manager-rest-api' ),
			'type'              => 'string',
			'enum'              => array_merge( array( 'any', 'future','expired' ), array_keys( get_post_statuses() ) ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
	
		$params['featured']       = array(
			'description'       => __( 'Limit result set to featured events.', 'wp-event-manager-rest-api' ),
			'type'              => 'boolean',
			'sanitize_callback' => 'wc_string_to_bool',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['category']       = array(
			'description'       => __( 'Limit result set to events assigned a specific category ID.', 'wp-event-manager-rest-api' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['tag']            = array(
			'description'       => __( 'Limit result set to events assigned a specific tag ID.', 'wp-event-manager-rest-api' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		

		return $params;
	}
	
	/**
	 * Get the query params and check if user has the event permission.
	 *
	 * @return array
	 */
	public function check_event_permissions($request){
		
	}

	/**
	 * Get the query params and return event fields
	 *
	 * @return array
	 */
	public function get_event_fields($request){

		if(!class_exists('WP_Event_Manager_Form_Submit_Event') ) {
			include_once( EVENT_MANAGER_PLUGIN_DIR . '/forms/wp-event-manager-form-abstract.php' );
			include_once( EVENT_MANAGER_PLUGIN_DIR . '/forms/wp-event-manager-form-submit-event.php' );	
		}
		$form_submit_event_instance = call_user_func( array( 'WP_Event_Manager_Form_Submit_Event', 'instance' ) );
		$fields = $form_submit_event_instance->merge_with_custom_fields('frontend');

		$event_fields = [];

		foreach ($fields as $group_key => $group_fields) 
		{
			foreach ($group_fields as $key => $field) 
			{
				if($field['type'] === 'term-select')
				{
					$field['options'] = $this->get_event_texonomy($field['taxonomy']);
				}
				
				$event_fields[$group_key][$key] = $field;
			}	
		}

		return $event_fields;
	}
	
	/**
	 * Get the query params and return event fields
	 *
	 * @return array
	 */
	public function get_event_texonomy($texonomy = ''){
		$terms = get_terms( array(
		    'taxonomy' => $texonomy,
		    'hide_empty' => false,
		) );

		$data = [];

		if(!empty($terms))
		{
			foreach ($terms as $term) {
				$data[$term->term_id] = $term->name;
			}
		}

		return $data;
	}
}


new WPEM_REST_Events_Controller();
