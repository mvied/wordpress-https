<?php 
/**
 * Network admin Settings Module
 * 
 * Adds the network settings page.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

class WordPressHTTPS_Module_Network extends Mvied_Plugin_Module implements Mvied_Plugin_Module_Interface {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() && isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
			// Network admin
			if ( strpos($_SERVER['REQUEST_URI'], 'wp-admin/network') !== false ) {
				if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'wphttps-network' ) {
					add_action('plugins_loaded', array(&$this, 'save'), 1);
				}

				// Add meta boxes
				add_action('admin_init', array(&$this, 'add_meta_boxes'));
			}
		}

		if ( is_multisite() ) {
			//add_action('network_admin_menu', array(&$this, 'network_admin_menu'));
		}
	}

	/**
	 * Network admin panel menu option
	 * WordPress Hook - network_admin_menu
	 *
	 * @param none
	 * @return void
	 */
	public function network_admin_menu() {
		add_menu_page('HTTPS', 'HTTPS', 'manage_options', $this->getPlugin()->getSlug(), array(&$this, 'dispatch'), '', 88);
	}

	/**
	 * Add meta boxes to WordPress HTTPS Settings page.
	 *
	 * @param none
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			$this->getPlugin()->getSlug() . '_settings',
			__( 'Network Settings', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug() . '_network',
			'main',
			'core',
			array( 'metabox' => 'network' )
		);
		add_meta_box(
			$this->getPlugin()->getSlug() . '_donate2',
			__( 'Loading...', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug() . '_network',
			'main',
			'low',
			array( 'metabox' => 'ajax', 'url' => 'http://wordpresshttps.com/client/donate2.php' )
		);
	}

	/**
	 * Dispatch request for settings page
	 *
	 * @param none
	 * @return void
	 */
	public function dispatch() {
		if ( !current_user_can('manage_network_options') ) {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		self::render();
	}

	/**
	 * Render settings page
	 *
	 * @param none
	 * @return void
	 */
	public function render() {
		require_once($this->getPlugin()->getDirectory() . '/admin/templates/network.php');
	}
	
	/**
	 * Save Settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug() . '-options') ) {
			return false;
		}

		$message = "Network settings saved.";
		$errors = array();
		$reload = false;
		$logout = false;
		if ( isset($_POST['network-settings-reset']) ) {

		} else if ( isset($_POST['network-settings-save']) ) {

		}

		if ( $logout ) {
			wp_logout();
		}

		if ( array_key_exists('ajax', $_POST) ) {
			error_reporting(0);
			while(@ob_end_clean());
			if ( sizeof( $errors ) > 0 ) {
				echo "<div class=\"error below-h2 fade wphttps-message\" id=\"message\">\n\t<ul>\n";
				foreach ( $errors as $error ) {
					echo "\t\t<li><p>".$error."</p></li>\n";
				}
				echo "\t</ul>\n</div>\n";
			} else {
				echo "<div class=\"updated below-h2 fade wphttps-message\" id=\"message\"><p>" . $message . "</p></div>\n";
				if ( $logout || $reload ) {
					echo "<script type=\"text/javascript\">window.location.reload();</script>";
				}
			}
			exit();
		}
	}
	
}