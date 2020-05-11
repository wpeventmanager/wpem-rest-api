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