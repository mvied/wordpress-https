<?php
/**
 * Core Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Core extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {
	
	/**
	 * HTTP URL
	 *
	 * @var WordPressHTTPS_Url
	 */
	protected $_http_url;

	/**
	 * HTTPS URL
	 *
	 * @var WordPressHTTPS_Url
	 */
	protected $_https_url;
	
	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		// HTTP URL
		$this->set('http_url', WordPressHTTPS::factory('Url')->fromString(home_url('/', 'http')));
		// HTTPS URL
		$this->set('https_url', WordPressHTTPS::factory('Url')->fromString(home_url('/', 'https')));

		// If using a different host for SSL
		if ( $this->getSetting('ssl_host') && $this->getSetting('ssl_host')->get('host') != $this->get('https_url')->get('host') ) {
			// Assign HTTPS URL to SSL Host
			$this->updateSetting('ssl_host_diff', 1);
			$this->set('https_url', WordPressHTTPS::factory('Url')->fromString( $this->getSetting('ssl_host') ));
		} else {
			$this->updateSetting('ssl_host_diff', 0);
		}

		// Add SSL Port to HTTPS URL
		$this->get('https_url')->set('port', $this->getSetting('ssl_port'));

		$this->log('Version: ' . $this->get('version'));
		$this->log('HTTP URL: ' . $this->get('http_url'));
		$this->log('HTTPS URL: ' . $this->get('https_url'));
		$this->log('SSL: ' . ( $this->is_ssl() ? 'Yes' : 'No' ));
		$this->log('Subdomain: ' . ( $this->getSetting('ssl_host_subdomain') == 1 ? 'Yes' : 'No' ));
		$this->log('Proxy: ' . ( isset($_COOKIE['https_proxy']) && $_COOKIE['https_proxy'] == 1 ? 'Yes' : 'No') );

		// Redirect admin/login pages. This is not pluggable due to the redirect methods used in wp-login.php
		if ( ( is_admin() || $GLOBALS['pagenow'] == 'wp-login.php' ) && $this->getSetting('ssl_admin') ) {
			add_action('wp_redirect', array(&$this, 'wp_redirect_admin'), 1, 1);
			if ( !$this->is_ssl() ) {
				$this->redirect('https');
			}
		}
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
	 * Install
	 * 
	 * @param none
	 * @return void
	 */
	public function install() {
		// Add WordPress HTTPS settings to WordPress options
		foreach ( $this->get('settings') as $option => $value ) {
			if ( get_option($option) === false ) {
				add_option($option, $value);
			}
		}

		// Checks to see if the SSL Host is a subdomain
		$http_domain = $this->get('http_url')->getBaseHost();
		$https_domain = $this->get('https_url')->getBaseHost();

		if ( $this->get('https_url')->set('scheme', 'http') != $this->get('http_url') && $http_domain == $https_domain ) {
			$this->updateSetting('ssl_host_subdomain', 1);
		}

		// Run plugin updates
		$this->update();
	}

	/**
	 * Update
	 *
	 * @param none
	 * @return void
	 */
	protected function update() {
		// Remove deprecated options
		$deprecated_options = array(
			$this->get('slug') . '_sharedssl_site',
			$this->get('slug') . '_internalurls',
			$this->get('slug') . '_externalurls',
			$this->get('slug') . '_external_urls',
			$this->get('slug') . '_bypass',
			$this->get('slug') . '_disable_autohttps'
		);
		foreach( $deprecated_options as $option ) {
			delete_option($option);
		}

		// Upgrade from version < 2.0
		if ( $this->get('sharedssl') ) {
			$shared_ssl = (($this->get('sharedssl') == 1) ? true : false);

			$options = array(
				$this->get('slug') . '_sharedssl' =>		$this->get('sharedssl'),
				$this->get('slug') . '_sharedssl_host' =>	$this->get('sharedssl_host'),
				$this->get('slug') . '_sharedssl_admin' =>	$this->get('sharedssl_admin')
			);

			foreach( $options as $option => $value) {
				if ( $shared_ssl && $value ) {
					if ( $option == $this->get('slug') . '_sharedssl_host' ) {
						if ( $ssl_port = parse_url($value, PHP_URL_PORT) ) {
							update_option($this->get('slug') . '_ssl_port', $ssl_port);
							$value = str_replace(':' . $ssl_port, '', $value);
						}
						update_option($this->get('slug') . '_ssl_host', $value);
					}
					if ( $option == $this->get('slug') . '_sharedssl_admin' ) {
						update_option($this->get('slug') . '_ssl_admin', $value);
						delete_option($option);
					}
				}
				delete_option($option);
			}
		}
		
		// Update current version
		update_option($this->get('slug') . '_version', $this->get('version'));
	}

	/**
	 * Is Local URL
	 * 
	 * Determines if URL is local or external
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function is_local_url($url) {
		$string = $url;
		$url = WordPressHTTPS::factory('Url')->fromString($string);

		if ( $this->get('http_url')->get('host') != $url->get('host') && $this->get('https_url')->get('host') != $url->get('host') ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Is Subdomain Install
	 * 
	 * Determines if the HTTPS URL is a subdomain of the HTTP URL
	 *
	 * @param none
	 * @return boolean
	 */
	public function is_subdomain_install() {
		$http_host_parts = explode('.', $this->get('http_url')->get('host'));
		$https_host_parts = explode('.', $this->get('https_url')->get('host'));
		$host_intersect = array_intersect($https_host_parts, $http_host_parts);

		// Assuming if at least 2 pieces of the URL's are the same, that this is a subdomain install
		if ( sizeof($host_intersect) >= 2 ) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Replaces HTTP Host with HTTPS Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function replace_http_url( $string ) {
		$url_original = WordPressHTTPS::factory('Url')->fromString( $string ); // URL in string to be replaced
		$url = WordPressHTTPS::factory('Url')->fromString( $string ); // URL to replace HTTP URL
		if ( $this->is_local_url($url_original) ) {
			$url->set(array(
				'scheme' => 'https',
				'host'   => $this->get('https_url')->get('host'),
				'port'   => $this->get('https_url')->get('port'),
			));

			if ( $this->getSetting('ssl_host_diff') ) { 
				if ( strpos($url_original->get('path'), $this->get('https_url')->get('path')) === false ) {
					if ( $url_original->get('path') == '/' ) {
						$url->set('query', $url_original->get('query'));
						$url->set('path', $this->get('https_url')->get('path'));
					} else {
						$url->set('path', $this->get('https_url')->get('path') . $url->get('path') );
					}
				}
			}

			return str_replace($url_original, $url, $string);
		} else if ( $url_original == null ) {
			$this->log('[ERROR] WordPressHTTPS->replace_http_url - Invalid input:' . $string);
		}
	}

	/**
	 * Replaces HTTPS Host with HTTP Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function replace_https_url( $string ) {
		$url_original = WordPressHTTPS::factory('Url')->fromString( $string ); // URL in string to be replaced
		$url = WordPressHTTPS::factory('Url')->fromString( $string ); // URL to replace HTTP URL
		if ( $this->is_local_url($url_original) ) {
			$url->set(array(
				'scheme' => 'http',
				'host'   => $this->get('http_url')->get('host'),
				'port'   => $this->get('http_url')->get('port'),
			));

			if ( $this->getSetting('ssl_host_diff') ) { 
				if ( $this->get('https_url')->get('path') != '/' && strpos($url->get('path'), $this->get('https_url')->get('path')) !== false ) {
					$url->set('path', str_replace($this->get('https_url')->get('path'), '', $url->get('path')));
				}
			}

			return str_replace($url_original, $url, $string);
		} else if ( $url_original == null ) {
			$this->log('[ERROR] WordPressHTTPS->replace_https_url - Invalid input:' . $string);
		}
	}

	/**
	 * Checks if the current page is SSL
	 *
	 * @param none
	 * @return bool
	 */
	public function is_ssl() {
		// Some extra checks for proxies and Shared SSL
		if ( isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] == true ) {
			return true;
		} else if ( is_ssl() && strpos($_SERVER['HTTP_HOST'], $this->get('https_url')->get('host')) === false && $_SERVER['SERVER_ADDR'] != $_SERVER['HTTP_HOST'] ) {
			return false;
		} else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ) {
			return true;
		} else if ( $this->getSetting('ssl_host_diff') && !is_ssl() && isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && $this->get('https_url')->get('host') == $_SERVER['HTTP_X_FORWARDED_SERVER'] ) {
			return true;
		} else if ( $this->getSetting('ssl_host_diff') && !is_ssl() && $this->get('https_url')->get('host') == $_SERVER['HTTP_HOST'] && ( $this->get('https_url')->get('port') < 0 || $_SERVER['SERVER_PORT'] == $this->get('https_url')->get('port') ) && strpos($_SERVER['REQUEST_URI'], $this->get('https_url')->get('path')) !== false ) {
			return true;
		}
		return is_ssl();
	}

	/**
	 * Redirects page to HTTP or HTTPS accordingly
	 *
	 * @param string $scheme Either http or https
	 * @return void
	 */
	public function redirect( $scheme = 'https' ) {
		if ( !$this->is_ssl() && $scheme == 'https' ) {
			$url = clone $this->get('https_url');
			$url->set('scheme', $scheme);
		} else if ( $this->is_ssl() && $scheme == 'http' ) {
			$url = clone $this->get('http_url');
			$url->set('scheme', $scheme);
		} else {
			$url = false;
		}

		if ( $url ) {
			$url->set('path', $url->get('path') . $_SERVER['REQUEST_URI']);
			// Use a cookie to detect redirect loops
			$redirect_count = ( isset($_COOKIE['redirect_count']) && is_int($_COOKIE['redirect_count']) ? $_COOKIE['redirect_count']++ : 1 );
			setcookie('redirect_count', $redirect_count, 0, '/', '.' . $url->get('host'));
			// If redirect count is 3 or higher, prevent redirect and log the redirect loop
			if ( $redirect_count >= 3 ) {
				$this->log('[ERROR] Redirect Loop!');
			// If no redirect loop, continue with redirect...
			} else {
				// Redirect
				if ( function_exists('wp_redirect') ) {
					wp_redirect($url, 301);
				} else {
					// End all output buffering and redirect
					while(@ob_end_clean());
	
					// If redirecting to an admin page
					if ( strpos($url->get('path'), 'wp-admin') !== false || strpos($url->get('path'), 'wp-login') !== false ) {
						$url = WordPressHTTPS::factory('Url')->fromString($this->wp_redirect_admin($url));
					}
	
					header("Location: " . $url);
				}
				exit();
			}
		}
	}

}