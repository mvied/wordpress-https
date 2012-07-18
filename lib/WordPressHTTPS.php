<?php 
/**
 * WordPress HTTPS
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS extends Mvied_Plugin {

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
		'ssl_host' =>               '',      // Hostname for SSL Host
		'ssl_port' =>               '',      // Port number for SSL Host
		'secure_external_urls' =>   array(), // Secure external URL's
		'unsecure_external_urls' => array(), // Unsecure external URL's
		'ssl_host_diff' =>          0,       // Is SSL Host different than WordPress host
		'ssl_host_subdomain' =>     0,       // Is SSL Host a subdomain of WordPress host
		'exclusive_https' =>        0,       // Exclusively force SSL on posts and pages with the `Force SSL` option checked.
		'frontpage' =>              0,       // Force SSL on front page
		'ssl_admin' =>              0,       // Force SSL Over Administration Panel (The same as FORCE_SSL_ADMIN)
		'ssl_proxy' =>              0,       // Proxy detection
		'debug' =>                  0,       // Debug Mode
		'admin_menu' =>             'side',  // HTTPS Admin Menu location
		'secure_filter' =>          array(), // Expressions to secure URL's against
		'ssl_host_mapping' =>       array(), // External SSL Hosts whose HTTPS content is on another domain
	);

	/**
	 * Default External SSL Host Mapping
	 * @var array
	 */
	public static $ssl_host_mapping = array(
		'w.sharethis.com' => 'ws.sharethis.com',
		'\d.gravatar.com' => 'secure.gravatar.com',
	);

	/**
	 * Get HTTP Url
	 * 
	 * @param none
	 * @return WordPressHTTPS_Url
	 */
	public function getHttpUrl() {
		if ( !isset($this->_http_url) ) {
			$this->_http_url = WordPressHTTPS_Url::fromString('http://' . parse_url(get_bloginfo('template_url'), PHP_URL_HOST) . parse_url(home_url('/'), PHP_URL_PATH));
		}
		return $this->_http_url;
	}

	/**
	 * Get HTTPS Url
	 * 
	 * @param none
	 * @return WordPressHTTPS_Url
	 */
	public function getHttpsUrl() {
		if ( !isset($this->_https_url) ) {
			$this->_https_url = WordPressHTTPS_Url::fromString('https://' . parse_url(get_bloginfo('template_url'), PHP_URL_HOST) . parse_url(home_url('/'), PHP_URL_PATH));

			// If using a different host for SSL
			if ( is_string($this->getSetting('ssl_host')) && $this->getSetting('ssl_host') != '' && $this->getSetting('ssl_host') != $this->_https_url->toString() ) {
				// Assign HTTPS URL to SSL Host
				$this->setSetting('ssl_host_diff', 1);
				$this->_https_url = WordPressHTTPS_Url::fromString( rtrim($this->getSetting('ssl_host'), '/') . '/' );
			} else {
				$this->setSetting('ssl_host_diff', 0);
			}

			// Prepend SSL Host path
			if ( strpos($this->_https_url->getPath(), $this->getHttpUrl()->getPath()) === false ) {
				$this->_https_url->setPath( $this->_https_url->getPath() . $this->getHttpUrl()->getPath() );
			}

			// Add SSL Port to HTTPS URL
			$this->_https_url->setPort($this->getSetting('ssl_port'));
		}

		return $this->_https_url;
	}
	
	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		$this->getLogger()->log('Version: ' . $this->getVersion());
		$this->getLogger()->log('HTTP URL: ' . $this->getHttpUrl()->toString());
		$this->getLogger()->log('HTTPS URL: ' . $this->getHttpsUrl()->toString());
		$this->getLogger()->log('SSL: ' . ( $this->isSsl() ? 'Yes' : 'No' ));
		$this->getLogger()->log('Diff Host: ' . ( $this->getSetting('ssl_host_diff') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Subdomain: ' . ( $this->getSetting('ssl_host_subdomain') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Proxy: ' . ( $this->getSetting('ssl_proxy') === 'auto' ? 'Auto' : ( $this->getSetting('ssl_proxy') ? 'Yes' : 'No' ) ));
		$this->getLogger()->log('Secure External URLs: [ ' . implode(', ', (array)$this->getSetting('secure_external_urls')) . ' ]');
		$this->getLogger()->log('Unsecure External URLs: [ ' . implode(', ', (array)$this->getSetting('unsecure_external_urls')) . ' ]');

		// Redirect login page. This is not pluggable due to the redirect methods used in wp-login.php
		if ( ( $GLOBALS['pagenow'] == 'wp-login.php' ) ) {
			setcookie(constant('TEST_COOKIE'), 'WP Cookie check', 0);
			if ( $this->getSetting('ssl_admin') && ! $this->isSsl() ) {
				$this->redirect('https');
			}
		}

		parent::init();
	}

	/**
	 * Install
	 * 
	 * @param none
	 * @return void
	 */
	public function install() {
		global $wpdb;

		if ( function_exists('is_multisite') && is_multisite() && isset($_GET['networkwide']) && $_GET['networkwide'] == 1 ) {
			$blogs = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM " . $wpdb->blogs));
		} else {
			$blogs = array($wpdb->blogid);
		}
		foreach ( $blogs as $blog_id ) {
			// Add Settings
			foreach ( $this->getSettings() as $option => $value ) {
				if ( get_blog_option($blog_id, $option) === false ) {
					add_blog_option($blog_id, $option, $value);
				}
			}

			// Fix a bug that saved the ssl_host as an object
			if ( ! is_string($this->getSetting('ssl_host', $blog_id)) ) {
				$this->setSetting('ssl_host', $this->_settings['ssl_host'], $blog_id);
				$this->setSetting('ssl_port', $this->_settings['ssl_port'], $blog_id);
				$this->setSetting('ssl_host_diff', $this->_settings['ssl_host_diff'], $blog_id);
				$this->setSetting('ssl_host_subdomain', $this->_settings['ssl_host_subdomain'], $blog_id);
			}

			// Reset cache
			$this->setSetting('secure_external_urls', $this->_settings['secure_external_urls'], $blog_id);
			$this->setSetting('unsecure_external_urls', $this->_settings['unsecure_external_urls'], $blog_id);
		}

		// Checks to see if the SSL Host is a subdomain
		$http_domain = $this->getHttpUrl()->getBaseHost();
		$https_domain = $this->getHttpsUrl()->getBaseHost();

		if ( $this->getHttpsUrl()->setScheme('http')->toString() != $this->getHttpUrl()->toString() && $http_domain == $https_domain ) {
			$this->setSetting('ssl_host_subdomain', 1, $blog_id);
		} else {
			$this->setSetting('ssl_host_subdomain', 0, $blog_id);
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
		$url_parts = parse_url($url);

		if ( $url_parts && $this->getHttpUrl()->getHost() != $url_parts['host'] && $this->getHttpsUrl()->getHost() != $url_parts['host'] ) {
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
		if ( !is_string($string) ) {
			return $string;
		}

		$url = WordPressHTTPS_Url::fromString( $string );
		if ( $url ) {
			if ( $this->isUrlLocal($url) ) {
				if ( $url->getScheme() == 'http' || $this->getSetting('ssl_host_diff') ) {
					$updated = clone $url;
					$updated->setScheme('https');
					$updated->setHost($this->getHttpsUrl()->getHost());
					$updated->setPort($this->getHttpsUrl()->getPort());
					if ( $this->getSetting('ssl_host_diff') && strpos($updated->getPath(), $this->getHttpsUrl()->getPath()) === false ) {
						if ( $this->getHttpUrl()->getPath() == '/' ) {
							$updated->setPath(rtrim($this->getHttpsUrl()->getPath(), '/') . $updated->getPath());
						} else {
							$updated->setPath(str_replace($this->getHttpUrl()->getPath(), $this->getHttpsUrl()->getPath(), $updated->getPath()));
						}
					}
					$string = str_replace($url, $updated, $string);
				}
			} else {
				$updated = clone $url;
				$updated = WordPressHTTPS_Url::fromString( apply_filters('https_external_url', $updated->setScheme('https')->toString()) );
				if ( @in_array($updated->toString(), $this->getSetting('secure_external_urls')) == false && @in_array($updated->toString(), $this->getSetting('unsecure_external_urls')) == false ) {
					if ( $updated->isValid() ) {
						// Cache this URL as available over HTTPS for future reference
						$this->addSecureExternalUrl($updated->toString());
					} else {
						// If not available over HTTPS, mark as an unsecure external URL
						$this->addUnsecureExternalUrl($updated->toString());
					}
				}
				if ( $url->toString() != $updated->toString() || in_array($updated->toString(), $this->getSetting('secure_external_urls')) ) {
					$string = str_replace($url, $updated, $string);
				}
			}
			unset($updated);
			unset($url);
		}
		return $string;
	}

	/**
	 * Replaces HTTPS Host with HTTP Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function makeUrlHttp( $string ) {
		if ( !is_string($string) ) {
			return $string;
		}

		$url = WordPressHTTPS_Url::fromString( $string );
		if ( $url ) {
			if ( $this->isUrlLocal($url) ) {
				if ( $url->getScheme() == 'http' || $this->getSetting('ssl_host_diff') ) {
					$updated = clone $url;
					$updated->setScheme('http');
					$updated->setHost($this->getHttpUrl()->getHost());
					$updated->setPort($this->getHttpUrl()->getPort());
					if ( $this->getSetting('ssl_host_diff') && strpos($updated->getPath(), $this->getHttpsUrl()->getPath()) !== false ) {
						$updated->setPath(str_replace($this->getHttpsUrl()->getPath(), $this->getHttpUrl()->getPath(), $updated->getPath()));
					}
					$string = str_replace($url, $updated, $string);
				}
			} else {
				$updated = apply_filters('http_external_url', str_replace('https://', 'http://', $url));
				$string = str_replace($url, $updated, $string);
			}
		}
		unset($updated);
		unset($url);
		return $string;
	}

	/**
	 * Add Secure External URL
	 *
	 * @param string $value
	 * @return $this
	 */
	public function addSecureExternalUrl( $value ) {
		if ( trim($value) == '' ) {
			return $this;
		}

		$secure_external_urls = (array) $this->getSetting('secure_external_urls');
		array_push($secure_external_urls, (string) $value);
		$this->setSetting('secure_external_urls', $secure_external_urls);

		return $this;
	}

	/**
	 * Add Unsecure External URL
	 *
	 * @param string $value
	 * @return $this
	 */
	public function addUnsecureExternalUrl( $value ) {
		if ( trim($value) == '' ) {
			return $this;
		}

		$unsecure_external_urls = (array) $this->getSetting('unsecure_external_urls');
		array_push($unsecure_external_urls, (string) $value);
		$this->setSetting('unsecure_external_urls', $unsecure_external_urls);

		return $this;
	}

	/**
	 * Checks if the current page is SSL
	 *
	 * @param none
	 * @return bool
	 */
	public function isSsl() {
		// Some extra checks for Shared SSL
		if ( is_ssl() && strpos($_SERVER['HTTP_HOST'], $this->getHttpsUrl()->getHost()) === false && $_SERVER['SERVER_ADDR'] != $_SERVER['HTTP_HOST'] ) {
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
	 * Maintained for backwards compatibility.
	 *
	 * @param none
	 * @return bool
	 */
	public function is_ssl() {
		return $this->isSsl();
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
			$path = ( isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['REQUEST_URI'] );
			if ( strpos($_SERVER['REQUEST_URI'], '?') !== false && isset($_SERVER['REDIRECT_URL']) && strpos($_SERVER['REDIRECT_URL'], '?') === false ) {
				$path .= substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '?'));
			}

			if ( $this->getHttpsUrl()->getPath() != '/' ) {
				$path = str_replace($this->getHttpsUrl()->getPath(), '', $path);
			}
			$path = ltrim($path, '/');

			if ( $scheme == 'https' ) {
				if ( $this->getSetting('ssl_host_diff') && $this->getHttpUrl()->getPath() != '/' ) {
					$url->setPath(str_replace($this->getHttpUrl()->getPath(), $this->getHttpsUrl()->getPath(), $_SERVER['REQUEST_URI']));
				} else {
					$url->setPath(rtrim($this->getHttpsUrl()->getPath(), '/') . '/' . $path);
				}
			} else if ($scheme == 'http' ) {
				if ( $this->getSetting('ssl_host_diff') &&  $this->getHttpsUrl()->getPath() != '/' ) {
					$url->setPath(str_replace($this->getHttpsUrl()->getPath(), $this->getHttpUrl()->getPath(), $_SERVER['REQUEST_URI']));
				} else {
					$url->setPath(rtrim($this->getHttpUrl()->getPath(), '/') . '/' . $path);
				}
			}

			// Use a cookie to detect redirect loops
			$redirect_count = ( isset($_COOKIE['redirect_count']) && is_numeric($_COOKIE['redirect_count']) ? (int)$_COOKIE['redirect_count']+1 : 1 );
			setcookie('redirect_count', $redirect_count, 0, '/');
			// If redirect count is greater than 2, prevent redirect and log the redirect loop
			if ( $redirect_count > 2 ) {
				setcookie('redirect_count', null, -time(), '/');
				$this->getLogger()->log('[ERROR] Redirect Loop!');
				return;
			}

			// Redirect
			if ( function_exists('wp_redirect') ) {
				wp_redirect($url, 301);
			} else {
				// End all output buffering and redirect
				while(@ob_end_clean());

				// If redirecting to an admin page
				if ( strpos($url->getPath(), 'wp-admin') !== false || strpos($url->getPath(), 'wp-login') !== false ) {
					$url = WordPressHTTPS_Url::fromString($this->redirectAdmin($url));
				}

				header("Location: " . $url, true, 301);
			}
			exit();
		}
	}
	
	/**
	 * WP Redirect Admin
	 * WordPress Filter - wp_redirect_admin
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function redirectAdmin( $url ) {
		$url = $this->makeUrlHttps($url);

		// Fix redirect_to
		preg_match('/redirect_to=([^&]+)/i', $url, $redirect);
		$redirect_url = @$redirect[1];
		$url = str_replace($redirect_url, urlencode($this->makeUrlHttps(urldecode($redirect_url))), $url);
		return $url;
	}
}