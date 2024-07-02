var WPEMRestAPIAdmin = function () {
    /// <summary>Constructor function of the event settings class.</summary>
    /// <returns type="Settings" />
    return {
        ///<summary>
        ///Initializes the AdminSettings.
        ///</summary>
        ///<returns type="initialization AdminSettings" />
        /// <since>1.0.0</since>
        init: function () {
            //Bind on click event of the settings section
            jQuery("#update_api_key").on('click', WPEMRestAPIAdmin.actions.saveApiKey);
            jQuery("select#key_user").chosen();
            jQuery("select#event_id").chosen();
            jQuery("input#date_expires").datepicker({ dateFormat: "yy-mm-dd" });

            jQuery("table#app-branding-color-dark").hide();

            //light mode
            jQuery(".wpem-app-branding-mode .app-branding-mode .wpem-light-mode").click(function () {
                jQuery(".wpem-app-branding-mode").removeClass("wpem-dark-mode");
                jQuery(".wpem-app-branding-mode").addClass("wpem-light-mode");
                jQuery("table#app-branding-color").show();
                jQuery("table#app-branding-color-dark").hide();
            });
            //dark mode
            jQuery(".wpem-app-branding-mode .app-branding-mode .wpem-dark-mode").click(function () {
                jQuery("table#app-branding-color").hide();
                jQuery("table#app-branding-color-dark").show();
                jQuery(".wpem-app-branding-mode").removeClass("wpem-light-mode");
                jQuery(".wpem-app-branding-mode").addClass("wpem-dark-mode");
            });

            jQuery("#update_app_branding").on('click', WPEMRestAPIAdmin.actions.saveAppBranding);

            let ajaxTimer;
            jQuery('.wpem-colorpicker').wpColorPicker({
                defaultColor: true,
                change: function (event, ui) {
                    var element = event.target;
                    var color = ui.color.toString();

                    clearTimeout(ajaxTimer);
                    ajaxTimer = setTimeout(function() {
                        WPEMRestAPIAdmin.actions.changeBrightness(event, color);
                    }, 500);
                },
            });
        },
        actions: {
            /// <summary>
            ///
            /// </summary>
            /// <param name="parent" type="Event"></param>
            /// <returns type="actions" />
            /// <since>1.0.0</since>
            saveApiKey: function (event) {
                event.preventDefault();
                var self = this;
                
                jQuery("#api_key_loader").show();
                jQuery("#update_api_key").attr("disabled", true);

                //self.block();
                jQuery.ajax({
                    type: 'POST',
                    url: wpem_rest_api_admin.ajaxUrl,
                    data: {
                        action: 'save_rest_api_keys',
                        security: wpem_rest_api_admin.save_api_nonce,
                        key_id: jQuery('#key_id').val(),
                        description: jQuery('#key_description').val(),
                        user: jQuery('#key_user').val(),
                        permissions: jQuery('#key_permissions').val(),
                        event_id: jQuery('#event_id').val(),
                        date_expires: jQuery('#date_expires').val()
                    },
                    beforeSend: function (jqXHR, settings) { },
                    success: function (response) {
                        if(response.success) {
                            var data = response.data;

                            jQuery('h2, h3', self.el).first().html('<div class="wpem-api-message updated"><p>' + data.message + '</p></div>');

                            if (0 < data.consumer_key.length && 0 < data.consumer_secret.length) {
                                jQuery('#api-keys-options', self.el).parent().remove();
                                jQuery('p.submit', self.el).empty().append(data.revoke_url);

                                var template = wp.template('api-keys-template');

                                jQuery('#key-fields p.submit', self.el).before(
                                    template({
                                        consumer_key: data.consumer_key,
                                        consumer_secret: data.consumer_secret,
                                        app_key: data.app_key
                                    })
                                );
                            } else {
                                jQuery('#key_description', self.el).val(data.description);
                                jQuery('#key_user', self.el).val(data.user_id);
                                jQuery('#key_permissions', self.el).val(data.permissions);
                            }
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        jQuery('h2, h3', self.el).first().append('<div class="wpem-api-message error"><p>' + errorThrown + '</p></div>');
                    },
                    complete: function (jqXHR, textStatus) {
                        jQuery("#api_key_loader").hide();
                        jQuery("#update_api_key").attr("disabled", false);
                    }
                });
            },

            /// <summary>
            ///
            /// </summary>
            /// <param name="parent" type="Event"></param>
            /// <returns type="actions" />
            /// <since>1.0.0</since>
            saveAppBranding: function (event) {
                event.preventDefault();
                var self = this;

                jQuery.ajax({
                    type: 'POST',
                    url: wpem_rest_api_admin.ajaxUrl,
                    data: {
                        action: 'save_app_branding',
                        security: wpem_rest_api_admin.save_app_branding_nonce,
                        wpem_primary_color: jQuery('input[name="wpem_primary_color"]').val(),
                        wpem_success_color: jQuery('input[name="wpem_success_color"]').val(),
                        wpem_info_color: jQuery('input[name="wpem_info_color"]').val(),
                        wpem_warning_color: jQuery('input[name="wpem_warning_color"]').val(),
                        wpem_danger_color: jQuery('input[name="wpem_danger_color"]').val(),

                        wpem_primary_dark_color: jQuery('input[name="wpem_primary_dark_color"]').val(),
                        wpem_success_dark_color: jQuery('input[name="wpem_success_dark_color"]').val(),
                        wpem_info_dark_color: jQuery('input[name="wpem_info_dark_color"]').val(),
                        wpem_warning_dark_color: jQuery('input[name="wpem_warning_dark_color"]').val(),
                        wpem_danger_dark_color: jQuery('input[name="wpem_danger_dark_color"]').val(),
                    },
                    beforeSend: function (jqXHR, settings) {},
                    success: function (response) {
                        jQuery('.wpem-branding-status').html('<div class="wpem-api-message updated"><p>' + response.data.message + '</p></div>');
                        jQuery('.update_app_branding_message').html('<div class="update_app_branding_message_update"><i class="wpem-icon-checkmark"></i> Your preferred color for your app branding has been successfully saved.</div>');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        jQuery('.wpem-branding-status').html('<div class="wpem-api-message error"><p>' + errorThrown + '</p></div>');
                        jQuery('.update_app_branding_message').html('<div class="update_app_branding_message_update"><i class="wpem-icon-cross"></i> Your preferred color for your app branding has not been successfully saved.</div>');
                    },
                    complete: function (jqXHR, textStatus) {}
                });
            },

            changeBrightness: function (event, color) {
                var name = event.target.name;
                var tableid = jQuery(event.target).parents('table').attr('id');

                jQuery.ajax({
                    url: wpem_rest_api_admin.ajaxUrl,
                    type: 'POST',
                    dataType: 'HTML',
                    data: {
                        action: 'change_brighness_color',
                        color: color,
                    },
                    success: function (response) {
                        const html = response.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                        jQuery('#' + tableid + ' tbody tr td#' + name).html(html);
                    }
                });
            },
        }
    } //enf of return
}; //end of class

WPEMRestAPIAdmin = WPEMRestAPIAdmin();
jQuery(document).ready(function ($) {
    WPEMRestAPIAdmin.init();
}); 