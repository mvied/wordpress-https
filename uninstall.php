<?php

require_once('wordpress-https.php');

if ( !defined('WP_UNINSTALL_PLUGIN') ) {
	die();
}

if ( function_exists('is_multisite') && is_multisite() && isset($_GET['networkwide']) && $_GET['networkwide'] == 1 ) {
	$blogs = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs));
} else {
	$blogs = array($wpdb->blogid);
}

foreach ( $blogs as $blog_id ) {
	// Delete WordPress HTTPS options
	delete_blog_option($blog_id, 'wordpress-https_external_urls');
	delete_blog_option($blog_id, 'wordpress-https_secure_external_urls');
	delete_blog_option($blog_id, 'wordpress-https_unsecure_external_urls');
	delete_blog_option($blog_id, 'wordpress-https_ssl_host');
	delete_blog_option($blog_id, 'wordpress-https_ssl_host_diff');
	delete_blog_option($blog_id, 'wordpress-https_ssl_port');
	delete_blog_option($blog_id, 'wordpress-https_exclusive_https');
	delete_blog_option($blog_id, 'wordpress-https_frontpage');
	delete_blog_option($blog_id, 'wordpress-https_ssl_admin');
	delete_blog_option($blog_id, 'wordpress-https_ssl_proxy');
	delete_blog_option($blog_id, 'wordpress-https_ssl_host_subdomain');
	delete_blog_option($blog_id, 'wordpress-https_version');
	delete_blog_option($blog_id, 'wordpress-https_debug');
	delete_blog_option($blog_id, 'wordpress-https_admin_menu');
	delete_blog_option($blog_id, 'wordpress-https_secure_filter');
}

// Delete force_ssl custom_field from posts and pages
delete_metadata('post', null, 'force_ssl', null, true);