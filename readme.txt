=== WPEM - REST API ===

Contributors: wpeventmanager,ashokdudhat,krinay
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=55FRYATTFLA5N
Tags: event manager, Event, events, event manager api , listings
Requires at least: 4.1
Tested up to: 5.9
Stable tag: 1.0.3
Requires PHP: 5.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WP Event Manager Rest API


== Description ==

WP Event Manager Rest API

* [Demo](https://wp-eventmanager.com)
* [Add-ons](http://www.wp-eventmanager.com/plugins/)
* [Documentation](http://www.wp-eventmanager.com/documentation/)

[youtube https://www.youtube.com/watch?v=CPK0P7ToRgM]



= Documentation =


Documentation for the core plugin and add-ons can be found [on the docs site here](https://wp-eventmanager.com/knowledge-base/). Please take a look before requesting support because it covers all frequently asked questions!


= Be a contributor =
If you want to contribute, go to our [WP Event Manager GitHub Repository](https://github.com/wpeventmanager/wp-event-manager) and see where you can help.

You can also add a new language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-event-manager). We've built a short guide explaining [how to translate and localize the plugin](https://www.wp-eventmanager.com/documentation/translating-wp-event-manager/).

Thanks to all of our contributors.

= Documentation and Support =
- For documentation and tutorials go to our [Documentation](https://wp-eventmanager.com/knowledge-base/).
- If you have any more questions, visit our support on the [Plugin's Forum](https://wordpress.org/support/plugin/wp-event-manager).
- If you want help with a customisation, you can contact any one for the [Listed Certified Developers](https://www.wp-eventmanager.com/hire-certified-wp-event-manager-developers/?utm_source=wp-repo&utm_medium=link&utm_campaign=readme).
- If you need help with one of our add-ons, [please contact here](https://www.wp-eventmanager.com/get-support/?utm_source=wp-repo&utm_medium=link&utm_campaign=readme).
- For more information about features, FAQs and documentation, check out our website at [WP Event Manager](https://www.wp-eventmanager.com/?utm_source=wp-repo&utm_medium=link&utm_campaign=readme).


= Connect With US =
To stay in touch and get latest update about WP Event Manager's further releases and features, you can connect with us via:
- [Facebook](https://www.facebook.com/wpeventmanager/)
- [Twitter](https://twitter.com/wp_eventmanager)
- [Google Plus](https://plus.google.com/u/0/b/107105224603939407328/107105224603939407328)
- [Linkedin](https://www.linkedin.com/company/wp-event-manager)
- [Pinterest](https://www.pinterest.com/wpeventmanager/)
- [Youtube](https://www.youtube.com/channel/UCnfYxg-fegS_n9MaPNU61bg).


== Installation ==


= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic install, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.



In the search field type "WP Event Manager" and click Search Plugins. Once you've found the plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by clicking _Install Now_.



= Manual installation =



The manual installation method involves downloading the plugin and uploading it to your web server via your favorite FTP application.



* Download the plugin file to your computer and unzip it

* Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.

* Activate the plugin from the Plugins menu within the WordPress admin.



= Getting started =



Once installed:



1. Create a page called "events" and inside place the `[events]` shortcode. This will list your events.

2. Create a page called "submit event" and inside place the `[submit_event_form]` shortcode if you want front-end submissions.

3. Create a page called "event dashboard" and inside place the `[event_dashboard]` shortcode for logged in users to manage their listings.



**Note when using shortcodes**, if the content looks blown up/spaced out/poorly styled, edit your page and above the visual editor click on the 'text' tab. Then remove any 'pre' or 'code' tags wrapping your shortcode.


== Frequently Asked Questions ==



= How do I setup WP Event Manager? =

View the getting [installation](http://www.wp-eventmanager.com/plugins-documentation/wp-event-manager/installation/) and [setup](http://www.wp-eventmanager.com/plugins-documentation/wp-event-manager/setting-up-wp-event-manager/) guide for advice getting started with the plugin. In most cases it's just a case of adding some shortcodes to your pages!



= Can I use WP Event Manager without frontend event submission? =

Yes! If you don't setup the [submit_event_form] shortcode, you can just post from the admin backend.


= How can I customize the event submission form? =

There are three ways to customize the fields in WP Event Manager;


1. For simple text changes, using a localisation file or a plugin such as [Say What](https://wordpress.org/plugins/say-what/).

2. For field changes, or adding new fields, using functions/filters inside your theme's functions.php file: [Read more](http://www.wp-eventmanager.com/plugins-documentation/wp-event-manager/editing-event-submission-form-fields/).

3. Use a 3rd party plugin which has a UI for field editing.



If you'd like to learn about WordPress filters, here is a great place to start: [Read more](https://pippinsplugins.com/a-quick-introduction-to-using-filters/).



= How can I be notified of new events via email? =

If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).


== Screenshots ==


== Changelog ==

= 1.0.3 [ 22nd Dec 2022 ] =

Fixed - Event Ticket scaning issue with mobile app.
Fixed - Order tab option not working in mobile app.
Fixed - Event organizer user permission issue.
Fixed - Organizer add note feature not working correctly.
Fixed - Ticket sales report visibility issue. 
Fixed - Removed licence key for Rest API addon.

= 1.0.2 [ May 18th, 2022 ] =

Fixed - Ecosystem Controller file.

= 1.0.1 [ May 17th, 2022 ] =

Fixed - Past events search.
Fixed - Message and status code improvements.
Fixed - Data improvements.


= 1.0.0 [ 9th Apr 2021 ] =

* Inital release.
