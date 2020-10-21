<?php
/**
 * WPEM Admin API Keys Class
 *
 * 
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPEM_Rest_APP_Branding.
 */
class WPEM_Rest_APP_Branding {

	/**
	 * Initialize the API Keys admin actions.
	 */
	public function __construct() 
	{
		// Ajax
		add_action( 'wp_ajax_change_brighness_color', array( $this, 'change_brighness_color' ) );
	}

	/**
	 * Page output.
	 */
	public static function page_output() {
		// Hide the save button.
		$GLOBALS['hide_save_button'] = true;
		wp_enqueue_script('wpem-rest-api-admin-js');

		$primary_color 	= !empty(get_option('wpem_primary_color')) ? get_option('wpem_primary_color') : '#3366FF';
		$success_color 	= !empty(get_option('wpem_success_color')) ? get_option('wpem_success_color') : '#77DD37';
		$info_color 	= !empty(get_option('wpem_info_color')) ? get_option('wpem_info_color') : '#42BCFF';
		$warning_color 	= !empty(get_option('wpem_warning_color')) ? get_option('wpem_warning_color') : '#FCD837';
		$danger_color 	= !empty(get_option('wpem_danger_color')) ? get_option('wpem_danger_color') : '#FC4C20';

		include dirname( __FILE__ ) . '/templates/html-app-branding.php';
	}
	

	/**
	 * change brighness color
	 *
	 * @param  int $key_id API Key ID.
	 * @return bool
	 */
	public function change_brighness_color() 
	{
		$output = '';
		if( isset($_REQUEST['color']) && !empty($_REQUEST['color']) )
		{
			$color_code = $_REQUEST['color'];

			for($i=1;$i<10;$i++)
			{
				$code = wpem_color_brightness($color_code, $i/10);
				$brightness = (1000 - $i*100) ;

				if($i < 5)
				{
					$output .= '<div class="wpem-color-pallet-wrapper"><div>'. $brightness .'</div> <div class="wpem-color-pallet" style="background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div></div>';
				}
				else
				{
					$output .= '<div class="wpem-color-pallet-wrapper"><div>'. $brightness .'</div> <div class="wpem-color-pallet" style="background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$danger_color.'">'.$code.'</span></div></div>';
				}
			}
		}

		echo $output;

        wp_die();
	}


}

new WPEM_Rest_APP_Branding();
