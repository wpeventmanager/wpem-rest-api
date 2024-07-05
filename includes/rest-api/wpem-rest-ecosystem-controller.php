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
#[AllowDynamicProperties]
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
		$plugins = get_plugins();
		$items = array();
		foreach( $plugins as $filename => $plugin ) {
			if( $plugin['AuthorName'] == 'WP Event Manager' && is_plugin_active( $filename ) ) {
				$licence_key = get_option( $plugin['TextDomain'] . '_licence_key' );
				$items[$plugin["TextDomain"]] = array(
					"version" => $plugin["Version"],
					'activated' => !empty($licence_key)
				);
			}
		}
		return $items;
	}
}
new WPEM_REST_Ecosystem_Controller();