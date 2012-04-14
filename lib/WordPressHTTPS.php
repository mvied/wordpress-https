<?php 
/**
 * WordPressHTTPS Class for the WordPress plugin WordPress HTTPS
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */
class WordPressHTTPS extends WordPressHTTPS_Plugin {

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
	 * Plugin Settings
	 * 
	 * setting_name => default_value
	 *
	 * @var array
	 */
	protected $_settings = array(
		'ssl_host' =>               '', // Hostname for SSL Host
		'ssl_port' =>               '', // Port number for SSL Host
		'ssl_host_diff' =>          0,  // Is SSL Host different than WordPress host
		'ssl_host_subdomain' =>     0,  // Is SSL Host a subdomain of WordPress host
		'exclusive_https' =>        0,  // Exclusively force SSL on posts and pages with the `Force SSL` option checked.
		'frontpage' =>              0,  // Force SSL on front page
		'ssl_admin' =>              0,  // Force SSL Over Administration Panel (The same as FORCE_SSL_ADMIN)
		'debug' =>                  0,  // Debug Mode
	);

	/**
	 * Set HTTP Url
	 * 
	 * @param string $http_url
	 * @return object $this
	 */
	public function setHttpUrl( $http_url ) {
		$this->_http_url = $http_url;
		return $this;
	}
	
	/**
	 * Get HTTP Url
	 * 
	 * @param none
	 * @return string
	 */
	public function getHttpUrl() {
		return $this->_http_url;
	}
	
	/**
	 * Set HTTPS Url
	 * 
	 * @param string $https_url
	 * @return object $this
	 */
	public function setHttpsUrl( $https_url ) {
		$this->_https_url = $https_url;
		return $this;
	}
	
	/**
	 * Get HTTPS Url
	 * 
	 * @param none
	 * @return string
	 */
	public function getHttpsUrl() {
		return $this->_https_url;
	}
	
	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		// HTTP URL
		$this->setHttpUrl(WordPressHTTPS_Url::fromString(home_url('/', 'http')));
		// HTTPS URL
		$this->setHttpsUrl(WordPressHTTPS_Url::fromString(home_url('/', 'https')));

		// If using a different host for SSL
		if ( $this->getSetting('ssl_host') && $this->getSetting('ssl_host') != $this->getHttpsUrl()->toString() ) {
			// Assign HTTPS URL to SSL Host
			$this->setSetting('ssl_host_diff', 1);
			$this->setHttpsUrl(WordPressHTTPS_Url::fromString( $this->getSetting('ssl_host') ));
		} else {
			$this->setSetting('ssl_host_diff', 0);
		}

		// Add SSL Port to HTTPS URL
		$this->getHttpsUrl()->setPort($this->getSetting('ssl_port'));

		$this->getLogger()->log('Version: ' . $this->getVersion());
		$this->getLogger()->log('HTTP URL: ' . $this->getHttpUrl());
		$this->getLogger()->log('HTTPS URL: ' . $this->getHttpsUrl());
		$this->getLogger()->log('SSL: ' . ( $this->isSsl() ? 'Yes' : 'No' ));
		$this->getLogger()->log('Diff Host: ' . ( $this->getSetting('ssl_host_diff') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Subdomain: ' . ( $this->getSetting('ssl_host_subdomain') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Proxy: ' . ( isset($_COOKIE['https_proxy']) && $_COOKIE['https_proxy'] == 1 ? 'Yes' : 'No') );

		// Redirect admin/login pages. This is not pluggable due to the redirect methods used in wp-login.php
		if ( ( is_admin() || $GLOBALS['pagenow'] == 'wp-login.php' ) && $this->getSetting('ssl_admin') ) {
			add_action('wp_redirect', array($this->getModule('Hooks'), 'wp_redirect_admin'), 1, 1);
			if ( !$this->isSsl() ) {
				$this->redirect('https');
			}
		}

		parent::init();
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
		foreach ( $this->getSettings() as $option => $value ) {
			if ( get_option($option) === false ) {
				add_option($option, $value);
			}
		}

		// Checks to see if the SSL Host is a subdomain
		$http_domain = $this->getHttpUrl()->getBaseHost();
		$https_domain = $this->getHttpsUrl()->getBaseHost();

		if ( $this->getHttpsUrl()->setScheme('http') != $this->getHttpUrl() && $http_domain == $https_domain ) {
			$this->setSetting('ssl_host_subdomain', 1);
		}
	}
	/**
	 * Is Local URL
	 * 
	 * Determines if URL is local or external
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function isUrlLocal($url) {
		$string = $url;
		$url = WordPressHTTPS_Url::fromString($string);

		if ( $this->getHttpUrl()->getHost() != $url->getHost() && $this->getHttpsUrl()->getHost() != $url->getHost() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Replaces HTTP Host with HTTPS Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function makeUrlHttps( $string ) {
		$url_original = WordPressHTTPS_Url::fromString( $string ); // URL in string to be replaced
		$url = WordPressHTTPS_Url::fromString( $string ); // URL to replace HTTP URL
		if ( $url && $this->isUrlLocal($url_original->toString()) ) {
			$url->setScheme('https');
			$url->setHost($this->getHttpsUrl()->getHost());
			$url->setPort($this->getHttpsUrl()->getPort());

			if ( $this->getSetting('ssl_host_diff') ) {
				if ( strpos($url_original->getPath(), $this->getHttpsUrl()->getPath()) === false ) {
					if ( $url_original->getPath() == '/' ) {
						$url->setQuery($url_original->getQuery());
						$url->setPath($this->getHttpsUrl()->getPath());
					} else {
						$url->setPath($this->getHttpsUrl()->getPath() . $url->getPath());
					}
				}
			}
			return $url;
		} else {
			return $string;
		}
	}

	/**
	 * Replaces HTTPS Host with HTTP Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function makeUrlHttp( $string ) {
		$url_original = WordPressHTTPS_Url::fromString( $string ); // URL in string to be replaced
		$url = WordPressHTTPS_Url::fromString( $string ); // URL to replace HTTP URL
		if ( $url && $this->isUrlLocal($url_original) ) {
			$url->setScheme('http');
			$url->setHost($this->getHttpUrl()->getHost());
			$url->setPort($this->getHttpUrl()->getPort());
			
			if ( $this->getSetting('ssl_host_diff') ) { 
				if ( $this->getHttpsUrl()->getPath() != '/' && strpos($url->getPath(), $this->getHttpsUrl()->getPath()) !== false ) {
					$url->setPath(str_replace($this->getHttpsUrl()->getPath(), '', $url->getPath()));
				}
			}
			return $url;
		} else {
			return $string;
		}
	}

	/**
	 * Checks if the current page is SSL
	 *
	 * @param none
	 * @return bool
	 */
	public function isSsl() {
		// Some extra checks for proxies and Shared SSL
		if ( isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] == true ) {
			return true;
		} else if ( is_ssl() && strpos($_SERVER['HTTP_HOST'], $this->getHttpsUrl()->getHost()) === false && $_SERVER['SERVER_ADDR'] != $_SERVER['HTTP_HOST'] ) {
			return false;
		} else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ) {
			return true;
		} else if ( $this->getSetting('ssl_host_diff') && !is_ssl() && isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && $this->getHttpsUrl()->getHost() == $_SERVER['HTTP_X_FORWARDED_SERVER'] ) {
			return true;
		} else if ( $this->getSetting('ssl_host_diff') && !is_ssl() && $this->getHttpsUrl()->getHost() == $_SERVER['HTTP_HOST'] && ( $this->getHttpsUrl()->getPort() <= 0 || $_SERVER['SERVER_PORT'] == $this->getHttpsUrl()->getPort() ) && strpos($_SERVER['REQUEST_URI'], $this->getHttpsUrl()->getPath()) !== false ) {
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
		if ( !$this->isSsl() && $scheme == 'https' ) {
			$url = clone $this->getHttpsUrl();
			$url->setScheme($scheme);
		} else if ( $this->isSsl() && $scheme == 'http' ) {
			$url = clone $this->getHttpUrl();
			$url->setScheme($scheme);
		} else {
			$url = false;
		}

		if ( $url ) {
			// Prepend secure URL path if it does not exist.
			if ( strpos($url->getPath(), $_SERVER['REQUEST_URI']) != 0 ) {
				$url->setPath($url->getPath() . $_SERVER['REQUEST_URI']);
			} else {
				$url->setPath($_SERVER['REQUEST_URI']);
			}

			// Use a cookie to detect redirect loops
			$redirect_count = ( isset($_COOKIE['redirect_count']) && is_int($_COOKIE['redirect_count']) ? $_COOKIE['redirect_count']++ : 1 );
			setcookie('redirect_count', $redirect_count, 0, '/', '.' . $url->getHost());
			// If redirect count is 3 or higher, prevent redirect and log the redirect loop
			if ( $redirect_count >= 3 ) {
				$this->getLogger()->log('[ERROR] Redirect Loop!');
			// If no redirect loop, continue with redirect...
			} else {
				// Redirect
				if ( function_exists('wp_redirect') ) {
					wp_redirect($url, 301);
				} else {
					// End all output buffering and redirect
					while(@ob_end_clean());
	
					// If redirecting to an admin page
					if ( strpos($url->getPath(), 'wp-admin') !== false || strpos($url->getPath(), 'wp-login') !== false ) {
						$url = WordPressHTTPS_Url::fromString($this->getModule('Hooks')->wp_redirect_admin($url));
					}
	
					header("Location: " . $url);
				}
				exit();
			}
		}
	}
	
}