<?php
/**
* Plugin Name: WP Event Manager - REST API
* Plugin URI: http://www.wp-eventmanager.com/plugins/
* 
* Description: Lets users connect the Mobile application with their WordPress events website.
* Author: WP Event Manager
* Author URI: http://www.wp-eventmanager.com
* 
* Text Domain: wpem-rest-api
* Domain Path: /languages
* Version: 1.1.2
* Since: 1.0.0
* 
* Requires WordPress Version at least: 6.5.1
* Copyright: 2019 WP Event Manager
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* 
*/

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH.'wp-admin/includes/plugin.php';
/**
 * WP_Event_Manager_Rest_API class.
 */
class WPEM_Rest_API{

    /**
     * __construct function.
     */
    public function __construct()    {
        //if wp event manager not active return from the plugin
        if( !in_array( 'wp-event-manager/wp-event-manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            return;
        }

        // Define constants
        define( 'WPEM_REST_API_VERSION', '1.1.2' );
        define( 'WPEM_REST_API_FILE', __FILE__ );
        define( 'WPEM_REST_API_PLUGIN_DIR', untrailingslashit( plugin_dir_path(__FILE__ ) ) );
        define( 'WPEM_REST_API_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path(__FILE__) ), basename(__FILE__) ) ) );
        define( 'WPEM_PLUGIN_ACTIVATION_API_URL', 'https://wp-eventmanager.com/?wc-api=wpemstore_licensing_expire_license' );
        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', '9s59d4s9d49ed94sf46dsf74d96');
        }
        if (!defined('JWT_ALGO')) {
            define('JWT_ALGO', 'HS256');
        }
        if( is_admin() ) {
            include 'admin/wpem-rest-api-admin.php';
        }
        //include
        include 'wpem-rest-api-functions.php';

        include 'includes/wpem-rest-api-dashboard.php';
        include 'includes/wpem-rest-conroller.php';
        include 'includes/wpem-rest-posts-conroller.php';
        include 'includes/wpem-rest-crud-controller.php';
        include 'includes/wpem-rest-authentication.php';        
        include 'includes/wpem-rest-events-controller.php';
        include 'includes/wpem-rest-app-branding.php';
        include 'includes/wpem-rest-ecosystem-controller.php';

        // match making api
        include 'includes/wpem-rest-matchmaking-profile.php';
		include 'includes/wpem-rest-matchmaking-get-texonomy.php';
        include 'includes/wpem-rest-matchmaking-user-messages.php';
        include 'includes/wpem-rest-matchmaking-filter-users.php';
        include 'includes/wpem-rest-matchmaking-user-settings.php';
        include 'includes/wpem-rest-matchmaking-create-meetings.php';
        include 'includes/wpem-rest-matchmaking-user-registred-events.php';

        // Activate
        register_activation_hook( __FILE__, array( $this, 'install' ) );

        // Add actions
        add_action( 'init', array( $this, 'load_plugin_textdomain' ), 12 );

        // Call when update plugin
        add_action('admin_init', array($this, 'updater'));
    }

    /**
     * Localisation
     **/
    public function load_plugin_textdomain(){
        $domain = 'wpem-rest-api';
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );
        load_textdomain( $domain, WP_LANG_DIR . "/wpem-rest-api/".$domain."-" .$locale. ".mo" );
        load_plugin_textdomain( $domain, false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
    }

    /**
	 * Handle Updates.
	 * @since 1.1.2
	 */
	public function updater() {
        if(version_compare(get_option('wpem_rest_api_version', WPEM_REST_API_VERSION), '1.0.9', '<')) {
			$this->check_rest_api_table();
			flush_rewrite_rules();
		}
	}

    /**
     * Check rest api table
     * @since 1.1.2
     * @return void
     */
    public function check_rest_api_table() {
         global $wpdb;

        $wpdb->hide_errors();
        $collate = '';

        if( $wpdb->has_cap( 'collation' ) ) {
            if( !empty( $wpdb->charset ) ) {
                $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }
            if( !empty( $wpdb->collate ) ) {
                $collate .= " COLLATE $wpdb->collate";
            }
        }

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table for storing licence keys for purchases
        $sql = "
            CREATE TABLE {$wpdb->prefix}wpem_rest_api_keys (
            key_id BIGINT UNSIGNED NOT NULL auto_increment,
            app_key varchar(200) NOT NULL,	
            user_id BIGINT UNSIGNED NOT NULL,
            event_id varchar(255) NULL,
            description varchar(200) NULL,
            permissions varchar(10) NOT NULL,
            consumer_key char(64) NOT NULL,
            consumer_secret char(43) NOT NULL,
            nonces longtext NULL,
            truncated_key char(7) NOT NULL,
            last_access datetime NULL default null,
            event_show_by varchar(20) NULL default 'loggedin',
			selected_events longtext NULL,
            date_created datetime NULL default null,
            date_expires datetime NULL default null,
            PRIMARY KEY  (key_id),
            KEY consumer_key (consumer_key),
            KEY consumer_secret (consumer_secret)
            ) $collate;";

        dbDelta( $sql );
        
        // Check if we need to alter existing table
		$table_name = $wpdb->prefix . 'wpem_rest_api_keys';
		$columns = $wpdb->get_col("DESC {$table_name}", 0);
		
		// Add event_show_by column if it doesn't exist
		if (!in_array('event_show_by', $columns)) {
			$wpdb->query("ALTER TABLE {$table_name} ADD COLUMN event_show_by varchar(20) NULL DEFAULT 'loggedin'");
		}
		
		// Add selected_events column if it doesn't exist
		if (!in_array('selected_events', $columns)) {
			$wpdb->query("ALTER TABLE {$table_name} ADD COLUMN selected_events longtext NULL");
		}

        update_option( 'wpem_rest_api_version', WPEM_REST_API_VERSION );
        
        //check for Application Name is already defined
        if( empty( get_option( 'wpem_rest_api_app_name' ) ) ) {
            update_option( 'wpem_rest_api_app_name', 'WP Event Manager' );
        };
    }
    /**
     * Install
     */
    public function install(){
       $this->check_rest_api_table();
    }
}

// check for WP Event Manager is active
if( is_plugin_active( 'wp-event-manager/wp-event-manager.php' ) ) {
    $GLOBALS['wpem_rest_api'] = new WPEM_Rest_API();
}

/**
 * Check if WP Event Manager is not active then show notice at admin
 * @since 1.0.0
 */
function wpem_rest_api_pre_check_before_installing_event_rest_api() {
    /*
    * Check weather WP Event Manager is installed or not
    */
    if( !in_array( 'wp-event-manager/wp-event-manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            global $pagenow;
        if($pagenow == 'plugins.php' ) {
            echo '<div id="error" class="error notice is-dismissible"><p>';
            echo __( 'WP Event Manager is require to use WP Event Manager Rest API ', 'wpem-rest-api' );
            echo '</p></div>';
        }
        return false;
    }
}
add_action( 'admin_notices', 'wpem_rest_api_pre_check_before_installing_event_rest_api' );
