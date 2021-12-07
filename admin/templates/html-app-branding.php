<?php
/**
 * Admin view: Edit API keys
 */

defined('ABSPATH') || exit;
?>

<div id="key-fields" class="settings-panel">
    <h3 class="wpem-admin-tab-title"><?php esc_html_e('App Branding', 'wpem-rest-api'); ?></h3>
    <div class="wpem-branding-status"></div>
    <div class="app-branding-mode">
        <div class="wpem-light-mode button"><label>Day</label><img src="<?php echo WPEM_REST_API_PLUGIN_URL;?>/assets/images/sun.png" alt=""></div>
        <div class="wpem-dark-mode button"><label>Night</label><img src="<?php echo WPEM_REST_API_PLUGIN_URL;?>/assets/images/moon.png" alt=""></div>
    </div>

    <table id="app-branding-color" class="form-table">
        <thead>
            <tr valign="top">
                <th scope="row" class="title-primary">
                    <label><?php esc_html_e('Primary', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-success">
                    <label><?php esc_html_e('Success', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-info">
                    <label><?php esc_html_e('Info', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-warning">
                    <label><?php esc_html_e('Warning', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-danger">
                    <label><?php esc_html_e('Danger', 'wpem-rest-api'); ?></label>
                </th>
            </tr>
        </thead>

        <tbody>
            <tr valign="top">
                <td scope="row" class="title-primary-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_primary_color" class="wpem-colorpicker" value="<?php echo esc_html($primary_color); ?>" data-default-color="#3366FF">
                </td>
                <td scope="row" class="title-success-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_success_color" class="wpem-colorpicker" value="<?php echo esc_html($success_color); ?>" data-default-color="#77DD37">
                </td>
                <td scope="row" class="title-info-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_info_color" class="wpem-colorpicker" value="<?php echo esc_html($info_color); ?>" data-default-color="#42BCFF">
                </td>
                <td scope="row" class="title-warning-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_warning_color" class="wpem-colorpicker" value="<?php echo esc_html($warning_color); ?>" data-default-color="#FCD837">
                </td>
                <td scope="row" class="title-danger-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_danger_color" class="wpem-colorpicker" value="<?php echo esc_html($danger_color); ?>" data-default-color="#FC4C20">
                </td>
            </tr>

            <tr valign="top">
                <td scope="row" id="wpem_primary_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }

                        $code = wpem_rest_api_color_brightness($primary_color, $adjust_percentage);

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness).'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($primary_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_success_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }

                        $code = wpem_rest_api_color_brightness($success_color, $adjust_percentage);
                        

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($success_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_info_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }

                        $code = wpem_rest_api_color_brightness($info_color, $adjust_percentage);

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($info_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_warning_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($warning_color, $adjust_percentage);
                        

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($warning_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_danger_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($danger_color, $adjust_percentage);

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'.esc_html($brightness).'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($danger_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

        <table id="app-branding-color-dark" class="form-table">
        <thead>
            <tr valign="top">
                <th scope="row" class="title-primary">
                    <label><?php esc_html_e('Primary', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-success">
                    <label><?php esc_html_e('Success', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-info">
                    <label><?php esc_html_e('Info', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-warning">
                    <label><?php esc_html_e('Warning', 'wpem-rest-api'); ?></label>
                </th>
                <th scope="row" class="title-danger">
                    <label><?php esc_html_e('Danger', 'wpem-rest-api'); ?></label>
                </th>
            </tr>
        </thead>

        <tbody>
            <tr valign="top">
                <td scope="row" class="title-primary-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_primary_dark_color" class="wpem-colorpicker" value="<?php echo esc_attr($primary_dark_color); ?>" data-default-color="#3366FF">
                </td>
                <td scope="row" class="title-success-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_success_dark_color" class="wpem-colorpicker" value="<?php echo esc_attr($success_dark_color); ?>" data-default-color="#77DD37">
                </td>
                <td scope="row" class="title-info-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_info_dark_color" class="wpem-colorpicker" value="<?php echo esc_attr($info_dark_color); ?>" data-default-color="#42BCFF">
                </td>
                <td scope="row" class="title-warning-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_warning_dark_color" class="wpem-colorpicker" value="<?php echo esc_attr($warning_dark_color); ?>" data-default-color="#FCD837">
                </td>
                <td scope="row" class="title-danger-color"> <p><?php _e('Select color', 'wpem-rest-api'); ?></p>
                    <input type="text" name="wpem_danger_dark_color" class="wpem-colorpicker" value="<?php echo esc_attr($danger_dark_color); ?>" data-default-color="#FC4C20">
                </td>
            </tr>

            <tr valign="top">
                <td scope="row" id="wpem_primary_dark_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($primary_dark_color, $adjust_percentage);

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($primary_dark_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_success_dark_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($success_dark_color, $adjust_percentage);
                        

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($success_dark_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_info_dark_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($info_dark_color, $adjust_percentage);
                        

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($info_dark_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_warning_dark_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($warning_dark_color, $adjust_percentage);
                    

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($warning_dark_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
                <td scope="row" id="wpem_danger_dark_color">
                    <?php
                    for($i=1;$i<10;$i++)
                    {
                        $brightness = (1000 - $i*100);
                        $adjust_percentage = $i/10;
                        if($brightness == 500) {
                            $adjust_percentage = 0;
                        }
                        $code = wpem_rest_api_color_brightness($danger_dark_color, $adjust_percentage);
                        

                        if($i < 5) {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:#fff">'.esc_html($code).'</span></div></div>';    
                        }
                        else
                        {
                            echo '<div class="wpem-color-pallet-wrapper"><div>'. esc_html($brightness) .'</div> <div class="wpem-color-pallet" style="background-color:'.esc_html($code).'" data-color-code="'.esc_html($code).'"><span style="color:'.esc_html($danger_dark_color).'">'.esc_html($code).'</span></div></div>';    
                        }
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(__('Save', 'wpem-rest-api'), 'primary wpem-backend-theme-button', 'update_app_branding'); ?>

</div>
