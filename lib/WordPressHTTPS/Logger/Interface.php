<?php 
/**
 * Logger Interface
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

interface WordPressHTTPS_Logger_Interface
{
	
	/**
	 * Get singleton instance
	 *
	 * @param none
	 * @return object
	 */
	public static function getInstance();

	/**
	 * Get Log
	 *
	 * @param none
	 * @return array
	 */
	public function getLog();
	
	/**
	 * Adds a string to an array of log entries
	 *
	 * @param none
	 * @return $this
	 */
	public function log( $string );
	
}