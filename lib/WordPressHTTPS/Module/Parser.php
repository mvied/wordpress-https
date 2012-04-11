<?php
/**
 * HTML Parser Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Parser extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * HTML
	 *
	 * @var string
	 */
	protected $_html;
	
	/**
	 * Extensions
	 * 
	 * Array of file extensions to be loaded securely.
	 *
	 * @var array
	 */
	protected $_extensions = array('jpg', 'jpeg', 'png', 'gif', 'css', 'js');
	
	/**
	 * Secure External URL's
	 * 
	 * External URL's that are available over HTTPS.
	 *
	 * @var string
	 */
	protected $_secure_external_urls = array();
	
	/**
	 * Unsecure External URL's
	 * 
	 * External URL's that are not available over HTTPS.
	 *
	 * @var string
	 */
	protected $_unsecure_external_urls = array();

	/**
	 * Add Secure External URL
	 * 
	 * Stores the value of this array in WordPress options.
	 *
	 * @param array $value
	 * @return $this
	 */
	public function setSecureExternalUrls( $value ) {
		$property = '_secure_external_urls';
		$this->$property = $value;
		update_option($this->get('slug') . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Get Secure External URL's
	 * 
	 * Retrieves the value of this array from WordPress options.
	 *
	 * @param none
	 * @return array
	 */
	public function getSecureExternalUrls() {
		$property = '_secure_external_urls';
		$option = get_option($this->get('slug') . $property);
		if ( $option !== false ) {
			return $option;
		} else {
			return $this->$property;
		}
	}
	
	/**
	 * Add Secure External URL
	 * 
	 * @param string $value
	 * @return $this
	 */
	public function addSecureExternalUrl( $value ) {
		if ( $value == '' ) {
			return $this;
		}
		
		$property = '_secure_external_urls';
		array_push($this->$property, $value);
		update_option($this->get('slug') . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Set Unsecure External URL's
	 * 
	 * Stores the value of this array in WordPress options.
	 *
	 * @param array $value
	 * @return $this
	 */
	public function setUnsecureExternalUrls( $value = array() ) {
		$property = '_unsecure_external_urls';
		$this->$property = $value;
		update_option($this->get('slug') . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Add Unsecure External URL
	 * 
	 * @param string $value
	 * @return $this
	 */
	public function addUnsecureExternalUrl( $value ) {
		if ( $value == '' ) {
			return $this;
		}
		
		$property = '_unsecure_external_urls';
		array_push($this->$property, $value);
		update_option($this->get('slug') . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Get Unsecure External URL's
	 * 
	 * Retrieves the value of this array from WordPress options.
	 *
	 * @param none
	 * @return array
	 */
	public function getUnsecureExternalUrls() {
		$property = '_unsecure_external_urls';
		$option = get_option($this->get('slug') . $property);
		if ( $option !== false ) {
			return $option;
		} else {
			return $this->$property;
		}
	}
	
	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		// Start output buffering
		add_action('init', array(&$this, 'startOutputBuffering'));
	}
	
	/**
	 * Runs when the plugin settings are reset.
	 *
	 * @param none
	 * @return void
	 */
	public function reset() {
		delete_option($this->get('slug') . '_secure_external_urls');
		delete_option($this->get('slug') . '_unsecure_external_urls');
	}

	/**
	 * Console Log
	 * 
	 * Output contents of the log to the browser's console.
	 *
	 * @param none
	 * @return void
	 */
	public function consoleLog() {
		$code = "<script type=\"text/javascript\">\n\tif ( typeof console === 'object' ) {\n";
		$log = $this->get('log');
		array_unshift($log, '[BEGIN WordPress HTTPS Debug Log]');
		array_push($log, '[END WordPress HTTPS Debug Log]');
		foreach( $log as $log_entry ) {
			if ( is_array($log_entry) ) {
				$log_entry = json_encode($log_entry);
			} else {
				$log_entry = "'" . addslashes($log_entry) . "'";
			}
			$code .= "\t\tconsole.log(" . $log_entry . ");\n";
		}
		$code .= "\t}\n</script>\n";
		$this->_html = str_replace("</body>", $code . "\n</body>", $this->_html);
	}
	
	/**
	 * Parse HTML
	 * 
	 * Parses the output buffer to fix HTML output
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function parseHtml( $buffer ) {
		$this->_html = $buffer;
		
		$this->fixExtensions();
		$this->fixElements();
		$this->fixLinksAndForms();

		if ( $this->getSetting('debug') == true ) {
			$this->consoleLog();
		}
		
		return $this->_html;
	}
	
	/**
	 * Start output buffering
	 *
	 * @param none
	 * @return void
	 */
	public function startOutputBuffering() {
		ob_start(array(&$this, 'parseHtml'));
	}
	
	/**
	 * Fix Elements
	 * 
	 * Fixes schemes on DOM elements.
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function fixElements() {
		// Fix any occurrence of the HTTPS version of the regular domain when using different SSL Host
		if ( $this->getSetting('ssl_host_diff') ) {
			$url = clone $this->get('http_url');
			$url->set('scheme', 'https');
			
			$count = substr_count($this->_html, $url);
			if ( $count > 0 ) {
				$this->log('[FIXED] Updated ' . $count . ' Occurrences of URL: ' . $url . ' => ' . $this->replace_https_url($url));
				$this->_html = str_replace($url, $this->replace_https_url($url), $this->_html);
			}
		}

		if ( $this->is_ssl() ) {
			if ( is_admin() ) {
				preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
			} else {
				preg_match_all('/\<(script|link|img|form|input|embed|param)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
			}
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$html = $matches[0][$i];
				$type = $matches[1][$i];
				$url = $matches[2][$i];
				$scheme = $matches[3][$i];
				$updated = false;

				if ( $type == 'img' || $type == 'script' || $type == 'embed' ||
					( $type == 'link' && ( strpos($html, 'stylesheet') !== false || strpos($html, 'pingback') !== false ) ) ||
					( $type == 'form' && strpos($html, 'wp-pass.php') !== false ) ||
					( $type == 'form' && strpos($html, 'commentform') !== false ) ||
					( $type == 'input' && strpos($html, 'image') !== false ) ||
					( $type == 'param' && strpos($html, 'movie') !== false )
				) {
					// Fix image tags in the admin panel
					if ( is_admin() && $type == 'img' ) {
						if ( $this->is_local_url($url) && $this->is_ssl() ) {
							$updated = $this->replace_http_url($url);
							$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
						}
					} else {
						$url = $this->factory('Url')->fromString($url);
						// If local
						if ( $this->is_local_url($url) ) {
							$updated = $this->replace_http_url($url);
							$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
						// If external and not HTTPS
						} else if ( strpos($url, 'https') === false ) {
							if ( @in_array($url, $this->getSecureExternalUrls()) == false && @in_array($url, $this->getUnsecureExternalUrls()) == false ) {
								$test_url = clone $url;
								$test_url->set('scheme', 'https');
								if ( $test_url->isValid() ) {
									// Cache this URL as available over HTTPS for future reference
									$this->addSecureExternalUrl($url);
								} else {
									// If not available over HTTPS, mark as an unsecure external URL
									$this->addUnsecureExternalUrl($url);
								}
							}

							if ( in_array($url, $this->getSecureExternalUrls()) ) {
								$updated = clone $url;
								$updated->set('scheme', 'https');
								$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
							}
						}

						if ( $updated == false && strpos($url, 'https') === false ) {
							$this->log('[WARNING] Unsecure Element: <' . $type . '> - ' . $url);
						}
					}
				}

				if ( $updated && $url != $updated ) {
					$this->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated);
				}
			}

			// Fix any CSS background images or imports
			preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^)]+)[\'"]?\)/im', $this->_html, $matches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$css = $matches[0][$i];
				$url = $matches[2][$i];
				$updated = $this->replace_http_url($url);
				$this->_html = str_replace($css, str_replace($url, $updated, $css), $this->_html);
				$this->log('[FIXED] CSS: ' . $url . ' => ' . $updated);
			}

			// Look for any relative paths that should be udpated to the SSL Host path
			if ( $this->get('http_url')->get('path') != $this->get('https_url')->get('path') ) {
				preg_match_all('/\<(script|link|img|input|form|embed|param|a)[^>]+(src|href|action|data|movie|image|value)=[\'"](\/[^\'"]*)[\'"][^>]*>/im', $this->_html, $matches);

				for ($i = 0; $i < sizeof($matches[0]); $i++) {
					$html = $matches[0][$i];
					$type = $matches[1][$i];
					$attr = $matches[2][$i];
					$url_path = $matches[3][$i];
					if (
						$type != 'input' ||
						( $type == 'input' && $attr == 'image' ) ||
						( $type == 'input' && strpos($html, '_wp_http_referer') !== false )
					) {
						$updated = clone $this->get('https_url');
						$updated->set('path', $url_path);
						$this->_html = str_replace($html, str_replace($url_path, $updated, $html), $this->_html);
						$this->log('[FIXED] Element: <' . $type . '> - ' . $url_path . ' => ' . $updated);
					}
				}
			}
		}
		
	}
		
	/**
	 * Fix Extensions
	 * 
	 * Fixes schemes on DOM elements with extensions specified in $this->_extensions
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function fixExtensions() {
		if ( $this->is_ssl() ) {
			@preg_match_all('/(http|https):\/\/[\/-\w\d\.,~#@^!\'()?=\+&%;:[\]]+/i', $this->_html, $matches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$url = rtrim($matches[0][$i], '\'"');
				$filename = basename($url);
				$scheme = $matches[1][$i];
				$updated = false;
	
				foreach( $this->_extensions as $extension ) {
					if ( strpos($filename, '.' . $extension) !== false ) {
						$url = $this->factory('Url')->fromString($url);
						if ( $this->is_local_url( $url ) ) {
							$updated = $this->replace_http_url($url);
							$this->_html = str_replace($url, $updated, $this->_html);
						} else if ( $url->get('scheme') != 'https' ) {
							if ( @in_array($url, $this->getSecureExternalUrls()) == false && @in_array($url, $this->getUnsecureExternalUrls()) == false ) {
								$test_url = clone $url;
								$test_url->set('scheme', 'https');
								if ( $test_url->isValid() ) {
									// Cache this URL as available over HTTPS for future reference
									$this->addSecureExternalUrl($url);
								} else {
									// If not available over HTTPS, mark as an unsecure external URL
									$this->addUnsecureExternalUrl($url);
								}
							}
			
							if ( in_array($url, $this->getSecureExternalUrls()) ) {
								$updated = clone $url;
								$updated->set('scheme', 'https');
								$this->_html = str_replace($url, $updated, $this->_html);
								$this->_html = str_replace(preg_quote($url), preg_quote($updated), $this->_html);
							}
						}
		
						if ( $updated && $url != $updated ) {
							$this->log('[FIXED] Element: ' . $url . ' => ' . $updated);
						} else if ( $updated == false && $url->get('scheme') == 'http' ) {
							$this->log('[WARNING] Unsecure Element: <' . $type . '> - ' . $url);
						}
					}
				}
			}
		}
	}

	/**
	 * Fix links and forms
	 *
	 * @param none
	 * @return void
	 */
	public function fixLinksAndForms() {
		// Update anchor and form tags to appropriate URL's
		preg_match_all('/\<(a|form)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);

		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$html = $matches[0][$i];
			$type = $matches[1][$i];
			$url = $matches[2][$i];
			$scheme = $matches[3][$i];
			$updated = false;

			unset($force_ssl);

			if ( $this->is_local_url($url) ) {
				$url_parts = parse_url($url);
				if ( $this->getSetting('ssl_host_diff') && $this->get('https_url')->get('path') != '/' ) {
					$url_parts['path'] = str_replace($this->get('https_url')->get('path'), '', $url_parts['path']);
				}
				$url_parts['path'] = str_replace($this->get('http_url')->get('path'), '', $url_parts['path']);

				if ( preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
					$post = $postID[1];
				} else if ( $post = get_page_by_path($url_parts['path']) ) {
					$post = $post->ID;
				} else if ( $url_parts['path'] == '/' ) {
					if ( get_option('show_on_front') == 'posts' ) {
						$post = true;
						$force_ssl = (( $this->getSetting('frontpage') == 1 ) ? true : false);
					} else {
						$post = get_option('page_on_front');
					}
				//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
				} else if ( ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) && ( $this->is_ssl() || $this->getSetting('ssl_admin') ) && ( !is_multisite() || ( is_multisite() && $url_parts['host'] == $this->get('https_url')->get('host') ) ) ) {
					$post = true;
					$force_ssl = true;
				}

				if ( isset($post) ) {
					// Always change links to HTTPS when logged in via different SSL Host
					if ( $type == 'a' && $this->getSetting('ssl_host_subdomain') == 0 && $this->getSetting('ssl_host_diff') && $this->getSetting('ssl_admin') && is_user_logged_in() ) {
						$force_ssl = true;
					} else if ( (int) $post > 0 ) {
						$force_ssl = (( !isset($force_ssl) ) ? get_post_meta($post, 'force_ssl', true) : $force_ssl);
						
						$postParent = get_post($post);
						while ( $postParent->post_parent ) {
							$postParent = get_post( $postParent->post_parent );
							if ( get_post_meta($postParent->ID, 'force_ssl_children', true) == 1 ) {
								$force_ssl = true;
								break;
							}
						}
						
						$force_ssl = apply_filters('force_ssl', $force_ssl, $post );
					}

					if ( $force_ssl == true ) {
						$updated = $this->replace_http_url($url);
						$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
					} else if ( $this->getSetting('exclusive_https') ) {
						$updated = $this->replace_https_url($url);
						$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
					}
				}

				if ( $updated && $url != $updated ) {
					$this->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated);
				}
			}
		}
	}

}