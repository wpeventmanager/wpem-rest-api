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
									'general' => __('General Settings','wp-event-manager-rest-api'),
							),
							'fields'		=> array(
									'general' => array(
											array(
												'name'       => 'enable_wpem_rest_api',
												'std'        => '1',
												'label'      => __( 'Enable Rest API', 'wp-event-manager-rest-api' ),
												'cb_label'   => __( 'Disable this to remove the functionality of Your Event Website', 'wp-event-manager' ),
												'desc'       => '',
												'type'       => 'checkbox',
												'attributes' => array(),
											),

											array(
												'name'       => 'wpem_rest_api_app_logo',
												'std'        => '',
												'label'      => __( 'App Logo', 'wp-event-manager-rest-api' ),
												'desc'       => '',
												'attributes' => array(),
											),

											array(
												'name'       => 'wpem_rest_api_app_splash_screen_image',
												'std'        => '',
												'label'      => __( 'App Splash Image', 'wp-event-manager-rest-api' ),
												'desc'       => '',
												'attributes' => array(),
											),
									),
							)	
					),
					'api-access' => array(
							'label'			=>	__( 'API Access', 'wp-event-manager' ),
							'icon'			=>	'link',
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

		wp_enqueue_style( 'wpem-rest-api-backend', WPEM_REST_API_PLUGIN_URL.'/assets/css/backend.css' );
		wp_enqueue_script( 'wpem-rest-api-admin-js' );

		?>
		<div id="wpbody" role="main">
		  <div id="wpbody-content" class="wpem-admin-container">
		    <h2><?php _e('Rest API Settings','wp-event-manager-rest-api');?></h2>
		    <div class="wrap">
				<form method="post" name="wpem-rest-settings-form" action="options.php">	

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
				      <div class="wpem-admin-right-container wrap wpem-app-branding-mode wpem-light-mode">
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
				        </div>
				      </div>
				      <p class="submit">
					<input type="submit" class="button-primary" id="save-changes" value="<?php _e( 'Save Changes', 'wp-event-manager' ); ?>" />
				</p>
			    </form>
		    </div>
		 </div>
		</div>
		<?php  wp_enqueue_script( 'wp-event-manager-admin-settings');
	}
	
	
}
