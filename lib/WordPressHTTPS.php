<?php 
/**
 * WordPressHTTPS Class for the WordPress plugin WordPress HTTPS
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Plugin.php');

class WordPressHTTPS extends WordPressHTTPS_Plugin {

	/**
	 * Plugin Settings
	 * 
	 * setting_name => default_value
	 *
	 * @var array
	 */
	protected $_settings = array(
		'ssl_host' =>				'',	// Hostname for SSL Host
		'ssl_port' =>				'',	// Port number for SSL Host
		'ssl_host_diff' =>			0,	// Is SSL Host different than WordPress host
		'ssl_host_subdomain' =>		0,	// Is SSL Host a subdomain of WordPress host
		'exclusive_https' =>		0,	// Exclusively force SSL on posts and pages with the `Force SSL` option checked.
		'frontpage' =>				0,	// Force SSL on front page
		'ssl_admin' =>				0,	// Force SSL Over Administration Panel (The same as FORCE_SSL_ADMIN)
		'debug' =>					0,	// Debug Mode
	);

}