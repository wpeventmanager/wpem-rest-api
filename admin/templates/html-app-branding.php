<?php
/**
 * Admin view: Edit API keys
 *
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="key-fields" class="settings-panel">
	<h2><?php esc_html_e( 'App Branding', 'wp-event-manager-rest-api' ); ?></h2>

	<div class="app-branding-mode">
		<div class="wpem-light-mode button">Day</div>
		<div class="wpem-dark-mode button">Night</div>
	</div>

	<table id="app-branding-color" class="form-table">
		<thead>
			<tr valign="top">
				<th scope="row" class="title-primary">
					<label><?php esc_html_e( 'Primary', 'wp-event-manager-rest-api' ); ?></label>
				</th>
				<th scope="row" class="title-success">
					<label><?php esc_html_e( 'Success', 'wp-event-manager-rest-api' ); ?></label>
				</th>
				<th scope="row" class="title-info">
					<label><?php esc_html_e( 'Info', 'wp-event-manager-rest-api' ); ?></label>
				</th>
				<th scope="row" class="title-warning">
					<label><?php esc_html_e( 'Warning', 'wp-event-manager-rest-api' ); ?></label>
				</th>
				<th scope="row" class="title-danger">
					<label><?php esc_html_e( 'Danger', 'wp-event-manager-rest-api' ); ?></label>
				</th>
			</tr>
		</thead>

		<tbody>
			<tr valign="top">
				<td scope="row" class="title-primary-color">
					<input type="text" name="wpem_primary_color" class="wpem-colorpicker" value="<?php echo $primary_color; ?>" data-default-color="#3366FF">
				</td>
				<td scope="row" class="title-success-color">
					<input type="text" name="wpem_success_color" class="wpem-colorpicker" value="<?php echo $success_color; ?>" data-default-color="#77DD37">
				</td>
				<td scope="row" class="title-info-color">
					<input type="text" name="wpem_info_color" class="wpem-colorpicker" value="<?php echo $info_color; ?>" data-default-color="#42BCFF">
				</td>
				<td scope="row" class="title-warning-color">
					<input type="text" name="wpem_warning_color" class="wpem-colorpicker" value="<?php echo $warning_color; ?>" data-default-color="#FCD837">
				</td>
				<td scope="row" class="title-danger-color">
					<input type="text" name="wpem_danger_color" class="wpem-colorpicker" value="<?php echo $danger_color; ?>" data-default-color="#FC4C20">
				</td>
			</tr>

			<tr valign="top">
				<td scope="row" id="wpem_primary_color">
					<?php
					for($i=1;$i<10;$i++)
					{
						$code = wpem_color_brightness($primary_color, $i/10);
						$brightness = (1000 - $i*100) ;

						if($i < 5)
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div>';	
						}
						else
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$primary_color.'">'.$code.'</span></div>';	
						}
					}
					?>
				</td>
				<td scope="row" id="wpem_success_color">
					<?php
					for($i=1;$i<10;$i++)
					{
						$code = wpem_color_brightness($success_color, $i/10);
						if($i < 5)
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div>';	
						}
						else
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$success_color.'">'.$code.'</span></div>';	
						}
					}
					?>
				</td>
				<td scope="row" id="wpem_info_color">
					<?php
					for($i=1;$i<10;$i++)
					{
						$code = wpem_color_brightness($info_color, $i/10);
						if($i < 5)
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div>';	
						}
						else
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$info_color.'">'.$code.'</span></div>';	
						}
					}
					?>
				</td>
				<td scope="row" id="wpem_warning_color">
					<?php
					for($i=1;$i<10;$i++)
					{
						$code = wpem_color_brightness($warning_color, $i/10);
						if($i < 5)
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div>';	
						}
						else
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$warning_color.'">'.$code.'</span></div>';	
						}
					}
					?>
				</td>
				<td scope="row" id="wpem_danger_color">
					<?php
					for($i=1;$i<10;$i++)
					{
						$code = wpem_color_brightness($danger_color, $i/10);
						if($i < 5)
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:#fff">'.$code.'</span></div>';	
						}
						else
						{
							echo '<div>'. $brightness .'</div> <div style="width:70%;height:50px;background-color:'.$code.'" data-color-code="'.$code.'"><span style="color:'.$danger_color.'">'.$code.'</span></div>';	
						}
					}
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<?php
	submit_button( __( 'Save', 'wp-event-manager-rest-api' ), 'primary', 'update_app_branding' );
	?>

</div>