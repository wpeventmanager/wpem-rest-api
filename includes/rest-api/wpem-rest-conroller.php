<?php
/**
 * REST Controller
 *
 * This class extend `WP_REST_Controller` in order to include /batch endpoint
 * for almost all endpoints in WP Event Manager REST API.
 *
 * It's required to follow "Controller Classes" guide before extending this class:
 * <https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/>
 *
 * NOTE THAT ONLY CODE RELEVANT FOR MOST ENDPOINTS SHOULD BE INCLUDED INTO THIS CLASS.
 * If necessary extend this class and create new abstract classes like `WP_REST_CRUD_Controller`.
 *
 * @class WPEM_REST_Controller
 * @see   https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 */
if( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract Rest Controller Class
 *
 * @extends WP_REST_Controller
 * @version 1.0.0
 */
abstract class WPEM_REST_Controller extends WP_REST_Controller {

    /**
     * Endpoint namespace.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $namespace = 'wpem/';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = '';

    /**
     * Add the schema from additional fields to an schema array.
     *
     * The type of object is inferred from the passed schema.
     *
     * @since 1.0.0
     * @param array $schema Schema array.
     *
     * @return array
     */
    protected function add_additional_fields_schema( $schema ) {
        if( empty( $schema['title']) ) {
            return $schema;
        }

        /**
         * Can't use $this->get_object_type otherwise we cause an inf loop.
         */
        $object_type = $schema['title'];
        $additional_fields = $this->get_additional_fields( $object_type );
        foreach ( $additional_fields as $field_name => $field_options ) {
            if (! $field_options['schema'] ) {
                continue;
            }
            $schema['properties'][ $field_name ] = $field_options['schema'];
        }
        $schema['properties'] = apply_filters( 'wpem_rest_' . $object_type . '_schema', $schema['properties'] );
        return $schema;
    }

    /**
     * Get normalized rest base.
     *
     * @since  1.0.0
     * @return string
     */
    protected function get_normalized_rest_base() {
        return preg_replace('/\(.*\)\//i', '', $this->rest_base);
    }

    /**
     * Check batch limit.
     *
     * @since  1.0.0
     * @param  array $items Request items.
     * @return bool|WP_Error
     */
    protected function check_batch_limit( $items ) {
        $limit = apply_filters( 'wpem_rest_batch_items_limit', 100, $this->get_normalized_rest_base() );
        $total = 0;

        if( !empty( $items['create'] ) ) {
            $total += count( $items['create'] );
        }

        if( !empty($items['update'] ) ) {
            $total += count( $items['update'] );
        }

        if( !empty( $items['delete']) ) {
            $total += count( $items['delete'] );
        }

        if( $total > $limit ) {
            return parent::prepare_error_for_response(413);
        }
        return true;
    }

    /**
     * Bulk create, update and delete items.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return array Of WP_Error or WP_REST_Response.
     */
    public function batch_items( $request ) {
        /**
         * REST Server
         *
         * @var WP_REST_Server $wp_rest_server
         */
        global $wp_rest_server;

        // Get the request params.
        $items    = array_filter( $request->get_params() );
        $query    = $request->get_query_params();
        $response = array();

        // Check batch limit.
        $limit = $this->check_batch_limit( $items );
        if( is_wp_error( $limit ) ) {
            return $limit;
        }

        if( !empty( $items['create'] ) ) {
            foreach( $items['create'] as $item ) {
                $_item = new WP_REST_Request('POST');

                // Default parameters.
                $defaults = array();
                $schema   = $this->get_public_item_schema();
                foreach( $schema['properties'] as $arg => $options ) {
                    if( isset($options['default']) ) {
                        $defaults[ $arg ] = $options['default'];
                    }
                }
                $_item->set_default_params( $defaults );

                // Set request parameters.
                $_item->set_body_params( $item );

                // Set query (GET) parameters.
                $_item->set_query_params( $query );

                $_response = $this->create_item( $_item );

                if( is_wp_error( $_response ) ) {
                    $response['create'][] = array(
                    'id'    => 0,
                    'error' => array(
                    'code'    => $_response->get_error_code(),
                    'message' => $_response->get_error_message(),
                    'data'    => $_response->get_error_data(),
                    ),
                    );
                } else {
                    $response['create'][] = $wp_rest_server->response_to_data( $_response, '' );
                }
            }
        }

        if( !empty( $items['update'] ) ) {
            foreach( $items['update'] as $item ) {
                $_item = new WP_REST_Request('PUT');
                $_item->set_body_params($item);
                $_response = $this->update_item($_item);

                if( is_wp_error( $_response ) ) {
                    $response['update'][] = array(
                        'id'    => $item['id'],
                        'error' => array(
                        'code'    => $_response->get_error_code(),
                        'message' => $_response->get_error_message(),
                        'data'    => $_response->get_error_data(),
                        ),
                    );
                } else {
                    $response['update'][] = $wp_rest_server->response_to_data( $_response, '' );
                }
            }
        }

        if( !empty( $items['delete'] ) ) {
            foreach( $items['delete'] as $id ) {
                $id = (int) $id;

                if( 0 === $id ) {
                    continue;
                }

                $_item = new WP_REST_Request( 'DELETE' );
                $_item->set_query_params(
                    array(
                        'id'    => $id,
                        'force' => true,
                    )
                );
                $_response = $this->delete_item( $_item );

                if( is_wp_error( $_response ) ) {
                       $response['delete'][] = array(
                            'id'    => $id,
                            'error' => array(
                                'code'    => $_response->get_error_code(),
                                'message' => $_response->get_error_message(),
                                'data'    => $_response->get_error_data(),
                            ),
                       );
                } else {
                    $response['delete'][] = $wp_rest_server->response_to_data( $_response, '' );
                }
            }
        }
        return $response;
    }

    /**
     * Validate a text value for a text based setting.
     *
     * @since  1.0.0
     * @param  string $value   Value.
     * @param  array  $setting Setting.
     * @return string
     */
    public function validate_setting_text_field( $value, $setting ) {
        $value = is_null( $value ) ? '' : $value;
        return wp_kses_post( trim( stripslashes( $value ) ) );
    }

    /**
     * Validate select based settings.
     *
     * @since  1.0.0
     * @param  string $value   Value.
     * @param  array  $setting Setting.
     * @return string|WP_Error
     */
    public function validate_setting_select_field( $value, $setting ) {
        if( array_key_exists( $value, $setting['options'] ) ) {
            return $value;
        } else {
            return parent::prepare_error_for_response(400);
        }
    }

    /**
     * Validate multiselect based settings.
     *
     * @since  1.0.0
     * @param  array $values  Values.
     * @param  array $setting Setting.
     * @return array|WP_Error
     */
    public function validate_setting_multiselect_field( $values, $setting ) {
        if( empty( $values ) ) {
            return array();
        }

        if( !is_array( $values ) ) {
            return parent::prepare_error_for_response(400);
        }

        $final_values = array();
        foreach( $values as $value ) {
            if( array_key_exists( $value, $setting['options'] ) ) {
                $final_values[] = $value;
            }
        }
        return $final_values;
    }

    /**
     * Validate image_width based settings.
     *
     * @since  1.0.0
     * @param  array $values  Values.
     * @param  array $setting Setting.
     * @return string|WP_Error
     */
    public function validate_setting_image_width_field( $values, $setting ) {
        if( !is_array( $values ) ) {
            return parent::prepare_error_for_response(400);
        }

        $current = $setting['value'];
        if (isset($values['width']) ) {
            $current['width'] = intval($values['width']);
        }
        if (isset($values['height']) ) {
            $current['height'] = intval($values['height']);
        }
        if (isset($values['crop']) ) {
            $current['crop'] = (bool) $values['crop'];
        }
        return $current;
    }

    /**
     * Validate radio based settings.
     *
     * @since  1.0.0
     * @param  string $value   Value.
     * @param  array  $setting Setting.
     * @return string|WP_Error
     */
    public function validate_setting_radio_field( $value, $setting ) {
        return $this->validate_setting_select_field( $value, $setting );
    }

    /**
     * Validate checkbox based settings.
     *
     * @since  1.0.0
     * @param  string $value   Value.
     * @param  array  $setting Setting.
     * @return string|WP_Error
     */
    public function validate_setting_checkbox_field( $value, $setting ) {
        if( in_array( $value, array( 'yes', 'no' ) ) ) {
            return $value;
        } elseif( empty( $value ) ) {
            $value = isset( $setting['default'] ) ? $setting['default'] : 'no';
            return $value;
        } else {
            return parent::prepare_error_for_response(400);
        }
    }

    /**
     * Validate textarea based settings.
     *
     * @since  1.0.0
     * @param  string $value   Value.
     * @param  array  $setting Setting.
     * @return string
     */
    public function validate_setting_textarea_field( $value, $setting ) {
        $value = is_null( $value ) ? '' : $value;
        return wp_kses(
            trim( stripslashes( $value ) ),
            array_merge(
                array(
                    'iframe' => array(
                    'src'   => true,
                    'style' => true,
                    'id'    => true,
                    'class' => true,
                    ),
                ),
                wp_kses_allowed_html( 'post' )
            )
        );
    }

    /**
     * Add meta query.
     *
     * @since  1.0.0
     * @param  array $args       Query args.
     * @param  array $meta_query Meta query.
     * @return array
     */
    protected function add_meta_query( $args, $meta_query ) {
        if( empty($args['meta_query'] ) ) {
            $args['meta_query'] = array();
        }
        $args['meta_query'][] = $meta_query;

        return $args['meta_query'];
    }

    /**
     * Get the batch schema, conforming to JSON Schema.
     *
     * @since  1.0.0
     * @return array
     */
    public function get_public_batch_schema() {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'batch',
            'type'       => 'object',
            'properties' => array(
                'create' => array(
                    'description' => __( 'List of created resources.', 'wpem-rest-api' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'    => 'object',
                    ),
                ),
                'update' => array(
                    'description' => __( 'List of updated resources.', 'wpem-rest-api' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'    => 'object',
                    ),
                ),
                'delete' => array(
                    'description' => __( 'List of delete resources.', 'wpem-rest-api' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'    => 'integer',
                    ),
                ),
            ),
        );
        return $schema;
    }

    /**
     * Gets an array of fields to be included on the response.
     *
     * Included fields are based on item schema and `_fields=` request argument.
     * Updated from WordPress 5.3, included into this class to support old versions.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return array Fields to be included in the response.
     */
    public function get_fields_for_response( $request ) {
        $schema     = $this->get_item_schema();
        $properties = isset( $schema['properties'] ) ? $schema['properties'] : array();

        $additional_fields = $this->get_additional_fields();
        foreach( $additional_fields as $field_name => $field_options ) {
            // For back-compat, include any field with an empty schema
            // because it won't be present in $this->get_item_schema().
            if( is_null( $field_options['schema'] ) ) {
                $properties[ $field_name ] = $field_options;
            }
        }

        // Exclude fields that specify a different context than the request context.
        $context = $request['context'];
        if( $context ) {
            foreach( $properties as $name => $options ) {
                if( !empty( $options['context'] ) && !in_array( $context, $options['context'], true ) ) {
                    unset( $properties[ $name ] );
                }
            }
        }

        $fields = array_keys( $properties );

        if( !isset( $request['_fields'] ) ) {
            return $fields;
        }
        $requested_fields = wp_parse_list( $request['_fields'] );
        if( 0 === count( $requested_fields ) ) {
            return $fields;
        }
        // Trim off outside whitespace from the comma delimited list.
        $requested_fields = array_map('trim', $requested_fields);
        // Always persist 'id', because it can be needed for add_additional_fields_to_object().
        if( in_array( 'id', $fields, true ) ) {
            $requested_fields[] = 'id';
        }
        // Return the list of all requested fields which appear in the schema.
        return array_reduce(
            $requested_fields,
            function( $response_fields, $field ) use ( $fields ) {
                if( in_array( $field, $fields, true ) ) {
                    $response_fields[] = $field;
                    return $response_fields;
                }
                // Check for nested fields if $field is not a direct match.
                $nested_fields = explode( '.', $field );
                // A nested field is included so long as its top-level property is
                // present in the schema.
                if( in_array( $nested_fields[0], $fields, true ) ) {
                    $response_fields[] = $field;
                }
                return $response_fields;
            },
            array()
        );
    }
}