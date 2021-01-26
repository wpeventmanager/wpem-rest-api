<?php

/**
 * WPEM Functions
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parses and formats a date for ISO8601/RFC3339.
 *
 * Required WP 4.4 or later.
 * See https://developer.wordpress.org/reference/functions/mysql_to_rfc3339/
 *
 * @since  1.0.0
 * @param  string|null| $date Date.
 * @param  bool                    $utc  Send false to get local/offset time.
 * @return string|null ISO8601/RFC3339 formatted datetime.
 */
function wpem_rest_prepare_date_response( $date, $utc = true ) {

	//need improvements as per wpem date time class
	return $date;
}

/**
 * Check permissions of posts on REST API.
 *
 * @since 1.0.0
 * @param string $post_type Post type.
 * @param string $context   Request context.
 * @param int    $object_id Post ID.
 * @return bool
 */
function wpem_rest_check_post_permissions( $post_type, $context = 'read', $object_id = 0 ) {
	$contexts = array(
		'read'   => 'read_private_posts',
		'create' => 'publish_posts',
		'edit'   => 'edit_post',
		'delete' => 'delete_post',
		'batch'  => 'edit_others_posts',
	);

	if ( 'revision' === $post_type ) {
		$permission = false;
	} else {
        
		$cap              = $contexts[ $context ];
		
		$post_type_object = get_post_type_object( $post_type );

		$permission       = current_user_can( $post_type_object->cap->$cap, $object_id );
		
	}

	return apply_filters( 'wpem_rest_check_permissions', $permission, $context, $object_id, $post_type );
}

/**
 * WPEM API - Hash.
 *
 * @since  1.0.0
 * @param  string $data Message to be hashed.
 * @return string
 */
function wpem_api_hash( $data ) {
    return hash_hmac( 'sha256', $data, 'wpem-api' );
}

/**
 * Encodes a value according to RFC 3986.
 * Supports multidimensional arrays.
 *
 * @since 1.0.0
 * @param string|array $value The value to encode.
 * @return string|array       Encoded values.
 */
function wpem_rest_urlencode_rfc3986( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wpem_rest_urlencode_rfc3986', $value );
	}

	return str_replace( array( '+', '%7E' ), array( ' ', '~' ), rawurlencode( $value ) );
}

/**
 * WPEM Color Brightness
 *
 * @since  1.0.0
 * @param  string $data Message to be hashed.
 * @return string
 */
function wpem_color_brightness($hexCode, $adjustPercent) {
    $hexCode = ltrim($hexCode, '#');

    if (strlen($hexCode) == 3) {
        $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
    }

    $hexCode = array_map('hexdec', str_split($hexCode, 2));

    foreach ($hexCode as & $color) {
        $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
        $adjustAmount = ceil($adjustableLimit * $adjustPercent);

        $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
    }

    return '#' . implode($hexCode);
}

/**
 * WPEM hex to rgb
 *
 * @since  1.0.0
 * @param  string $data Message to be hashed.
 * @return string
 */
function wpem_hex_to_rgb( $colour ) {
    if ( $colour[0] == '#' ) {
            $colour = substr( $colour, 1 );
    }
    if ( strlen( $colour ) == 6 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
    } elseif ( strlen( $colour ) == 3 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
    } else {
            return false;
    }
    $r = hexdec( $r );
    $g = hexdec( $g );
    $b = hexdec( $b );
    return array( 'red' => $r, 'green' => $g, 'blue' => $b );
}


/**
 * Check manager permissions on REST API.
 *
 * @since 2.6.0
 * @param string $object  Object.
 * @param string $context Request context.
 * @return bool
 */
function wpem_rest_check_manager_permissions( $object, $context = 'read' ) {

    $objects = array(
        'reports'          => 'read_private_posts',
    );

    $permission = current_user_can( $objects[ $object ] );

    return apply_filters( 'wpem_rest_check_permissions', $permission, $context, 0, $object );
}
