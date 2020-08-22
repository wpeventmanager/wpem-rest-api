<?php
/**
 * WPEM API Keys Table List
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * API Keys table list class.
 */
class WPEM_API_Keys_Table_List extends WP_List_Table {

	/**
	 * Initialize the API key table list.
	 */
	public function __construct() {
		parent::__construct(
			 array('ajax'      => false   )
		);
	}

	/**
	 * No items found text.
	 */
	public function no_items() {
		esc_html_e( 'No keys found.', 'wp-event-manager-rest-api' );
	}

	/**
	 * Get list columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'app_key'          => __( 'App Key', 'wp-event-manager-rest-api' ),
			'title'         => __( 'Description', 'wp-event-manager-rest-api' ),
			 'truncated_key' => __( 'Consumer key ending in', 'wp-event-manager-rest-api' ),
			 'user_id'          => __( 'User', 'wp-event-manager-rest-api' ),
			 'event_id'          => __( 'Event', 'wp-event-manager-rest-api' ),
			 
			 'permissions'   => __( 'Permissions', 'wp-event-manager-rest-api' ),
			 'last_access'   => __( 'Last access', 'wp-event-manager-rest-api' ),
		);
	}

	/**
	 * Column cb.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_cb( $key ) {
		return sprintf( '<input type="checkbox" name="key[]" value="%1$s" />', $key['key_id'] );
	}

	/**
	 * Return title column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_title( $key ) {
		$url     =  admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access&edit-key=' . $key['key_id'] );
		$user_id = intval( $key['user_id'] );

		// Check if current user can edit other users or if it's the same user.
		$can_edit = current_user_can( 'edit_user', $user_id ) || get_current_user_id() === $user_id;

		$output = '<strong>';
		if ( $can_edit ) {
			$output .= '<a href="' . esc_url( $url ) . '" class="row-title">';
		}
		if ( empty( $key['description'] ) ) {
			$output .= esc_html__( 'API key', 'wp-event-manager-rest-api' );
		} else {
			$output .= esc_html( $key['description'] );
		}
		if ( $can_edit ) {
			$output .= '</a>';
		}
		$output .= '</strong>';

		// Get actions.
		$actions = array(
			/* translators: %s: API key ID. */
			'id' => sprintf( __( 'ID: %d', 'wp-event-manager-rest-api' ), $key['key_id'] ),
		);

		if ( $can_edit ) {
			$actions['edit']  = '<a href="' . esc_url( $url ) . '">' . __( 'View/Edit', 'wp-event-manager-rest-api' ) . '</a>';
			$actions['trash'] = '<a class="submitdelete" aria-label="' . esc_attr__( 'Revoke API key', 'wp-event-manager-rest-api' ) . '" href="' . esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'revoke-key' => $key['key_id'],
						),
						admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' )
					),
					'revoke'
				)
			) . '">' . esc_html__( 'Revoke', 'wp-event-manager-rest-api' ) . '</a>';
		}

		$row_actions = array();

		foreach ( $actions as $action => $link ) {
			$row_actions[] = '<span class="' . esc_attr( $action ) . '">' . $link . '</span>';
		}

		$output .= '<div class="row-actions">' . implode( ' | ', $row_actions ) . '</div>';

		return $output;
	}

	/**
	 * Return truncated consumer key column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_truncated_key( $key ) {
		return '<code>&hellip;' . esc_html( $key['truncated_key'] ) . '</code>';
	}

	/**
	 * Return user column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_user_id( $key ) {
		$user = get_user_by( 'id', $key['user_id'] );

		if ( ! $user ) {
			return '';
		}

		if ( current_user_can( 'edit_user', $user->ID ) ) {
			return '<a href="' . esc_url( add_query_arg( array( 'user_id' => $user->ID ), admin_url( 'user-edit.php' ) ) ) . '">' . esc_html( $user->display_name ) . '</a>';
		}

		return esc_html( $user->display_name );
	}

	/**
	 * Return event column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_event_id( $key ) {
		if(!empty($key['event_id'])){
			return '<a href="'.admin_url( 'post.php?post=' . $key['event_id'] ) . '&action=edit'.'" />'.get_the_title($key['event_id']).'</a>';
		}
		return;
	}
	/**
	 * Return permissions column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_permissions( $key ) {
		$permission_key = $key['permissions'];
		$permissions    = array(
			'read'       => __( 'Read', 'wp-event-manager-rest-api' ),
			'write'      => __( 'Write', 'wp-event-manager-rest-api' ),
			'read_write' => __( 'Read/Write', 'wp-event-manager-rest-api' ),
		);

		if ( isset( $permissions[ $permission_key ] ) ) {
			return esc_html( $permissions[ $permission_key ] );
		} else {
			return '';
		}
	}

	/**
	 * Return last access column.
	 *
	 * @param  array $key Key data.
	 * @return string
	 */
	public function column_last_access( $key ) {
		if ( ! empty( $key['last_access'] ) ) {
			/* translators: 1: last access date 2: last access time */
			$date = sprintf( __( '%1$s at %2$s', 'wp-event-manager-rest-api' ), date_i18n( 'Y-m-d', strtotime( $key['last_access'] ) ), date_i18n( 'H:s:i', strtotime( $key['last_access'] ) ) );

			return apply_filters( 'wpem_api_key_last_access_datetime', $date, $key['last_access'] );
		}

		return __( 'Unknown', 'wp-event-manager-rest-api' );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		if ( ! current_user_can( 'remove_users' ) ) {
			return array();
		}

		return array(
			'revoke' => __( 'Revoke', 'wp-event-manager-rest-api' ),
		);
	}

	/**
	 * Search box.
	 *
	 * @param  string $text     Button text.
	 * @param  string $input_id Input ID.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) { // WPCS: input var okay, CSRF ok.
			return;
		}

		$input_id     = $input_id . '-search-input';
		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // WPCS: input var okay, CSRF ok.

		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $text ) . ':</label>';
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . esc_attr( $search_query ) . '" />';
		submit_button(
			$text,
			'',
			'',
			false,
			array(
				'id' => 'search-submit',
			)
		);
		echo '</p>';
	}

	public function column_default($item, $column_name) {
    	return $item[$column_name];
	}

	public function get_hidden_columns(){
		return array();
	}

	/**
	 * Prepare table list items.
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = $this->get_items_per_page( '10' );
		$current_page = $this->get_pagenum();

		$columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

		if ( 1 < $current_page ) {
			$offset = $per_page * ( $current_page - 1 );
		} else {
			$offset = 0;
		}

		$search = '';

		if ( ! empty( $_REQUEST['s'] ) ) { // WPCS: input var okay, CSRF ok.
			$search = "AND description LIKE '%" . esc_sql( $wpdb->esc_like(  wp_unslash( $_REQUEST['s'] )  ) ) . "%' "; // WPCS: input var okay, CSRF ok.
		}

		// Get the API keys.
		$keys = $wpdb->get_results(
			"SELECT key_id,app_key, user_id, event_id, description, permissions, truncated_key, last_access FROM {$wpdb->prefix}wpem_rest_api_keys WHERE 1 = 1 {$search}" .
			$wpdb->prepare( 'ORDER BY key_id DESC LIMIT %d OFFSET %d;', $per_page, $offset ),
			ARRAY_A
		); // WPCS: unprepared SQL ok.

		$count = $wpdb->get_var( "SELECT COUNT(key_id) FROM {$wpdb->prefix}wpem_rest_api_keys WHERE 1 = 1 {$search};" ); // WPCS: unprepared SQL ok.
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $keys;

		// Set the pagination.
		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
				'total_pages' => ceil( $count / $per_page ),
			)
		);
	}
}
