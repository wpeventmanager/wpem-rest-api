<div id="wpbody" role="main">
  <div id="wpbody-content" class="wpem-admin-container">
    <h2>Setting</h2>
    <div class="wrap">
      <div class="wpem-admin-left-sidebar">
        <ul class="wpem-admin-left-menu">
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab <?php if ( isset( $_GET['tab'] ) && ( $_GET['tab'] == 'general' || empty($_GET['tab']) )  ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=general' ) );?>">General</a>
          </li>
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab <?php if ( isset( $_GET['tab'] ) &&  $_GET['tab'] == 'api-access' ) echo 'nav-tab-active'; ?>" href="<?php echo  esc_url( admin_url( 'edit.php?post_type=event_listing&page=wpem-rest-api-settings&tab=api-access' ) );?>"><?php _e('API Access','wp-event-manager-rest-api');?></a>
          </li>
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab" href="#">----</a>
          </li>
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab" href="#">---</a>
          </li>
          
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab" href="#">-----</a>
          </li>
          <li class="wpem-admin-left-menu-item">
            <a class="nav-tab" href="#">----</a>
          </li>
        </ul>
      </div>
      <div class="wpem-admin-right-container wrap">
        <div class="metabox-holder wpem-admin-right-container-holder">
          <div class="wpem-admin-top-title-section postbox">
            
            <?php if ( isset( $_GET['tab'] ) &&  $_GET['tab'] == 'api-access' ) { ?>
              <h3><?php _e('API Access','wp-event-manager-rest-api');?></h3>
            <div class="wpem-admin-bottom-content">
               <?php $rest_api_keys = new WPEM_Rest_API_Keys();$rest_api_keys::page_output();?>
            </div>
            <?php 
          } elseif(isset( $_GET['tab'] ) &&  $_GET['tab'] == 'general'  ){
            ?>
            <h3><?php _e('General','wp-event-manager-rest-api');?></h3>
            <?php

            }
             ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
