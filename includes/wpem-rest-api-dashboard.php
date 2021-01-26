<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPEM_Rest_API_Dashboard class.
 */
class WPEM_Rest_API_Dashboard {

	/**
	 * __construct function.
	 */
	public function __construct() {

		add_filter( 'wpem_dashboard_menu', array($this,'wpem_dashboard_menu_add') );
		add_action( 'event_manager_event_dashboard_content_show_registrations', array( $this, 'show_registrations' ) );
	}

	/**
	 * add dashboard menu function.
	 *
	 * @access public
	 * @return void
	 */
	public function wpem_dashboard_menu_add($menus) 
	{
		$menus['rest_api'] = [
						'title' => __('Rest API', 'wp-event-manager'),
						'icon' => 'wpem-icon-users',
						'submenu' => [
							'show_registrations' => [
								'title' => __('List', 'wp-event-manager'),
								'query_arg' => ['action' => 'show_registrations'],
							],
						]
					];
		return $menus;
	}

}
new WPEM_Rest_API_Dashboard();
