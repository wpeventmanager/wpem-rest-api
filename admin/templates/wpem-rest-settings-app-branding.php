<?php
wp_enqueue_style( 'wp-color-picker' );

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
$tab_settings =  isset($this->settings[$tab]) ? $this->settings[$tab] : array();
?>
<h3>
<?php 
  if(isset($tab_settings[0])) 
    printf(__('%s','wp-event-manager-rest-api'),$tab_settings[0])
?>
</h3>
<div class="wpem-admin-bottom-content">
  <pre>
  	<?php print_r($tab_settings);?>
  </pre>
  <?php

    foreach ($tab_settings as $key => $section) {
            echo '<div id="settings-' . sanitize_title( $key ) . '" class="settings_panel">';

            echo '<table class="form-table">';
            if(isset( $section[1] )) 
            foreach ( $section[1] as $option ) {

              $placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';

              $class          = ! empty( $option['class'] ) ? $option['class'] : '';

              $value          = get_option( $option['name'] );

              $option['type'] = ! empty( $option['type'] ) ? $option['type'] : '';

              $attributes     = array();

              if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) )

                foreach ( $option['attributes'] as $attribute_name => $attribute_value )

                  $attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';

              echo '<tr valign="top" class="' . $class . '"><th scope="row"><label for="setting-' . $option['name'] . '">' . $option['label'] . '</a></th><td>';

              switch ( $option['type'] ) {

                case "checkbox" :

                  ?><label><input id="setting-<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" type="checkbox" value="1" <?php echo implode( ' ', $attributes ); ?> <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php

                  if ( $option['desc'] )

                    echo ' <p class="description">' . $option['desc'] . '</p>';

                break;

                case "textarea" :

                  ?><textarea id="setting-<?php echo $option['name']; ?>" class="large-text" cols="50" rows="3" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?>><?php echo esc_textarea( $value ); ?></textarea><?php

                  if ( $option['desc'] )

                    echo ' <p class="description">' . $option['desc'] . '</p>';

                break;

                case "select" :

                  ?><select id="setting-<?php echo $option['name']; ?>" class="regular-text" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?>><?php

                    foreach( $option['options'] as $key => $name )

                      echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';

                  ?></select><?php

                  if ( $option['desc'] ) {

                    echo ' <p class="description">' . $option['desc'] . '</p>';

                  }

                break;
                case "radio":
                  ?><fieldset>
                    <legend class="screen-reader-text">
                      <span><?php echo esc_html( $option['label'] ); ?></span>
                    </legend><?php

                  if ( $option['desc'] ) {
                    echo '<p class="description">' . $option['desc'] . '</p>';
                  }

                  foreach( $option['options'] as $key => $name )
                    echo '<label><input name="' . esc_attr( $option['name'] ) . '" type="radio" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . ' />' . esc_html( $name ) . '</label><br>';

                  ?></fieldset><?php

                break;

                case "page" :

                  $args = array(

                    'name'             => $option['name'],

                    'id'               => $option['name'],

                    'sort_column'      => 'menu_order',

                    'sort_order'       => 'ASC',

                    'show_option_none' => __( '--no page--', 'wp-event-manager' ),

                    'echo'             => false,

                    'selected'         => absint( $value )

                  );

                  echo str_replace(' id=', " data-placeholder='" . __( 'Select a page&hellip;', 'wp-event-manager' ) .  "' id=", wp_dropdown_pages( $args ) );

                  if ( $option['desc'] ) {

                    echo ' <p class="description">' . $option['desc'] . '</p>';

                  }
                  
                break;

                case "password" :

                  ?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

                  if ( $option['desc'] ) {

                    echo ' <p class="description">' . $option['desc'] . '</p>';
                  }

                break;

                case "" :

                case "input" :

                case "text" :

                  ?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

                  if ( $option['desc'] ) {

                    echo ' <p class="description">' . $option['desc'] . '</p>';
                }

                break;    
                
                case "multi-select-checkbox":
                    $this->create_multi_select_checkbox($option);
                  break;
                case "color-picker":
                   ?><input id="setting-<?php echo $option['name']; ?>" class="regular-text wpem-colorpicker" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

                  if ( $option['desc'] ) {

                    echo ' <p class="description">' . $option['desc'] . '</p>';
                }
                  break;

                case "template":
                     if( isset( $_GET['tab'] ) && file_exists (__DIR__. '/wpem-rest-settings-'.$_GET['tab'].'.php') )
                              include('wpem-rest-settings-'.$_GET['tab'].'.php');
                            else
                              _e('Setting template file not exists','wp-event-manager-rest-api');
                break;

                default :

                  do_action( 'wp_event_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );

                break;
              }
              echo '</td></tr>';
            }
            echo '</table></div>';
        }
  ?>

</div>
