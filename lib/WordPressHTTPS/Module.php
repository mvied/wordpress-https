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

require_once('Base.php');

class WordPressHTTPS_Module extends WordPressHTTPS_Base {

	/**
	 * Plugin object that this module extends
	 *
	 * @var WordPressHTTPS
	 */
	protected $_plugin;

	/**
	 * Getter
	 * 
	 * Gets property from plugin object.
	 * If property does not exist on the plugin object, the plugin object will check every module for the property.
	 *
	 * @param string $property
	 * @return mixed
	 */
	public function get( $property ) {
		$property = '_' . ltrim($property, '_');
		
		if ( property_exists($this, $property) ) {
			return parent::get($property);
		} else {
			return $this->_plugin->get(ltrim($property, '_'));
		}
	}

	/**
	 * Setter
	 * 
	 * Sets property on plugin object. Falls back to module.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return $this
	 */
	public function set( $property, $value = null ) {
		$property = '_' . ltrim($property, '_');
		
		if ( property_exists($this, $property) ) {
			parent::set($property, $value);
		} else {
			$this->_plugin->set(ltrim($property, '_'), $value);
		}
		
		return $this;
	}

	/**
	 * If method does not exist, look in plugin.
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call( $method, $args = array() ) {
		if ( isset($this->_plugin) ) {
	 		return call_user_func_array(array($this->_plugin, $method), $args);
		}
	}

}