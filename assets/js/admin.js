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

			jQuery( ".wpem-app-branding-mode .app-branding-mode .wpem-light-mode" ).click(function() {
				jQuery( ".wpem-app-branding-mode" ).removeClass("wpem-dark-mode");
				jQuery( ".wpem-app-branding-mode" ).addClass("wpem-light-mode");
			});
			jQuery( ".wpem-app-branding-mode .app-branding-mode .wpem-dark-mode" ).click(function() {
				jQuery( ".wpem-app-branding-mode" ).removeClass("wpem-light-mode");
				jQuery( ".wpem-app-branding-mode" ).addClass("wpem-dark-mode");
			});

			jQuery("#update_app_branding").on('click',WPEMRestAPIAdmin.actions.saveAppBranding);

			jQuery('.wpem-colorpicker').wpColorPicker({
				defaultColor: true,
				change: function (event, ui) {
			        var element = event.target;
			        var color = ui.color.toString();

			        WPEMRestAPIAdmin.actions.changeBriteness(event, color);
			    },
			});
			
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
											jQuery( '#api-keys-options', self.el ).parent().remove();
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
									jQuery( 'h2, h3', self.el ).first().append( '<div class="wpem-api-message error"><p>' + response.data.message + '</p></div>' );
								},
								complete: function (jqXHR, textStatus) 
								{
									//jQuery('#key-fields').find('.status-message').addClass('notice notice notice-success');
									
								}
				        });

			   	},

			   	/// <summary>
			   	/// 
			   	/// </summary>
			   	/// <param name="parent" type="Event"></param>    
			   	/// <returns type="actions" />
			   	/// <since>1.0.0</since>    
			   	saveAppBranding: function(event) 
			   	{                   
			   		event.preventDefault();
			   		var self = this;

					//self.block();
			   		jQuery.ajax({
								 type: 'POST',
								 url: wpem_rest_api_admin.ajaxUrl,
								 data: 
								 {
								 	action: 'save_app_branding',
								 	security: wpem_rest_api_admin.save_app_branding_nonce,
								 	wpem_primary_color: jQuery('input[name="wpem_primary_color"]').val(),
								 	wpem_success_color: jQuery('input[name="wpem_success_color"]').val(),
								 	wpem_info_color: jQuery('input[name="wpem_info_color"]').val(),
								 	wpem_warning_color: jQuery('input[name="wpem_warning_color"]').val(),
								 	wpem_danger_color: jQuery('input[name="wpem_danger_color"]').val(),
								 },
								beforeSend: function(jqXHR, settings) 
								{
								    //Common.logInfo("Before send called...");
								},
								success: function(response)
								{
									jQuery( 'h2, h3', self.el ).first().append( '<div class="wpem-api-message updated"><p>' + response.data.message + '</p></div>' );
								},
								error: function(jqXHR, textStatus, errorThrown) 
								{
									jQuery( 'h2, h3', self.el ).first().append( '<div class="wpem-api-message error"><p>' + response.data.message + '</p></div>' );
								},
								complete: function (jqXHR, textStatus) 
								{
									//jQuery('#key-fields').find('.status-message').addClass('notice notice notice-success');									
								}
				        });

			   	},

			   	changeBriteness: function(event, color)
			   	{
			   		var name = event.target.name;
			   		
			   		jQuery.ajax({
                        url: wpem_rest_api_admin.ajaxUrl,
                        type: 'POST',
                        dataType: 'HTML',
                        data: {
                            action: 'change_brighness_color',
                            color: color,
                        },
                        success: function (responce)
                        {
                            jQuery('#app-branding-color tbody tr td#' + name).html(responce);
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