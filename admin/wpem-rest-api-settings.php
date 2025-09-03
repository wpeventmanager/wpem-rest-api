<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WPEM_Rest_API_Settings class used to create settings fields and save settings.
 */
class WPEM_Rest_API_Settings {

	public $settings, $settings_group;
	/**
	 * __construct function.
	 * @access public
	 * @return void
	 */

	public function __construct() {
		$this->settings_group = 'wpem_rest_api';
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * init_settings function.
	 * @access public
	 * @return void
	 */
	public function init_settings() {
		$this->settings = apply_filters( 'wpem_rest_api_settings',
			array(
				'general' => array(
					'label'   => __( 'General', 'wpem-rest-api' ),
					'icon'   => 'meter',
					'type'     => 'fields',
					'sections' => array(
						'general' => __('General Settings','wpem-rest-api'),
					), 
					'fields' => array(
						'general' => array(
							array(
								'name'       => 'enable_wpem_rest_api',
								'std'        => '1',
								'label'      => __( 'Enable Rest API', 'wpem-rest-api' ),
								'cb_label'   => __( 'Disable to remove the API functionality from your event website.', 'wpem-rest-api' ),
								'desc'       => '',
								'type'       => 'checkbox',
								'attributes' => array(),
							),
							array(
								'name'       => 'wpem_rest_api_app_name',
								'std'        => 'WP Event Manager',
								'label'      => __( 'Application Name', 'wpem-rest-api' ),
								'cb_label'   => __( 'WP Event Manager', 'wpem-rest-api' ),
								'desc'       => '',
								'type'       => 'text',
								'attributes' => array(),
							),
							array(
								'name'       => 'wpem_rest_api_app_logo',
								'std'        => '',
								'cb_label'   => __( 'Upload  the logo of your own brand.', 'wpem-rest-api' ),
								'label'      => __( 'App Logo', 'wpem-rest-api' ),
								'desc'       => __( 'Upload smallest file possible to ensure lesser loading time', 'wpem-rest-api' ),
								'type'       => 'file',
								'attributes' => array(),
							),
						),
					)
				),
				'api-access' => array(
					'label' => __( 'API Access', 'wpem-rest-api' ),
					'icon'  => 'loop',
					'type'  => 'template',
				),
				'app-branding' => array(
					'label' => __( 'APP Branding', 'wpem-rest-api' ),
					'icon'  => 'mobile',
					'type'  => 'template',
				),
				// New Settings tab
				'settings' => array(
					'label'    => __( 'Settings', 'wpem-rest-api' ),
					'icon'     => 'setting',
					'type'     => 'fields',
					'sections' => array(
						'general' => __( 'General Settings', 'wpem-rest-api' ),
					),
					'fields'   => array(
						'general' => array(
							array(
								'name'       => 'wpem_rest_allowed_roles',
								'std'        => array( 'organizer', 'wpem-scanner' ),
								'label'      => __( 'Allowed Roles to access Organizer App', 'wpem-rest-api' ),
								'cb_label'   => __( 'Selected roles allows to access Organizer app.', 'wpem-rest-api' ),
								'desc'       => __( 'Choose one or more user roles.', 'wpem-rest-api' ),
								'type'       => 'multi-select-checkbox',
								'attributes' => array(),
								'options'    => $this->get_all_roles_for_multiselect(),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * register_settings function.
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
	 * output function used to display setting fields at backend side in settings.
	 * @access public
	 * @return void
	 */
	public function output() {
		$this->init_settings();

		wp_enqueue_style( 'wpem-rest-api-backend', WPEM_REST_API_PLUGIN_URL.'/assets/css/backend.min.css' );
		wp_enqueue_script( 'wpem-rest-api-admin-js' );

		$current_tab = isset($_REQUEST['tab']) ? sanitize_text_field($_REQUEST['tab']) : 'general';

		$action = '';
		if(in_array($current_tab, ['general','settings'])){
			$action = 'action=options.php';
		} ?>
		<div class="wrap">
        	<h1><?php esc_html_e( 'Rest API Settings', 'wpem-rest-api' ); ?></h1>
    	</div>

		<div id="wpbody" role="main">
		  	<div id="wpbody-content" class="wpem-admin-container">
	    		<div class="wpem-wrap">
					<form method="post" name="wpem-rest-settings-form" <?php echo esc_attr($action); ?> >

						<?php settings_fields( $this->settings_group ); ?>
						<div class="wpem-admin-left-sidebar">
							<ul class="wpem-admin-left-menu">
								<?php foreach ( $this->settings as $key => $section ) { ?>
									<li class="wpem-admin-left-menu-item">
										<a class="wpem-icon-<?php echo isset($section['icon']) ? esc_attr($section['icon']) : 'meter';?> nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == $key )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab='.$key ) );?>"><?php echo esc_html( $section['label'] ) ;?></a>
									</li>
								<?php } ?>
							</ul>
						</div>
						<?php 
							$mode = get_option('wpem_active_mode');
							$class = "wpem-light-mode";
							if( $mode == 'dark' ){
								$class = "wpem-dark-mode";
							}
						?>
						<div class="wpem-admin-right-container wpem-<?php echo esc_html( $current_tab ); ?> wpem-app-branding-mode <?php if($current_tab == 'app-branding' ){ echo esc_attr( $class ); } ?>">
							<div class="metabox-holder wpem-admin-right-container-holder">
								<div class="wpem-admin-top-title-section postbox">
									<?php
										if (!empty($_GET['settings-updated'])) {
											flush_rewrite_rules();
											echo '<div class="updated fade event-manager-updated"><p>' . __( 'Settings successfully saved', 'wpem-rest-api' ) . '</p></div>';
										}	
										include('templates/wpem-rest-settings-panel.php'); ?>
								</div>
								<p class="submit">
										<input type="submit" class="button-primary wpem-backend-theme-button" id="save-changes" value="<?php esc_html_e( 'Save Changes', 'wpem-rest-api' ); ?>" />
								</p>
							</div>
						</div>
		    	</form>
	    	</div>
	 		</div>
		</div>
		<?php wp_enqueue_script( 'wp-event-manager-admin-settings');
	}

	/**
	 * Get all roles as [role_key => role_label] for multiselect.
	 * @access private
	 * @return array
	 * @since 1.2.0
	 */
	private function get_all_roles_for_multiselect() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}
		$roles_obj = wp_roles();
		$roles = isset( $roles_obj->roles ) ? $roles_obj->roles : array();
		$options = array();
		foreach ( $roles as $key => $data ) {
			$options[ $key ] = isset( $data['name'] ) ? $data['name'] : $key;
		}
		return $options;
	}

	/**
	 * Render a multi-select checkbox control compatible with the panel renderer.
	 * Expects $option keys: name, options (key=>label), std (array), desc
	 * @since 1.2.0
	 */
	private function create_multi_select_checkbox( $option ) {
		$saved = get_option( $option['name'] );
		if ( ! is_array( $saved ) ) {
			$saved = (array) $option['std'];
		}
		echo '<fieldset class="wpem-multicheck">';
		if ( ! empty( $option['desc'] ) ) {
			echo '<p class="description">' . esc_html( $option['desc'] ) . '</p>';
		}
		foreach ( (array) $option['options'] as $key => $label ) {
			$checked = in_array( $key, $saved, true ) ? 'checked="checked"' : '';
			echo '<label style="display:block; margin:2px 0;">';
			echo '<input type="checkbox" name="' . esc_attr( $option['name'] ) . '[]" value="' . esc_attr( $key ) . '" ' . $checked . ' /> ' . esc_html( $label );
			echo '</label>';
		}
		echo '</fieldset>';
	}
}
