<?php
if (!defined( 'WP_UNINSTALL_PLUGIN')) {
	exit();
}

$options = array(
	'wpem_rest_api_version',
	'wpem_primary_color',
	'wpem_success_color',
	'wpem_info_color',
	'wpem_warning_color',
	'wpem_danger_color',
	'wpem_primary_dark_color',
	'wpem_success_dark_color',
	'wpem_info_dark_color',
	'wpem_warning_dark_color',
	'wpem_danger_dark_color',
	'wpem_app_branding_settings',
	'wpem_app_branding_dark_settings',
	'wpem_rest_api_app_logo',
	'wpem_rest_api_app_name'
);

foreach ($options as $option) {
	delete_option($option);
}
