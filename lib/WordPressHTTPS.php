<?php
/**
 * WordPress HTTPS
 *
 * @class WordPressHTTPS
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS extends Mvied_Plugin_Modular {

	/**
	 * HTTP URL
	 *
	 * @var Mvied_Url
	 */
	protected $_http_url;

	/**
	 * HTTPS URL
	 *
	 * @var Mvied_Url
	 */
	protected $_https_url;

	/**
	 * Local URL Cache
	 *
	 * @var array:string
	 */
	protected $_local_url_cache = array();

	/**
	 * Local HTTP URL Cache
	 *
	 * @var array:string
	 */
	protected $_local_http_url_cache = array();

	/**
	 * Local HTTPS URL Cache
	 * @var array:string
	 */
	protected $_local_https_url_cache = array();

	/**
	 * Plugin Settings
	 *
	 * setting_name => default_value
	 *
	 * @var array:any
	 */
	protected $_settings = array(
		'ssl_host' =>               '',      // Hostname for SSL Host
		'secure_external_urls' =>   array(), // Secure external URL's
		'unsecure_external_urls' => array(), // Unsecure external URL's
		'ssl_host_diff' =>          0,       // Is SSL Host different than WordPress host
		'ssl_host_subdomain' =>     0,       // Is SSL Host a subdomain of WordPress host
		'exclusive_https' =>        0,       // Redirect pages that are not secured to HTTP
		'content_fixer' =>          1,       // Fix unsecure elements in HTML
		'remove_unsecure' =>        0,       // Remove unsecure elements from HTML
		'ssl_admin' =>              0,       // Force SSL Over Administration Panel (The same as FORCE_SSL_ADMIN)
		'ssl_proxy' =>              0,       // Proxy detection
		'debug' =>                  0,       // Debug Mode
		'admin_menu' =>             'side',  // HTTPS Admin Menu location
		'secure_filter' =>          array(), // Expressions to secure URL's against
		'unsecure_filter' =>        array(), // Expressions to unsecure URL's against
		'ssl_host_mapping' =>       array(), // External SSL Hosts whose HTTPS content is on another domain
		'network_defaults' =>       array(), // Default settings for new blogs on a multisite network
		'path_cache' =>             array(), // Cache of URL paths to Post IDs
		'blog_cache' =>             array(), // Cache of URL paths to Blog IDs
		'version' =>                '',      // Version of the plugin this blog has installed
		'hosts' =>					array(),
	);

	/**
	 * File extensions to be loaded securely.
	 * File type => Array of extensions
	 *
	 * @var array:array
	 */
	protected $_file_extensions = array(
		'script' => array(
			'js'
		),
		'img'    => array(
			'jpg',
			'jpeg',
			'png',
			'gif',
			'ico'
		),
		'style'  => array(
			'css'
		),
		'font'   => array(
			'ttf',
			'otf'
		)
	);

	/**
	 * Default External SSL Host Mapping
	 * @var array:array
	 */
	public static $ssl_host_mapping = array(
		array(
			array(
				'scheme' => 'http',
				'host' =>   'w.sharethis.com'
			),array(
				'scheme' => 'https',
				'host'   => 'ws.sharethis.com'
			)
		),array(
			array(
				'scheme' => 'https',
				'host' =>   'ws.sharethis.com'
			),array(
				'scheme' => 'http',
				'host'   => 'w.sharethis.com'
			)
		),array(
			array(
				'scheme' => 'http',
				'host' =>   '\d.gravatar.com'
			),array(
				'scheme' => 'https',
				'host'   => 'secure.gravatar.com'
			)
		),array(
			array(
				'scheme' => 'https',
				'host' =>   'secure.gravatar.com'
			),array(
				'scheme' => 'http',
				'host'   => '0.gravatar.com'
			)
		)
	);

	/**
	 * Get File Extensions to Secure
	 *
	 * @return array
	 */
	public function getFileExtensions() {
		return $this->_file_extensions;
	}

	/**
	 * Get HTTP Url
	 *
	 * @return Mvied_Url
	 */
	public function getHttpUrl() {
		if ( !isset($this->_http_url) ) {
			$this->_http_url = Mvied_Url::fromString('http://' . parse_url(get_bloginfo('template_url'), PHP_URL_HOST) . parse_url(home_url('/'), PHP_URL_PATH));
		}
		return $this->_http_url;
	}

	/**
	 * Get HTTPS Url
	 *
	 * @return Mvied_Url
	 */
	public function getHttpsUrl() {
		if ( !isset($this->_https_url) ) {
			$this->_https_url = clone $this->getHttpUrl();
			$this->_https_url->setScheme('https');

			if ( is_string($this->getSetting('ssl_host')) && $this->getSetting('ssl_host') != '' ) {
				$ssl_host = rtrim($this->getSetting('ssl_host'), '/') . '/';
				// If using a different host for SSL
				if ( $ssl_host != $this->_https_url->toString() ) {
					// Assign HTTPS URL to SSL Host
					$this->setSetting('ssl_host_diff', 1);
					if ( strpos($ssl_host, 'http://') === false && strpos($ssl_host, 'https://') === false ) {
						$ssl_host = 'https://' . $ssl_host;
					}
					$this->_https_url = Mvied_Url::fromString( $ssl_host );
				} else {
					$this->setSetting('ssl_host_diff', 0);
				}
			}

			// Prepend SSL Host path
			if ( strpos($this->_https_url->getPath(), $this->getHttpUrl()->getPath()) === false ) {
				$this->_https_url->setPath( $this->_https_url->getPath() . $this->getHttpUrl()->getPath() );
			}
		}

		return $this->_https_url;
	}

	/**
	 * Get domains local to the WordPress installation.
	 *
	 * @return array $hosts Array of domains local to the WordPress installation.
	 */
	public function getLocalDomains() {
		$hosts = $this->getSetting( 'hosts' );

		if ( ! empty( $hosts ) )
			return $hosts;

		global $wpdb;
		$hosts = array(
			$this->getHttpUrl()->getHost(),
			$this->getHttpsUrl()->getHost(),
		);

		if ( is_multisite() && is_subdomain_install() ) {
			$multisite_hosts = $wpdb->get_col("SELECT domain FROM {$wpdb->blogs}");
			$hosts = array_merge( $hosts, $multisite_hosts );
		}

		if ( function_exists( 'domain_mapping_siteurl' ) ) {
			if ( $mapped_host = parse_url( domain_mapping_siteurl( false ), PHP_URL_HOST ) )
				$hosts[] = $mapped_host;
		}

		$this->setSetting( 'hosts', $hosts );

		return $hosts;
	}

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public function init() {
		$this->getLogger()->log('WordPress HTTPS: ' . $this->getVersion());
		$this->getLogger()->log('HTTP URL: ' . $this->getHttpUrl()->toString());
		$this->getLogger()->log('HTTPS URL: ' . $this->getHttpsUrl()->toString());
		$this->getLogger()->log('SSL: ' . ( $this->isSsl() ? 'Yes' : 'No' ));
		$this->getLogger()->log('Diff Host: ' . ( $this->getSetting('ssl_host_diff') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Subdomain: ' . ( $this->getSetting('ssl_host_subdomain') ? 'Yes' : 'No' ));
		$this->getLogger()->log('Proxy: ' . ( $this->getSetting('ssl_proxy') === 'auto' ? 'Auto' : ( $this->getSetting('ssl_proxy') ? 'Yes' : 'No' ) ));
		$this->getLogger()->log('Secure External URLs: [ ' . implode(', ', (array)$this->getSetting('secure_external_urls')) . ' ]');
		$this->getLogger()->log('Unsecure External URLs: [ ' . implode(', ', (array)$this->getSetting('unsecure_external_urls')) . ' ]');

		parent::init();
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install() {
		global $wpdb;

		if ( is_multisite() && is_network_admin() ) {
			$blogs = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
		} else {
			$blogs = array($wpdb->blogid);
		}

		$defaults = $this->getSetting('network_defaults');
		foreach ( $blogs as $blog_id ) {
			if ( version_compare($this->getSetting('version', $blog_id), $this->getVersion(), '<') ) {
				if ( $this->getSetting('version', $blog_id) == '' ) {
					// Add Settings
					foreach ( $this->getSettings() as $option => $value ) {
						if ( is_multisite() ) {
							if ( add_blog_option($blog_id, $option, $value) && isset($defaults[$option]) && $defaults[$option] != '' ) {
								if ( $option == 'ssl_host' && strpos($value, 'https://') !== 0 ) {
									$value = 'https://' . rtrim($defaults[$option], '/') . '/';
								} else {
									$value = $defaults[$option];
								}
								$this->setSetting($option, $value, $blog_id);
							}
						} else {
							add_option($option, $value);
						}
					}
				}

				// Fix a bug that saved the ssl_host as an object
				if ( ! is_string($this->getSetting('ssl_host', $blog_id)) ) {
					$this->setSetting('ssl_host', $this->_settings['ssl_host'], $blog_id);
					$this->setSetting('ssl_host_diff', $this->_settings['ssl_host_diff'], $blog_id);
					$this->setSetting('ssl_host_subdomain', $this->_settings['ssl_host_subdomain'], $blog_id);
				}

				// Remove old ssl_port setting and append to HTTPS URL
				if ( (int)$this->getSetting('ssl_port', $blog_id) > 0 ) {
					if ( $this->getSetting('ssl_port', $blog_id) != 443 ) {
						$ssl_host = Mvied_Url::fromString( $this->getSetting('ssl_host', $blog_id) );
						$ssl_host->setPort($this->getSetting('ssl_port', $blog_id));
						$this->setSetting('ssl_host', $ssl_host->toString(), $blog_id);
					}
					$this->setSetting('ssl_port', null, $blog_id);
				}

				// If secure front page option exists, create front page filter
				if ( $this->getSetting('frontpage', $blog_id) ) {
					$this->setSetting('secure_filter', array_merge($this->getSetting('secure_filter'), array(rtrim(str_replace('http://', '', $this->getHttpUrl()->toString()), '/') . '/$')));
					$this->setSetting('frontpage', 0, $blog_id);
				}

				// Reformat ssl_host_mapping
				$ssl_host_mapping = $this->getSetting('ssl_host_mapping', $blog_id);
				if ( $ssl_host_mapping != array() && !is_array($ssl_host_mapping[0]) ) {
					$mappings = array();
					foreach( $ssl_host_mapping as $http_host => $https_host ) {
						$mappings[] = array(
							array(
								'scheme' => 'http',
								'host'   => $http_host
							),
							array(
								'scheme' => 'https',
								'host'   => $https_host
							)
						);
					}
					$this->setSetting('ssl_host_mapping', $mappings, $blog_id);
				}
				// Set default URL Mapping
				if ( $this->getSetting('ssl_host_mapping', $blog_id) == array() ) {
					$this->setSetting('ssl_host_mapping', WordPressHTTPS::$ssl_host_mapping, $blog_id);
				}

				// Reset cache
				$this->setSetting('secure_external_urls', $this->_settings['secure_external_urls'], $blog_id);
				$this->setSetting('unsecure_external_urls', $this->_settings['unsecure_external_urls'], $blog_id);
				$this->setSetting('path_cache', $this->_settings['path_cache'], $blog_id);
				$this->setSetting('blog_cache', $this->_settings['blog_cache'], $blog_id);
				$this->setSetting('hosts', $this->_settings['hosts'], $blog_id);
			}

			$this->setSetting('version', $this->getVersion(), $blog_id);
		}

		$is_subdomain = $this->getHttpsUrl()->isSubdomain($this->getHttpUrl());
		foreach ( $blogs as $blog_id ) {
			$this->setSetting('ssl_host_subdomain', $is_subdomain, $blog_id);
		}

		// Check for deprecated modules
		if ( file_exists( $this->getModuleDirectory() . '/DomainMapping.php') ) {
			@unlink($this->getModuleDirectory() . '/DomainMapping.php');
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
		if(is_object($url) && !method_exists($url, "__toString")) {
			return false;
		} else if ( (string)$url == '' ) {
			return false;
		}
		$origUrl = (string)$url;
		if (array_key_exists($origUrl, $this->_local_url_cache))
			return $this->_local_url_cache[$origUrl];
		$hosts = $this->getLocalDomains();
		if ( ($url_parts = @parse_url($url)) && isset($url_parts['host']) && !in_array($url_parts['host'], $hosts) ) {
			$this->_local_url_cache[$origUrl] = false;
			return false;
		}
		$this->_local_url_cache[$origUrl] = true;
		return true;
	}

	/**
	 * Replaces HTTP Host with HTTPS Host
	 *
	 * @param string $string
	 * @return string $string
	 */
	public function makeUrlHttps( $string ) {
		if(is_object($string) && !method_exists($string, "__toString")) {
			return $string;
		} else if ( (string)$string == '' ) {
			return $string;
		}
		$origString = (string)$string;
		if (array_key_exists($origString, $this->_local_https_url_cache))
			return $this->_local_https_url_cache[$origString];

		// If relative, prepend appropriate path
		if ( strpos($string, '/') === 0 ) {
			if ( $this->getSetting('ssl_host_diff') && strpos($string, $this->getHttpsUrl()->getPath()) === false ) {
				if ( $this->getHttpUrl()->getPath() == '/' ) {
					$string = $this->_local_https_url_cache[$origString] = rtrim($this->getHttpsUrl()->getPath(), '/') . $string;
				} else {
					$string = $this->_local_https_url_cache[$origString] = str_replace($this->getHttpUrl()->getPath(), $this->getHttpsUrl()->getPath(), $string);
				}
			}
		} else if ( $url = Mvied_Url::fromString( $string ) ) {
			if ( $this->isUrlLocal($url) ) {
				if ( $url->getScheme() == 'http' || ( $url->getScheme() == 'https' && $this->getSetting('ssl_host_diff') ) ) {
					$has_host = ( $this->getHttpUrl()->getHost() == $this->getHttpsUrl()->getHost() ) || strpos($url, $this->getHttpsUrl()->getHost()) !== false;
					$has_path = ( $this->getHttpUrl()->getPath() == $this->getHttpsUrl()->getPath() ) || strpos($url, $this->getHttpsUrl()->getPath()) !== false;
					$has_port = ( (int)$this->getHttpsUrl()->getPort() > 0 ? strpos($url, ':' . $this->getHttpsUrl()->getPort()) !== false : true );
					if ( $url->getScheme() == 'http' || !$has_host || !$has_path || !$has_port ) {
						$updated = Mvied_Url::fromString( apply_filters('https_internal_url', $url->toString()) );
						$updated->setScheme('https');
						$updated->setHost($this->getHttpsUrl()->getHost());
						$updated->setPort($this->getHttpsUrl()->getPort());
						if ( $this->getSetting('ssl_host_diff') && strpos($updated->getPath(), $this->getHttpsUrl()->getPath()) === false ) {
							if ( $this->getHttpUrl()->getPath() == '/' ) {
								$updated->setPath(rtrim($this->getHttpsUrl()->getPath(), '/') . $updated->getPath());
							} else if ( strpos($updated->getPath(), $this->getHttpUrl()->getPath()) !== false ) {
								$updated->setPath(str_replace($this->getHttpUrl()->getPath(), $this->getHttpsUrl()->getPath(), $updated->getPath()));
							} else if ( strpos($updated->getPath(), rtrim($this->getHttpUrl()->getPath(), '/')) !== false ) {
								$updated->setPath(str_replace(rtrim($this->getHttpUrl()->getPath(), '/'), $this->getHttpsUrl()->getPath(), $updated->getPath()));
							}
						}
						foreach( $this->getLocalDomains() as $domain ) {
							$updated->setHost($domain);
							$string = str_replace($url, $updated, $string);
						}
						// specific case for admin redirect URLs
						if ( ( ( $this->isSsl() && !$this->getSetting('exclusive_https') ) || ( defined('FORCE_SSL_ADMIN') && constant('FORCE_SSL_ADMIN') ) || $this->getSetting('ssl_admin') ) && strpos($url, 'wp-admin') !== false && preg_match('/redirect_to=([^&]+)/i', $updated->toString(), $redirect) && isset($redirect[1]) ) {
							$redirect_url = $redirect[1];
							$string = $this->_local_https_url_cache[$origString] = str_replace($redirect_url, urlencode($this->makeUrlHttps(urldecode($redirect_url))), $updated->toString());
						} else if ( $url->toString() != $updated->toString() ) {
							// if old url does not appear in string, this is probably due to trailing slash
							if ( ! strpos( $url->toString(), $string ) && strpos($url->toString(), rtrim($string, '/')) ) {
								$string = $this->_local_https_url_cache[$origString] = str_replace( $url->toString(), $updated->toString(), rtrim($string, '/') );
							} else {
								$string = $this->_local_https_url_cache[$origString] = str_replace( $url->toString(), $updated->toString(), $string );
							}
						} else {
							$this->_local_https_url_cache[$origString] = $origString;
						}
					}
				}
			} else {
				$updated = Mvied_Url::fromString( apply_filters('https_external_url', $url->toString()) );
				if (!$updated || !is_string((string)$updated))
					return;
				if ( @in_array($updated->toString(), $this->getSetting('secure_external_urls')) == false && @in_array($updated->toString(), $this->getSetting('unsecure_external_urls')) == false ) {
					$test = clone $updated;
					$test->setScheme('https');
					if ( $test->isValid() ) {
						// Cache this URL as available over HTTPS for future reference
						$this->addSecureExternalUrl($updated->toString());
						$updated->setScheme('https');
					} else {
						// If not available over HTTPS, mark as an unsecure external URL
						$this->addUnsecureExternalUrl($updated->toString());
					}
				} else if ( in_array($updated->toString(), $this->getSetting('secure_external_urls')) ) {
					$updated->setScheme('https');
				}
				if ( $url->toString() == $updated->toString() ) {
					$this->_local_https_url_cache[$origString] = $origString;
				} else {
					$string = $this->_local_https_url_cache[$origString] = str_replace($url, $updated, $string);
				}
			}
			unset($test);
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
		if(is_object($string) && !method_exists($string, "__toString")) {
			return $string;
		} else if ( (string)$string == '' ) {
			return $string;
		}
		$origString = (string)$string;
		if (array_key_exists($origString, $this->_local_http_url_cache))
			return $this->_local_http_url_cache[$origString];

		// If relative
		if ( strpos($string, '/') === 0 ) {
			if ( $this->getSetting('ssl_host_diff') && strpos($string, $this->getHttpsUrl()->getPath()) !== false ) {
				$string = $this->_local_http_url_cache[$origString] = str_replace($this->getHttpsUrl()->getPath(), $this->getHttpUrl()->getPath(), $string);
			}
		} else if ( $url = Mvied_Url::fromString( $string ) ) {
			if ( $this->isUrlLocal($url) ) {
				if ( $url->getScheme() == 'https' ) {
					$updated = Mvied_Url::fromString(apply_filters('http_internal_url', $url->toString()));
					$updated->setScheme('http');
					$updated->setHost($this->getHttpUrl()->getHost());
					$updated->setPort($this->getHttpUrl()->getPort());
					if ( $this->getSetting('ssl_host_diff') && strpos($updated->getPath(), $this->getHttpsUrl()->getPath()) !== false ) {
						$updated->setPath(str_replace($this->getHttpsUrl()->getPath(), $this->getHttpUrl()->getPath(), $updated->getPath()));
					}
					if ( strpos($url, 'wp-admin') !== false && preg_match('/redirect_to=([^&]+)/i', $url, $redirect) && isset($redirect[1]) ) {
						$redirect_url = $redirect[1];
						$url = str_replace($redirect_url, urlencode($this->makeUrlHttp(urldecode($redirect_url))), $url);
					}
					$string = $this->_local_http_url_cache[$origString] = str_replace($url, $updated, $string);
				}
			} else {
				$updated = Mvied_Url::fromString( apply_filters('http_external_url', $url->toString()) );
				$updated->setScheme('http');
				if ( $url->toString() == $updated->toString() ) {
					$this->_local_http_url_cache[$origString] = $origString;
				} else {
					$string = $this->_local_http_url_cache[$origString] = str_replace($url, $updated, $string);
				}
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
	 * @return bool
	 */
	public function isSsl() {
		$is_ssl = is_ssl();
		// Some extra checks for Shared SSL
		if ( $is_ssl && strpos($_SERVER['HTTP_HOST'], $this->getHttpsUrl()->getHost()) === false && $_SERVER['SERVER_ADDR'] != $_SERVER['HTTP_HOST'] ) {
			$is_ssl = false;
		} else if ( isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') ) {
			$is_ssl = true;
		} else if ( isset($_SERVER['HTTP_X_FORWARDED_SSL']) && ( strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) == 'on' || $_SERVER['HTTP_X_FORWARDED_SSL'] == 1 ) ) {
			$is_ssl = true;
		} else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ) {
			$is_ssl = true;
		} else if ( $this->getSetting('ssl_host_diff') && !$is_ssl && isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && $this->getHttpsUrl()->getHost() == $_SERVER['HTTP_X_FORWARDED_SERVER'] ) {
			$is_ssl = true;
		} else if ( $this->getSetting('ssl_host_diff') && !$is_ssl && $this->getHttpsUrl()->getHost() == $_SERVER['HTTP_HOST'] && ( $this->getHttpsUrl()->getPort() <= 0 || $_SERVER['SERVER_PORT'] == $this->getHttpsUrl()->getPort() ) && strpos($_SERVER['REQUEST_URI'], $this->getHttpsUrl()->getPath()) !== false ) {
			$is_ssl = true;
		}
		return apply_filters('is_ssl', $is_ssl);
	}

	/**
	 * Maintained for backwards compatibility.
	 *
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
		if ( isset($_SERVER['REDIRECT_URL']) && strpos($_SERVER['REDIRECT_URL'], 'index.php') === false ) {
			$current_path = $_SERVER['REDIRECT_URL'];
			if ( strpos($_SERVER['REQUEST_URI'], '?') !== false && strpos($_SERVER['REDIRECT_URL'], '?') === false ) {
				$current_path .= substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '?'));
			}
		} else {
			$current_path = $_SERVER['REQUEST_URI'];
		}

		$current_url = ( $this->isSsl() ? 'https' : 'http' ) . '://' . ( isset($_SERVER['HTTP_X_FORWARDED_SERVER']) ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['HTTP_HOST'] ) . $current_path;
		if ( $scheme == 'https' ) {
			$url = $this->makeUrlHttps($current_url);
		} else {
			$url = $this->makeUrlHttp($current_url);
		}

		if ( $current_url != $url ) {
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
				header("Location: " . $url, true, 301);
			}
			exit();
		}
	}

	/**
	 * Get relevant files and directories within WordPress
	 *
	 * @return array $scannedDirectories
	 */
	public function getDirectories() {
		$directories = array();
		$scannedDirectories = array();
		$directories[] = get_theme_root() . '/' . get_template();

		foreach( $directories as $directory ) {
			$scannedDirectories[$directory]['name'] = $directory;
			if ( is_readable($directory) && ($files = scandir($directory)) ) {
				$scannedDirectories[$directory]['files'] = $files;
				unset($files);
			} else {
				$scannedDirectories[$directory]['error'] = "Unable to read directory.";
			}
		}
		return $scannedDirectories;
	}

}
