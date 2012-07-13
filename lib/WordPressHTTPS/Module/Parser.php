<?php
/**
 * HTML Parser Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Module_Parser extends Mvied_Plugin_Module implements Mvied_Plugin_Module_Interface {

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
		$upload_dir = wp_upload_dir();
		$upload_path = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), $this->getPlugin()->getHttpUrl()->getPath(), parse_url($upload_dir['baseurl'], PHP_URL_PATH));

		if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
			$updated = $this->getPlugin()->makeUrlHttps($url);
			$this->_html = str_replace($url, $updated, $this->_html);
		}
	
		// Add log entry if this change hasn't been logged
		if ( $updated && $url != $updated ) {
			$log = '[FIXED] Element: ' . ( $type != '' ? '<' . $type . '> ' : '' ) . $url . ' => ' . $updated;
		} else if ( $updated == false && strpos($url, 'http://') == 0 ) {
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

		if ( ! is_admin() || ( is_admin() && strpos($url, $upload_path) === false ) ) {
			$updated = $this->getPlugin()->makeUrlHttp($url);
			$this->_html = str_replace($url, $updated, $this->_html);
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
		if ( $this->getPlugin()->getSetting('ssl_host_diff') && !is_admin() && $GLOBALS['pagenow'] != 'wp-login.php' ) {
			$url = clone $this->getPlugin()->getHttpsUrl();
			$url->setScheme('http');
			preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"\)]*)[\'"]?/im', $this->_html, $httpsMatches);

			if ( $this->getPlugin()->isSsl() ) {
				$url = clone $this->getPlugin()->getHttpUrl();
				$url->setScheme('https');
				preg_match_all('/(' . str_replace('/', '\/', preg_quote($url->toString())) . '[^\'"\)]*)[\'"]?/im', $this->_html, $httpMatches);
			}

			$matches = array_merge($httpMatches, $httpsMatches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				if ( isset($matches[1][$i]) ) {
					$url_parts = parse_url($matches[1][$i]);
					if ( $url_parts && strpos($url_parts['path'], $this->getPlugin()->getHttpsUrl()) !== false && strpos($url_parts['path'], 'wp-admin') === false && strpos($url_parts['path'], 'wp-login') === false ) {
						$this->_html = str_replace($url, $this->getPlugin()->makeUrlHttp($url), $this->_html);
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
		if ( is_admin() ) {
			preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"\)]+)[\'"\)][^>]*>/im', $this->_html, $matches);
		} else {
			preg_match_all('/\<(script|link|img|input|embed|param)[^>]+[\'"]((http|https):\/\/[^\'"\)]+)[\'"\)][^>]*>/im', $this->_html, $matches);
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
				if ( $this->getPlugin()->isSsl() && ( $this->getPlugin()->getSetting('ssl_host_diff') || ( !$this->getPlugin()->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
					$this->secureElement($url, $type);
				} else if ( !$this->getPlugin()->isSsl() && strpos($url, 'https://') === 0 ) {
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
		preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^\'"\)]+)[\'"\)]?\)/im', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$css = $matches[0][$i];
			$url = $matches[2][$i];
			if ( $this->getPlugin()->isSsl() && ( $this->getPlugin()->getSetting('ssl_host_diff') || ( !$this->getPlugin()->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
				$this->secureElement($url, 'style');
			} else if ( !$this->getPlugin()->isSsl() && strpos($url, 'https://') === 0 ) {
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
	 * Fixes schemes on DOM elements with extensions specified in $this->_extensions
	 *
	 * @param none
	 * @return void
	 */
	public function fixExtensions() {
		@preg_match_all('/(http|https):\/\/[^\'"\)\s]+[\'"\)]+/i', $this->_html, $matches);
		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$url = $matches[0][$i];
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
					if ( $this->getPlugin()->isSsl() && ( $this->getPlugin()->getSetting('ssl_host_diff') || ( !$this->getPlugin()->getSetting('ssl_host_diff') && strpos($url, 'http://') === 0 ) ) ) {
						$this->secureElement($url, $type);
					} else if ( !$this->getPlugin()->isSsl() && strpos($url, 'https://') === 0 ) {
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

			$force_ssl = apply_filters('force_ssl', null, 0, $url );

			if ( $force_ssl == true ) {
				$updated = $this->getPlugin()->makeUrlHttps($url);
				$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
			} else if ( $this->getPlugin()->isUrlLocal($url) && $this->getPlugin()->getSetting('exclusive_https') ) {
				$updated = $this->getPlugin()->makeUrlHttp($url);
				$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
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