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

		// 'general' 		=> __('General','wp-event-manager-rest-api'),
		// 	'api-access' 	=> __('API Access','wp-event-manager-rest-api'),
		// 	'settings' 		=> __('Settings','wp-event-manager-rest-api'),
		// 	'styling' => __('Logo & Colors','wp-event-manager-rest-api'),
		// 	'translation' => __('Translation','wp-event-manager-rest-api'),

		$this->settings = apply_filters( 'wpem_rest_api_settings',

			array(
				'general' =>  array( 
									__( 'General', 'wp-event-manager-rest-api' ),
									array(
										array(
												'name'       => 'enable_rest_api_keys',
												'std'        => '1',
												'label'      => __( 'Rest api', 'wp-event-manager-rest-api' ),
												'cb_label'   => __( 'Enable api keys.', 'wp-event-manager-rest-api' ),
												//'desc'       => '',
												'type'       => 'wp-table',
												'attributes' => array(),
										),
										array(
												'name'       => 'enable_rest_api_keys',
												'std'        => '1',
												'label'      => __( 'Rest api', 'wp-event-manager-rest-api' ),
												'cb_label'   => __( 'Enable api keys.', 'wp-event-manager-rest-api' ),
												//'desc'       => '',
												'type'       => 'wp-table',
												'attributes' => array(),
										),
									)
								),
				'api-access' =>  array( 
									__( 'API Access', 'wp-event-manager-rest-api' ),
									array(
										array(
												'name'       => 'enable_rest_api_keys',
												'std'        => '1',
												'label'      => __( 'Rest api', 'wp-event-manager-rest-api' ),
												'cb_label'   => __( 'Enable api keys.', 'wp-event-manager-rest-api' ),
												//'desc'       => '',
												'type'       => 'wp-table',
												'attributes' => array(),
										),
										array(
												'name'       => 'enable_rest_api_keys',
												'std'        => '1',
												'label'      => __( 'Rest api', 'wp-event-manager-rest-api' ),
												'cb_label'   => __( 'Enable api keys.', 'wp-event-manager-rest-api' ),
												//'desc'       => '',
												'type'       => 'wp-table',
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
		wp_enqueue_style( 'wpem-rest-api-backend', WPEM_REST_API_PLUGIN_URL.'/assets/css/backend.css' );
		?>
		<!------API Settings start ------>
			<div id="wpbody" role="main">
			  <div id="wpbody-content" class="wpem-admin-container">
			  	<?php settings_fields( $this->settings_group ); ?>
			    <h2><?php _e('Rest API Setting','wp-event-manager-rest-api');?></h2>
			    <div class="wrap">
			      <div class="wpem-admin-left-sidebar">
			        <ul class="wpem-admin-left-menu">
			        <!--   <li class="wpem-admin-left-menu-item">
			            <a class="nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == 'general' || empty($_GET['tab']) )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=general' ) );?>">General</a>
			          </li> -->
			        <!--   <li class="wpem-admin-left-menu-item">
			            <a class="nav-tab <?php if ( isset( $_GET['tab'] ) &&  $_GET['tab'] == 'api-access' ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) );?>"><?php _e('API Access','wp-event-manager-rest-api');?></a>
			          </li>
 -->
			          	<?php
			    		foreach ( $this->settings as $key => $section ) {

			    			echo '<a href="#settings-' . sanitize_title( $key ) . '" class="nav-tab">' . esc_html( $section[0] ) . '</a>';
			    		}
			    		?>
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
					
					foreach ( $this->settings as $key => $section ) {

						echo '<div id="settings-' . sanitize_title( $key ) . '" class="settings_panel">';

						echo '<table class="form-table">';

						foreach ( $section[1] as $option ) {

							$placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';

							$class          = ! empty( $option['class'] ) ? $option['class'] : '';

							$value          = get_option( $option['name'] );

							$option['type'] = ! empty( $option['type'] ) ? $option['type'] : '';

							$attributes     = array();

							if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) )

								foreach ( $option['attributes'] as $attribute_name => $attribute_value )

									$attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';

							echo '<tr valign="top" class="' . $class . '"><th scope="row"><label for="setting-' . $option['name'] . '">' . $option['label'] . '</a></th><td>';

							switch ( $option['type'] ) {

								case "checkbox" :

									?><label><input id="setting-<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" type="checkbox" value="1" <?php echo implode( ' ', $attributes ); ?> <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php

									if ( $option['desc'] )

										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;

								case "textarea" :

									?><textarea id="setting-<?php echo $option['name']; ?>" class="large-text" cols="50" rows="3" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?>><?php echo esc_textarea( $value ); ?></textarea><?php

									if ( $option['desc'] )

										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;

								case "select" :

									?><select id="setting-<?php echo $option['name']; ?>" class="regular-text" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?>><?php

										foreach( $option['options'] as $key => $name )

											echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';

									?></select><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';

									}

								break;
								case "radio":
									?><fieldset>
										<legend class="screen-reader-text">
											<span><?php echo esc_html( $option['label'] ); ?></span>
										</legend><?php

									if ( $option['desc'] ) {
										echo '<p class="description">' . $option['desc'] . '</p>';
									}

									foreach( $option['options'] as $key => $name )
										echo '<label><input name="' . esc_attr( $option['name'] ) . '" type="radio" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . ' />' . esc_html( $name ) . '</label><br>';

									?></fieldset><?php

								break;

								case "page" :

									$args = array(

										'name'             => $option['name'],

										'id'               => $option['name'],

										'sort_column'      => 'menu_order',

										'sort_order'       => 'ASC',

										'show_option_none' => __( '--no page--', 'wp-event-manager' ),

										'echo'             => false,

										'selected'         => absint( $value )

									);

									echo str_replace(' id=', " data-placeholder='" . __( 'Select a page&hellip;', 'wp-event-manager' ) .  "' id=", wp_dropdown_pages( $args ) );

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';

									}
									
								break;

								case "password" :

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;

								case "" :

								case "input" :

								case "text" :

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';
								}

								break;		
								
								case "multi-select-checkbox":
								    $this->create_multi_select_checkbox($option);
									break;

								default :

									do_action( 'wp_event_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );

								break;
							}
							echo '</td></tr>';
						}
						echo '</table></div>';
					}
				?>
			          </div>
			        </div>
			      </div>
			    </div>
			  </div>
			</div>

		<!-------API Settings end ----->
       
		<!-- <div class="wrap event-manager-settings-wrap">	

			<form method="post" name="wpem-settings-form" action="options.php">	

				<?php settings_fields( $this->settings_group ); ?>

			    <h2 class="nav-tab-wrapper">

			    	<?php

			    		foreach ( $this->settings as $key => $section ) {

			    			echo '<a href="#settings-' . sanitize_title( $key ) . '" class="nav-tab">' . esc_html( $section[0] ) . '</a>';
			    		}
			    	?>
			    </h2>
			    
			 <div class="admin-setting-left">
			     	
			     <div class="white-background">
			     		
				<?php

					if ( ! empty( $_GET['settings-updated'] ) ) {

						flush_rewrite_rules();

						echo '<div class="updated fade event-manager-updated"><p>' . __( 'Settings successfully saved', 'wp-event-manager' ) . '</p></div>';
					}

					foreach ( $this->settings as $key => $section ) {

						echo '<div id="settings-' . sanitize_title( $key ) . '" class="settings_panel">';

						echo '<table class="form-table">';



						foreach ( $section[1] as $option ) {

							$placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';

							$class          = ! empty( $option['class'] ) ? $option['class'] : '';

							$value          = get_option( $option['name'] );

							$option['type'] = ! empty( $option['type'] ) ? $option['type'] : '';

							$attributes     = array();

							if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) )

								foreach ( $option['attributes'] as $attribute_name => $attribute_value )

									$attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';

							echo '<tr valign="top" class="' . $class . '"><th scope="row"><label for="setting-' . $option['name'] . '">' . $option['label'] . '</a></th><td>';

							switch ( $option['type'] ) {

								case "checkbox" :

									?><label><input id="setting-<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" type="checkbox" value="1" <?php echo implode( ' ', $attributes ); ?> <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php

									if ( $option['desc'] )

										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;

								case "textarea" :

									?><textarea id="setting-<?php echo $option['name']; ?>" class="large-text" cols="50" rows="3" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?>><?php echo esc_textarea( $value ); ?></textarea><?php

									if ( $option['desc'] )

										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;

								case "select" :

									?><select id="setting-<?php echo $option['name']; ?>" class="regular-text" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?>><?php

										foreach( $option['options'] as $key => $name )

											echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';

									?></select><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';

									}

								break;
								case "radio":
									?><fieldset>
										<legend class="screen-reader-text">
											<span><?php echo esc_html( $option['label'] ); ?></span>
										</legend><?php

									if ( $option['desc'] ) {
										echo '<p class="description">' . $option['desc'] . '</p>';
									}

									foreach( $option['options'] as $key => $name )
										echo '<label><input name="' . esc_attr( $option['name'] ) . '" type="radio" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . ' />' . esc_html( $name ) . '</label><br>';

									?></fieldset><?php

								break;

								case "page" :

									$args = array(

										'name'             => $option['name'],

										'id'               => $option['name'],

										'sort_column'      => 'menu_order',

										'sort_order'       => 'ASC',

										'show_option_none' => __( '--no page--', 'wp-event-manager' ),

										'echo'             => false,

										'selected'         => absint( $value )

									);

									echo str_replace(' id=', " data-placeholder='" . __( 'Select a page&hellip;', 'wp-event-manager' ) .  "' id=", wp_dropdown_pages( $args ) );

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';

									}
									
								break;

								case "password" :

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;

								case "" :

								case "input" :

								case "text" :

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {

										echo ' <p class="description">' . $option['desc'] . '</p>';
								}

								break;		
								
								case "multi-select-checkbox":
								    $this->create_multi_select_checkbox($option);
								break;
								default :
									do_action( 'wp_event_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );

								break;
							}
							echo '</td></tr>';
						}
						echo '</table></div>';
					}
				?>
				 </div>   <!-- .white-background- -->
				<p class="submit">
					<input type="submit" class="button-primary" id="save-changes" value="<?php _e( 'Save Changes', 'wp-event-manager' ); ?>" />
				</p>
			 </div>  <!-- .admin-setting-left -->						
		    </form>
		  -->   
        	

		<?php  //wp_enqueue_script( 'wp-event-manager-admin-settings');
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
