<?php 
/**
 * Module Interface
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

interface WordPressHTTPS_Module_Interface
{
    /**
     * Initializes the module
     *
	 * @param none
	 * @return void
	 */
    public function init();
    
	/**
	 * Runs when the plugin settings are reset.
	 *
	 * @param none
	 * @return void
	 */
    public function reset();
}