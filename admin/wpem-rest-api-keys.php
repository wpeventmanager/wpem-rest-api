<?php
defined('ABSPATH') || exit;

/**
 * WPEM_Rest_API_Keys class used to show rest api key list table.
 */
class WPEM_Rest_API_Keys {

    /**
     * Initialize the API Keys admin actions.
     */
    public function __construct(){
        add_action( 'admin_init', array( $this, 'actions' ) );
    }

    /**
     * Check if should allow save settings.
     * This prevents "Your settings have been saved." notices on the table list.
     *
     * @param  bool $allow If allow save settings.
     * @return bool
     */
    public function allow_save_settings( $allow ) {
        if( !isset( $_GET['create-key'], $_GET['edit-key'] ) ) { // WPCS: input var okay, CSRF ok.
            return false;
        }

        return $allow;
    }

    /**
     * Check if is API Keys settings page.
     *
     * @return bool
     */
    private function is_api_keys_settings_page() {
        return isset( $_GET['page'] ) &&  'wpem-rest-api-settings' === $_GET['page'] ; // WPCS: input var okay, CSRF ok.
    }

    /**
     * Page output.
     */
    public static function page_output(){
        // Hide the save button.
        $GLOBALS['hide_save_button'] = true;
        wp_enqueue_script( 'wpem-rest-api-admin-js' );

        if( isset( $_GET['create-key'] ) || isset( $_GET['edit-key'] ) ) {
        
            $key_id   = isset( $_GET['edit-key'] ) ? absint( $_GET['edit-key'] ) : 0; // WPCS: input var okay, CSRF ok.
            $key_data = self::get_key_data( $key_id );
            $user_id  = (int) $key_data['user_id'];

            if ( $key_id && $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
                if ( get_current_user_id() !== $user_id ) {
                    wp_die( esc_html__( 'You do not have permission to edit this API Key', 'wpem-rest-api' ) );
                }
            }
            include dirname(__FILE__) . '/templates/html-keys-edit.php';
        } else {
            self::table_list_output();
        }
    }

    /**
     * Add screen option.
     */
    public function screen_option() {
        global $keys_table_list;

        if( !isset( $_GET['create-key'] ) && !isset( $_GET['edit-key'] ) && $this->is_api_keys_settings_page() ) { // WPCS: input var okay, CSRF ok.
            $keys_table_list = new WPEM_API_Keys_Table_List();

            // Add screen option.
            add_screen_option(
                'per_page',
                array(
                    'default' => 10,
                    'option'  => 10,
                )
            );
        }
        self::page_output();
    }

    /**
     * Table list output.
     */
    private static function table_list_output() {
        global $wpdb, $keys_table_list;
 		$keys_table_list = new WPEM_API_Keys_Table_List();

        $add_key = false;
        $all_users = get_wpem_event_users();
        global $wpdb;
        $app_user = $wpdb->get_col("SELECT user_id FROM {$wpdb->prefix}wpem_rest_api_keys");
        $user_id        = ! empty( $key_data['user_id'] ) ? absint( $key_data['user_id'] ) : '';
        foreach ( $all_users as $user ) { 
			if(!in_array($user['ID'], $app_user) || $user_id == $user['ID']) {
                $add_key = true;
                break;
            }
        }
        if($add_key)
            echo '<h3 class="wpem-admin-tab-title">' . esc_html__( 'REST API', 'wpem-rest-api' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access&create-key=1' ) ) . '" class="add-new-h2 wpem-backend-theme-button">' . esc_html__( 'Add Key', 'wpem-rest-api' ) . '</a></h3>';
        else
            echo '<h3 class="wpem-admin-tab-title">' . esc_html__( 'REST API', 'wpem-rest-api' ) . '</h3>';
        // Get the API keys count.
        $count = $wpdb->get_var( "SELECT COUNT(key_id) FROM {$wpdb->prefix}wpem_rest_api_keys WHERE 1 = 1;" );

        if (absint($count) && $count > 0 ) {
            $keys_table_list->prepare_items();
            $keys_table_list->views();
            $keys_table_list->search_box(__( 'Search key', 'wp-event-manager-organizer-app-access' ), 'key' );
            $keys_table_list->display();

        } else {
            echo '<div class="wpem-rest-api-BlankState wpem-rest-api-BlankState--api wpem-admin-body">'; ?>
            <div class="wpem-no-api-wrap">
                <div class="wpem-no-api-icon">
                    <span class="dashicons dashicons-cloud-saved"></span>
                </div>
            <h2 class="wpem-rest-api-BlankState-message"><?php esc_html_e( 'Enable and generate Rest API keys.', 'wpem-rest-api' ); ?></h2>
            <a class="wpem-rest-api-BlankState-cta button-primary wpem-backend-theme-button button" href="<?php echo esc_url(admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access&create-key=1' ) ); ?>"><?php esc_html_e( 'Create an API key', 'wpem-rest-api' ); ?></a>
            </div>
            <style type="text/css">#posts-filter .wp-list-table, #posts-filter .tablenav.top, .tablenav.bottom .actions { display: none; }</style>
            <?php
        }
        echo '</div>';
    }

    /**
     * Get key data.
     *
     * @param  int $key_id API Key ID.
     * @return array
     */
    private static function get_key_data( $key_id ){
        global $wpdb;

        $empty = array(
            'key_id'        => 0,
            'user_id'       => '',
            'event_id'      => '',
            'description'   => '',
            'permissions'   => '',
            'truncated_key' => '',
            'last_access'   => '',
            'date_expires'  => '',
        );

        if ( 0 === $key_id ) {
            return $empty;
        }

        $key = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT key_id, user_id, event_id, description, permissions, truncated_key, last_access, event_show_by, selected_events, date_expires
				FROM {$wpdb->prefix}wpem_rest_api_keys
				WHERE key_id = %d",
                $key_id
            ),
            ARRAY_A
        );

        if ( is_null( $key ) ) {
            return $empty;
        }

        return $key;
    }

    /**
     * API Keys admin actions.
     */
    public function actions() {
        if ( $this->is_api_keys_settings_page() ) {
            // Revoke key.
            if( isset( $_REQUEST['revoke-key'] ) ) { // WPCS: input var okay, CSRF ok.
                $this->revoke_key();
            }

            // Bulk actions.
            if( isset( $_REQUEST['action'] ) && isset($_REQUEST['key'] ) ) { // WPCS: input var okay, CSRF ok.
                $this->bulk_actions();
            }
        }
    }

    /**
     * Notices.
     */
    public static function notices() {
        if( isset( $_GET['revoked'] ) ) { // WPCS: input var okay, CSRF ok.
            $revoked = absint( $_GET['revoked'] ); // WPCS: input var okay, CSRF ok.

            /* translators: %d: count */
            sprintf( _n( '%d API key permanently revoked.', '%d API keys permanently revoked.', $revoked, 'wpem-rest-api' ), $revoked );
        }
    }

    /**
     * Revoke key.
     */
    private function revoke_key(){
        global $wpdb;
        check_admin_referer('revoke');

        if ( isset( $_REQUEST['revoke-key'] ) ) { // WPCS: input var okay, CSRF ok.
            $key_id  = absint( $_REQUEST['revoke-key'] ); // WPCS: input var okay, CSRF ok.
            $user_id = (int) $wpdb->get_var($wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}wpem_rest_api_keys WHERE key_id = %d", $key_id ) );

            if ( $key_id && $user_id && ( current_user_can( 'edit_user', $user_id ) || get_current_user_id() === $user_id ) ) {
                $this->remove_key( $key_id );
            } else {
                wp_die( esc_html__( 'You do not have permission to revoke this API Key', 'wpem-rest-api' ) );
            }
        }

        wp_safe_redirect( esc_url_raw( add_query_arg( array( 'revoked' => 1 ), admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) ) ) );
        exit();
    }

    /**
     * Bulk actions.
     */
    private function bulk_actions() {
        if( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to edit API Keys', 'wpem-rest-api' ) );
        }

        if ( isset( $_REQUEST['action'] ) ) { // WPCS: input var okay, CSRF ok.
            $action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ); // WPCS: input var okay, CSRF ok.
            $keys   = isset( $_REQUEST['key'] ) ? array_map( 'absint', (array) $_REQUEST['key']) : array(); // WPCS: input var okay, CSRF ok.

            if ( 'revoke' === $action ) {
                $this->bulk_revoke_key( $keys );
            }
        }
    }

    /**
     * Bulk revoke key.
     *
     * @param array $keys API Keys.
     */
    private function bulk_revoke_key( $keys ) {
        if( !current_user_can( 'remove_users' ) ) {
            wp_die( esc_html__( 'You do not have permission to revoke API Keys', 'wpem-rest-api' ) );
        }

        $qty = 0;
        foreach ( $keys as $key_id ) {
            $result = $this->remove_key( $key_id);
            if( $result ) {
                $qty++;
            }
        }

        // Redirect to webhooks page.
        wp_safe_redirect( esc_url_raw( add_query_arg( array( 'revoked' => $qty ), admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) ) ) );
        exit();
    }

    /**
     * Remove key.
     *
     * @param  int $key_id API Key ID.
     * @return bool
     */
    private function remove_key( $key_id ) {
        global $wpdb;
        $delete = $wpdb->delete( $wpdb->prefix . 'wpem_rest_api_keys', array( 'key_id' => $key_id ), array( '%d' ) );
        return $delete;
    }
}
new WPEM_Rest_API_Keys();
