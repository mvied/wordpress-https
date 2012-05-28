<?php
/**
 * HTML Parser Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('Mvied/Module.php');
require_once('Mvied/Module/Interface.php');

class WordPressHTTPS_Module_Parser extends Mvied_Module implements Mvied_Module_Interface {

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
	 * Add Secure External URL
	 * 
	 * @param string $value
	 * @return $this
	 */
	public function addSecureExternalUrl( $value ) {
		if ( trim($value) == '' ) {
			return $this;
		}

		$secure_external_urls = (array) $this->getPlugin()->getSetting('secure_external_urls');
		array_push($secure_external_urls, (string) $value);
		$this->getPlugin()->setSetting('secure_external_urls', $secure_external_urls);

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

		$unsecure_external_urls = (array) $this->getPlugin()->getSetting('unsecure_external_urls');
		array_push($unsecure_external_urls, (string) $value);
		$this->getPlugin()->setSetting('unsecure_external_urls', $unsecure_external_urls);

		return $this;
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
	 * Parse HTML
	 * 
	 * Parses the output buffer to fix HTML output
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function parseHtml( $buffer ) {
		$this->_html = $buffer;

		$this->normalizeElements();
		$this->fixLinksAndForms();
		$this->fixExtensions();
		$this->fixElements();
		$this->fixCssElements();
		$this->fixRelativeElements();
		
		// Output logger contents to browsers console if in Debug Mode
		if ( $this->getPlugin()->getSetting('debug') == true ) {
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
	 * Secure element
	 *
	 * @param string $url
	 * @param string $type
	 * @return void
	 */
	public function secureElement( $url, $type = '' ) {
		$updated = false;
		$url = WordPressHTTPS_Url::fromString($url);
		$upload_dir = wp_upload_dir();
		$upload_path = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), $this->getPlugin()->getHttpUrl()->getPath(), parse_url($upload_dir['baseurl'], PHP_URL_PATH));

		// If local
		if ( $this->getPlugin()->isUrlLocal($url) ) {
			if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
				$updated = $this->getPlugin()->makeUrlHttps($url);
				$this->_html = str_replace($url, $updated, $this->_html);
			}
		// If external and not HTTPS
		} else if ( $url->getPath() != 'https' ) {
			if ( @in_array($url->toString(), $this->getPlugin()->getSetting('secure_external_urls')) == false && @in_array($url->toString(), $this->getPlugin()->getSetting('unsecure_external_urls')) == false ) {
				if ( $url->getScheme() != 'https' ) {
					$test_url = clone $url;
					$test_url->setScheme('https');
					if ( $test_url->isValid() ) {
						// Cache this URL as available over HTTPS for future reference
						$this->addSecureExternalUrl($url->toString());
					} else {
						// If not available over HTTPS, mark as an unsecure external URL
						$this->addUnsecureExternalUrl($url->toString());
					}
				}
			}

			if ( in_array($url, $this->getPlugin()->getSetting('secure_external_urls')) ) {
				$updated = clone $url;
				$updated->setScheme('https');
				$this->_html = str_replace($url, $updated, $this->_html);
			}
		}
	
		// Add log entry if this change hasn't been logged
		if ( $updated && $url != $updated ) {
			$log = '[FIXED] Element: ' . ( $type != '' ? '<' . $type . '> ' : '' ) . $url . ' => ' . $updated;
		} else if ( $updated == false && $url->getScheme() == 'http' ) {
			$log = '[WARNING] Unsecure Element: <' . $type . '> - ' . $url;
		}
		if ( isset($log) && ! in_array($log, $this->getPlugin()->getLogger()->getLog()) ) {
			$this->getPlugin()->getLogger()->log($log);
		}
	}
	
	/**
	 * Unsecure element
	 *
	 * @param string $url
	 * @param string $type
	 * @return void
	 */
	public function unsecureElement( $url, $type = '' ) {
		$updated = false;
		$url = WordPressHTTPS_Url::fromString($url);

		// If local
		if ( $this->getPlugin()->isUrlLocal($url) ) {
			if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
				$updated = $this->getPlugin()->makeUrlHttp($url);
				$this->_html = str_replace($url, $updated, $this->_html);
			}
		}
		
		// Add log entry if this change hasn't been logged
		if ( $updated && $url != $updated ) {
			$log = '[FIXED] Element: ' . ( $type != '' ? '<' . $type . '> ' : '' ) . $url . ' => ' . $updated;
		}
		if ( isset($log) && ! in_array($log, $this->getPlugin()->getLogger()->getLog()) ) {
			$this->getPlugin()->getLogger()->log($log);
		}
	}

	/**
	 * Normalize all local URL's to HTTP
	 *
	 * @param none
	 * @return void
	 */
	public function normalizeElements() {
		$httpMatches = array();
		$httpsMatches = array();
		if ( ! is_admin() && $GLOBALS['pagenow'] != 'wp-login.php' ) {
			if ( $this->getPlugin()->getSetting('ssl_host_diff') ) {
				$url = clone $this->getPlugin()->getHttpsUrl();
				$url->setScheme('http');
				preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"]*)[\'"]?/im', $this->_html, $httpsMatches);
			}

			if ( WordPressHTTPS_Url::fromString(get_option('home'))->getScheme() != 'https' ) {
				$url = clone $this->getPlugin()->getHttpUrl();
				$url->setScheme('https');
				preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"]*)[\'"]?/im', $this->_html, $httpMatches);
			}
			$matches = array_merge($httpMatches, $httpsMatches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				if ( isset($matches[1][$i]) ) {
					$url = WordPressHTTPS_Url::fromString($matches[1][$i]);
					if ( $url && strpos($url->getPath(), 'wp-admin') === false && strpos($url->getPath(), 'wp-login') === false ) {
						$url = $url->toString();
						$this->_html = str_replace($url, $this->getPlugin()->makeUrlHttp($url), $this->_html);
					}
				}
			}
		}
	}

	/**
	 * Fix Elements
	 * 
	 * Fixes schemes on DOM elements.
	 *
	 * @param none
	 * @return void
	 */
	public function fixElements() {
		if ( is_admin() ) {
			preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
		} else {
			preg_match_all('/\<(script|link|img|input|embed|param)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
		}

		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$html = $matches[0][$i];
			$type = $matches[1][$i];
			$url = $matches[2][$i];
			$scheme = $matches[3][$i];
			$updated = false;

			if	( $type == 'img' || $type == 'script' || $type == 'embed' ||
				( $type == 'link' && ( strpos($html, 'stylesheet') !== false || strpos($html, 'pingback') !== false ) ) ||
				( $type == 'form' && strpos($html, 'wp-pass.php') !== false ) ||
				( $type == 'form' && strpos($html, 'commentform') !== false ) ||
				( $type == 'input' && strpos($html, 'image') !== false ) ||
				( $type == 'param' && strpos($html, 'movie') !== false )
			) {
				if ( $scheme == 'http' && ( $this->getPlugin()->isSsl() ) ) {
					$this->secureElement($url, $type);
				} else if ( $scheme == 'https' && ! $this->getPlugin()->isSsl() && strpos($url, 'wp-admin') === false ) {
					$this->unsecureElement($url, $type);
				}
			}
		}
	}
	
	/**
	 * Fix CSS background images or imports.
	 *
	 * @param none
	 * @return void
	 */
	public function fixCssElements() {
		preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^)]+)[\'"]?\)/im', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$css = $matches[0][$i];
			$url = $matches[2][$i];
			if ( $this->getPlugin()->isSsl() ) {
				$this->secureElement($url, 'style');
			} else {
				$this->unsecureElement($url, 'style');
			}
		}
	}
	
	/**
	 * Fix elements that are being referenced relatively.
	 *
	 * @param none
	 * @return void
	 */
	public function fixRelativeElements() {
		if ( $this->getPlugin()->isSsl() && $this->getPlugin()->getHttpUrl()->getPath() != $this->getPlugin()->getHttpsUrl()->getPath() ) {
			preg_match_all('/\<(script|link|img|input|form|embed|param)[^>]+(src|href|action|data|movie|image|value)=[\'"](\/[^\'"]*)[\'"][^>]*>/im', $this->_html, $matches);

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
					$updated = clone $this->getPlugin()->getHttpsUrl();
					$updated->setPath($url_path);
					$this->_html = str_replace($html, str_replace($url_path, $updated, $html), $this->_html);
					$this->getPlugin()->getLogger()->log('[FIXED] Element: <' . $type . '> - ' . $url_path . ' => ' . $updated);
				}
			}
		}
	}
		
	/**
	 * Fix Extensions
	 * 
	 * Fixes schemes on DOM elements with extensions specified in $this->_extensions
	 *
	 * @param none
	 * @return void
	 */
	public function fixExtensions() {
		@preg_match_all('/(http|https):\/\/[^\'"]+[\'"]+/i', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$url = rtrim($matches[0][$i], '\'"');
			$filename = basename($url);
			$scheme = $matches[1][$i];

			foreach( $this->_extensions as $extension ) {
				if ( $extension == 'js' ) {
					$type = 'script';
				} else if ( $extension == 'css' ) {
					$type = 'style';
				} else if ( in_array($extension, array('jpg', 'jpeg', 'png', 'gif')) ) {
					$type = 'img';
				} else {
					$type = '';
				}

				if ( strpos($filename, '.' . $extension) !== false ) {
					if ( $this->getPlugin()->isSsl() ) {
						$this->secureElement($url, $type);
					} else {
						$this->unsecureElement($url, $type);
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

			$url_parts = parse_url($url);
			if ( $this->getPlugin()->getHttpsUrl()->getPath() != '/' ) {
				if ( $this->getPlugin()->getSetting('ssl_host_diff') ) {
					$url_parts['path'] = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $url_parts['path']);
				}
				if ( $this->getPlugin()->getHttpUrl()->getPath() != '/' ) {
					$url_parts['path'] = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $url_parts['path']);
				}
			}

			// qTranslate integration - strips language from beginning of url path
			if ( defined('QTRANS_INIT') && constant('QTRANS_INIT') == true ) {
				global $q_config;
				if ( isset($q_config['enabled_languages']) ) {
					foreach($q_config['enabled_languages'] as $language) {
						$url_parts['path'] = preg_replace('/^\/' . $language . '\//', '/', $url_parts['path']);
					}
				}
			}

			if ( $this->getPlugin()->isUrlLocal($url) && preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
				$post = $postID[1];
			} else if ( $this->getPlugin()->isUrlLocal($url) && ( $url_parts['path'] == '' || $url_parts['path'] == '/' ) ) { 
				if ( get_option('show_on_front') == 'posts' ) {
					$post = true;
				} else {
					$post = get_option('page_on_front');
				}
				if ( $this->getPlugin()->getSetting('frontpage') ) {
					$force_ssl = true;
				} else if ( $this->getPlugin()->getSetting('exclusive_https') ) {
					$force_ssl = false;
				}
			} else if ( $this->getPlugin()->isUrlLocal($url) && ($post = get_page_by_path($url_parts['path'])) ) {
				$post = $post->ID;
			//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
			} else if ( ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) && ( $this->getPlugin()->isSsl() || $this->getPlugin()->getSetting('ssl_admin') ) ) {
				if ( ! is_multisite() || ( is_multisite() && strpos($url_parts['host'], $this->getPlugin()->getHttpsUrl()->getHost()) !== false ) ) {
					$post = true;
					$force_ssl = true;
				} else if ( is_multisite() ) {
					// get_blog_details returns an object with a property of blog_id
					if ( $blog_details = get_blog_details( array( 'domain' => $url_parts['host'] )) ) {
						// set $blog_id using $blog_details->blog_id
						$blog_id = $blog_details->blog_id;
						if ( $this->getPlugin()->getSetting('ssl_admin', $blog_id) && $scheme != 'https' && ( ! $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) || ( $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) && is_user_logged_in() ) ) ) {
							$this->_html = str_replace($url, str_replace('http', 'https', $url), $this->_html);
						}
					}
				}
			}

			if ( isset($post) ) {
				// Always change links to HTTPS when logged in via different SSL Host
				if ( $type == 'a' && ! $this->getPlugin()->getSetting('ssl_host_subdomain') && $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && is_user_logged_in() ) {
					$force_ssl = true;
				} else if ( (int) $post > 0 ) {
					$force_ssl = apply_filters('force_ssl', $force_ssl, $post );
				}

				if ( $force_ssl == true || WordPressHTTPS_Url::fromString(get_option('home'))->getScheme() == 'https' ) {
					$updated = $this->getPlugin()->makeUrlHttps($url);
					$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
				} else if ( $this->getPlugin()->getSetting('exclusive_https') ) {
					$updated = $this->getPlugin()->makeUrlHttp($url);
					$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
				}
			}

			// Add log entry if this change hasn't been logged
			if ( $updated && $url != $updated ) {
				$log = '[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated;
				if ( ! in_array($log, $this->getPlugin()->getLogger()->getLog()) ) {
					$this->getPlugin()->getLogger()->log($log);
				}
			}
		}
	}

	/**
	 * Output contents of the log to the browser's console.
	 *
	 * @param none
	 * @return void
	 */
	public function consoleLog() {
		$this->_html = str_replace('</body>', $this->getPlugin()->getLogger()->consoleLog() . "\n\n</body>", $this->_html);
	}

}