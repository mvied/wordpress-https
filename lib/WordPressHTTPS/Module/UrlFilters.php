<?php 
/**
 * Url Filters Module
 * 
 * Provides a mechanism to secure posts and pages using a simple
 * string match or regular expressions.
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
		if ( is_admin() ) {
			add_action('wp_ajax_' . $this->getPlugin()->getSlug() . '_url_filters_save', array(&$this, 'save'));
			add_action('wp_ajax_' . $this->getPlugin()->getSlug() . '_url_filters_reset', array(&$this, 'reset'));
			if ( isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
				// Add meta boxes
				add_action('admin_init', array(&$this, 'add_meta_boxes'));
			}
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
			array( 'metabox' => 'url_filters' )
		);
	}

	/**
	 * Reset Url Filters
	 *
	 * @param array $settings
	 * @return void
	 */
	public function reset() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug()) ) {
			return false;
		}

		$message = __('URL Filters reset.','wordpress-https');
		$errors = array();
		$reload = true;

		$this->getPlugin()->setSetting('secure_filter', array());

		$this->getPlugin()->renderView('ajax_message', array('message' => $message, 'errors' => $errors, 'reload' => $reload));
	}

	/**
	 * Save Url Filters
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug()) ) {
			return false;
		}

		$message = __('URL Filters saved.','wordpress-https');
		$errors = array();
		$reload = false;

		$filters = array_map('trim', explode("\n", $_POST['secure_filter']));
		$filters = array_filter($filters); // Removes blank array items
		$filters = stripslashes_deep($filters);
		$this->getPlugin()->setSetting('secure_filter', $filters);

		$this->getPlugin()->renderView('ajax_message', array('message' => $message, 'errors' => $errors, 'reload' => $reload));
	}
	
}