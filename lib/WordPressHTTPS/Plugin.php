<?php
/**
 * Plugin Class for the WordPress plugin WordPress HTTPS
 * 
 * This is a re-usable base class for a WordPress plugin.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('Base.php');

class WordPressHTTPS_Plugin extends WordPressHTTPS_Base {

	/**
	 * Base directory
	 *
	 * @var string
	 */
	protected $_directory;
	
	/**
	 * Module directory
	 *
	 * @var string
	 */
	protected $_module_directory;

	/**
	 * Log Entries
	 *
	 * @var array
	 */
	protected $_log = array();

	/**
	 * Loaded Modules
	 *
	 * @var array
	 */
	protected $_modules = array();

	/**
	 * Plugin URL
	 *
	 * @var string
	 */
	protected $_plugin_url;
	
	/**
	 * Plugin Settings
	 *
	 * @var array
	 */
	protected $_settings = array();

	/**
	 * Plugin Slug
	 *
	 * Used as a unqiue identifier for the plugin.
	 *
	 * @var string
	 */
	protected $_slug;
	
	/**
	 * Plugin Version
	 *
	 * @var string
	 */
	protected $_version;
	
	/**
	 * Set Module
	 *
	 * @param string $module
	 * @param object $object
	 * @return $this
	 */
	public function setModule( $module, $object ) {
		$this->_modules[$module] = $object;
		return $this;
	}

	/**
	 * Get Available Modules
	 *
	 * @param none
	 * @return array $modules
	 */
	public function getAvailableModules() {
		$modules = array();
		if ( is_dir($this->get('module_directory')) && $module_directory = opendir($this->get('module_directory')) ) {
			while ( false !== ($entry = readdir($module_directory)) ) {
				if ( $entry != '.' && $entry != '..' ) {
					$module = str_replace('.php', '', $entry);
					if ( $module != 'Interface' ) {
						$modules[] = $module;
						if ( is_dir($this->get('module_directory') . $module) && $sub_module_directory = opendir($this->get('module_directory') . $module) ) {
							while ( false !== ($entry = readdir($sub_module_directory)) ) {
								if ( $entry != '.' && $entry != '..' ) {
									$sub_module = str_replace('.php', '', $entry);
									$modules[] = $module . '\\' . $sub_module;
								}
							}
						}
					}
				}
			}
		}
		return $modules;
	}
	
	/**
	 * Getter
	 *
	 * @param string $property
	 * @return mixed
	 */
	public function get( $property ) {
		$property = '_' . ltrim($property, '_');
		foreach( $this->getModules() as $module ) {
			if ( property_exists($module, $property) ) {
				return $module->get(ltrim($property, '_'));
			}
		}
		return parent::get($property);
	}

	/**
	 * Get Log
	 *
	 * @param none
	 * @return array
	 */
	public function getLog() {
		if ( ! is_array($this->_log) || sizeof($this->_log) == 0 ) {
			$this->_log = array('No log entries.');
		}
		return $this->_log;
	}

	/**
	 * Get Module
	 *
	 * @param string $module
	 * @return object
	 */
	public function getModule( $module ) {
		$module = 'Module\\' . $module;
		if ( isset($module) ) {
			if ( isset($this->_modules[$module]) ) {
				return $this->_modules[$module];
			}
		}
		
		throw new Exception('Module not found: \'' . $module . '\'.');
	}

	/**
	 * Get Modules
	 * 
	 * Returns an array of all loaded modules
	 *
	 * @param none
	 * @return array $modules
	 */
	public function getModules() {
		$modules = array();
		if ( isset($this->_modules) ) {
			$modules = $this->_modules;
		}
		return $modules;
	}

	/**
	 * Get Plugin Setting
	 *
	 * @param string $setting
	 * @return mixed
	 */
	public function getSetting( $setting ) {
		$setting = $this->get('slug') . '_' . $setting;
		return get_option($setting);
	}

	/**
	 * Init
	 * 
	 * Initializes all of the modules.
	 *
	 * @param none
	 * @return $this
	 */
	public function init() {
		$modules = $this->getModules();
		foreach( $modules as $module ) {
			$module->init();
		}
		return $this;
	}

	/**
	 * If method does not exist, look in all modules
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call( $method, $args = array() ) {
		$modules = array();
		foreach( $this->getModules() as $module ) {
			if ( method_exists($module, $method) ) {
				$modules[] = $module;
			}
		}
		
		if ( sizeof($modules) >= 1 ) {
			return call_user_func_array(array($modules[0], $method), $args);
		}
	}

	/**
	 * Object Factory
	 *
	 * @param string $class
	 * @param array $args
	 * @return object
	 */
	public static function factory( $class, $args = array() ) {
		$base_class = 'WordPressHTTPS';
		if ( strpos($class, $base_class) !== false ) {
			$class = str_replace('\\', '_', $class);
		} else {
			$class = $base_class . '_' . str_replace('\\', '_', $class);
		}
		
		$filename = str_replace('_', '/', $class);
		$filename = $filename . '.php';
		
		try {
			require_once($filename);
		} catch ( Exception $e ) {
			throw new Exception('Unable to load class: ' . $class);
		}

		if ( sizeof($args) > 0 ) {
			$reflector = new ReflectionClass($class);
			$object = $reflector->newInstanceArgs($args);
		} else {
			$object = new $class;
		}

		return $object;
	}
	
	/**
	 * Module Loaded?
	 *
	 * @param string $module
	 * @return boolean
	 */
	public function isModuleLoaded( $module ) {
		if ( is_object($this->getModule($module)) ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Adds a string to an array of log entries
	 *
	 * @param none
	 * @return $this
	 */
	public function log( $string ) {
		$this->_log[] = $string;
		return $this;
	}

	/**
	 * Load Module
	 *
	 * @param string $module
	 * @param array $args
	 * @return $this
	 */
	public function loadModule( $module, $args = array() ) {
		if ( strpos(get_class($this), '_') !== false ) {
			$base_class = substr(get_class($this), 0, strpos(get_class($this), '_'));
		} else {
			$base_class = get_class($this);
		}
		$module_full = 'Module\\' . $module;

		$filename = str_replace('\\', '/', $module_full) . '.php';
		$class = $base_class . '_' . str_replace('\\', '_', $module_full);
		if ( ! isset($this->_modules[$class]) || ! is_object($this->_modules[$class]) || get_class($this->_modules[$class]) != $class ) {
			require_once($filename);

			$object = WordPressHTTPS::factory($module_full, $args);
			
			if ( is_object($object) ) {
				$this->setModule($module_full, $object);
				$this->getModule($module)->set('plugin', $this);
			} else {
				throw new Exception('Unable to load module: \'' . $module . '\'.');
			}
		}

		return $this;
	}
	
	/**
	 * Load Modules
	 * 
	 * Load specified modules. If no modules are specified, all modules are loaded.
	 *
	 * @param array $modules
	 * @return $this
	 */
	public function loadModules( $modules = array() ) {
		if ( sizeof($modules) == 0 ) {
			$modules = $this->getAvailableModules();
		}
		
		// Load Core Module
		if ( in_array('Core', $modules) ) {
			$module = 'Core';
			$this->loadModule( $module );
			$this->getModule( $module )->set('plugin', $this);
			unset($modules[$module]);
		}
		
		foreach( $modules as $module ) {
			$this->loadModule( $module );
		}
		return $this;
	}

	/**
	 * Unload Module
	 *
	 * @param string $module
	 * @return $this
	 */
	public function unloadModule( $module ) {
		if ( strpos(get_class($this), '_') !== false ) {
			$base_class = substr(get_class($this), 0, strpos(get_class($this), '_'));
		} else {
			$base_class = get_class($this);
		}
		$module = 'Module\\' . $module;

		$modules = $this->getModules();
		
		unset($modules[$module]);
		
		$this->_modules = $modules;

		return $this;
	}
	
	/**
	 * Update Plugin Setting
	 *
	 * @param string $setting
	 * @param mixed $value
	 * @return $this
	 */
	public function updateSetting( $setting, $value ) {
		$setting = $this->get('slug') . '_' . $setting;
		update_option($setting, $value);
		return $this;
	}
	
	/**
	 * Resets all plugin options to the defaults
	 *
	 * @param none
	 * @return $this
	 */
	public function reset() {
		foreach ( $this->get('settings') as $option => $value ) {
			update_option($this->get('slug') . '_' . $option, $value);
		}
		
		foreach( $this->getModules() as $module ) {
			if( method_exists($module, 'reset') ) {
				$module->reset();
			}
		}
		
		return $this;
	}

}