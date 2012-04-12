<?php
/**
 * Module Class for the WordPress plugin WordPress HTTPS.
 * 
 * Each Module in the project will extend this base Module class. This class provides some
 * special getter and setters. If a method or property is being accessed and does not exist on
 * the current module, the module passes the request up to the Plugin class. The Plugin class
 * has special getter and setters that check each module for a method or property if the plugin
 * does not have that method or property. In essence, these getters and setters allow the developer
 * to access any method or property defined anywhere in the project from any other module auto-magically.
 * Modules can be treated as an independent plugins. Think of them as sub-plugins.
 * 
 * If you need to unload a module, just place something like this:
 * $wordpress_https->unloadModule('Hooks');
 * In wordpress-https.php, immediately after:
 * $wordpress_https->loadModules();
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */
class WordPressHTTPS_Module {

	/**
	 * Plugin object that this module extends
	 *
	 * @var WordPressHTTPS
	 */
	protected $_plugin;

	/**
	 * Set Plugin
	 * 
	 * @param WordPressHTTPS_Plugin $plugin
	 * @return object $this
	 */
	public function setPlugin( WordPressHTTPS_Plugin $plugin ) {
		$this->_plugin = $plugin;		
		return $this;
	}
	
	/**
	 * Get Plugin
	 * 
	 * @param none
	 * @return WordPressHTTPS_Plugin
	 */
	public function getPlugin() {
		if ( ! isset($this->_plugin) ) {
			die('Module ' . __CLASS__ . ' missing Plugin dependency.');
		}
		
		return $this->_plugin;
	}
	
}