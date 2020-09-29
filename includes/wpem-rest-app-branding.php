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
 * REST API APP Branding controller class.
 *
 * @extends WPEM_REST_CRUD_Controller
 */
class WPEM_REST_APP_Branding_Controller extends WPEM_REST_CRUD_Controller {
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
	protected $rest_base = 'branding';

	/**
	 * Initialize event actions.
	 */
	public function __construct() {
		//add_action( "wpem_rest_insert_{$this->post_type}_object", array( $this, 'clear_transients' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );

		if(!class_exists('WPEM_Rest_API_Settings'))
		require_once( WPEM_REST_API_PLUGIN_DIR.'/admin/wpem-rest-api-settings.php' );
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
					'callback'            => array( $this, 'get_branding_settings' ),
					//'permission_callback' => array( $this, 'get_items_permissions_check' ),
					//'args'                => $this->get_collection_params(),
				)
				//'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * function get_branding_settings
	 * @since 1.0.0
	 * @param 
	 */
	public function get_branding_settings(){
		

			$wpem_rest_settings = new WPEM_Rest_API_Settings();
			$wpem_rest_settings->init_settings();
			$data = [];
			
			if(isset($wpem_rest_settings->settings ))
			foreach($wpem_rest_settings->settings as $setting_group){
			if(isset( $setting_group['sections'] ))
			foreach ( $setting_group['sections'] as $section_key => $section ) {
	      	if(isset($setting_group['fields'][$section_key]))
		      	foreach ( $setting_group['fields'][$section_key] as $option ) {
		      		if(isset($option['name']))
		      		$data[$section_key][$option['name']] = get_option($option['name']);
		      	}
			}
			}

			return apply_filters('wpem_app_branding_settings',$data);
	}
}



new WPEM_REST_APP_Branding_Controller();
