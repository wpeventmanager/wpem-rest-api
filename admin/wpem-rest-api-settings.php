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

		$this->setting_sections = apply_filters( 'wpem_rest_api_setting_sections',array());


		$this->settings = apply_filters( 'wpem_rest_api_settings',

			array(
					'general' => array(
							'label'			=>	__( 'General', 'wp-event-manager' ),
							'icon'			=>	'meter',
							'type'       => 'fields',
							'sections'		=> array(),
							'fields'		=> array()	
					),
					'api-access' => array(
							'label'			=>	__( 'API Access', 'wp-event-manager' ),
							'icon'			=>	'link',
							'type'       => 'template',	
					),
					'app-branding' => array(
							
									'label'			=>	__( 'APP Branding', 'wp-event-manager' ),
									'icon'			=>	'mobile',
									'type'       	=> 'fields',
									'sections'		=> array(
															'general' => __('General','wp-event-manager-rest-api'),
															'login_screen' => __('Login Screen','wp-event-manager-rest-api'),
															'splash_screen' => __('Splash Screen','wp-event-manager-rest-api'),
															'select_event_screen' => __('Select Event Screen','wp-event-manager-rest-api'),
														),
									'fields'		=>array(
														'general' => array(
															array(
																	'name'       => 'app_logo',
																	'std'        => '',
																	'label'      => __( 'Logo', 'wp-event-manager-rest-api' ),
																	'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																	'desc'       => '',
																	'type'       => 'text',
																	'attributes' => array(),
																),
															array(
																'name'       => 'app_splash_screen_image_color',
																'std'        => '#000',
																'label'      => __( 'Top Background Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_loading_icon_color',
																'std'        => '#000',
																'label'      => __( 'Loading Icon Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_top_and_footer_navigation_and_selected_tab_line_background_color',
																'std'        => '#000',
																'label'      => __( 'Top and Footer Navigation & Selected Tab Line Background Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_top_and_footer_navigation_background_text_color',
																'std'        => '#000',
																'label'      => __( 'Top and Footer Navigation Background Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_top_and_footer_navigation_icon_color',
																'std'        => '#000',
																'label'      => __( 'Top and Footer Navigation Icon Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_round_circle_background_color',
																'std'        => '#000',
																'label'      => __( 'Round Circle Background Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_round_circle_calculated_percentage_background_color',
																'std'        => '#000',
																'label'      => __( 'Round Circle Calculated Percentage Background Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															),
															array(
																'name'       => 'app_round_circle_inside_text_color',
																'std'        => '#000',
																'label'      => __( 'Round Circle inside Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
															)
														),
														'login_screen' => array(
															array(
															'name'       => 'app_login_screen_top_background_color',
															'std'        => '1',
															'label'      => __( 'Top Background Color', 'wp-event-manager-rest-api' ),
															'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
															'desc'       => '',
															'type'       => 'color-picker',
															'attributes' => array(),
													),
															array(
															'name'       => 'app_login_screen_top_text_color',
															'std'        => '1',
															'label'      => __( 'Top Text Color', 'wp-event-manager-rest-api' ),
															'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
															'desc'       => '',
															'type'       => 'color-picker',
															'attributes' => array(),
													),
															array(
															'name'       => 'app_login_screen_to_heading_color',
															'std'        => '1',
															'label'      => __( 'Login Screen Top Heading Text Color', 'wp-event-manager-rest-api' ),
															'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
															'desc'       => '',
															'type'       => 'color-picker',
															'attributes' => array(),
													),
															array(
															'name'       => 'app_login_screen_top_sub_heading_text_color',
															'std'        => '1',
															'label'      => __( 'Login Screen Top Sub Heading Text Color', 'wp-event-manager-rest-api' ),
															'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
															'desc'       => '',
															'type'       => 'color-picker',
															'attributes' => array(),
													),
															array(
															'name'       => 'app_login_screen_bottom_heading_text_color',
															'std'        => '1',
															'label'      => __( 'Login Screen Bottom Heading Text Color', 'wp-event-manager-rest-api' ),
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
													)
						),
													'splash_screen' => array(
														array(
																'name'       => 'app_splash_logo',
																'std'        => '1',
																'label'      => __( 'Splash Screen logo', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'file',
																'attributes' => array(),
														),

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
																'name'       => 'app_splash_bottom_heading_text',
																'std'        => '1',
																'label'      => __( 'Splash Bottom Heading Text', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
														array(
																'name'       => 'app_splash_bottom_sub_heading_text',
																'std'        => '1',
																'label'      => __( 'Splash Bottom Sub Heading Text', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
														array(
																'name'       => 'app_splash_skip_text_color',
																'std'        => '1',
																'label'      => __( 'Splash Skip Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
														array(
																'name'       => 'app_splash_bottom_three_dot_icon_color',
																'std'        => '1',
																'label'      => __( 'Splash Bottom Three dot Icon Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
													),
														'select_event_screen' => array(
array(
																'name'       => 'app_event_title_text_color',
																'std'        => '1',
																'label'      => __( 'Event Title Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'file',
																'attributes' => array(),
														),

															array(
																'name'       => 'app_tab_title_text_color',
																'std'        => '1',
																'label'      => __( 'Tab Title Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
														array(
																'name'       => 'app_event_address_and_time_text_color',
																'std'        => '1',
																'label'      => __( 'Event Address & TIme Text Color', 'wp-event-manager-rest-api' ),
																'cb_label'   => __( '', 'wp-event-manager-rest-api' ),
																'desc'       => '',
																'type'       => 'color-picker',
																'attributes' => array(),
														),
														
														),
													)
								)

							/*array(
							
							array(
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
						)*/
					),


			

				
				
			
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

		/*foreach ( $this->settings as $sections ) {

			foreach ( $section['fields'] as $option ) {

				if ( isset( $option['std'] ) )

					add_option( $option['name'], $option['std'] );

				register_setting( $this->settings_group, $option['name'] );
			}
		}*/
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
				            <a class="wpem-icon-<?php echo isset($section['icon']) ? $section['icon'] : 'meter';?> nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == $key )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab='.$key ) );?>"><?php echo esc_html( $section['label'] ) ;?></a>
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
