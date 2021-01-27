<?php
defined( 'ABSPATH' ) || exit;
/**
 * WPEM_Rest_API_Admin class.
 */
class WPEM_Rest_API_Admin {
	/**
	 * __construct function.
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		include( 'wpem-rest-api-settings.php' );
		include( 'wpem-rest-api-keys.php' );
		include( 'wpem-rest-app-branding.php' );
		include( 'wpem-rest-api-keys-table-list.php' );

		$this->settings_page = new WPEM_Rest_API_Settings();

		//add actions
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter('event_manager_admin_screen_ids',array($this,'wpem_rest_api_add_admin_screen') );

		add_action("wp_ajax_save_rest_api_keys",array($this, "update_api_key") );

		add_action("wp_ajax_save_app_branding",array($this, "save_app_branding") );
	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */

	public function admin_enqueue_scripts() {

		if(isset($_GET['page']) && $_GET['page'] == 'wpem-rest-api-settings' ){

			wp_enqueue_media();

			wp_register_script( 'wpem-rest-api-admin-js', WPEM_REST_API_PLUGIN_URL. '/assets/js/admin.js', array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'wp-util','wp-color-picker'), WPEM_REST_API_VERSION, true );

			wp_localize_script( 'wpem-rest-api-admin-js', 'wpem_rest_api_admin', array(			
			 	'ajaxUrl' => admin_url('admin-ajax.php'),
			 	'save_api_nonce' =>  wp_create_nonce( 'save-api-key' ),
			 	'save_app_branding_nonce' =>  wp_create_nonce( 'save-api-branding' ),
			) );

			wp_enqueue_style( 'jquery-ui' );  


			wp_enqueue_style( 'jquery-ui-style',EVENT_MANAGER_PLUGIN_URL. '/assets/js/jquery-ui/jquery-ui.min.css', array() );
		}

	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function wpem_rest_api_add_admin_screen($screen_ids){
		
		$screen_ids[]='event_listing_page_wpem-rest-api-settings';
		return $screen_ids;
	}	

	/**
	 * admin_menu function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function admin_menu() {

		//add_submenu_page( 'edit.php?post_type=event_listing', __( 'Rest API', 'wp-event-manager-rest-api' ), __( 'Rest API', 'wp-event-manager-rest-api' ), 'manage_options', 'wpem-rest-api-settings', array( $this, 'page_output' ) );


		add_submenu_page( 'edit.php?post_type=event_listing', __( 'Rest API', 'wp-event-manager-rest-api' ), __( 'Rest API', 'wp-event-manager-rest-api' ), 'manage_options', 'wpem-rest-api-settings', array( $this->settings_page, 'output' ) );

		
	}

	/**
	 * page_output function will output the rest api settings in admin panel.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function page_output(){

		$this->settings_page->output();

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'wpem-rest-api-settings' )  {
			$tab = (isset($_GET['tab'])) ? $_GET['tab'] : '';
			if (empty($tab)) {
			    $tab = 'general';
			}

		    // switch ($tab) {

		    //     case 'general':
		    //         $this->settings_page->show_setting_tab('general');
		    //         break;

		    //     case 'api-access':
		    //         $this->settings_page->show_setting_tab('api-access');
		    //         break;

		    //     case 'app-branding':
		    //         $this->settings_page->show_setting_tab('api-access');
		    //         break;
		    //     default: do_action('wpem_settings_menu_' . $tab);
		    //         break;
		    // }

		
			wp_enqueue_style( 'wpem-rest-api-backend', WPEM_REST_API_PLUGIN_URL.'/assets/css/backend.css' );
			wp_enqueue_script( 'wpem-rest-api-admin-js' );
			
			//include dirname( __FILE__ ) . '/templates/wpem-rest-settings-panel.php';
		} 
	}

	/**
	 * Create/Update API key.
	 *
	 * @throws Exception On invalid or empty description, user, or permissions.
	 */
	public  function update_api_key() {
		ob_start();

		global $wpdb;

		check_ajax_referer( 'save-api-key', 'security' );

		$response = array();

		try {
			if ( empty( $_POST['description'] ) ) {
				throw new Exception( __( 'Description is missing.', 'wp-event-manager-rest-api' ) );
			}
			if ( empty( $_POST['user'] ) ) {
				throw new Exception( __( 'User is missing.', 'wp-event-manager-rest-api' ) );
			}
			if ( empty( $_POST['permissions'] ) ) {
				throw new Exception( __( 'Permissions is missing.', 'wp-event-manager-rest-api' ) );
			}

			$key_id      = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
			$description = sanitize_text_field( wp_unslash( $_POST['description'] ) );
			$permissions = ( in_array( wp_unslash( $_POST['permissions'] ), array( 'read', 'write', 'read_write' ), true ) ) ? sanitize_text_field( wp_unslash( $_POST['permissions'] ) ) : 'read';
			$user_id      = absint( $_POST['user'] );
			$event_id     = !empty( $_POST['event_id']) ?  $_POST['event_id'] : '' ;
			$date_expires = !empty( $_POST['date_expires']) ?  date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $_POST['date_expires']))) : NULL ;
			
			

			// Check if current user can edit other users.
			if ( $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
				if ( get_current_user_id() !== $user_id ) {
					throw new Exception( __( 'You do not have permission to assign API Keys to the selected user.', 'wp-event-manager-rest-api' ) );
				}
			}

			if ( 0 < $key_id ) {
				$data = array(
					'user_id'     		=> $user_id,
					'description' 		=> $description,
					'permissions' 		=> $permissions,
					'event_id' 	  		=> $event_id,
					'date_expires' 	  	=> $date_expires,
				);

				$wpdb->update(
					$wpdb->prefix . 'wpem_rest_api_keys',
					$data,
					array( 'key_id' => $key_id ),
					array(
						'%d',
						'%s',
						'%s',
						'%d',
						'%s',
						
					),
					array( '%d' )
				);

				$response                    = $data;
				$response['consumer_key']    = '';
				$response['consumer_secret'] = '';
				$response['message']         = __( 'API Key updated successfully.', 'wp-event-manager-rest-api' );
			} else {
				$app_key = wp_rand();
				$consumer_key    = 'ck_' . sha1( wp_rand() );
				$consumer_secret = 'cs_' . sha1( wp_rand() );

				$data = array(
					'user_id'         => $user_id,
					'app_key'         => $app_key,
					'description'     => $description,
					'permissions'     => $permissions,
					'event_id'     	  => $event_id,
					'consumer_key'    =>  $consumer_key ,
					'consumer_secret' => $consumer_secret,
					'truncated_key'   => substr( $consumer_key, -7 ),
					'date_created'    => current_time( 'mysql' ) ,
					'date_expires'    => $date_expires,
				);

				$wpdb->insert(
					$wpdb->prefix . 'wpem_rest_api_keys',
					$data,
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);

				$key_id                      = $wpdb->insert_id;
				$response                    = $data;
				$response['consumer_key']    = $consumer_key;
				$response['consumer_secret'] = $consumer_secret;
				$response['app_key'] 		 = $app_key;
				$response['message']         = __( 'API Key generated successfully. Make sure to copy your new keys now as the secret key will be hidden once you leave this page.', 'wp-event-manager-rest-api' );
				$response['revoke_url']      = '<a class="wpem-backend-theme-button wpem-revoke-button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'revoke-key' => $key_id ), admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) ), 'revoke' ) ) . '">' . __( 'Revoke key', 'wp-event-manager-rest-api' ) . '</a>';
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		// wp_send_json_success must be outside the try block not to break phpunit tests.
		wp_send_json_success( $response );
	}

	public function save_app_branding() {

		check_ajax_referer( 'save-api-branding', 'security' );

		//normal colors
		if(isset($_POST['wpem_primary_color']))
		{
			update_option('wpem_primary_color', $_POST['wpem_primary_color']);
		}

		if(isset($_POST['wpem_success_color']))
		{
			update_option('wpem_success_color', $_POST['wpem_success_color']);
		}

		if(isset($_POST['wpem_info_color']))
		{
			update_option('wpem_info_color', $_POST['wpem_info_color']);
		}

		if(isset($_POST['wpem_warning_color']))
		{
			update_option('wpem_warning_color', $_POST['wpem_warning_color']);
		}

		if(isset($_POST['wpem_danger_color']))
		{
			update_option('wpem_danger_color', $_POST['wpem_danger_color']);
		}

		$primary_color 	= !empty(get_option('wpem_primary_color')) ? get_option('wpem_primary_color') : '#3366FF';
		$success_color 	= !empty(get_option('wpem_success_color')) ? get_option('wpem_success_color') : '#77DD37';
		$info_color 	= !empty(get_option('wpem_info_color')) ? get_option('wpem_info_color') : '#42BCFF';
		$warning_color 	= !empty(get_option('wpem_warning_color')) ? get_option('wpem_warning_color') : '#FCD837';
		$danger_color 	= !empty(get_option('wpem_danger_color')) ? get_option('wpem_danger_color') : '#FC4C20';


		//dark mode colors
		if(isset($_POST['wpem_primary_dark_color']))
		{
			update_option('wpem_primary_dark_color', $_POST['wpem_primary_dark_color']);
		}

		if(isset($_POST['wpem_success_dark_color']))
		{
			update_option('wpem_success_dark_color', $_POST['wpem_success_dark_color']);
		}

		if(isset($_POST['wpem_info_dark_color']))
		{
			update_option('wpem_info_dark_color', $_POST['wpem_info_dark_color']);
		}

		if(isset($_POST['wpem_warning_dark_color']))
		{
			update_option('wpem_warning_dark_color', $_POST['wpem_warning_dark_color']);
		}

		if(isset($_POST['wpem_danger_dark_color']))
		{
			update_option('wpem_danger_dark_color', $_POST['wpem_danger_dark_color']);
		}
		$primary_dark_color 	= !empty(get_option('wpem_primary_dark_color')) ? get_option('wpem_primary_dark_color') : '#3366FF';
		$success_dark_color 	= !empty(get_option('wpem_success_dark_color')) ? get_option('wpem_success_dark_color') : '#77DD37';
		$info_dark_color 	= !empty(get_option('wpem_info_dark_color')) ? get_option('wpem_info_dark_color') : '#42BCFF';
		$warning_dark_color 	= !empty(get_option('wpem_warning_dark_color')) ? get_option('wpem_warning_dark_color') : '#FCD837';
		$danger_dark_color 	= !empty(get_option('wpem_danger_dark_color')) ? get_option('wpem_danger_dark_color') : '#FC4C20';

		

		$wpem_colors = $this->generate_scheme_formatted_colorcodes($primary_color,$success_color,$info_color,$warning_color,$danger_color);

		$wpem_dark_colors = $this->generate_scheme_formatted_colorcodes($primary_dark_color,$success_dark_color,$info_dark_color,$warning_dark_color,$danger_dark_color);

		if(!empty($wpem_colors))
		{
			ksort($wpem_colors);

			update_option('wpem_app_branding_settings', $wpem_colors);	
		}

		if(!empty($wpem_dark_colors))
		{
			ksort($wpem_dark_colors);
			
			update_option('wpem_app_branding_dark_settings', $wpem_dark_colors);	
		}

		$response = [];
		$response['message'] = __( 'Successfully save App Branding.', 'wp-event-manager-rest-api' );

		wp_send_json_success( $response );

		wp_die();
	}


	public function generate_scheme_formatted_colorcodes($primary_color = "#3366FF",$success_color="#77DD37",$info_color = "#42BCFF",$warning_color = "#FCD837",$danger_color = "#FC4C20"){

		$rgb_primary_color 	= wpem_hex_to_rgb($primary_color);
		$rgb_success_color 	= wpem_hex_to_rgb($success_color);
		$rgb_info_color 	= wpem_hex_to_rgb($info_color);
		$rgb_warning_color 	= wpem_hex_to_rgb($warning_color);
		$rgb_danger_color 	= wpem_hex_to_rgb($danger_color);

		$default_rgb = 0.08;

		$wpem_colors = [];

		$data_color = [];
		for($i=1;$i<10;$i++)
		{	
			$brightness = $i*100;

			$wpem_colors['color-primary-'.$brightness] = wpem_color_brightness($primary_color, (1 - $i/10));
			$wpem_colors['color-success-'.$brightness] = wpem_color_brightness($success_color, (1 - $i/10));
			$wpem_colors['color-info-'.$brightness] = wpem_color_brightness($info_color, (1 - $i/10));
			$wpem_colors['color-warning-'.$brightness] = wpem_color_brightness($warning_color, (1 - $i/10));
			$wpem_colors['color-danger-'.$brightness] = wpem_color_brightness($danger_color, (1 - $i/10));

			if($brightness <= 600)
			{
				$wpem_colors['color-primary-transparent-'.$brightness] = 'rgba('.$rgb_primary_color['red'].', '.$rgb_primary_color['green'].', '.$rgb_primary_color['blue'].', '. $i*$default_rgb .')';	

				$wpem_colors['color-success-transparent-'.$brightness] = 'rgba('.$rgb_success_color['red'].', '.$rgb_success_color['green'].', '.$rgb_success_color['blue'].', '. $i*$default_rgb .')';	

				$wpem_colors['color-info-transparent-'.$brightness] = 'rgba('.$rgb_info_color['red'].', '.$rgb_info_color['green'].', '.$rgb_info_color['blue'].', '. $i*$default_rgb .')';	

				$wpem_colors['color-warning-transparent-'.$brightness] = 'rgba('.$rgb_warning_color['red'].', '.$rgb_warning_color['green'].', '.$rgb_warning_color['blue'].', '. $i*$default_rgb .')';	

				$wpem_colors['color-danger-transparent-'.$brightness] = 'rgba('.$rgb_danger_color['red'].', '.$rgb_danger_color['green'].', '.$rgb_danger_color['blue'].', '. $i*$default_rgb .')';	
			}
			
		}
		return $wpem_colors;
	}
}
new WPEM_Rest_API_Admin();


