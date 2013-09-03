<?php 
/**
 * URL Mapping Module
 * 
 * Provides a way for users to specify if a host hosts their HTTPS
 * content on a different domain.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

class WordPressHTTPS_Module_UrlMapping extends Mvied_Plugin_Module {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() ) {
			add_action('wp_ajax_' . $this->getPlugin()->getSlug() . '_url_mapping_save', array(&$this, 'save'));
			add_action('wp_ajax_' . $this->getPlugin()->getSlug() . '_url_mapping_reset', array(&$this, 'reset'));
			if ( isset($_GET['page']) && strpos($_GET['page'], $this->getPlugin()->getSlug()) !== false ) {
				// Add meta boxes
				add_action('admin_init', array(&$this, 'add_meta_boxes'));
			}
		}

		add_filter('http_internal_url', array(&$this, 'map_http_url'), 10);
		add_filter('http_external_url', array(&$this, 'map_http_url'), 10);
		add_filter('https_internal_url', array(&$this, 'map_https_url'), 10);
		add_filter('https_external_url', array(&$this, 'map_https_url'), 10);
	}

	/**
	 * Map HTTP URL
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function map_http_url( $url ) {
		$plugin = $this->getPlugin();
		if ( is_array($plugin->getSetting('ssl_host_mapping')) && sizeof($plugin->getSetting('ssl_host_mapping')) > 0 ) {
			foreach( $plugin->getSetting('ssl_host_mapping') as $mapping ) {
				if ( !is_array($mapping) || ( isset($mapping[1]['scheme']) && $mapping[1]['scheme'] != 'http' ) ) {
					continue;
				}
				$http_url = 'http://' . ( isset($mapping[1]['host']) ? $mapping[1]['host'] : '' );
				$https_url = 'https://' . ( isset($mapping[0]['host']) ? $mapping[0]['host'] : '' );
				preg_match('|' . $https_url . '|', $url, $matches);
				if ( sizeof($matches) > 0 ) {
					$url = preg_replace('|' . $https_url . '|', $http_url, $url);
				}
			}
		}
		return $url;
	}

	/**
	 * Map HTTPS URL
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function map_https_url( $url ) {
		$plugin = $this->getPlugin();
		if ( is_array($plugin->getSetting('ssl_host_mapping')) && sizeof($plugin->getSetting('ssl_host_mapping')) > 0 ) {
			foreach( $plugin->getSetting('ssl_host_mapping') as $mapping ) {
				if ( !is_array($mapping) || ( isset($mapping[1]['scheme']) && $mapping[1]['scheme'] != 'https' ) ) {
					continue;
				}
				$http_url = 'http://' . ( isset($mapping[0]['host']) ? $mapping[0]['host'] : '' );
				$https_url = 'https://' . ( isset($mapping[1]['host']) ? $mapping[1]['host'] : '' );
				preg_match('|' . $http_url . '|', $url, $matches);
				if ( sizeof($matches) > 0 ) {
					$url = preg_replace('|' . $http_url . '|', $https_url, $url);
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
			$this->getPlugin()->getSlug() . '_url_mapping',
			__( 'URL Mapping', 'wordpress-https' ),
			array($this->getPlugin()->getModule('Admin'), 'meta_box_render'),
			'toplevel_page_' . $this->getPlugin()->getSlug(),
			'main',
			'core',
			array( 'metabox' => 'url_mapping' )
		);
	}

	/**
	 * Reset URL Mapping
	 *
	 * @param array $settings
	 * @return void
	 */
	public function reset() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug()) ) {
			return false;
		}

		$message = __('URL Mapping reset.','wordpress-https');
		$errors = array();
		$reload = true;

		$this->getPlugin()->setSetting('ssl_host_mapping', WordPressHTTPS::$ssl_host_mapping);

		$this->getPlugin()->renderView('ajax_message', array('message' => $message, 'errors' => $errors, 'reload' => $reload));
	}

	/**
	 * Save URL Mapping
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		if ( !wp_verify_nonce($_POST['_wpnonce'], $this->getPlugin()->getSlug()) ) {
			return false;
		}

		$message = __('URL Mapping saved.','wordpress-https');
		$errors = array();
		$reload = false;

		$ssl_host_mapping = $this->getPlugin()->getSetting('ssl_host_mapping');
		$mappings = array();
		$i = 0;
		for( $j=0; $j<sizeof($_POST['url_mapping']['scheme']); $j+=2 ) {
			if ( isset($_POST['url_mapping']['host'][$j]) && $_POST['url_mapping']['host'][$j] != '' && isset($_POST['url_mapping']['host'][$j+1]) && $_POST['url_mapping']['host'][$j+1] != '' ) {
				$mappings[$i][] = array(
					'scheme' => $_POST['url_mapping']['scheme'][$j],
					'host'   => $_POST['url_mapping']['host'][$j]
				);
				$mappings[$i][] = array(
					'scheme' => $_POST['url_mapping']['scheme'][$j+1],
					'host'   => $_POST['url_mapping']['host'][$j+1]
				);
				$i++;
			}
		}
		$mappings = stripslashes_deep($mappings);
		$this->getPlugin()->setSetting('ssl_host_mapping', $mappings);

		$this->getPlugin()->renderView('ajax_message', array('message' => $message, 'errors' => $errors, 'reload' => $reload));
	}
	
}