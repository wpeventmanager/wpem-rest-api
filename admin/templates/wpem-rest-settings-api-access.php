<h3><?php _e('API Access','wp-event-manager-rest-api');?></h3>
<div class="wpem-admin-bottom-content">
       <?php 
       $rest_api_keys = new WPEM_Rest_API_Keys();
       $rest_api_keys::page_output();?>
    </div>