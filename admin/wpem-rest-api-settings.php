<?php
/*
* This file use for settings at admin site for wp event manager plugin.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WPEM_Rest_API_Settings class.
 */

class WPEM_Rest_API_Settings {

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
	 * @access protected
	 * @return void
	 */

	protected function init_settings() {

		// Prepare roles option

		$roles         = get_editable_roles();

		$account_roles = array();
		foreach ( $roles as $key => $role ) {

			if ( $key == 'administrator' ) {

				continue;
			}

			$account_roles[ $key ] = $role['name'];
		}

		$this->settings = apply_filters( 'wpem_rest_api_settings',

			array(
					'general' => array(
							
							__( 'General', 'wp-event-manager' ),

							
							array(

								array(
											'name'       => 'enable_rest_api_keys',
											'std'        => '1',
											'label'      => __( 'Enable rest api', 'wp-event-manager-rest-api' ),
											'cb_label'   => __( 'Enable api keys.', 'wp-event-manager-rest-api' ),
											'desc'       => '',
											'type'       => 'checkbox',
											'attributes' => array(),
									),
							)
					),
					'api-access' => array(
							
							__( 'API Access', 'wp-event-manager' ),
							array(
								array(
											'name'       => 'wpem_api_access',
											'std'        => '1',
											'label'      => __( '', 'wp-event-manager-rest-api' ),
											'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
											'desc'       => '',
											'type'       => 'template',
											'attributes' => array(),
									),
							)
					),
					'app-branding' => array(
							
							__( 'APP Branding', 'wp-event-manager' ),
							array(
							
							array(
									'name'       => 'app_splash_screen_background_color',
									'std'        => '1',
									'label'      => __( 'Splash Image Background Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),
							array(
									'name'       => 'app_splash_screen_image_color',
									'std'        => '1',
									'label'      => __( 'Splash Image Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_heading_color',
									'std'        => '1',
									'label'      => __( 'Heading Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_sub_heading_color',
									'std'        => '1',
									'label'      => __( 'Sub Heading Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_to_heading_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Top Heading Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_bottom_background_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Bottom Background Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_bottom_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Bottom Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_textbox_icon_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Textbox Icon Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_button_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Button Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),array(
									'name'       => 'app_login_screen_button_text_color',
									'std'        => '1',
									'label'      => __( 'Login Screen Button Text Color', 'wp-event-manager-rest-api' ),
									'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
									'desc'       => '',
									'type'       => 'color-picker',
									'attributes' => array(),
							),
						)
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

		foreach ( $this->settings as $section ) {

			foreach ( $section[1] as $option ) {

				if ( isset( $option['std'] ) )

					add_option( $option['name'], $option['std'] );

				register_setting( $this->settings_group, $option['name'] );
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

		?>
		<div id="wpbody" role="main">
		  <div id="wpbody-content" class="wpem-admin-container">
		    <h2><?php _e('Rest API Settings','wp-event-manager-rest-api');?></h2>
		    <div class="wrap">
				<form method="post" name="wpem-settings-form" action="options.php">	

					<?php settings_fields( $this->settings_group ); ?>

					<div class="wpem-admin-left-sidebar">
				        <ul class="wpem-admin-left-menu">
				        	<?php foreach ( $this->settings as $key => $section ) { ?>
				          <li class="wpem-admin-left-menu-item">
				            <a class="wpem-icon-meter nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == $key )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab='.$key ) );?>"><?php echo esc_html( $section[0] ) ;?></a>
				          </li>
				       		<?php } ?>
				        </ul>
				      </div>
				      <div class="wpem-admin-right-container wrap">
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
	
	/**
	 * Creates Multiselect checkbox.
	 * This function generate multiselect 
	 * @param $value
	 * @return void
	 */ 
	public function create_multi_select_checkbox($value) 
	{ 
		
		echo '<ul class="mnt-checklist" id="'.$value['name'].'" >'."\n";
		foreach ($value['options'] as $option_value => $option_list) {
			$checked = " ";
			if (get_option($value['name'] ) ) {
			
                                 $all_country = get_option( $value['name'] );
                                 $start_string = strpos($option_list['name'],'[');
                                 $country_code = substr($option_list['name'] ,$start_string + 1 ,  2 );
                                 $coutry_exist = array_key_exists($country_code , $all_country);
                              if( $coutry_exist ){
                                     $checked = " checked='checked' ";       
                                     
                              }
			}
			echo "<li>\n";

			echo '<input id="setting-'.$option_list['name'].'" name="'.$option_list['name'].'" type="checkbox" '.$checked.'/>'.$option_list['cb_label']."\n";
			echo "</li>\n";
		}
		echo "</ul>\n";
    }
}
