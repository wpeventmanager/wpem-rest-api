<?php
/**
 * WPEM Rest Api public functions
 * @version 1.0.0
 */
defined( 'ABSPATH' ) || exit;

if( !function_exists( 'wpem_rest_api_prepare_date_response' ) ) {
    /**
     * Parses and formats a date for ISO8601/RFC3339.
     *
     * Required WP 4.4 or later.
     * See https://developer.wordpress.org/reference/functions/mysql_to_rfc3339/
     *
     * @since  1.0.0
     * @param  string|null| $date Date.
     * @param  bool         $utc  Send false to get local/offset time.
     * @return string|null ISO8601/RFC3339 formatted datetime.
     */
    function wpem_rest_api_prepare_date_response( $date, $utc = true ) {
        //need improvements as per wpem date time class
        return $date;
    }
}

if( !function_exists( 'wpem_rest_api_check_post_permissions' ) ) {
    /**
     * Check permissions of posts on REST API.
     *
     * @since  1.0.0
     * @param  string $post_type Post type.
     * @param  string $context   Request context.
     * @param  int    $object_id Post ID.
     * @return bool
     */
    function wpem_rest_api_check_post_permissions( $post_type, $context = 'read', $object_id = 0 ) {
        global $wpdb;
        $contexts = array(
            'read'   => 'read',
            'create' => 'publish_posts',
            'edit'   => 'edit_post',
            'delete' => 'delete_post',
            'batch'  => 'edit_others_posts',
        );
        $permission = true;       

        return apply_filters( 'wpem_rest_api_check_permissions', $permission, $context, $object_id, $post_type );
    }
}

if( !function_exists( 'wpem_rest_api_urlencode_rfc3986' ) ) {
    /**
     * Encodes a value according to RFC 3986.
     * Supports multidimensional arrays.
     *
     * @since  1.0.0
     * @param  string|array $value The value to encode.
     * @return string|array       Encoded values.
     */
    function wpem_rest_api_urlencode_rfc3986( $value ){
        if ( is_array( $value ) ) {
            return array_map('wpem_rest_api_urlencode_rfc3986', $value);
        }

        return str_replace( array( '+', '%7E' ), array( ' ', '~' ), rawurlencode( $value ) );
    }
}

if( !function_exists( 'wpem_rest_api_color_brightness' ) ) {

    /**
     * WPEM Color Brightness
     *
     * @since  1.0.0
     * @param  string $data Message to be hashed.
     * @return string
     */
    function wpem_rest_api_color_brightness( $hexCode, $adjustPercent ){
        $hexCode = ltrim( $hexCode, '#' );

        if ( strlen( $hexCode ) == 3 ) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }

        $hexCode = array_map( 'hexdec', str_split( $hexCode, 2 ) );

        foreach ( $hexCode as & $color ) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = ceil( $adjustableLimit * $adjustPercent );

            $color = str_pad(dechex( $color + $adjustAmount ), 2, '0', STR_PAD_LEFT );
        }

        return '#' . implode( $hexCode );
    }
}

if( !function_exists( 'wpem_rest_api_hex_to_rgb' ) ) {
    /**
     * WPEM hex to rgb
     *
     * @since  1.0.0
     * @param  string $data Message to be hashed.
     * @return string
     */
    function wpem_rest_api_hex_to_rgb( $colour ){
        if ($colour[0] == '#' ) {
            $colour = substr($colour, 1);
        }
        if ( strlen($colour) == 6 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
        } elseif ( strlen($colour ) == 3 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
        } else {
            return false;
        }
        $r = hexdec( $r );
        $g = hexdec( $g );
        $b = hexdec( $b );
        return array( 'red' => $r, 'green' => $g, 'blue' => $b );
    }
}
if( !function_exists( 'wpem_rest_api_check_manager_permissions' ) ) {
    /**
     * Check manager permissions on REST API.
     *
     * @since  2.6.0
     * @param  string $object  Object.
     * @param  string $context Request context.
     * @return bool
     */
    function wpem_rest_api_check_manager_permissions( $object, $context = 'read' ){
        $permission = true;

        return apply_filters( 'wpem_rest_api_check_permissions', $permission, $context, 0, $object );
    }
}

if( !function_exists( 'wpem_response_default_status' ) ) {
    /**
     * This function is used to get error code, status, messages
     * @since 1.0.1
     */
    function wpem_response_default_status() {
        $error_info = apply_filters('wpem_rest_response_default_status', array(
            array(
                'code' => 200,
                'status' => 'OK',
                'message' => __( 'Request is successfully completed.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 201,
                'status' => 'Created',
                'message' => __( 'Resource was successfully created.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 202,
                'status' => 'Updated',
                'message' => __( 'Resource was successfully updated.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 204,
                'status' => 'No Content',
                'message' => __( 'Request was successfully processed and there is no content to return.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 400,
                'status' => 'Bad request',
                'message' => __( 'Invalid syntax, incorrectly formatted JSON, or data violating a database constraint.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 401,
                'status' => 'Unauthorized',
                'message' => __( 'Username or Password Wrong, Please try again.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 403,
                'status' => 'Forbidden',
                'message' => __( 'Does not have permissions to access the requested resource.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 404,
                'status' => 'Not found',
                'message' => __( 'Data not found.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 406,
                'status' => 'Unauthorized',
                'message' => __( 'Username already exists.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 407,
                'status' => 'Unauthorized',
                'message' => __( 'Email already exists.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 413,
                'status' => 'Error',
                'message' => __( 'Unable to accept items for this request.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 408,
                'status' => 'Error',
                'message' => __( 'Failed to create Resource.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 409,
                'status' => 'Error',
                'message' => __( 'Failed to update Resource.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 410,
                'status' => 'Error',
                'message' => __( 'The item already deleted.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 412,
                'status' => 'Error',
                'message' => __( 'You Do Not Have Permission to Delete Resource.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 500,
                'status' => 'Internal server error',
                'message' => __( 'An unexpected error has occurred in processing the request. View the logs on the device for details.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 503,
                'status' => 'Service unavailable',
                'message' => __( 'You Do Not Have Permission to access this app.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 504,
                'status' => 'Permission Denied',
                'message' => __( 'You do not have permission to edit this resource.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 203,
                'status' => 'Non-Authorative Information',
                'message' => __( 'You does not have read permissions.', 'wpfm-rest-api' )
            ),
            array(
                'code' => 405,
                'status' => 'Authentication Failed',
                'message' => __( 'User not exist.', 'wpfm-rest-api' )
            ),
        ) );
        return $error_info;
    }
}

if( !function_exists( 'get_wpem_rest_api_ecosystem_info' ) ) {
    /**
     * This function is used to get ecosystem information of website
     * @since 1.0.1
     */
    function get_wpem_rest_api_ecosystem_info(){
        // Create required plugin list for wpem rest api
        $required_plugins = apply_filters( 'wpem_rest_api_required_plugin_list', array(
            'woocommerce' => 'Woocommerce',
            'wp-event-manager' => 'WP Event Manager',
            'wpem-rest-api' => 'WPEM Rest API',
            'wp-event-manager-sell-tickets' => 'WP Event Manager Sell Tickets',
            'wp-event-manager-registrations' => 'WP Event Manager Registrations',
            'wpem-guests' => 'WP Event Manager Guests',
        ) );

        // Get ecosystem data
        $plugins = get_plugins();
        $ecosystem_info = array();
        $api_url = 'https://wp-eventmanager.com/?wc-api=wpemstore_licensing_update_api';

        
        foreach( $plugins as $filename => $plugin ) {
            if( 'woocommerce' == $plugin['TextDomain'] || 'wp-event-manager' == $plugin['TextDomain'] || 'wpem-rest-api' == $plugin['TextDomain']){
                $ecosystem_info[$plugin["TextDomain"]] = array(
                    'version' => $plugin["Version"],
                    'activated' => is_plugin_active( $filename ),
                    'plugin_name' => $plugin["Name"]
                );
                
            } else{
                if( $plugin['AuthorName'] == 'WP Event Manager' && is_plugin_active( $filename ) ) {
                    $licence_activate = get_option( $plugin['TextDomain'] . '_licence_key' );

                    if( !empty ( $licence_activate ) ) {
                        $license_status = check_wpem_license_expire_date($licence_activate, $api_url );
                        $ecosystem_info[$plugin["TextDomain"]] = array(
                            'version' => $plugin["Version"],
                            'activated' => $license_status,
                            'plugin_name' => $plugin["Name"]
                        );
                    } else {
                        $ecosystem_info[$plugin["TextDomain"]] = array(
                            'version' => $plugin["Version"],
                            'activated' => false,
                            'plugin_name' => $plugin["Name"]
                        );
                    }
                }
            }
        }

        $plugin_list = array();
        // Check id required plugin is not in list
        foreach( $required_plugins as $plugin_key => $plugin_name){
            if( array_key_exists( $plugin_key, $ecosystem_info ) ) {
                $plugin_list[$plugin_key] = $ecosystem_info[$plugin_key];
            } else {
                $plugin_list[$plugin_key] = array(
                    'version' => '',
                    'activated' => false,
                    'plugin_name' => $plugin_name
                );
            }
        }
        return $plugin_list;
    }
}

if( !function_exists( 'check_wpem_license_expire_date' ) ) {
    /**
     * This function is used to check plugin license key is expired or not
     */
    function check_wpem_license_expire_date($licence_key, $api_url) {
        
        $args = array();
        $defaults = array(
            'request'        => 'check_expire_key',
            'licence_key'    => $licence_key,
        );

        $args    = wp_parse_args($args, $defaults);
        $request = wp_remote_get($api_url . '&' . http_build_query($args, '', '&'));

        if(is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }

        $response = json_decode(wp_remote_retrieve_body($request),true);
        $response = (object)$response;

        if ( isset( $response->errors ) ) {
            return false;
        }

        // Set version variables
        if ( isset( $response ) && is_object( $response ) && $response !== false ) {
            return true;
        }
    }
}