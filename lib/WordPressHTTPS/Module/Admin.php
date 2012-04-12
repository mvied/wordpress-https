<?php
/**
 * Admin Module
 * 
 * This module creates the admin panel
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Admin extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		// Add admin menus
		add_action('admin_menu', array(&$this, 'menu'));

		// Load on plugins page
		if ( $GLOBALS['pagenow'] == 'plugins.php' ) {
			add_filter( 'plugin_row_meta', array(&$this, 'plugin_links'), 10, 2);
		}

		// Add global admin scripts
		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

	}

	/**
	 * Runs when the plugin settings are reset.
	 *
	 * @param none
	 * @return void
	 */
	public function reset() {
		
	}
	
	/**
	 * Adds javascript and stylesheets to admin panel
	 * WordPress Hook - admin_enqueue_scripts
	 *
	 * @param none
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style($this->getPlugin()->getSlug() . '-admin-global', $this->getPlugin()->getPluginUrl() . '/admin/css/admin.css', $this->getPlugin()->getVersion(), true);
	}

	/**
	 * Admin panel menu option
	 * WordPress Hook - admin_menu
	 *
	 * @param none
	 * @return void
	 */
	public function menu() {
		add_menu_page('HTTPS', 'HTTPS', 'manage_options', $this->getPlugin()->getSlug(), array($this->getPlugin()->getModule('Admin\Settings'), 'dispatch'), '', 88);
		//remove_submenu_page( $this->getPlugin()->getSlug(), $this->getPlugin()->getSlug() );
		//add_submenu_page($this->getPlugin()->getSlug() . '-menu', 'Updates', 'Updates', 'manage_options', $this->getPlugin()->getSlug() . '-updates', array(&$this, 'dispatch'));
	}

	/**
	 * Renders a meta box
	 *
	 * @param string $module
	 * @param array $metabox
	 * @return void
	 */
	public function meta_box_render( $module, $metabox = array() ) {
		if ( isset($metabox['args']['metabox']) ) {
			include('admin/templates/metabox/' . $metabox['args']['metabox'] . '.php');
		}
	}

	/**
	 * Plugin links on Manage Plugins page in admin panel
	 * WordPress Hook - plugin_row_meta
	 *
	 * @param array $links
	 * @param string $file
	 * @return array $links
	 */
	public function plugin_links($links, $file) {
		if ( strpos($file, $this->getPlugin()->getSlug()) === false ) {
			return $links;
		}

		$links[] = '<a href="' . site_url() . '/wp-admin/admin.php?page=wordpress-https" title="WordPress HTTPS Settings">Settings</a>';
		$links[] = '<a href="http://wordpress.org/extend/plugins/wordpress-https/faq/" title="Frequently Asked Questions">FAQ</a>';
		$links[] = '<a href="http://wordpress.org/tags/wordpress-https#postform" title="Support">Support</a>';
		$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=N9NFVADLVUR7A" title="Support WordPress HTTPS development with a donation!">Donate</a>';
		return $links;
	}

}