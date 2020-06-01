<?php
/**
 * Admin view: Edit API keys
 *
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="key-fields" class="settings-panel">
	<h2><?php esc_html_e( 'Key details', 'wp-event-manager-organizer-app-access' ); ?></h2>

	<input type="hidden" id="key_id" value="<?php echo esc_attr( $key_id ); ?>" />

	<table id="api-keys-options" class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="key_description">
						<?php esc_html_e( 'Description', 'wp-event-manager-organizer-app-access' ); ?>
						<?php  _e( 'Friendly name for identifying this key.', 'wp-event-manager-organizer-app-access' ); ?>
					</label>
				</th>
				<td class="forminp">
					<input id="key_description" type="text" class="input-text regular-input" value="<?php echo esc_attr( $key_data['description'] ); ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="key_user">
						<?php esc_html_e( 'Event', 'wp-event-manager-rest-api' ); ?>
						
					</label>
				</th>
				<td class="forminp">
					<?php
					$event_id        = ! empty( $key_data['event_id'] ) ? absint( $key_data['event_id'] ) : '';
					$events = get_posts(array(
											'post_type'        => 'event_listing',
											'numberposts' =>-1,
											'author'        =>  get_current_user_id(),
										));
				
					?>
					<select class="wpem-enhanced-select" id="event_id" data-placeholder="<?php esc_attr_e( 'Search for a user&hellip;', 'wp-event-manager-rest-api' ); ?>" data-allow_clear="true">
						<option value=""><?php _e('All Events','wp-event-manager-rest-api');?></option>
						<?php foreach ($events as $keys => $event) { ?>
							<option value="<?php echo esc_attr( $event->ID ); ?>" <?php if($event_id == $event->ID )  echo 'selected="selected"';?>><?php echo htmlspecialchars( wp_kses_post($event->post_title ) ); // htmlspecialchars to prevent XSS when rendered by chosen. ?></option>
						<?php
						}
						?>
						
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="key_user">
						<?php esc_html_e( 'User', 'wp-event-manager-organizer-app-access' ); ?>
						<?php  _e( 'Owner of these keys.', 'wp-event-manager-organizer-app-access' ); ?>
					</label>
				</th>
				<td class="forminp">
					<?php
					$curent_user_id = get_current_user_id();
					$user_id        = ! empty( $key_data['user_id'] ) ? absint( $key_data['user_id'] ) : $curent_user_id;
					$user           = get_user_by( 'id', $user_id );
					$user_string    = sprintf(
						/* translators: 1: user display name 2: user ID 3: user email */
						esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'wp-event-manager-rest-api' ),
						$user->display_name,
						absint( $user->ID ),
						$user->user_email
					);
					?>
					<select class="event-manager-select-chosen" id="key_user" data-placeholder="<?php esc_attr_e( 'Search for a user&hellip;', 'wp-event-manager-rest-api' ); ?>" data-allow_clear="true">
						<option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo htmlspecialchars( wp_kses_post( $user_string ) ); // htmlspecialchars to prevent XSS when rendered by chosen. ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="key_description">
						<?php esc_html_e( 'Expiry date', 'wp-event-manager-rest-api' ); ?>
					</label>
				</th>
				<td class="forminp">
					<input id="date_expires" type="text" class="input-text regular-input" value="<?php echo esc_attr( $key_data['date_expires'] ); ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="key_permissions">
						<?php esc_html_e( 'Permissions', 'wp-event-manager-rest-api' ); ?>
						<?php   _e( 'Select the access type of these keys.', 'wp-event-manager-rest-api' ); ?>
					</label>
				</th>
				<td class="forminp">
					<select id="key_permissions" class="wpem-enhanced-select">
						<?php
						$permissions = array(
							'read'       => __( 'Read', 'wp-event-manager-rest-api' ),
							'write'      => __( 'Write', 'wp-event-manager-rest-api' ),
							'read_write' => __( 'Read/Write', 'wp-event-manager-rest-api' ),
						);

						foreach ( $permissions as $permission_id => $permission_name ) :
							?>
							<option value="<?php echo esc_attr( $permission_id ); ?>" <?php selected( $key_data['permissions'], $permission_id, true ); ?>><?php echo esc_html( $permission_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<?php if ( 0 !== $key_id ) : ?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<?php esc_html_e( 'Consumer key ending in', 'wp-event-manager-rest-api' ); ?>
					</th>
					<td class="forminp">
						<code>&hellip;<?php echo esc_html( $key_data['truncated_key'] ); ?></code>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<?php esc_html_e( 'Last access', 'wp-event-manager-rest-api' ); ?>
					</th>
					<td class="forminp">
						<span>
						<?php
						if ( ! empty( $key_data['last_access'] ) ) {
							/* translators: 1: last access date 2: last access time */
							$date = sprintf( __( '%1$s at %2$s', 'wp-event-manager-organizer-app-access' ), date_i18n( get_option('date_format'), strtotime( $key_data['last_access'] ) ), date_i18n(get_option('time_format'), strtotime( $key_data['last_access'] ) ) );

							echo esc_html( apply_filters( 'wpem_api_key_last_access_datetime', $date, $key_data['last_access'] ) );
						} else {
							esc_html_e( 'Unknown', 'wp-event-manager-organizer-app-access' );
						}
						?>
						</span>
					</td>
				</tr>
			<?php endif ?>
		</tbody>
	</table>

	<?php do_action( 'wpem_admin_key_fields', $key_data ); ?>

	<?php
	if ( 0 === intval( $key_id ) ) {
		submit_button( __( 'Generate API key', 'wp-event-manager-organizer-app-access' ), 'primary', 'update_api_key' );
	} else {
		?>
		<p class="submit">
			<?php submit_button( __( 'Save changes', 'wp-event-manager-organizer-app-access' ), 'primary', 'update_api_key', false ); ?>
			<a style="color: #a00; text-decoration: none; margin-left: 10px;" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'revoke-key' => $key_id ), admin_url( 'edit.php?post_type=event_listing&page=event-manager-organizer-app-access-settings' ) ), 'revoke' ) ); ?>"><?php esc_html_e( 'Revoke key', 'wp-event-manager-organizer-app-access' ); ?></a>
		</p>
		<?php
	}
	?>
</div>

<script type="text/template" id="tmpl-api-keys-template">
	<p id="copy-error"></p>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'App key', 'wp-event-manager-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="key_consumer_key" type="text" value="{{ data.app_key }}" size="55" readonly="readonly">
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'Consumer key', 'wp-event-manager-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="key_consumer_key" type="text" value="{{ data.consumer_key }}" size="55" readonly="readonly">
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php esc_html_e( 'Consumer secret', 'wp-event-manager-rest-api' ); ?>
				</th>
				<td class="forminp">
					<input id="key_consumer_secret" type="text" value="{{ data.consumer_secret }}" size="55" readonly="readonly">
				</td>
			</tr>
			
		</tbody>
	</table>
</script>


