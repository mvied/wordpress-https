<?php
/**
 * Parser Module
 * 
 * Parses the output buffer with awesomeness.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Module_Parser extends Mvied_Plugin_Module {

	/**
	 * HTML
	 *
	 * @var string
	 */
	protected $_html;

	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() && apply_filters( 'wordpress_https_parser_ob', true )  ) {
			// Start output buffering
			add_action('init', array(&$this, 'startOutputBuffering'));
		}
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
	 * @return boolean
	 */
	public function secureElement( $url, $type = '' ) {
		$plugin = $this->getPlugin();
		$updated = false;
		$result = false;
		$upload_dir = wp_upload_dir();
		$upload_path = str_replace($plugin->getHttpsUrl()->getPath(), $plugin->getHttpUrl()->getPath(), parse_url($upload_dir['baseurl'], PHP_URL_PATH));

		if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
			$updated = $plugin->makeUrlHttps($url);
			if ( $url != $updated ) {
				$this->_html = str_replace($url, $updated, $this->_html);
			} else {
				$updated = false;
			}
		}

		// Add log entry if this change hasn't been logged
		if ( $updated ) {
			$log = '[FIXED] Element: ' . ( $type != '' ? '<' . $type . '> ' : '' ) . $url . ' => ' . $updated;
			$result = true;
		} else if ( strpos($url, 'http://') === 0 ) {
			if ( $plugin->getSetting('remove_unsecure') ) {
				$log = '[FIXED] Removed Unsecure Element: <' . $type . '> - ' . $url;
			} else {
				$log = '[WARNING] Unsecure Element: <' . $type . '> - ' . $url;
			}
		}
		if ( isset($log) && ! in_array($log, $plugin->getLogger()->getLog()) ) {
			$plugin->getLogger()->log($log);
		}

		return $result;
	}

	/**
	 * Unsecure element
	 *
	 * @param string $url
	 * @param string $type
	 * @return boolean
	 */
	public function unsecureElement( $url, $type = '' ) {
		$plugin = $this->getPlugin();
		$updated = false;
		$result = false;
		$upload_dir = wp_upload_dir();
		$upload_path = str_replace($plugin->getHttpsUrl()->getPath(), $plugin->getHttpUrl()->getPath(), parse_url($upload_dir['baseurl'], PHP_URL_PATH));

		// Only filter external resources that are being unsecured
		if ( !$plugin->isUrlLocal($url) ) {
			$updated = apply_filters('http_external_url', $url);
		} else {
			if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
				$updated = $plugin->makeUrlHttp($url);
				$this->_html = str_replace($url, $updated, $this->_html);
			}
		}

		// Add log entry if this change hasn't been logged
		if ( $updated && $url != $updated ) {
			$log = '[FIXED] Element: ' . ( $type != '' ? '<' . $type . '> ' : '' ) . $url . ' => ' . $updated;
			$result = true;
		}
		if ( isset($log) && ! in_array($log, $plugin->getLogger()->getLog()) ) {
			$plugin->getLogger()->log($log);
		}

		return $result;
	}

	/**
	 * Normalize all local URL's to HTTP
	 *
	 * @param none
	 * @return void
	 */
	public function normalizeElements() {
		$plugin = $this->getPlugin();
		$httpMatches = array();
		$httpsMatches = array();
		if ( $plugin->getSetting('ssl_host_diff') && !is_admin() ) {
			$url = clone $plugin->getHttpsUrl();
			$url->setScheme('http');
			preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"]*)[\'"]?/im', $this->_html, $httpsMatches);

			$url = clone $plugin->getHttpUrl();
			$url->setScheme('https');
			preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"]*)[\'"]?/im', $this->_html, $httpMatches);

			$matches = array_merge($httpMatches, $httpsMatches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				if ( isset($matches[1][$i]) ) {
					$url_parts = parse_url($matches[1][$i]);
					if ( $url_parts && strpos($url_parts['path'], 'wp-admin') === false && strpos($url_parts['path'], 'wp-login') === false ) {
						$this->_html = str_replace($url, $plugin->makeUrlHttp($url), $this->_html);
					}
				}
			}
		}
	}

	/**
	 * Fixes schemes on DOM elements.
	 *
	 * @param none
	 * @return void
	 */
	public function fixElements() {
		$plugin = $this->getPlugin();
		if ( is_admin() ) {
			preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>(<\/(script|link|img|input|embed|param|iframe)>\s*)?/im', $this->_html, $matches);
		} else {
			preg_match_all('/\<(script|link|img|input|embed|param|iframe)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>(<\/(script|link|img|input|embed|param|iframe)>\s*)?/im', $this->_html, $matches);
		}

		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$html = $matches[0][$i];
			$type = $matches[1][$i];
			$url = $matches[2][$i];
			$scheme = $matches[3][$i];
			$updated = false;

			if	( $type == 'img' || $type == 'script' || $type == 'embed' || $type == 'iframe' ||
				( $type == 'link' && ( strpos($html, 'stylesheet') !== false || strpos($html, 'pingback') !== false ) ) ||
				( $type == 'form' && strpos($html, 'wp-pass.php') !== false ) ||
				( $type == 'form' && strpos($html, 'wp-login.php?action=postpass') !== false ) ||
				( $type == 'form' && strpos($html, 'commentform') !== false ) ||
				( $type == 'input' && strpos($html, 'image') !== false ) ||
				( $type == 'param' && strpos($html, 'movie') !== false )
			) {
				if ( $plugin->isSsl() && ( $plugin->getSetting('ssl_host_diff') || ( !$plugin->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
					if ( !$this->secureElement($url, $type) && $plugin->getSetting('remove_unsecure') ) {
						$this->_html = str_replace($html, '', $this->_html);
					}
				} else if ( !$plugin->isSsl() && strpos($url, 'https://') === 0 ) {
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
		$plugin = $this->getPlugin();
		preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^\'"\)]+)[\'"\)]?\)/im', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$css = $matches[0][$i];
			$url = $matches[2][$i];
			if ( $plugin->isSsl() && ( $plugin->getSetting('ssl_host_diff') || ( !$plugin->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
				$this->secureElement($url, 'style');
			} else if ( !$plugin->isSsl() && strpos($url, 'https://') === 0 ) {
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
		$plugin = $this->getPlugin();
		if ( $plugin->isSsl() && $plugin->getHttpUrl()->getPath() != $plugin->getHttpsUrl()->getPath() ) {
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
					if ( strpos($url_path, '//') !== 0 ) {
						$updated = clone $plugin->getHttpsUrl();
						$updated->setPath($url_path);
						$this->_html = str_replace($html, str_replace($url_path, $updated, $html), $this->_html);
						$plugin->getLogger()->log('[FIXED] Element: <' . $type . '> - ' . $url_path . ' => ' . $updated);
					}
				}
			}
		}
	}

	/**
	 * Fixes schemes on DOM elements with extensions specified in $this->_extensions
	 *
	 * @param none
	 * @return void
	 */
	public function fixExtensions() {
		$plugin = $this->getPlugin();
		@preg_match_all('/((http|https):\/\/[^\'"\)\s]+)[\'"\)]?/i', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[1]); $i++) {
			$url = $matches[1][$i];
			$filename = basename($url);
			$scheme = $matches[2][$i];

			foreach( $plugin->getFileExtensions() as $type => $extensions ) {
				foreach( $extensions as $extension ) {
					if ( preg_match('/\.' . $extension . '(\?|$)/', $filename) ) {
						if ( $plugin->isSsl() && ( $plugin->getSetting('ssl_host_diff') || ( !$plugin->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
							$this->secureElement($url, $type);
						} else if ( !$plugin->isSsl() && strpos($url, 'https://') === 0 ) {
							$this->unsecureElement($url, $type);
						}
						break 2;
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
		global $wpdb;
		// Update anchor and form tags to appropriate URL's
		preg_match_all('/\<(a|form)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);

		$plugin = $this->getPlugin();
		$path_cache = $plugin->getSetting('path_cache');
		$blog_cache = $plugin->getSetting('blog_cache');

		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$html = $matches[0][$i];
			$type = $matches[1][$i];
			$url = $matches[2][$i];
			$scheme = $matches[3][$i];
			$updated = false;
			$post_id = null;
			$blog_id = null;
			$force_ssl = null;
			$url_path = null;
			$blog_path = null;
			$blog_url_path = '/';

			if ( !$plugin->isUrlLocal($url) ) {
				continue;
			}

			if ( $url != '' && ($url_parts = parse_url($url)) && isset($url_parts['path']) ) {
				if ( $plugin->getHttpsUrl()->getPath() != '/' ) {
					if ( $plugin->getSetting('ssl_host_diff') ) {
						$url_parts['path'] = str_replace($plugin->getHttpsUrl()->getPath(), '', $url_parts['path']);
					}
					if ( $plugin->getHttpUrl()->getPath() != '/' ) {
						$url_parts['path'] = str_replace($plugin->getHttpUrl()->getPath(), '', $url_parts['path']);
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

				if ( preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
					$post_id = $postID[1];
				} else if ( isset($url_parts['path']) && ( $url_parts['path'] == '' || $url_parts['path'] == '/' ) ) {
					if ( get_option('show_on_front') == 'page' ) {
						$post_id = get_option('page_on_front');
					}
				} else if ( isset($url_parts['path']) ) {
					$url_path = $url_parts['path'];
					if ( !array_key_exists($url_path, $path_cache) ) {
						if ( $post = get_page_by_path($url_path) ) {
							$post_id = $post->ID;
							$path_cache[$url_path] = $post_id;
						} else {
							$path_cache[$url_path] = 0;
						}
					} else {
						$post_id = $path_cache[$url_path];
					}
				}

				if ( is_multisite() && isset($url_parts['host']) ) {
					if ( is_subdomain_install() ) {
						$blog_path = $url_parts['host'] . '/';
						if ( array_key_exists($blog_path, $blog_cache) ) {
							$blog_id = $blog_cache[$blog_path];
						} else {
							$blog_id = get_blog_id_from_url( $url_parts['host'], '/');
						}
					} else {
						$url_path_segments = explode('/', $url_parts['path']);
						if ( sizeof($url_path_segments) > 1 ) {
							foreach( $url_path_segments as $url_path_segment ) {
								if ( is_null($blog_id) && $url_path_segment != '' ) {
									$blog_url_path .= $url_path_segment . '/';
									$blog_path = $url_parts['host'] . $blog_url_path;
									if ( array_key_exists($blog_path, $blog_cache) ) {
										$blog_id = $blog_cache[$blog_path];
									} else {
										$blog_id = $blog_cache[$blog_path] = get_blog_id_from_url( $url_parts['host'], $blog_url_path);
									}
								}
							}
						}
					}
				}
			}

			// Only apply force_ssl filters for current blog
			if ( is_null($blog_id) ) {
				$force_ssl = apply_filters('force_ssl', null, ( isset($post_id) ? $post_id : 0 ), $url );
			}

			if ( $force_ssl == true ) {
				if ( is_null($blog_id) ) {
					$updated = $plugin->makeUrlHttps($url);
				}
				$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
			} else if ( !is_null($force_ssl) && !$force_ssl ) {
				if ( is_null($blog_id) ) {
					$updated = $plugin->makeUrlHttp($url);
				}
				$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
			}

			// Add log entry if this change hasn't been logged
			if ( $updated && $url != $updated ) {
				$log = '[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated;
				if ( ! in_array($log, $plugin->getLogger()->getLog()) ) {
					$plugin->getLogger()->log($log);
				}
			}
		}
		$plugin->setSetting('path_cache', $path_cache);
		$plugin->setSetting('blog_cache', $blog_cache);
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
