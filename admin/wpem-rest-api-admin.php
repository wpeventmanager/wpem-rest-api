<?php
defined('ABSPATH') || exit;
/**
 * WPEM_Rest_API_Admin class used to create rest api tab in wp event manager plugin.
 */
class WPEM_Rest_API_Admin{
    
    public $settings_page;

    /**
     * __construct function.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function __construct(){

        include 'wpem-rest-api-settings.php';
        include 'wpem-rest-api-keys.php';
        include 'wpem-rest-app-branding.php';
        include 'wpem-rest-api-keys-table-list.php';

        $this->settings_page = new WPEM_Rest_API_Settings();

        //add actions
        add_action('admin_menu', array( $this, 'admin_menu' ), 10);
        add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ));
        add_filter('event_manager_admin_screen_ids', array($this,'wpem_rest_api_add_admin_screen'));

        add_action("wp_ajax_save_rest_api_keys", array($this, "update_api_key"));
    }

    /**
     * admin_enqueue_scripts function.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function admin_enqueue_scripts(){
        if( isset( $_GET['page']) && $_GET['page'] == 'wpem-rest-api-settings' ) {

            wp_enqueue_media();

            wp_register_script( 'wpem-rest-api-admin-js', WPEM_REST_API_PLUGIN_URL. '/assets/js/admin.min.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'wp-util','wp-color-picker' ), WPEM_REST_API_VERSION, true );
            wp_localize_script(
                'wpem-rest-api-admin-js', 'wpem_rest_api_admin', array(            
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'save_api_nonce' =>  wp_create_nonce( 'save-api-key' ),
                    'save_app_branding_nonce' =>  wp_create_nonce( 'save-api-branding' ),
                ) 
            );

            wp_enqueue_style( 'jquery-ui' );  
            wp_enqueue_style( 'jquery-ui-style', EVENT_MANAGER_PLUGIN_URL. '/assets/js/jquery-ui/jquery-ui.min.css', array() );
        }
    }

    /**
     * admin_enqueue_scripts function.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function wpem_rest_api_add_admin_screen( $screen_ids ){
        $screen_ids[]='event_listing_page_wpem-rest-api-settings';
        return $screen_ids;
    }    

    /**
     * admin_menu function.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function admin_menu(){
        add_submenu_page( 'edit.php?post_type=event_listing', __( 'Rest API Settings', 'wpem-rest-api' ), __( 'Rest API', 'wpem-rest-api' ), 'manage_options', 'wpem-rest-api-settings', array( $this->settings_page, 'output' ) );
    }

    /**
     * Create/Update API key.
     *
     * @throws Exception On invalid or empty description, user, or permissions.
     */
    public  function update_api_key(){
        ob_start();

        global $wpdb;

        check_ajax_referer('save-api-key', 'security');
        $response = array();
        try {
            if( empty( $_POST['description'] ) ) {
                throw new Exception( __( 'Description is missing.', 'wpem-rest-api' ) );
            }
            if( empty($_POST['user']) ) {
                throw new Exception( __( 'User is missing.', 'wpem-rest-api' ) );
            }
            if( empty($_POST['permissions']) ) {
                throw new Exception( __( 'Permissions is missing.', 'wpem-rest-api' ) );
            }

            $key_id      = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
            $description = sanitize_text_field( wp_unslash( $_POST['description'] ) );
            $permissions = ( in_array( wp_unslash( $_POST['permissions'] ), array( 'read', 'write', 'read_write' ), true ) ) ? sanitize_text_field( wp_unslash( $_POST['permissions'] ) ) : 'read';
            $user_id      = absint( $_POST['user'] );
            $event_id     = !empty( $_POST['event_id'] ) ?  absint( $_POST['event_id'] ) : '' ;
            $date_expires = !empty( $_POST['date_expires'] ) ?  date( 'Y-m-d H:i:s', strtotime( str_replace( '-', '/', $_POST['date_expires'] ) ) ) : null ;
            $restrict_check_in = isset( $_POST['restrict_check_in'] ) ? sanitize_text_field( $_POST['restrict_check_in'] ) : '';
            $event_show_by = isset($_POST['event_show_by']) ? sanitize_text_field($_POST['event_show_by']) : 'loggedin';
			$select_events = isset($_POST['select_events']) ? maybe_serialize(array_map('absint', $_POST['select_events'])) : maybe_serialize(array());
            $mobile_menu = isset($_POST['mobile_menu']) ? array_map('sanitize_text_field', $_POST['mobile_menu']) : array();
            
            update_user_meta($user_id, '_mobile_menu', $mobile_menu);
            
            // Check if current user can edit other users.
            if( $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
                if( get_current_user_id() !== $user_id ) {
                    throw new Exception( __( 'You do not have permission to assign API Keys to the selected user.', 'wpem-rest-api' ) );
                }
            }

            if( 0 < $key_id ) {
                $data = array(
                    'user_id'           => $user_id,
                    'description'       => $description,
                    'permissions'       => $permissions,
                    'event_id'          => $event_id,
                    'date_expires'      => $date_expires,
                    'event_show_by'     => $event_show_by,
					'selected_events'   => $select_events,
                );

                $wpdb->update(
                    $wpdb->prefix . 'wpem_rest_api_keys',
                    $data,
                    array( 'key_id' => $key_id ),
                        array(
                            '%d',
                            '%s',
                            '%s',
                            '%d',
                            '%s',
                            '%s',
							'%s',
                        ),
                    array( '%d' )
                );
                update_user_meta( $user_id, '_restrict_check_in', $restrict_check_in );
                $response                    = $data;
                $response['consumer_key']    = '';
                $response['consumer_secret'] = '';
                $response['message']         = __( 'API Key updated successfully.', 'wpem-rest-api' );
                $response['selected_events'] = maybe_unserialize($select_events);
            } else {
                $app_key = wp_rand();
                $consumer_key    = 'ck_' . sha1(wp_rand());
                $consumer_secret = 'cs_' . sha1(wp_rand());

                $data = array(
                    'user_id'         => $user_id,
                    'app_key'         => $app_key,
                    'description'     => $description,
                    'permissions'     => $permissions,
                    'event_id'        => $event_id,
                    'consumer_key'    => $consumer_key ,
                    'consumer_secret' => $consumer_secret,
                    'truncated_key'   => substr($consumer_key, -7),
                    'date_created'    => current_time( 'mysql' ) ,
                    'date_expires'    => $date_expires,
                    'event_show_by'    => $event_show_by,
					'selected_events' => $select_events,
                );
                $wpdb->insert(
                    $wpdb->prefix . 'wpem_rest_api_keys',
                    $data,
                    array(
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                    )
                );
                $key_id                      = $wpdb->insert_id;
                $response                    = $data;
                $response['consumer_key']    = $consumer_key;
                $response['consumer_secret'] = $consumer_secret;
                $response['app_key']         = $app_key;
                $response['message']         = __( 'API Key generated successfully. Make sure to copy your new keys now as the secret key will be hidden once you leave this page.', 'wpem-rest-api' );
                $response['revoke_url']      = '<a class="wpem-backend-theme-button" href="' . esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) ) . '">' . __('I have Copied the Keys', 'wpem-rest-api') . '</a> <br/><br/> <a class="wpem-backend-theme-button wpem-revoke-button" href="' . esc_url(wp_nonce_url(add_query_arg(array( 'revoke-key' => $key_id ), admin_url('edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access')), 'revoke')) . '">' . __('Revoke key', 'wpem-rest-api') . '</a>';
                $response['event_show_by'] = $event_show_by;
				$response['selected_events'] = maybe_unserialize($select_events);
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
        // wp_send_json_success must be outside the try block not to break phpunit tests.
        wp_send_json_success($response);
    }
}
new WPEM_Rest_API_Admin();