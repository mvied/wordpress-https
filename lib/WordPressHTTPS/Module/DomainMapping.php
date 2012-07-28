<?php 
/**
 * Admin Domain Mapping Module
 * 
 * Adds the settings page.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

class WordPressHTTPS_Module_DomainMapping extends Mvied_Plugin_Module implements Mvied_Plugin_Module_Interface {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() && isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'wphttps-domain-mapping' ) {
				add_action('plugins_loaded', array(&$this, 'save'), 1);
			}

			// Add meta boxes
			add_action('admin_init', array(&$this, 'add_meta_boxes'));
		}

		// Custom filter https_external_url
		add_filter('https_external_url', array(&$this, 'map_url'), 10);
	}

	/**
	 * Domain Mapping
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function map_url( $url ) {
		if ( is_array($this->getPlugin()->getSetting('ssl_host_mapping')) && sizeof($this->getPlugin()->getSetting('ssl_host_mapping')) > 0 ) {
			foreach( $this->getPlugin()->getSetting('ssl_host_mapping') as $http_domain => $https_domain ) {
				preg_match('/' . $http_domain . '/', $url, $matches);
				if ( sizeof($matches) > 0 ) {
					$url = preg_replace('/' . $http_domain . '/', $https_domain, $url);
				}
			}
		}
		return $url;
	}

	/**
	 * Add meta boxes to WordPress HTTPS Settings page.
	 *
	 * @param none
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			$this->getPlugin()->getSlug() . '_domain_mapping',
			__( 'Domain Mapping', $this->getPlugin()->getSlug() ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'main',
			'core',
			array( 'metabox' => 'domain_mapping' )
		);
	}

	/**
	 * Save Domain Mapping
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug() . '-options') ) {
			return false;
		}

		$message = "Domain Mapping saved.";
		$errors = array();
		$reload = false;
		$logout = false;
		if ( isset($_POST['domain_mapping-save']) ) {
			$ssl_host_mapping = array();
			for( $i=0; $i<sizeof($_POST['http_domain']); $i++ ) {
				if ( isset($_POST['http_domain'][$i]) && $_POST['http_domain'][$i] != '' && isset($_POST['https_domain'][$i]) && $_POST['https_domain'][$i] != '' ) {
					$ssl_host_mapping[$_POST['http_domain'][$i]] = $_POST['https_domain'][$i];
				}
			}
			$this->getPlugin()->setSetting('ssl_host_mapping', $ssl_host_mapping);
		} else if ( isset($_POST['domain_mapping-reset']) ) {
			$this->getPlugin()->setSetting('ssl_host_mapping', WordPressHTTPS::$ssl_host_mapping);
			$reload = true;
		}

		if ( $logout ) {
			wp_logout();
		}

		require_once($this->getPlugin()->getDirectory() . '/admin/templates/ajax_message.php');
	}
	
}