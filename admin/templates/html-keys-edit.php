<?php
defined( 'ABSPATH' ) || exit; ?>

<div id="key-fields" class="settings-panel">
	<h3 class="wpem-admin-tab-title"><?php esc_html_e( 'Key details', 'wpem-rest-api' ); ?></h3>

	<input type="hidden" id="key_id" value="<?php echo esc_attr( $key_id ); ?>" />
	<div class="wpem-admin-body">
		<table id="api-keys-options" class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="key_description">
							<?php esc_html_e( 'Description', 'wpem-rest-api' ); ?>
						</label>
					</th>
					<td class="forminp">
						<input id="key_description" type="text" class="input-text regular-input" value="<?php echo esc_attr( $key_data['description'] ); ?>" />
						<p class="description"><?php _e( 'Friendly name for identifying this key.', 'wpem-rest-api' );?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="key_user"> </label>
					</th>
					<td class="forminp">
						<?php
						$event_id        = ! empty( $key_data['event_id'] ) ? absint( $key_data['event_id'] ) : '';
						$events = array(); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="key_user">
							<?php esc_html_e( 'User', 'wpem-rest-api' ); ?>
							<?php  _e( 'Owner of these keys.', 'wpem-rest-api' ); ?>
						</label>
					</th>
					<td class="forminp">
						<?php

						$all_users = get_users( );
						$user_id        = ! empty( $key_data['user_id'] ) ? absint( $key_data['user_id'] ) : ''; ?>
						<select class="event-manager-select-chosen" id="key_user" data-placeholder="<?php esc_attr_e( 'Search for a user&hellip;', 'wpem-rest-api' ); ?>" data-allow_clear="true">
							<?php
							foreach ( $all_users as $user ) { ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>"  <?php if($user->ID == $user_id )  echo 'selected="selected"';?>><?php 
							echo '#'; 
							printf(__('%d','wpem-rest-api'),$user->ID);
							echo ' ';
								printf(__('%s','wpem-rest-api'),$user->user_login); // htmlspecialchars to prevent XSS when rendered by chosen. ?></option>
							<?php
							} ?>
						</select>
						<p class="description"><?php _e( 'Name of the owner of the Key.', 'wpem-rest-api' );?></p> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="key_description">
							<?php esc_html_e( 'Expiry date', 'wpem-rest-api' ); ?>
						</label>
					</th>
					<td class="forminp">
						<?php
						//convert date and time into date format
						if( isset( $key_data['date_expires'] ) && !empty( $key_data['date_expires'] ) ) {
							$expiry_date = date( 'Y-m-d', strtotime( $key_data['date_expires'] ) ); 
						} else { 
							$expiry_date = '';  
						} ?>
						<input id="date_expires" type="text" class="input-text regular-input" value="<?php echo esc_attr($expiry_date ); ?>" />
						<p class="description">
							<?php _e( 'Set an expiry date till which the key should be activated.','wpem-rest-api' );?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="key_permissions">
							<?php esc_html_e( 'Permissions', 'wpem-rest-api' ); ?>
						</label>
					</th>
					<td class="forminp">
						<select id="key_permissions" class="wpem-enhanced-select">
							<?php
							$permissions = array(
								'read'       => __( 'Read', 'wpem-rest-api' ),
								'write'      => __( 'Write', 'wpem-rest-api' ),
								'read_write' => __( 'Read/Write', 'wpem-rest-api' ),
							);
							foreach ( $permissions as $permission_id => $permission_name ) : ?>
								<option value="<?php echo esc_attr( $permission_id ); ?>" <?php selected( $key_data['permissions'], $permission_id, true ); ?>><?php echo esc_html( $permission_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
						<?php   _e( 'Select the access type of these keys.', 'wpem-rest-api' ); ?></p>
					</td>
				</tr>

				<?php if ( 0 !== $key_id ) : ?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Consumer key ending in', 'wpem-rest-api' ); ?>
						</th>
						<td class="forminp">
							<code>&hellip;<?php echo esc_html( $key_data['truncated_key'] ); ?></code>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<?php esc_html_e( 'Last access', 'wpem-rest-api' ); ?>
						</th>
						<td class="forminp">
							<span>
								<?php
								if ( ! empty( $key_data['last_access'] ) ) {
									/* translators: 1: last access date 2: last access time */
									$date = sprintf( __( '%1$s at %2$s', 'wp-event-manager-organizer-app-access' ), date_i18n( get_option('date_format'), strtotime( $key_data['last_access'] ) ), date_i18n(get_option('time_format'), strtotime( $key_data['last_access'] ) ) );

									echo esc_html( apply_filters( 'wpem_api_key_last_access_datetime', $date, $key_data['last_access'] ) );
								} else {
									esc_html_e( 'Unknown', 'wpem-rest-api' );
								} ?>
							</span>
						</td>
					</tr>
				<?php endif ?>
			</tbody>
		</table>	
	</div>

	<?php do_action( 'wpem_admin_key_fields', $key_data ); 

	if ( 0 === intval( $key_id ) ) {
		submit_button( __( 'Generate API key', 'wp-event-manager-organizer-app-access' ), 'primary wpem-backend-theme-button', 'update_api_key' );
	} else { ?>
		<p class="submit">
			<?php submit_button( __( 'Save changes', 'wp-event-manager-organizer-app-access' ), 'primary wpem-backend-theme-button', 'update_api_key', false ); ?>
			<a class="wpem-backend-theme-button wpem-revoke-button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'revoke-key' => $key_id ), admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) ), 'revoke' ) ); ?>"><?php esc_html_e( 'Revoke key', 'wpem-rest-api' ); ?></a>
		</p>
		<?php
	} ?>
</div>

<script type="text/template" id="tmpl-api-keys-template">
	<div class="wpem-admin-body">
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'App key', 'wpem-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="app_key" type="text" value="{{ data.app_key }}" size="55" readonly="readonly">
					<button class="wpem-backend-theme-button" type="button" onClick="app_key_copy_fun()" style="cursor:pointer">Copy App Key</button>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'Consumer key', 'wpem-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="key_consumer_key" type="text" value="{{ data.consumer_key }}" size="55" readonly="readonly">
					<button class="wpem-backend-theme-button" type="button" onClick="consumer_copy_fun()" style="cursor:pointer">Copy Consumer Key</button>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'Consumer secret', 'wpem-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="key_consumer_secret" type="text" value="{{ data.consumer_secret }}" size="55" readonly="readonly">
					<button class="wpem-backend-theme-button" type="button" onClick="secret_copy_fun()" style="cursor:pointer">Copy Consumer Secret</button>
				</td>
			</tr>
			
		</tbody>
	</table>
	</div>
</script>

<script>
	function app_key_copy_fun() {
		var app_key_copy = document.getElementById( "app_key" );
		var btn_text_app = document.querySelector( '#app_key + button' );
		navigator.clipboard.writeText( app_key_copy.value );
		btn_text_app.innerHTML = 'copied';
	}
	function consumer_copy_fun() {
		var consumer_copy = document.getElementById( "key_consumer_key" );
		var btn_text_key = document.querySelector( '#key_consumer_key + button' );
		navigator.clipboard.writeText( consumer_copy.value );
		btn_text_key.innerHTML = 'copied';
	}
	function secret_copy_fun() {
		var secret_copy = document.getElementById( "key_consumer_secret" );
		var btn_text_secret = document.querySelector( '#key_consumer_secret + button' );
		navigator.clipboard.writeText( secret_copy.value );
		btn_text_secret.innerHTML = 'copied';
	}
</script>