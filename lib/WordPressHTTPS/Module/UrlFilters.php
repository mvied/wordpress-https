<?php 
/**
 * Admin Url Filters Module
 * 
 * Adds the settings page.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

class WordPressHTTPS_Module_UrlFilters extends Mvied_Plugin_Module {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() && isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'wphttps-filters' ) {
				add_action('plugins_loaded', array(&$this, 'save'), 1);
			}

			// Add meta boxes
			add_action('admin_init', array(&$this, 'add_meta_boxes'));
		}
		
		add_filter('force_ssl', array(&$this, 'secure_filter_url'), 10, 3);
	}

	/**
	 * Secure Filter URL
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_filter_url( $force_ssl, $post_id = 0, $url = '' ) {
		// Check secure filters
		if ( is_null($force_ssl) && sizeof((array)$this->getPlugin()->getSetting('secure_filter')) > 0 ) {
			foreach( $this->getPlugin()->getSetting('secure_filter') as $filter ) {
				if ( preg_match('/' . str_replace('/', '\/', $filter) . '/', $url) === 1 ) {
					$force_ssl = true;
				}
			}
		}
		return $force_ssl;
	}

	/**
	 * Add meta boxes to WordPress HTTPS Settings page.
	 *
	 * @param none
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			$this->getPlugin()->getSlug() . '_filters',
			__( 'URL Filters', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'main',
			'default',
			array( 'metabox' => 'filters' )
		);
	}

	/**
	 * Save Url Filters
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug() . '-options') ) {
			return false;
		}

		$message = "URL Filters saved.";
		$errors = array();
		$reload = false;
		$logout = false;
		if ( isset($_POST['filters-save']) ) {
			$filters = array_map('trim', explode("\n", $_POST['secure_filter']));
			$filters = array_filter($filters); // Removes blank array items
			$this->getPlugin()->setSetting('secure_filter', $filters);
		} else if ( isset($_POST['filters-reset']) ) {
			$this->getPlugin()->setSetting('secure_filter', array());
			$reload = true;
		}

		if ( $logout ) {
			wp_logout();
		}

		require_once($this->getPlugin()->getDirectory() . '/admin/templates/ajax_message.php');
	}
	
}