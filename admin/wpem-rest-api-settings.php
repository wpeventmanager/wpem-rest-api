<?php
/*
* This file use for settings at admin site for wp event manager plugin.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WPEM_Rest_API_Settings class.
 */

class WPEM_Rest_API_Settings {

	public $settings;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */

	public function __construct() {

		$this->settings_group = 'wpem_rest_api';


		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * init_settings function.
	 *
	 * @access public
	 * @return void
	 */

	public function init_settings() {

		$this->settings = apply_filters( 'wpem_rest_api_settings',

			array(
					'general' => array(
							'label'			=>	__( 'General', 'wp-event-manager' ),
							'icon'			=>	'meter',
							'type'       => 'fields',
							'sections'		=> array(
									'general' => __('General Settings','wpem-rest-api'),
							),
							'fields'		=> array(
									'general' => array(
											array(
												'name'       => 'enable_wpem_rest_api',
												'std'        => '1',
												'label'      => __( 'Enable Rest API', 'wpem-rest-api' ),
												'cb_label'   => __( 'Disable to remove the API functionality from your event website.', 'wp-event-manager' ),
												'desc'       => '',
												'type'       => 'checkbox',
												'attributes' => array(),
											),

											array(
												'name'       => 'wpem_rest_api_app_logo',
												'std'        => '',
												'cb_label'   => __( 'Upload  the logo of your own brand.', 'wp-event-manager' ),
												'label'      => __( 'App Logo', 'wpem-rest-api' ),
												'desc'       => '',
												'type'       => 'file',
												
												'attributes' => array(),
											),

											// array(
											// 	'name'       => 'wpem_rest_api_app_splash_screen_image',
											// 	'std'        => '',
											// 	'label'      => __( 'App Splash Image', 'wpem-rest-api' ),
											// 	'desc'       => __('','wpem-rest-api'),
											// 	'cb_label'   => __( 'Splash image is the watermark that will help creating identity of your brand.', 'wp-event-manager' ),
											// 	'type'       => 'file',

											// 	'attributes' => array(),
											// ),
									),
							)	
					),
					'api-access' => array(
							'label'			=>	__( 'API Access', 'wp-event-manager' ),
							'icon'			=>	'loop',
							'type'       => 'template',	
					),
					'app-branding' => array(							
							'label'			=>	__( 'APP Branding', 'wp-event-manager' ),
							'icon'			=>	'mobile',
							'type'       	=> 'template',
					),
			)
		);
	}

	/**
	 * register_settings function.
	 *
	 * @access public
	 * @return void
	 */

	public function register_settings() {

		$this->init_settings();

		foreach($this->settings as $settings ){
		if(isset($settings['sections'] ))
			foreach ( $settings['sections'] as $section_key => $section ) {
				
	      		if(isset($settings['fields'][$section_key]))
		      	foreach ( $settings['fields'][$section_key] as $option ) {
		      		
		      		if(isset($option['name']) && isset($option['std']) )
		      			add_option( $option['name'], $option['std'] );

		      		register_setting( $this->settings_group, $option['name'] );
		      	}
			}
		}
	}


	/**
	 * output function.
	 *
	 * @access public
	 * @return void
	 */

	public function output() {

		$this->init_settings();

		wp_enqueue_style( 'wpem-rest-api-backend', WPEM_REST_API_PLUGIN_URL.'/assets/css/backend.min.css' );
		wp_enqueue_script( 'wpem-rest-api-admin-js' );

		$current_tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : 'general';

		$action = '';
		if(in_array($current_tab, ['general']))
		{
			$action = 'action="options.php"';
		}

		?>
		<div class="wrap">
        	<h1><?php _e( 'Rest API Settings', 'textdomain' ); ?></h1>
    	</div>

		<div id="wpbody" role="main">
		  <div id="wpbody-content" class="wpem-admin-container">
		    
		    <div class="wpem-wrap">
				<form method="post" name="wpem-rest-settings-form" <?php echo $action; ?> >	

					<?php settings_fields( $this->settings_group ); ?>

					<div class="wpem-admin-left-sidebar">
				        <ul class="wpem-admin-left-menu">

				        	<?php foreach ( $this->settings as $key => $section ) { ?>
				          <li class="wpem-admin-left-menu-item">
				            <a class="wpem-icon-<?php echo isset($section['icon']) ? $section['icon'] : 'meter';?> nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == $key )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab='.$key ) );?>"><?php echo esc_html( $section['label'] ) ;?></a>
				          </li>
				       		<?php } ?>
				        </ul>
				      </div>
				      <div class="wpem-admin-right-container wpem-<?php echo $current_tab; ?> wpem-app-branding-mode wpem-light-mode">
				      	<div class="metabox-holder wpem-admin-right-container-holder">
				          <div class="wpem-admin-top-title-section postbox">
				          	<?php
				          	if ( ! empty( $_GET['wpem-rest-api-settings-updated'] ) ) {
								flush_rewrite_rules();
								echo '<div class="updated fade event-manager-updated"><p>' . __( 'Settings successfully saved', 'wp-event-manager' ) . '</p></div>';
							}
				          	?>
				            <?php 
				              
				                  include('templates/wpem-rest-settings-panel.php');
				              
				             ?>
						  </div>
						  <p class="submit">
								<input type="submit" class="button-primary wpem-backend-theme-button" id="save-changes" value="<?php _e( 'Save Changes', 'wp-event-manager' ); ?>" />
						  </p>
						</div>
						
				      </div>
				      
			    </form>
		    </div>
		 </div>
		</div>
		<?php  wp_enqueue_script( 'wp-event-manager-admin-settings');
	}
	
	
}
