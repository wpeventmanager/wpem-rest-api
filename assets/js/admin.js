var WPEMRestAPIAdmin= function () {
    /// <summary>Constructor function of the event settings class.</summary>
    /// <returns type="Settings" />   
    return {
	    ///<summary>
        ///Initializes the AdminSettings.  
        ///</summary>     
        ///<returns type="initialization AdminSettings" />   
        /// <since>1.0.0</since> 
        init: function() 
        {
		     //Bind on click event of the settings section
			jQuery("#update_api_key").on('click',WPEMRestAPIAdmin.actions.saveApiKey);
			jQuery("select#key_user").chosen(); 
			jQuery("select#event_id").chosen(); 
			jQuery("input#date_expires").datepicker({ dateFormat: "yy-mm-dd" }); 
			jQuery('.wpem-colorpicker').wpColorPicker();
			
	   },
	actions :
	{
	    	   /// <summary>
			   /// 
			   /// </summary>
			   /// <param name="parent" type="Event"></param>    
			   /// <returns type="actions" />
			   /// <since>1.0.0</since>    
			   saveApiKey: function(event) 
			   {                   
			   		event.preventDefault();
			   		var self = this;

					//self.block();
			   		jQuery.ajax({
								 type: 'POST',
								 url: wpem_rest_api_admin.ajaxUrl,
								 data: 
								 {
								 	action:      'save_rest_api_keys',
								 	security:    wpem_rest_api_admin.save_api_nonce,
								 	key_id:      jQuery('#key_id').val(),
									description: jQuery('#key_description').val(),
									user:        jQuery('#key_user').val(),
									permissions: jQuery('#key_permissions').val(),
									event_id: jQuery('#event_id').val(),
									date_expires: jQuery('#date_expires').val()
								 },
								beforeSend: function(jqXHR, settings) 
								{
								    //Common.logInfo("Before send called...");
								},
								success: function(response)
								{
									if ( response.success ) {
										var data = response.data;

										jQuery( 'h2, h3', self.el ).first().append( '<div class="wpem-api-message updated"><p>' + data.message + '</p></div>' );

										if ( 0 < data.consumer_key.length && 0 < data.consumer_secret.length ) {
											jQuery( '#api-keys-options', self.el ).remove();
											jQuery( 'p.submit', self.el ).empty().append( data.revoke_url );

											var template = wp.template( 'api-keys-template' );

											jQuery( 'p.submit', self.el ).before( template({
												consumer_key:    data.consumer_key,
												consumer_secret: data.consumer_secret,
												app_key: data.app_key
											}) );
											//self.createQRCode( data.consumer_key, data.consumer_secret );
											//self.initTipTip( '.copy-key' );
											//self.initTipTip( '.copy-secret' );
										} else {
											jQuery( '#key_description', self.el ).val( data.description );
											jQuery( '#key_user', self.el ).val( data.user_id );
											jQuery( '#key_permissions', self.el ).val( data.permissions );
										}
									} else {
										
									}

								},
								error: function(jqXHR, textStatus, errorThrown) 
								{
									$( 'h2, h3', self.el ).first().append( '<div class="wpem-api-message error"><p>' + response.data.message + '</p></div>' );
								},
								complete: function (jqXHR, textStatus) 
								{
									//jQuery('#key-fields').find('.status-message').addClass('notice notice notice-success');
									
								}
				        });

			   },

			 		  
	}
    } //enf of return
}; //end of class

WPEMRestAPIAdmin = WPEMRestAPIAdmin();
jQuery(document).ready(function($) 
{
  WPEMRestAPIAdmin.init();
});