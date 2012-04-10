<?php
/**
 * Base Class for the WordPress plugin WordPress HTTPS.
 * 
 * This class sets up some fancy getter and setters for other classes in the project.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Base {

	/**
	 * Setter
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return $this
	 */
	public function set( $property, $value = null ) {
		if ( is_array($property) ) {
			$properties = $property;
		} else {
			$properties = array($property => $value);
		}

		foreach( $properties as $property => $value ) {
			$property = '_' . ltrim($property, '_');
			if ( property_exists($this, $property) ) {
				$parts = explode('_', $property);
				$parts = $parts ? array_map('ucfirst', $parts) : array($property);
				$method = 'set' . implode('', $parts);
				if ( method_exists($this, $method) ) {
					return call_user_func_array(array(&$this, $method), array($value));
				} else {
					$this->$property = $value;
				}
			} else {
				$backtrace = debug_backtrace();
				$backtrace = $backtrace[0];
				trigger_error('Call to undefined property \'' . $property . '\' on line ' . $backtrace['line'] . ' of ' . $backtrace['file']);
			}
		}
		return $this;
	}

	/**
	 * Getter
	 *
	 * @param string $property
	 * @return mixed
	 */
	public function get( $property ) {
		$property = '_' . ltrim($property, '_');
		if ( property_exists($this, $property) ) {
			$parts = explode('_', $property);
			$parts = $parts ? array_map('ucfirst', $parts) : array($property);
			$method = 'get' . implode('', $parts);
			if ( method_exists($this, $method) ) {
				return call_user_func(array(&$this, $method));
			} else {
				return @$this->$property;
			}
		} else {
			$backtrace = debug_backtrace();
			for($i = 0;$i<=sizeof($backtrace);$i++) {
				if ( strpos($backtrace[$i]['file'], 'Plugin.php') === false && strpos($backtrace[$i]['file'], 'Module.php') === false ) {
					$backtrace = $backtrace[$i];
					break;
				}
			}
			throw new Exception('Call to undefined property \'' . $property . '\' on line ' . $backtrace['line'] . ' of ' . $backtrace['file']);
		}
	}

}