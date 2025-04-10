var WPEMRestAPIAdmin = (function () {
    return {
        init: function () {
            jQuery("#update_api_key").on("click", WPEMRestAPIAdmin.actions.saveApiKey),
                jQuery("select#key_user").chosen(),
                jQuery("select#event_id").chosen(),
                jQuery("input#date_expires").datepicker({ dateFormat: "yy-mm-dd" }),
                jQuery("table#app-branding-color-dark").hide(),
                jQuery(".wpem-app-branding-mode .app-branding-mode .wpem-light-mode").click(function () {
                    jQuery(".wpem-app-branding-mode").removeClass("wpem-dark-mode").addClass("wpem-light-mode"), jQuery("table#app-branding-color").show(), jQuery("table#app-branding-color-dark").hide();
                }),
                jQuery(".wpem-app-branding-mode .app-branding-mode .wpem-dark-mode").click(function () {
                    jQuery("table#app-branding-color").hide(), jQuery("table#app-branding-color-dark").show(), jQuery(".wpem-app-branding-mode").removeClass("wpem-light-mode").addClass("wpem-dark-mode");
                }),
                jQuery("#update_app_branding").on("click", WPEMRestAPIAdmin.actions.saveAppBranding);
            var t;
            jQuery(".wpem-colorpicker").wpColorPicker({
                defaultColor: !0,
                change: function (e, a) {
                    var n = e.target;
                    clearTimeout(t),
                        (t = setTimeout(function () {
                            WPEMRestAPIAdmin.actions.changeBrightness(e, a.toString());
                        }, 500));
                },
            });
        },
        actions: {
            saveApiKey: function (e) {
                e.preventDefault();
                var a = this;
                jQuery("#api_key_loader").show(),
                    jQuery("#update_api_key").attr("disabled", !0),
                    jQuery.ajax({
                        type: "POST",
                        url: wpem_rest_api_admin.ajaxUrl,
                        data: {
                            action: "save_rest_api_keys",
                            security: wpem_rest_api_admin.save_api_nonce,
                            key_id: jQuery("#key_id").val(),
                            description: jQuery("#key_description").val(),
                            user: jQuery("#key_user").val(),
                            permissions: jQuery("#key_permissions").val(),
                            event_id: jQuery("#event_id").val(),
                            date_expires: jQuery("#date_expires").val(),
                            restrict_check_in: jQuery('input[name="restrict_check_in"]').attr("checked") ? 0 : 1,
                        },
                        beforeSend: function (e) {},
                        success: function (e) {
                            e.success
                                ? (jQuery("h2, h3", a.el)
                                      .first()
                                      .html('<div class="wpem-api-message updated"><p>' + e.data.message + "</p></div>"),
                                  0 < e.data.consumer_key.length && 0 < e.data.consumer_secret.length
                                      ? (jQuery("#api-keys-options", a.el).parent().remove(),
                                        jQuery("p.submit", a.el).empty().append(e.data.revoke_url),
                                        jQuery("#key-fields p.submit", a.el).before(wp.template("api-keys-template")({ consumer_key: e.data.consumer_key, consumer_secret: e.data.consumer_secret, app_key: e.data.app_key })))
                                      : (jQuery("#key_description", a.el).val(e.data.description), jQuery("#key_user", a.el).val(e.data.user_id), jQuery("#key_permissions", a.el).val(e.data.permissions)))
                                : jQuery("h2, h3", a.el)
                                      .first()
                                      .append('<div class="wpem-api-message error"><p>' + e.errorThrown + "</p></div>");
                        },
                        error: function (e, t, n) {
                            jQuery("h2, h3", a.el)
                                .first()
                                .append('<div class="wpem-api-message error"><p>' + n + "</p></div>");
                        },
                        complete: function (e, t) {
                            jQuery("#api_key_loader").hide(), jQuery("#update_api_key").attr("disabled", !1);
                        },
                    });
            },
            saveAppBranding: function (e) {
                e.preventDefault();
                var a = this,
                    n = "",
                    i = jQuery("#app-branding-color").is(":visible"),
                    p = jQuery("#app-branding-color-dark").is(":visible");
                i ? (n = "light") : p && (n = "dark"),
                    jQuery.ajax({
                        type: "POST",
                        url: wpem_rest_api_admin.ajaxUrl,
                        data: {
                            action: "save_app_branding",
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
                            active_mode: n,
                        },
                        beforeSend: function (e) {},
                        success: function (e) {
                            jQuery(".wpem-branding-status").html('<div class="wpem-api-message updated"><p>' + e.data.message + "</p></div>"),
                                jQuery(".update_app_branding_message").html(
                                    '<div class="update_app_branding_message_update"><i class="wpem-icon-checkmark"></i> Your preferred color for your app branding has been successfully saved.</div>'
                                ),
                                "dark" == e.data.mode && (jQuery("table#app-branding-color").hide(), jQuery("table#app-branding-color-dark").show());
                        },
                        error: function (e, t, n) {
                            jQuery(".wpem-branding-status").html('<div class="wpem-api-message error"><p>' + n + "</p></div>"),
                                jQuery(".update_app_branding_message").html(
                                    '<div class="update_app_branding_message_update"><i class="wpem-icon-cross"></i> Your preferred color for your app branding has not been successfully saved.</div>'
                                );
                        },
                        complete: function (e, t) {},
                    });
            },
            changeBrightness: function (e, a) {
                var n = e.target.name,
                    i = jQuery(e.target).parents("table").attr("id");
                jQuery.ajax({
                    url: wpem_rest_api_admin.ajaxUrl,
                    type: "POST",
                    dataType: "HTML",
                    data: { action: "change_brighness_color", color: a },
                    success: function (e) {
                        const a = e
                            .replace(/&amp;/g, "&")
                            .replace(/&lt;/g, "<")
                            .replace(/&gt;/g, ">")
                            .replace(/&quot;/g, '"')
                            .replace(/&#039;/g, "'");
                        jQuery("#" + i + " tbody tr td#" + n).html(a);
                    },
                });
            },
        },
    };
})();
jQuery(document).ready(function (a) {
    WPEMRestAPIAdmin.init();
});