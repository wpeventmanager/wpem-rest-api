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

	/**
	 * This function is used to get all required plugin with activation status
	 */
	public function get_echosystem_overview() {
		$auth_check = $this->wpem_check_authorized_user();
        if($auth_check){
            return self::prepare_error_for_response(405);
        } else {
			$response_data = self::prepare_error_for_response( 200 );
			$response_data['data'] = array(
				'ecosystem_info' => get_wpem_rest_api_ecosystem_info(),
				'user_status' => wpem_get_user_login_status(wpem_rest_get_current_user_id())
			);
			return wp_send_json($response_data);
		}
	}
}
new WPEM_REST_Ecosystem_Controller();