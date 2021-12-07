<?php
if (! defined('ABSPATH') ) {
    exit;
}

/**
 * WPEM_Rest_API_Dashboard class.
 */
class WPEM_Rest_API_Dashboard
{

    /**
     * __construct function.
     */
    public function __construct()
    {

        /*add_filter( 'wpem_dashboard_menu', array($this,'wpem_dashboard_menu_add') );
        add_action( 'event_manager_event_dashboard_content_wpem_rest_api_setting', array( $this, 'wpem_rest_api_output_setting' ) );*/
    }

    /**
     * add dashboard menu function.
     *
     * @access public
     * @return void
     */
    public function wpem_dashboard_menu_add($menus) 
    {
        $menus['rest_api'] = [
         'title' => __('Rest API', 'wp-event-manager'),
         'icon' => 'wpem-icon-loop',
         'submenu' => [
          'wpem_rest_api_setting' => [
                                'title' => __('Settings', 'wp-event-manager'),
                                'query_arg' => ['action' => 'wpem_rest_api_setting'],
          ],
         ]
                    ];
        return $menus;
    }

    /**
     * Show dashboard menu content function.
     *
     * @access public
     * @return void
     */
    public function wpem_rest_api_output_setting()
    {
        get_event_manager_template( 
            'wpem-dashboard-rest-api-settings.php', array(), 'wpem-rest-api', 
            WPEM_REST_API_PLUGIN_DIR . '/templates/' 
        );

    }

}
new WPEM_Rest_API_Dashboard();
