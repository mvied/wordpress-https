<?php 
/**
 * Logger Class for the WordPress plugin WordPress HTTPS.
 * 
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

<<<<<<< HEAD
class WordPressHTTPS_Logger implements WordPressHTTPS_Logger_Interface {
=======
class WordPressHTTPS_Logger {
>>>>>>> 23d88837eeef2be0b31e0062ce7fedb10c056d5e

	/**
	 * Instance
	 *
	 * @var WordPressHTTPS_Logger
	 */
<<<<<<< HEAD
	private static $_instance;
=======
	protected $_instance;
>>>>>>> 23d88837eeef2be0b31e0062ce7fedb10c056d5e

	/**
	 * Log Entries
	 *
	 * @var array
	 */
	protected $_log = array();
	
	/**
	 * Get singleton instance
	 *
	 * @param none
	 * @return WordPressHTTPS_Logger
	 */
	public static function getInstance() {
		if ( ! isset(self::$_instance) ) {
			self::$_instance = new self;
		}
		return self::$_instance;
	}
<<<<<<< HEAD

=======
	
	/**
	 * Construct
	 *
	 * @param none
	 * @return void
	 */
	protected function __construct() {
		throw new Exception('WordPressHTTPS_Logger can not be instantiated directly. Use WordPressHTTPS_Logger::getInstance()');
	}
	
>>>>>>> 23d88837eeef2be0b31e0062ce7fedb10c056d5e
	/**
	 * Get Log
	 *
	 * @param none
	 * @return array
	 */
	public function getLog() {
		return $this->_log;
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
	 * Console Log
	 * 
	 * Output contents of the log to the browser's console.
	 *
	 * @param none
	 * @return void
	 */
	public function consoleLog() {
		$code = "<script type=\"text/javascript\">\n\tif ( typeof console === 'object' ) {\n";
		$log = $this->getLog();
		array_unshift($log, '[BEGIN WordPress HTTPS Debug Log]');
		array_push($log, '[END WordPress HTTPS Debug Log]');
		foreach( $log as $log_entry ) {
			if ( is_array($log_entry) ) {
				$log_entry = json_encode($log_entry);
			} else {
				$log_entry = "'" . addslashes($log_entry) . "'";
			}
			$code .= "\t\tconsole.log(" . $log_entry . ");\n";
		}
		$code .= "\t}\n</script>\n";
		
		echo $code;
	}
	
}