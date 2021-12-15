<?php
wp_enqueue_style('wp-color-picker');

$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
$tab_settings =  isset($this->settings[$tab]) ? $this->settings[$tab] : array();?>
<div class="wpem-admin-bottom-content">
  <?php
    if(isset($tab_settings['type']) &&  $tab_settings['type'] == 'template') {

        if(isset($tab) && file_exists(__DIR__. '/wpem-rest-settings-'.$tab.'.php') ) {
            include 'wpem-rest-settings-'.$tab.'.php';
        }
        else {
            _e('Setting template file not exists', 'wpem-rest-api');
        }
        
    }
    else if($tab_settings['type'] == 'fields' && isset($tab_settings['fields']) && isset($tab_settings['sections'])) {
        foreach ( $tab_settings['sections'] as $section_key => $section ) {
      
            echo '<h3 class="wpem-admin-tab-title">'. esc_html($section).'</h3>';
            echo '<div class="wpem-admin-body">';
            echo '<table class="form-table">';

            if(isset($tab_settings['fields'][$section_key])) {
                foreach ( $tab_settings['fields'][$section_key] as $option ) {

                      $placeholder    = ( ! empty($option['placeholder']) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';

                      $class          = ! empty($option['class']) ? $option['class'] : '';

                      $value          = get_option($option['name']);

                      $option['type'] = ! empty($option['type']) ? $option['type'] : '';

                      $attributes     = array();

                    if (! empty($option['attributes']) && is_array($option['attributes']) ) {

                        foreach ( $option['attributes'] as $attribute_name => $attribute_value ) {

                            $attributes[] = esc_attr($attribute_name) . '="' . esc_attr($attribute_value) . '"';
                        }
                    }

                      echo '<tr valign="top" class="' . esc_attr($class) . '"><th scope="row"><label for="setting-' . esc_attr($option['name']) . '">' . esc_attr($option['label']) . '</a></th><td>';

                      switch ( $option['type'] ) {

                    case "checkbox" :

                        ?><label><input id="setting-<?php echo esc_attr($option['name']); ?>" name="<?php echo esc_attr($option['name']); ?>" type="checkbox" value="1" <?php echo implode(' ', $attributes); ?> <?php checked('1', $value); ?> /> <?php echo esc_html($option['cb_label']); ?></label><?php

if ($option['desc'] ) {

    echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';
}

                        break;

                    case "textarea" :

                        ?><textarea id="setting-<?php echo esc_attr($option['name']); ?>" class="large-text" cols="50" rows="3" name="<?php echo esc_attr($option['name']); ?>" <?php echo implode(' ', $attributes); ?> <?php echo esc_html($placeholder); ?>><?php echo esc_textarea($value); ?></textarea><?php

if ($option['desc'] ) {

    echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';
}

                        break;

                    case "select" :

                        ?><select id="setting-<?php echo esc_attr($option['name']); ?>" class="regular-text" name="<?php echo esc_attr($option['name']); ?>" <?php echo implode(' ', $attributes); ?>><?php

foreach( $option['options'] as $key => $name ) {

    echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($name) . '</option>';
}

?></select><?php

if ($option['desc'] ) {

    echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';

}

                        break;
                    case "radio":
                        ?><fieldset>
              <legend class="screen-reader-text">
                <span><?php echo esc_html($option['label']); ?></span>
              </legend><?php

                if ($option['desc'] ) {
                    echo '<p class="description">' . esc_html_e($option['desc']) . '</p>';
                }

                foreach( $option['options'] as $key => $name ) {
                    echo '<label><input name="' . esc_attr($option['name']) . '" type="radio" value="' . esc_attr($key) . '" ' . checked($value, $key, false) . ' />' . esc_html($name) . '</label><br>';
                }

                ?></fieldset><?php

                        break;

                    case "page" :

                        $args = array(

                        'name'             => $option['name'],

                        'id'               => $option['name'],

                        'sort_column'      => 'menu_order',

                        'sort_order'       => 'ASC',

                        'show_option_none' => __('--no page--', 'wp-event-manager'),

                        'echo'             => false,

                        'selected'         => absint($value)

                        );

                        echo str_replace(' id=', " data-placeholder='" . __('Select a page&hellip;', 'wp-event-manager') .  "' id=", wp_dropdown_pages($args));

                        if ($option['desc'] ) {

                            echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';

                        }
            
                        break;

                    case "password" :

                        ?><input id="setting-<?php echo esc_attr($option['name']); ?>" class="regular-text" type="password" name="<?php echo esc_attr($option['name']); ?>" value="<?php esc_attr_e($value); ?>" <?php echo implode(' ', $attributes); ?> <?php echo $placeholder; ?> /><?php

if ($option['desc'] ) {

    echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';
}

                        break;

                    case "" :

                    case "input" :

                    case "text" :

                        ?><input id="setting-<?php echo esc_attr($option['name']); ?>" class="regular-text" type="text" name="<?php echo esc_attr($option['name']); ?>" value="<?php esc_attr_e($value); ?>" <?php echo implode(' ', $attributes); ?> <?php echo $placeholder; ?> /><?php

                        if ($option['desc'] ) {

                            echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';
                        }

                        break;    
          
                    case "multi-select-checkbox":
                        $this->create_multi_select_checkbox($option);
                        break;
                    case "color-picker":
                        ?><input id="setting-<?php echo esc_attr($option['name']); ?>" class="regular-text wpem-colorpicker" type="text" name="<?php echo esc_attr($option['name']); ?>" value="<?php esc_attr_e($value); ?>" <?php echo implode(' ', $attributes); ?> <?php echo $placeholder; ?> /><?php

if ($option['desc'] ) {

    echo ' <p class="description">' . esc_html_e($option['desc']) . '</p>';
}
                        break;
                    case "file" :
                        ?>
            <p class="form-field">
                        <?php
                        if (! empty($option['multiple']) ) {
                            foreach ( (array) $option['value'] as $value ) {
                                ?><span class="file_url"><input type="text" name="<?php echo esc_attr($option['name']); ?>[]" placeholder="<?php echo esc_attr($option['cb_label']); ?>" value="<?php echo esc_attr($value); ?>" /><button class="button button-small wp_event_manager_upload_file_button" data-uploader_button_text="<?php _e('Use file', 'wp-event-manager'); ?>"><?php _e('Upload', 'wp-event-manager'); ?></button></span><?php
                            }
                        } else {
                            if(isset($option['value']) && is_array($option['value']) ) {
                            }                ?><span class="file_url"><input type="text" name="<?php echo esc_attr($option['name']); ?>" id="<?php echo esc_attr($option['name']); ?>" placeholder="<?php echo esc_attr($option['cb_label']); ?>" value="<?php echo esc_attr($value); ?>" /><button class="button button-small wp_event_manager_upload_file_button" data-uploader_button_text="<?php _e('Use file', 'wp-event-manager'); ?>"><?php _e('Upload', 'wp-event-manager'); ?></button></span><?php
                        }
                        if (! empty($option['multiple']) ) {
                            ?><button class="button button-small wp_event_manager_add_another_file_button" data-field_name="<?php echo esc_attr($key); ?>" data-field_placeholder="<?php echo esc_attr($option['cb_label']); ?>" data-uploader_button_text="<?php _e('Use file', 'wp-event-manager'); ?>" data-uploader_button="<?php _e('Upload', 'wp-event-manager'); ?>"><?php _e('Add file', 'wp-event-manager'); ?></button><?php
                        }
                        ?>
            </p>
                        <?php
                    default :

                        do_action('wp_event_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder);

                        break;

                      }
                }
            }
            echo '</td></tr></table></div>';
        }
    }
    ?>
</div>
