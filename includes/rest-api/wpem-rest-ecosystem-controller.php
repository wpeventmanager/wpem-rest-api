<?php
/**
 * REST API Events controller
 * Handles requests to the /ecosystem endpoint.
 * @Copyright - WP Event Manager | 2022
 * @author WP Event Manager | https://wp-eventmanager.com
 * @maintainer Jr Sarath | https://github.com/jrsarath
 * @since 1.0.1
 */

defined('ABSPATH') || exit;

/**
 * REST API Ecosystem controller class.
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_Ecosystem_Controller extends WPEM_REST_CRUD_Controller {
	/**
	 * Endpoint namespace.
	 * @var string
	 */
	protected $namespace = 'wpem';

	/**
	 * Route base.
	 * @var string
	 */
	protected $rest_base = 'ecosystem';

	/**
	 * Post type.
	 * @var string
	 */
	protected $post_type = 'event_listing';

	/**
	 * Controls visibility on frontend.
	 * @var string
	 */
	protected $public = false;

	/**
	 * Initialize Ecosystem actions.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}
	/**
	 * Register the routes for ecosystem.
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_echosystem_overview' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		));
	}

	public function get_echosystem_overview() {
		// Create required plugin list for wpem rest api
		$required_plugins = apply_filters( 'wpem_rest_api_required_plugin_list', array(
			'woocommerce',
			'wp-event-manager',
			'wpem-rest-api',
			'wp-event-manager-sell-tickets',
			'wp-event-manager-registrations',
			'wpem-guests',
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
						$license_status = $this->check_wpem_license_expire_date($licence_activate, $api_url );
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
		// Check id required plugin is not in list
		foreach( $required_plugins as $plugin_key){
			if( !array_key_exists( $plugin_key, $ecosystem_info ) ) {
			   foreach( $plugins as $filename => $plugin ) {
				   if (isset($plugin['TextDomain']) && $plugin['TextDomain'] === $plugin_key) {
					   return $plugin['Name'];
				   }
			   }
			}
		}
		return $ecosystem_info;

		// $plugins = get_plugins();
		// $items = array();

		// foreach( $plugins as $filename => $plugin ) {
		// 	if( $plugin['AuthorName'] == 'WP Event Manager' && is_plugin_active( $filename ) && !in_array( $plugin['TextDomain'], ["wp-event-manager", "wpem-rest-api", "wp-user-profile-avatar", "wpem-divi-elements", "wp-event-manager-migration"] ) ){
		// 		$response = array();
		// 		$licence_key = get_option( $plugin['TextDomain'] . '_licence_key' );
		// 		if( !empty($licence_key) ) {
		// 			$args = array();
		// 			$defaults = array(
		// 				'request'        => 'check_expire_key',
		// 				'licence_key'    => $licence_key,
		// 			);
			
		// 			$args    = wp_parse_args($args, $defaults);
		// 			$request = wp_remote_get($api_url . '&' . http_build_query($args, '', '&'));
			
		// 			if(is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
		// 				return false;
		// 			}
			
		// 			$response = json_decode(wp_remote_retrieve_body($request),true);
		// 			$response = (object)$response;
	
		// 			if ( isset( $response->errors ) ) {
		// 				$items[$plugin["TextDomain"]] = array(
		// 					"version" => $plugin["Version"],
		// 					'activated' => false
		// 				);
		// 			}
	
		// 			// Set version variables
		// 			if ( isset( $response ) && is_object( $response ) && $response !== false ) {
		// 				$items[$plugin["TextDomain"]] = array(
		// 					"version" => $plugin["Version"],
		// 					'activated' => true
		// 				);
		// 			}
		// 		} else {
		// 			$items[$plugin["TextDomain"]] = array(
		// 				"version" => $plugin["Version"],
		// 				'activated' => false
		// 			);
		// 		}
		// 	}
		// }
		// return $items;
	}

	public function check_wpem_license_expire_date($licence_key, $api_url) {
		
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
new WPEM_REST_Ecosystem_Controller();