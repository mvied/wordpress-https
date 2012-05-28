<?php
/**
 Plugin Name: WordPress HTTPS
 Plugin URI: http://mvied.com/projects/wordpress-https/
 Description: WordPress HTTPS is intended to be an all-in-one solution to using SSL on WordPress sites.
 Author: Mike Ems
 Version: 3.0.4
 Author URI: http://mvied.com/
 */

/*
    Copyright 2012  Mike Ems  (email : mike@mvied.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

$include_paths = array(
	get_include_path(),
	dirname(__FILE__),
	dirname(__FILE__) . '/lib'
);
set_include_path(implode(PATH_SEPARATOR, $include_paths));

function wphttps_autoloader($class) {
	$filename = str_replace('_', '/', $class) . '.php';
	@include $filename;
}
spl_autoload_register('wphttps_autoloader');

/*
 * WordPress HTTPS Reset
 * Uncomment the line below (remove the two forward slashes) to reset the plugin to its default settings.
 * When the plugin is reset, comment the line out again.
 */
//define('WPHTTPS_RESET', true);

if ( function_exists('get_bloginfo') && ! defined('WP_UNINSTALL_PLUGIN') ) {
	$wordpress_https = new WordPressHTTPS;
	$wordpress_https->setSlug('wordpress-https');
	$wordpress_https->setVersion('3.0.4');
	$wordpress_https->setLogger(WordPressHTTPS_Logger::getInstance());
	$wordpress_https->setPluginUrl(plugins_url('', __FILE__));
	$wordpress_https->setDirectory(dirname(__FILE__));
	$wordpress_https->setModuleDirectory(dirname(__FILE__) . '/lib/WordPressHTTPS/Module/');

	//Load Modules
	$wordpress_https->loadModules();

	// If WPHTTPS_RESET global is defined, reset settings
	if ( defined('WPHTTPS_RESET') && constant('WPHTTPS_RESET') == true ) {
		foreach($wordpress_https->getSettings() as $key => $default) {
			$wordpress_https->setSetting($key, $default);
		}
	}

	// Initialize Plugin
	$wordpress_https->init();

	// Register activation hook. Must be called outside of a class.
	register_activation_hook(__FILE__, array($wordpress_https, 'install'));
}