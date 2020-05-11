<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WPEM_Rest_API_Admin class.
 */
class WPEM_Rest_API_Admin {
	/**
	 * __construct function.
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		include( 'wpem-rest-api-settings.php' );
		include( 'wpem-rest-api-keys.php' );
		include( 'wpem-rest-api-keys-table-list.php' );

		$this->settings_page = new WPEM_Rest_API_Settings();

		//add actions
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		
		add_action("wp_ajax_save_rest_api_keys",array($this, "update_api_key") );

	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */

	public function admin_enqueue_scripts() {
			wp_register_script( 'wpem-rest-api-admin-js', WPEM_REST_API_PLUGIN_URL. '/assets/js/admin.js', array( 'jquery',), WPEM_REST_API_VERSION, true );
			 wp_localize_script( 'wpem-rest-api-admin-js', 'wpem_rest_api_admin', array(
			
			 	'ajaxUrl' => admin_url('admin-ajax.php'),
			 	'save_api_nonce' =>  wp_create_nonce( 'save-api-key' ),
			 	) );
			
			
		}	
		


	/**
	 * admin_menu function.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		
		//add_submenu_page( 'edit.php?post_type=event_listing', __( 'App Access', 'wp-event-manager' ), __( 'App Access', 'wp-event-manager' ), 'manage_options', 'event-manager-organizer-app-access-settings', array( $this->settings_page, 'output' ) );
		add_submenu_page( 'edit.php?post_type=event_listing', __( 'Rest API', 'wp-event-manager-rest-api' ), __( 'Rest API', 'wp-event-manager-rest-api' ), 'manage_options', 'wpem-rest-api-key-settings', array( 'WPEM_Rest_API_Keys', 'page_output' ) );
	}


	/**
	 * Create/Update API key.
	 *
	 * @throws Exception On invalid or empty description, user, or permissions.
	 */
	public  function update_api_key() {
		ob_start();

		global $wpdb;

		check_ajax_referer( 'save-api-key', 'security' );

		$response = array();

		try {
			if ( empty( $_POST['description'] ) ) {
				throw new Exception( __( 'Description is missing.', 'wp-event-manager-rest-api' ) );
			}
			if ( empty( $_POST['user'] ) ) {
				throw new Exception( __( 'User is missing.', 'wp-event-manager-rest-api' ) );
			}
			if ( empty( $_POST['permissions'] ) ) {
				throw new Exception( __( 'Permissions is missing.', 'wp-event-manager-rest-api' ) );
			}

			$key_id      = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
			$description = sanitize_text_field( wp_unslash( $_POST['description'] ) );
			$permissions = ( in_array( wp_unslash( $_POST['permissions'] ), array( 'read', 'write', 'read_write' ), true ) ) ? sanitize_text_field( wp_unslash( $_POST['permissions'] ) ) : 'read';
			$user_id     = absint( $_POST['user'] );

			// Check if current user can edit other users.
			if ( $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
				if ( get_current_user_id() !== $user_id ) {
					throw new Exception( __( 'You do not have permission to assign API Keys to the selected user.', 'wp-event-manager-rest-api' ) );
				}
			}

			if ( 0 < $key_id ) {
				$data = array(
					'user_id'     => $user_id,
					'description' => $description,
					'permissions' => $permissions,
				);

				$wpdb->update(
					$wpdb->prefix . 'wpem_rest_api_keys',
					$data,
					array( 'key_id' => $key_id ),
					array(
						'%d',
						'%s',
						'%s',
					),
					array( '%d' )
				);

				$response                    = $data;
				$response['consumer_key']    = '';
				$response['consumer_secret'] = '';
				$response['message']         = __( 'API Key updated successfully.', 'wp-event-manager-rest-api' );
			} else {
				$app_key = wp_rand();
				$consumer_key    = 'ck_' . sha1( wp_rand() );
				$consumer_secret = 'cs_' . sha1( wp_rand() );

				$data = array(
					'user_id'         => $user_id,
					'app_key'         => $app_key,
					'description'     => $description,
					'permissions'     => $permissions,
					'consumer_key'    =>  wpem_api_hash($consumer_key) ,
					'consumer_secret' => $consumer_secret,
					'truncated_key'   => substr( $consumer_key, -7 ),
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
					)
				);

				$key_id                      = $wpdb->insert_id;
				$response                    = $data;
				$response['consumer_key']    = $consumer_key;
				$response['consumer_secret'] = $consumer_secret;
				$response['message']         = __( 'API Key generated successfully. Make sure to copy your new keys now as the secret key will be hidden once you leave this page.', 'wp-event-manager-rest-api' );
				$response['revoke_url']      = '<a style="color: #a00; text-decoration: none;" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'revoke-key' => $key_id ), admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-key-settings' ) ), 'revoke' ) ) . '">' . __( 'Revoke key', 'wp-event-manager-rest-api' ) . '</a>';
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		// wp_send_json_success must be outside the try block not to break phpunit tests.
		wp_send_json_success( $response );

		wp_redirect(admin_url('edit.php?post_type=event_listing&page=wpem-rest-api-key-settings'));

	}
}
new WPEM_Rest_API_Admin();


