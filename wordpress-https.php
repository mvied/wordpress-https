<?php
/*
 Plugin Name: WordPress HTTPS
 Plugin URI: http://mvied.com/projects/wordpress-https/
 Description: WordPress HTTPS is intended to be an all-in-one solution to using SSL on WordPress sites.
 Author: Mike Ems
 Version: 2.0.4
 Author URI: http://mvied.com/
 */

/**
 * Class for the WordPress plugin WordPress HTTPS
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * @copyright Copyright 2011
 *
 */
if ( !class_exists('WordPressHTTPS') ) {
	class WordPressHTTPS {

		/**
		 * Plugin Version
		 *
		 * @var int
		 */
		public $version = '2.0.4';

		/**
		 * Debug Mode
		 *
		 * Enabled debug output to the browser's console.
		 *
		 * @var boolean
		 */
		public $debug = false;

		/**
		 * Log Entries
		 *
		 * @var array
		 */
		public $log = array();

		/**
		 * Plugin URL
		 *
		 * @var string
		 */
		public $plugin_url;

		/**
		 * HTTP URL
		 *
		 * @var string
		 */
		public $http_url;

		/**
		 * HTTPS URL
		 *
		 * @var string
		 */
		public $https_url;

		/**
		 * SSL Port
		 *
		 * @var int
		 */
		public $ssl_port;

		/**
		 * Different SSL Host
		 *
		 * Set to true if the secure host is set to a a host that is not the default WordPress host.
		 *
		 * @var boolean
		 */
		public $diff_host = false;

		/**
		 * Force SSL Admin
		 *
		 * Set to true if the admin panel is being forced to use the secure host.
		 *
		 * @var boolean
		 */
		public $ssl_admin = false;

		/**
		 * Default Options
		 *
		 * @var array
		 */
		protected $options_default = array(
			'wordpress-https_external_urls' =>			array(),	// External URL's that are okay to rewrite to HTTPS
			'wordpress-https_unsecure_external_urls' =>	array(),	// External URL's that are okay to rewrite to HTTPS
			'wordpress-https_ssl_host' =>				'',			// Hostname for SSL Host
			'wordpress-https_ssl_port' =>				'',			// Port number for SSL Host
			'wordpress-https_ssl_host_subdomain' =>		0,			// Is SSL Host a subdomain
			'wordpress-https_exclusive_https' =>		0,			// Exclusively force SSL on posts and pages with the `Force SSL` option checked.
			'wordpress-https_frontpage' =>				0,			// Force SSL on front page
			'wordpress-https_ssl_admin' =>				0,			// Force SSL Over Administration Panel (The same as FORCE_SSL_ADMIN)
		);

		/**
		 * Initialize (PHP4)
		 *
		 * @param none
		 * @return void
		 */
		public function WordPressHTTPS() {
			$argcv = func_get_args();
			call_user_func_array(array(&$this, '__construct'), $argcv);
		}

		/**
		 * Initialize (PHP5+)
		 *
		 * @param none
		 * @return void
		 */
		public function __construct() {
			// Assign plugin_url
			if ( version_compare( get_bloginfo('version'), '2.8', '>=' ) ) {
				$this->plugin_url = plugins_url('', __FILE__);
			} else {
				$this->plugin_url = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__));
			}
			
			// If WPHTTPS_RESET global is defined, run reset method
			if ( defined('WPHTTPS_RESET') && constant('WPHTTPS_RESET') == true ) {
				$this->reset();
			}

			// HTTP URL
			$this->http_url = 'http://' . parse_url(get_option('home'), PHP_URL_HOST);
			// HTTPS URL
			$this->https_url = $this->replace_http($this->http_url);
			// SSL Port
			$this->ssl_port = ((get_option('wordpress-https_ssl_port') > 0) ? get_option('wordpress-https_ssl_port') : null);
			// Force SSL Admin
			$this->ssl_admin = ((force_ssl_admin() || get_option('wordpress-https_ssl_admin') > 0) ? true : false);

			// If using a different host for SSL
			if ( get_option('wordpress-https_ssl_host') && get_option('wordpress-https_ssl_host') != $this->https_url ) {
				// Assign HTTPS URL to SSL Host
				$this->diff_host = true;
				$this->https_url = get_option('wordpress-https_ssl_host');

				// Prevent WordPress' canonical redirect when using a different SSL Host
				if ( $this->is_ssl() ) {
					remove_filter('template_redirect', 'redirect_canonical');
				}

				// Add SSL Host to allowed redirect hosts
				add_filter('allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10, 1);

				// Remove SSL Host authentication cookies on logout
				add_action('clear_auth_cookie', array(&$this, 'clear_cookies'));

				// Set authentication cookie
				if ( $this->is_ssl() ) {
					add_action('set_auth_cookie', array(&$this, 'set_cookie'), 10, 5);
					add_action('set_logged_in_cookie', array(&$this, 'set_cookie'), 10, 5);
				}

				// Fix admin_url on login page
				if ( $GLOBALS['pagenow'] == 'wp-login.php' && $this->is_ssl() ) {
					add_filter('admin_url', array(&$this, 'replace_http_url'));
				}

				// Filter site_url in admin panel
				if ( is_admin() && $this->is_ssl() ) {
					add_filter('site_url', array(&$this, 'replace_http_url'));
					add_action('wp_redirect', array(&$this, 'wp_redirect_admin'), 1, 1);
				}
			}

			// Add SSL Port to HTTPS URL
			if ( $this->ssl_port ) {
				$this->https_url = $this->add_port($this->https_url);
			}

			$this->log('HTTP URL: ' . $this->http_url);
			$this->log('HTTPS URL: ' . $this->https_url);

			// Redirect admin/login pages. This is not pluggable due to the redirect methods used in wp-login.php
			if ( ( is_admin() || $GLOBALS['pagenow'] == 'wp-login.php' ) && $this->ssl_admin ) {
				add_action('wp_redirect', array(&$this, 'wp_redirect_admin'), 1, 1);
				if ( !$this->is_ssl() ) {
					$this->redirect('https');
				}
			}

			// Start output buffering
			add_action('init', array(&$this, 'buffer_start'));

			// Check if the page needs to be redirected
			add_action('template_redirect', array(&$this, 'redirect_check'));

			// Admin panel
			if ( is_admin() ) {
				// Add admin menus
				add_action('admin_menu', array(&$this, 'menu'));

				// Load on plugins page
				if ( $GLOBALS['pagenow'] == 'plugins.php' ) {
					add_filter( 'plugin_row_meta', array(&$this, 'plugin_links'), 10, 2);
				}

				// Load on Settings page
				if ( @$_GET['page'] == 'wordpress-https' ) {
					wp_enqueue_script('jquery-form', $this->plugin_url . '/js/jquery.form.js', array('jquery'), '2.47', true);
					wp_enqueue_script('jquery-tooltip', $this->plugin_url . '/js/jquery.tooltip.js', array('jquery'), '1.3', true);
					wp_enqueue_script('wordpress-https', $this->plugin_url . '/js/admin.php', array('jquery'), $this->version, true);
					wp_enqueue_style('wordpress-https', $this->plugin_url . '/css/admin.css', $this->version, true);

					if ( function_exists('add_thickbox') ) {
						add_thickbox();
					}
				}

				// Add 'Force SSL' checkbox to add/edit post pages
				if ( version_compare( get_bloginfo('version'), '2.8', '>' ) ) {
					add_action('post_submitbox_misc_actions', array(&$this, 'post_checkbox'));
				} else {
					add_action('post_submitbox_start', array(&$this, 'post_checkbox'));
				}
				add_action('save_post', array(&$this, 'post_save'));
			}

			// Filter HTTPS from links in WP 3.0+
			if ( version_compare(get_bloginfo('version'), '3.0', '>') && !is_admin() && strpos(get_option('home'), 'https://') === false ) {
				add_filter('page_link', array(&$this, 'replace_https_url'));
				add_filter('post_link', array(&$this, 'replace_https_url'));
				add_filter('category_link', array(&$this, 'replace_https_url'));
				add_filter('get_archives_link', array(&$this, 'replace_https_url'));
				add_filter('tag_link', array(&$this, 'replace_https_url'));
				add_filter('search_link', array(&$this, 'replace_https_url'));
				add_filter('home_url', array(&$this, 'replace_https_url'));
				add_filter('bloginfo', array(&$this, 'bloginfo'), 10, 2);
				add_filter('bloginfo_url', array(&$this, 'bloginfo'), 10, 2);

			// If the whole site is not HTTPS, set links to the front-end to HTTP from within the admin panel
			} else if ( is_admin() && $this->is_ssl() && strpos(get_option('home'), 'https://') === false ) {
				add_filter('page_link', array(&$this, 'replace_https_url'));
				add_filter('post_link', array(&$this, 'replace_https_url'));
				add_filter('category_link', array(&$this, 'replace_https_url'));
				add_filter('get_archives_link', array(&$this, 'replace_https_url'));
				add_filter('tag_link', array(&$this, 'replace_https_url'));
				add_filter('search_link', array(&$this, 'replace_https_url'));
			}

			// Change all page and post links to HTTPS in the admin panel when using different SSL Host
			if ( get_option('wordpress-https_ssl_host_subdomain') == 0 && $this->diff_host && is_admin() && $this->is_ssl() ) {
				add_filter('page_link', array(&$this, 'replace_http_url'));
				add_filter('post_link', array(&$this, 'replace_http_url'));
			}
		}

		/**
		 * Operations performed when plugin is activated.
		 *
		 * @param none
		 * @return void
		 */
		public function install() {
			// Add plugin options
			foreach ( $this->options_default as $option => $value ) {
				if ( get_option($option) === false ) {
					add_option($option, $value);
				}
			}

			// Checks to see if the SSL Host is a subdomain
			$http_domain = $this->get_url_domain($this->http_url);
			$https_domain = $this->get_url_domain($this->https_url);

			if ( $this->replace_https($url) != $this->http_url && $http_domain == $https_domain ) {
				update_option('wordpress-https_ssl_host_subdomain', 1);
			}
			
			// Run plugin updates
			$this->update();
		}

		/**
		 * Updates plugin from one version to another
		 *
		 * @param none
		 * @return void
		 */
		protected function update() {
			// Remove deprecated options
			$deprecated_options = array(
				'wordpress-https_sharedssl_site',
				'wordpress-https_internalurls',
				'wordpress-https_externalurls',
				'wordpress-https_bypass',
				'wordpress-https_disable_autohttps'
			);
			foreach( $deprecated_options as $option ) {
				delete_option($option);
			}

			// Upgrade from version < 2.0
			if ( get_option('wordpress-https_sharedssl') ) {
				$shared_ssl = ((get_option('wordpress-https_sharedssl') == 1) ? true : false);

				$options = array(
					'wordpress-https_sharedssl' =>			get_option('wordpress-https_sharedssl'),
					'wordpress-https_sharedssl_host' =>		get_option('wordpress-https_sharedssl_host'),
					'wordpress-https_sharedssl_admin' =>	get_option('wordpress-https_sharedssl_admin')
				);

				foreach( $options as $option => $value) {
					if ( $shared_ssl && $value ) {
						if ( $option == 'wordpress-https_sharedssl_host' ) {
							if ( $ssl_port = parse_url($value, PHP_URL_PORT) ) {
								update_option('wordpress-https_ssl_port', $ssl_port);
								$value = str_replace(':' . $ssl_port, '', $value);
							}
							update_option('wordpress-https_ssl_host', $value);
						}
						if ( $option == 'wordpress-https_sharedssl_admin' ) {
							update_option('wordpress-https_ssl_admin', $value);
							delete_option($option);
						}
					}
					delete_option($option);
				}
			}
			
			// Update current version
			update_option('wordpress-https_version', $this->version);
		}
		
		/**
		 * Rests all plugin options to the defaults
		 *
		 * @param none
		 * @return void
		 */
		public function reset() {
			foreach ( $this->options_default as $option => $value ) {
				update_option($option, $value);
			}
		}

		/**
		 * Adds a string to an array of log entries
		 *
		 * @param none
		 * @return void
		 */
		public function log( $string ) {
			$this->log[] = $string;
		}

		/**
		 * Returns an array of warnings to notify the user of on the settings page
		 *
		 * @param none
		 * @return void
		 */
		public function warnings() {
			$warnings = array();
			$i = 0;

			// Warnings about unsecure external URL's
			$unsecure_external_urls = (array) get_option('wordpress-https_unsecure_external_urls');
			foreach( $unsecure_external_urls as $admin => $urls ) {
				if ( $urls && sizeof($urls) > 0 ) {
					$warnings[$i]['label'] = 'Unsecure External Content';
					$warnings[$i]['warnings'] = $urls;
				}
			}
			$i++;

			return $warnings;
		}

		/**
		 * Finds the URL in a string
		 *
		 * @param string $string
		 * @return string $url
		 */
		static function get_url($string) {
			preg_match_all('/(http|https):\/\/[\/-\w\d\.,~#@^!\'()?=\+&%;:[\]]+/i', $string, $url);
			$url = @$url[0][0];
			return $url;
		}

		/**
		 * Retrieves the base host of a given URL
		 *
		 * @param string $url
		 * @return string $url_host
		 */
		function get_url_domain($url) {
			$url = $this->get_url($url);
			$url_parts = parse_url($url);
			$url_host_parts = explode('.', @$url_parts['host']);

			// Find base hostname
			$url_host = @$url_parts['host'];
			for ($i = 0; $i < sizeof($url_host_parts)-1; $i++) {
				$test_host = str_replace($url_host_parts[$i] . '.', '', $url_host);
				if ( $this->get_file_contents($url_parts['scheme'] . '://' . $test_host) ) {
					$url_host = $test_host;
				} else {
					break;
				}
			}
			return $url_host;
		}

		/**
		 * Replace HTTPS with HTTP in a string
		 *
		 * @param string $string
		 * @return string $string
		 */
		static function replace_https($string) {
			return str_replace('https://', 'http://', $string);
		}

		/**
		 * Replace HTTP with HTTPS in a string
		 *
		 * @param string $string
		 * @return string $string
		 */
		static function replace_http($string) {
			return str_replace('http://', 'https://', $string);
		}

		/**
		 * Determines if URL is local or external
		 *
		 * @param string $url
		 * @return boolean
		 */
		function is_local($url) {
			if ( ($url_parts = parse_url($url)) && strpos($this->http_url, $url_parts['host']) !== false || strpos($this->https_url, $url_parts['host']) !== false ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Adds the SSL Port to URL in a string
		 *
		 * @param string $string
		 * @return string $string
		 */
		function add_port($string) {
			$url = $this->get_url($string);
			$url_parts = parse_url($url);
			if ( isset($url_parts['port']) ) {
				$url = $this->remove_port($url);
			}

			if ( $this->ssl_port && $this->ssl_port != 80 && $this->ssl_port != 443 && strpos($url, ':' . $this->ssl_port) === false ) {
				$url_host_port = $url_parts['host'] . ':' . $this->ssl_port;
				$string = str_replace($url_parts['host'], $url_host_port, $string);
			}
			return $string;
		}

		/**
		 * Remove the SSL Port from URL in a string
		 *
		 * @param string $string
		 * @return string $string
		 */
		function remove_port($string) {
			$url = $this->get_url($string);

			if ( $this->is_local($url) && $port = parse_url($url, PHP_URL_PORT) ) {
				$string = str_replace($url, str_replace(':' . $port, '', $url), $string);
			}
			return $string;
		}

		/**
		 * Replaces HTTP Host with HTTPS Host
		 *
		 * @param string $string
		 * @return string $string
		 */
		function replace_http_url($string) {
			// URL in string to be replaced
			$url_original = $this->get_url($string);
			if ( $this->is_local($url_original) ) {
				$url_parts = parse_url($url_original);
				$url = str_replace($url_parts['host'], parse_url($this->https_url, PHP_URL_HOST), $url_original);

				if ( $this->diff_host ) {
					$https_url_path = parse_url($this->https_url, PHP_URL_PATH);
					if ( strpos($url_parts['path'], $https_url_path) === false ) {
						if ( $url_parts['path'] == '/' ) {
							if ( isset($url_parts['query']) ) {
								$url_query = '?' . $url_parts['query'];
								$url = str_replace($url_query, '', $url);
							}
							$url = rtrim($url, '/') . $https_url_path . ((isset($url_query)) ? '/' . $url_query : '');
						} else {
							$url = str_replace($url_parts['path'], $https_url_path . $url_parts['path'], $url);
						}
					}
				}

				$url = $this->remove_port($url);
				$url = $this->add_port($url);
				$url = $this->replace_http($url);
				$string = str_replace($url_original, $url, $string);
			} else if ( $url_parts == null ) {
				$this->log('[ERROR] Unabled to parse URL: ' . $url_original);
			}

			return $string;
		}

		/**
		 * Replaces HTTPS Host with HTTP Host
		 *
		 * @param string $string
		 * @return string $string
		 */
		public function replace_https_url($string) {
			$url_original = $this->get_url($string);
			if ( $this->is_local($url_original) ) {
				$url_parts = parse_url($url_original);
				$url = str_replace($url_parts['host'], parse_url($this->http_url, PHP_URL_HOST), $url_original);
				if ( $this->diff_host ) {
					$https_url_path = parse_url($this->https_url, PHP_URL_PATH);
					if ( $https_url_path != '/' && strpos(@$url_parts['path'], $https_url_path) !== false ) {
						$url = str_replace($https_url_path, '', $url);
					}
				}
				$url = $this->remove_port($url);
				$url = $this->replace_https($url);
				$string = str_replace($url_original, $url, $string);
			} else if ( $url_parts == null ) {
				$this->log('[ERROR] Unabled to parse URL: ' . $url_original);
			}

			return $string;
		}

		/**
		 * Retrieves the contents of a local or external file
		 *
		 * @param string $url
		 * @return boolean|string Contents of existing file, or false if file does not exist
		 */
		static function get_file_contents($url) {
			if ( function_exists('curl_init') ) {
				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FAILONERROR, true);
				@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

				$content = curl_exec($ch);
				$info = curl_getinfo($ch);
				if ( !$info['http_code'] && ( $info['http_code'] == 0 || $info['http_code'] == 302 || $info['http_code'] == 404 ) ) {
					$content = false;
				} else if ( $content == "" ) {
					$content = true;
				}
				curl_close($ch);
				return $content;
			} else if ( @ini_get('allow_url_fopen') ) {
				$content = @file_get_contents($url);
				return $content;
			}
			return false;
		}

		/**
		 * Start output buffering
		 *
		 * @param none
		 * @return void
		 */
		public function buffer_start() {
			ob_start(array(&$this, 'process'));
		}

		/**
		 * Processes the output buffer to fix HTML output
		 *
		 * @param string $buffer
		 * @return string $buffer
		 */
		public function process($buffer) {
			$processed_urls = array();
			// Post = 2, Admin = 1, Other = 0
			$location = ((is_admin()) ? 1 : ((is_page() || is_home()) ? 2 : 0));

			$external_urls = get_option('wordpress-https_external_urls');
			if ( !is_array($external_urls) ) {
				$external_urls = array();
			}

			$unsecure_external_urls = get_option('wordpress-https_unsecure_external_urls');
			if ( !is_array($unsecure_external_urls) ) {
				$unsecure_external_urls = array();
			}

			// Fix any occurrence of the HTTPS version of the regular domain when using different SSL Host
			if ( $this->diff_host ) {
				$url = $this->replace_http($this->http_url);
				$count = substr_count($buffer, $url);
				if ( $count > 0 ) {
					$this->log('[FIXED] Updated ' . $count . ' Occurrences of URL: ' . $url . ' => ' . $this->replace_https_url($url));
					$buffer = str_replace($url, $this->replace_https_url($url), $buffer);
				}
			}

			if ( $this->is_ssl() ) {
				if ( is_admin() ) {
					preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $buffer, $matches);
				} else {
					preg_match_all('/\<(script|link|img|form|input|embed|param)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $buffer, $matches);
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
							if ( strpos($url, $this->http_url) !== false && $this->diff_host ) {
								$updated = true;
								$processed_urls[$url] = $this->replace_http_url($url);
								$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
							}
						} else {
							// If local
							if ( $this->is_local($url) ) {
								$updated = true;
								$processed_urls[$url] = $this->replace_http_url($url);
								$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
							// If external and not HTTPS
							} else if ( strpos($url, 'https://') === false ) {
								if ( @in_array($url, $external_urls) == false && @in_array($url, $unsecure_external_urls[$location]) == false ) {
									if ( $this->get_file_contents($this->replace_http($url)) !== false ) {
										// Cache this URL as available over HTTPS for future reference
										$external_urls[] = $url;
										update_option('wordpress-https_external_urls', $external_urls);
									} else {
										// If not available over HTTPS, mark as an unsecure external URL
										$unsecure_external_urls[$location][] = $url;
										update_option('wordpress-https_unsecure_external_urls', $unsecure_external_urls);
									}
								}

								if ( in_array($url, $external_urls) ) {
									$updated = true;
									$processed_urls[$url] = $this->replace_http($url);
									$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
								} else {
									$processed_urls[$url] = $url;
								}
							}

							if ( $updated == false && strpos($url, 'https://') === false ) {
								$this->log('[WARNING] Unsecure Element: <' . $type . '> - ' . $url);
							}
						}
					}

					if ( $updated && $url != $processed_urls[$url] ) {
						$this->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $processed_urls[$url]);
					}
				}

				// Fix any CSS background images or imports
				preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^)]+)[\'"]?\)/im', $buffer, $matches);
				for ($i = 0; $i < sizeof($matches[0]); $i++) {
					$css = $matches[0][$i];
					$url = $matches[2][$i];
					$processed_urls[$url] = $this->replace_http_url($url);
					$buffer = str_replace($css, str_replace($url, $processed_urls[$url], $css), $buffer);
					$this->log('[FIXED] CSS: ' . $url . ' => ' . $processed_urls[$url]);
				}

				// Look for any relative paths that should be udpated to the SSL Host path
				if ( $this->diff_host ) {
					preg_match_all('/\<(script|link|img|input|form|embed|param|a)[^>]+(src|href|action|data|movie)=[\'"](\/[^\'"]*)[\'"][^>]*>/im', $buffer, $matches);

					for ($i = 0; $i < sizeof($matches[0]); $i++) {
						$html = $matches[0][$i];
						$type = $matches[1][$i];
						$attr = $matches[2][$i];
						$url = $matches[3][$i];
						if ( $type != 'input' || ( $type == 'input' && $attr == 'image' ) ) {
							$https_url = $this->https_url;
							if ( strpos($url, parse_url($https_url, PHP_URL_PATH)) !== false ) {
								$https_url = str_replace(parse_url($https_url, PHP_URL_PATH), '', $https_url);
							}
							$processed_urls[$url] = $https_url . $url;
							$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
							$this->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $processed_urls[$url]);
						}
					}
				}
			}

			// Update anchor and form tags to appropriate URL's
			preg_match_all('/\<(a|form)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $buffer, $matches);

			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$html = $matches[0][$i];
				$type = $matches[1][$i];
				$url = $matches[2][$i];
				$scheme = $matches[3][$i];
				$updated = false;

				unset($force_ssl);

				if ( $this->is_local($url) ) {
					$url_parts = parse_url($url);
					if ( $this->diff_host ) {
						$url_parts['path'] = str_replace(parse_url($this->https_url, PHP_URL_PATH), '', $url_parts['path']);
					}
					$url_parts['path'] = str_replace(parse_url(get_option('home'), PHP_URL_PATH), '', $url_parts['path']);

					if ( preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
						$post = $postID[1];
					} else if ( $post = get_page_by_path($url_parts['path']) ) {
						$post = $post->ID;
					} else if ( $url_parts['path'] == '/' ) {
						if ( get_option('show_on_front') == 'posts' ) {
							$post = true;
							$force_ssl = (( get_option('wordpress-https_frontpage') == 1 ) ? true : false);
						} else {
							$post = get_option('page_on_front');
						}
					//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
					} else if ( ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) && ( $this->is_ssl() || $this->ssl_admin )) {
						$post = true;
						$force_ssl = true;
					}

					if ( isset($post) ) {
						// Always change links to HTTPS when logged in via different SSL Host
						if ( $type == 'a' && get_option('wordpress-https_ssl_host_subdomain') == 0 && $this->diff_host && $this->ssl_admin && is_user_logged_in() ) {
							$force_ssl = true;
						} else if ( (int) $post > 0 ) {
							$force_ssl = (( !isset($force_ssl) ) ? get_post_meta($post, 'force_ssl', true) : $force_ssl);
						}

						if ( $force_ssl == true ) {
							$updated = true;
							$processed_urls[$url] = $this->replace_http_url($url);
							$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
						} else if ( get_option('wordpress-https_exclusive_https') == 1 ) {
							$updated = true;
							$processed_urls[$url] = $this->replace_https_url($url);
							$buffer = str_replace($html, str_replace($url, $processed_urls[$url], $html), $buffer);
						}
					}

					if ( $updated && $url != $processed_urls[$url] ) {
						$this->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $processed_urls[$url]);
					}
				}
			}

			// If an unsecure element has been removed from the site, remove it from $unsecure_external_urls to clear warnings
			if ( isset($unsecure_external_urls[$location]) && is_array($unsecure_external_urls[$location]) ) {
				$unsecure_external_urls[$location] = array_values($unsecure_external_urls[$location]);
				for( $i = 0; $i < sizeof($unsecure_external_urls[$location]); $i++ ) {
					$removed = true;
					foreach( $processed_urls as $original_url => $new_url ) {
						// If unsecure_external_url was found in processed_urls, it has not been removed
						if ( $unsecure_external_urls[$location][$i] == $original_url ) {
							$removed = false;
						}
					}
					if ( $removed ) {
						$this->log('[FIXED] Removed Unsecure URL: ' . $unsecure_external_urls[$location][$i]);
						unset($unsecure_external_urls[$location][$i]);
						update_option('wordpress-https_unsecure_external_urls', $unsecure_external_urls);
					}

				}
			}

			// Add debug console logging. It's not pretty, but it works.
			if ( $this->debug && sizeof($this->log) > 0 ) {
				$code = "<script type=\"text/javascript\">\n\tif ( typeof console === 'object' ) {\n";

				array_unshift($this->log, '[BEGIN WordPress HTTPS Debug Log]');
				array_push($this->log, '[END WordPress HTTPS Debug Log]');
				foreach( $this->log as $log_entry ) {
					if ( is_array($log_entry) ) {
						$log_entry = json_encode($log_entry);
					} else {
						$log_entry = "'" . $log_entry . "'";
					}
					$code .= "\t\tconsole.log(" . $log_entry . ");\n";
				}
				$code .= "\t}\n</script>\n";
				$buffer = str_replace("</body>", $code . "\n</body>", $buffer);
			}

			return $buffer;
		}

		/**
		 * Filters HTTPS urls from bloginfo function
		 *
		 * @param string $result
		 * @param string $show
		 * @return string $result
		 */
		public function bloginfo($result = '', $show = '') {
			if ( $show == 'stylesheet_url' || $show == 'template_url' || $show == 'wpurl' || $show == 'home' || $show == 'siteurl' || $show == 'url' ) {
				$result = $this->replace_https_url($result);
			}
			return $result;
		}

		/**
		 * Checks if the current page is SSL
		 *
		 * @param none
		 * @return bool
		 */
		public function is_ssl() {
			$https_url = parse_url($this->https_url);
			// Some extra checks for proxies and Shared SSL
			if ( is_ssl() && strpos($_SERVER['HTTP_HOST'], $https_url['host']) === false && $_SERVER['SERVER_ADDR'] != $_SERVER['HTTP_HOST'] ) {
				return false;
			} else if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ) {
				return true;
			} else if ( $this->diff_host && !is_ssl() && isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && strpos($this->https_url, 'https://' . $_SERVER['HTTP_X_FORWARDED_SERVER']) !== false ) {
				return true;
			} else if ( $this->diff_host && !is_ssl() && strpos($_SERVER['HTTP_HOST'], $https_url['host']) !== false && (!$this->ssl_port || $_SERVER['SERVER_PORT'] == $this->ssl_port) && (isset($https_url['path']) && !$https_url['path'] || strpos($_SERVER['REQUEST_URI'], $https_url['path']) !== false) ) {
				return true;
			}
			return is_ssl();
		}

		/**
		 * Checks if the current page needs to be redirected
		 *
		 * @param none
		 * @return void
		 */
		public function redirect_check() {
			global $post;
			if ( is_front_page() && get_option('show_on_front') == 'posts' ) {
				if ( get_option('wordpress-https_frontpage') == 1 && !$this->is_ssl() ) {
					$scheme = 'https';
				} else if ( get_option('wordpress-https_frontpage') != 1 && get_option('wordpress-https_exclusive_https') == 1 && $this->is_ssl() && ( !$this->diff_host || ( $this->diff_host && $this->ssl_admin && !is_user_logged_in() ) ) ) {
					$scheme = 'http';
				}
			} else if ( ( is_single() || is_page() || is_front_page() || is_home() ) && $post->ID > 0 ) {
				$force_ssl = get_post_meta($post->ID, 'force_ssl', true);
				$force_ssl = apply_filters('force_ssl', $force_ssl, $post->ID );
				if ( !$this->is_ssl() && $force_ssl ) {
					$scheme = 'https';
				} else if ( get_option('wordpress-https_exclusive_https') == 1 && !$force_ssl && ( !$this->diff_host || ( $this->diff_host && $this->ssl_admin && !is_user_logged_in() ) ) ) {
					$scheme = 'http';
				}
			}

			if ( isset($scheme) ) {
				$this->redirect($scheme);
			}
		}

		/**
		 * Fix wp_redirect in admin/login when using a different SSL Host
		 *
		 * @param string $url
		 * @return string $url
		 */
		public function wp_redirect_admin( $url ) {
			$url = $this->replace_http_url($url);

			// Fix redirect_to
			preg_match('/redirect_to=([^&]+)/i', $url, $redirect);
			$redirect_url = $redirect[1];
			$url = str_replace($redirect_url, urlencode($this->replace_http_url(urldecode($redirect_url))), $url);
			return $url;
		}

		/**
		 * Redirects page to HTTP or HTTPS accordingly
		 *
		 * @param string $scheme Either http or https
		 * @return void
		 */
		public function redirect($scheme = 'https') {
			if ( !$this->is_ssl() && $scheme == 'https' ) {
				$url = parse_url($this->https_url);
				$url['scheme'] = $scheme;
			} else if ( $this->is_ssl() && $scheme == 'http' ) {
				$url = parse_url($this->http_url);
				$url['scheme'] = $scheme;
			} else {
				$url = false;
			}
			if ( $url ) {
				$destination = $url['scheme'] . '://' . $url['host'] . (( isset($url['port']) ) ? ':' . $url['port'] : '') . (( isset($url['path']) && strpos($_SERVER['REQUEST_URI'], $url['path']) !== true ) ? $url['path'] : '') . $_SERVER['REQUEST_URI'];
				if ( function_exists('wp_redirect') ) {
					wp_redirect($destination, 301);
				} else {
					// End all output buffering and redirect
					while(@ob_end_clean());

					// If redirecting to an admin page
					if ( strpos($destination, 'wp-admin') !== false || strpos($destination, 'wp-login') !== false ) {
						$destination = $this->wp_redirect_admin($destination);
					}

					header("Location: " . $destination);
				}
				exit();
			}
		}

		/**
		 * Add SSL Host host to allowed redirect hosts
		 *
		 * @param array $content
		 * @return array $content
		 */
		public function allowed_redirect_hosts($content) {
			$content[] = parse_url($this->https_url, PHP_URL_HOST);
			return $content;
		}

		/**
		 * Set Cookie
		 *
		 * Set authentication cookie when using different SSL Host
		 *
		 * @param none
		 * @return void
		 */
		public function set_cookie($cookie, $expire, $expiration, $user_id, $scheme) {
			if( $scheme == 'logged_in' ) {
				$cookie_name = LOGGED_IN_COOKIE;
			} elseif ( $secure ) {
				$cookie_name = SECURE_AUTH_COOKIE;
				$scheme = 'secure_auth';
			} else {
				$cookie_name = AUTH_COOKIE;
				$scheme = 'auth';
			}

			//$cookie_domain = COOKIE_DOMAIN;
			$cookie_path = COOKIEPATH;
			$cookie_path_site = SITECOOKIEPATH;
			$cookie_path_plugins = PLUGINS_COOKIE_PATH;
			$cookie_path_admin = ADMIN_COOKIE_PATH;

			if ( $this->diff_host && $this->is_ssl() ) {
				// If SSL Host is a subdomain and we're setting an authentication cookie, the cookie does not need to be set
				if ( get_option('wordpress-https_ssl_host_subdomain') == 1 && ( $scheme == 'auth' || $scheme == 'secure_auth' ) ) {
					return;
				// If SSL Host is a subdomain, make cookie domain a wildcard
				} else if ( get_option('wordpress-https_ssl_host_subdomain') == 1 ) {
					$cookie_domain = '.' . $this->get_url_domain($this->https_url);
				// Otherwise, cookie domain set for different SSL Host
				} else {
					$cookie_domain = parse_url($this->https_url, PHP_URL_HOST);
				}

				$cookie_path = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . $cookie_path;
				$cookie_path_site = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . $cookie_path_site;
				$cookie_path_plugins = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . $cookie_path_plugins;
				$cookie_path_admin = $cookie_path_site . 'wp-admin';
			}

			// Cookie paths defined to accomodate different SSL Host
			if ( version_compare(phpversion(), '5.2.0', '>=') ) {
				if ( $scheme == 'logged_in' ) {
					setcookie($cookie_name, $cookie, $expire, $cookie_path, $cookie_domain, $secure, true);
					if ( $cookie_path != $cookie_path_site ) {
						setcookie($cookie_name, $cookie, $expire, $cookie_path_site, $cookie_domain, $secure, true);
					}
				} else {
					setcookie($cookie_name, $cookie, $expire, $cookie_path_plugins, $cookie_domain, false, true);
					setcookie($cookie_name, $cookie, $expire, $cookie_path_admin, $cookie_domain, false, true);
				}
			} else {
				if ( !empty($cookie_domain) ) {
					$cookie_domain .= '; HttpOnly';
				}

				if ( $scheme == 'logged_in' ) {
					setcookie($cookie_name, $cookie, $expire, $cookie_path, $cookie_domain, $secure);
					if ( $cookie_path != $cookie_path_site ) {
						setcookie($cookie_name, $cookie, $expire, $cookie_path_site, $cookie_domain, $secure);
					}
				} else {
					setcookie($cookie_name, $cookie, $expire, $cookie_path_plugins, $cookie_domain);
					setcookie($cookie_name, $cookie, $expire, $cookie_path_admin, $cookie_domain);
				}
			}
		}

		/**
		 * Clear Cookies
		 *
		 * Clear authentication and logged in cookies when using a different SSL Host
		 *
		 * @param none
		 * @return void
		 */
		public function clear_cookies() {
			$cookie_domain = '.' . $this->get_url_domain($this->https_url);
			$cookie_path = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . COOKIEPATH;
			$cookie_path_site = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . SITECOOKIEPATH;
			$cookie_path_plugins = rtrim(parse_url($this->https_url, PHP_URL_PATH), '/') . PLUGINS_COOKIE_PATH;
			$cookie_path_admin = $cookie_path_site . 'wp-admin';

			if ( get_option('wordpress-https_ssl_host_subdomain') == 1 ) {
				setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path, $cookie_domain);
				setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site, $cookie_domain);
			}

			setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
			setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
			setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
			setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
			setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path);
			setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site);
		}

		/**
		 * Add 'Force SSL' checkbox to add/edit post pages
		 *
		 * @param none
		 * @return void
		 */
		public function post_checkbox() {
			global $post;

			wp_nonce_field(plugin_basename(__FILE__), 'wordpress-https');

			$checked = false;
			if ( $post->ID ) {
				$checked = get_post_meta($post->ID, 'force_ssl', true);
			}
			echo '<div class="misc-pub-section misc-pub-section-wphttps"><label>Force SSL: <input type="checkbox" value="1" name="force_ssl" id="force_ssl"' . (( $checked ) ? ' checked="checked"' : '') . ' /></label></div>';
		}

		/**
		 * Save Force SSL option to post or page
		 *
		 * @param int $post_id
		 * @return int $post_id
		 */
		public function post_save( $post_id ) {
			if ( array_key_exists('wordpress-https', $_POST) ) {
				if ( !wp_verify_nonce($_POST['wordpress-https'], plugin_basename(__FILE__)) ) {
					return $post_id;
				}

				if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
					return $post_id;
				}

				if ( $_POST['post_type'] == 'page' ) {
					if ( !current_user_can('edit_page', $post_id) ) {
						return $post_id;
					}
				} else {
					if ( !current_user_can('edit_post', $post_id) ) {
						return $post_id;
					}
				}

				$force_ssl = (( $_POST['force_ssl'] == 1 ) ? true : false);
				if ( $force_ssl ) {
					update_post_meta($post_id, 'force_ssl', 1);
				} else {
					delete_post_meta($post_id, 'force_ssl');
				}

				return $force_ssl;
			}
			return $post_id;
		}

		/**
		 * Admin panel menu option
		 *
		 * @param none
		 * @return void
		 */
		public function menu() {
			add_options_page('WordPress HTTPS Settings', 'WordPress HTTPS', 'manage_options', 'wordpress-https', array(&$this, 'settings'));
		}

		/**
		 * Plugin links on Manage Plugins page in admin panel
		 *
		 * @param array $links
		 * @param string $file
		 * @return array $links
		 */
		public function plugin_links($links, $file) {
			if ( strpos($file, basename( __FILE__)) === false ) {
				return $links;
			}

			$links[] = '<a href="' . site_url() . '/wp-admin/options-general.php?page=wordpress-https" title="WordPress HTTPS Settings">Settings</a>';
			$links[] = '<a href="http://wordpress.org/extend/plugins/wordpress-https/faq/" title="Frequently Asked Questions">FAQ</a>';
			$links[] = '<a href="http://wordpress.org/tags/wordpress-https#postform" title="Support">Support</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=N9NFVADLVUR7A" title="Support WordPress HTTPS development with a donation!">Donate</a>';
			return $links;
		}

		/**
		 * Settings Page
		 *
		 * @param none
		 * @return void
		 */
		public function settings() {
			if ( !current_user_can('manage_options') ) {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}

			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				$errors = array();
				$reload = false;
				$logout = false;
				if ( @$_POST['Reset'] ) {
					$this->reset();
					$reload = true;
				} else {
					foreach ($this->options_default as $key => $default) {
						if ( !array_key_exists($key, $_POST) && $default == 0 ) {
							$_POST[$key] = 0;
							update_option($key, $_POST[$key]);
						} else {
							if ( $key == 'wordpress-https_ssl_host' ) {
								if ( $_POST[$key] != '' ) {
									$url = strtolower($_POST[$key]);
									// Add scheme if it doesn't exist so that parse_url does not fail
									if ( strpos($url, 'http://') === false && strpos($url, 'https://') === false ) {
										$url = $this->replace_http('http://' . $url);
									}
									$url = parse_url($url);
									$port = ((isset($_POST['wordpress-https_ssl_port'])) ? $_POST['wordpress-https_ssl_port'] : $url['port']);
									$port = (($port != 80 && $port != 443) ? $port : null);
									$url = 'https://' . $url['host'] . (($port) ? ':' . $port : '') . @$url['path'];

									// If secure host is set to a different host
									if ( $url != $this->https_url ) {
										$home_url = $url . parse_url(get_option('home'), PHP_URL_PATH);
										// Add trailing slash
										$home_url = ((substr($home_url, -1) !== '/') ? $home_url . '/' : $home_url);
										// Ensure that the WordPress installation is accessible at this host
										if ( $this->get_file_contents($home_url) ) {
											// Remove trailing slash
											if ( substr($url, -1, 1) == '/' ) {
												$url = substr($url, 0, strlen($url)-1);
											}
											$this->log('[SETTINGS] Updated SSL Host: ' . $this->https_url . ' => ' . $url);

											// If secure domain has changed and currently on SSL, logout user
											if ( $this->is_ssl() ) {
												$logout = true;
											}
											$_POST[$key] = $this->remove_port($url);
										} else {
											$errors[] = '<strong>SSL Host</strong> - Invalid WordPress installation at ' . $home_url;
											$_POST[$key] = get_option($key);
										}
									} else {
										$_POST[$key] = $this->https_url;
									}
								} else {
									$_POST[$key] = get_option($key);
								}
							} else if ( $key == 'wordpress-https_ssl_admin' ) {
								if ( force_ssl_admin() || force_ssl_login() ) {
									$errors[] = '<strong>SSL Admin</strong> - FORCE_SSL_ADMIN and FORCE_SSL_LOGIN can not be set to true in your wp-config.php.';
									$_POST[$key] = 0;
								// If forcing SSL Admin and currently not SSL, logout user
								} else if ( !$this->is_ssl() ) {
									$logout = true;
								}
							} else if ( $key == 'wordpress-https_ssl_host_subdomain' ) {
								// Checks to see if the SSL Host is a subdomain
								$http_domain = $this->get_url_domain($this->http_url);
								$https_domain = $this->get_url_domain($this->https_url);

								if ( $this->replace_https($url) != $this->http_url && $http_domain == $https_domain ) {
									$_POST[$key] = 1;
								} else {
									$_POST[$key] = 0;
								}
							}

							update_option($key, $_POST[$key]);
						}
					}
				}

				if ( $logout ) {
					wp_logout();
				}

				if ( array_key_exists('ajax', $_POST) ) {
					while(@ob_end_clean());
					ob_start();
					if ( sizeof( $errors ) > 0 ) {
						echo "<div class=\"error below-h2 fade wphttps-message\" id=\"message\">\n\t<ul>\n";
						foreach ( $errors as $error ) {
							echo "\t\t<li><p>".$error."</p></li>\n";
						}
						echo "\t</ul>\n</div>\n";
					} else {
						echo "<div class=\"updated below-h2 fade wphttps-message\" id=\"message\"><p>Settings saved.</p></div>\n";
						if ( $logout || $reload ) {
							echo "<script type=\"text/javascript\">window.location.reload();</script>";
						}
					}
					exit();
				}
			}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>
	<h2>WordPress HTTPS Settings</h2>

<?php
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		if ( sizeof( $errors ) > 0 ) {
			echo "\t<div class=\"error below-h2 fade wphttps-message\" id=\"message\">\n\t<ul>\n";
			foreach ( $errors as $error ) {
				echo "\t\t<li><p>".$error."</p></li>\n";
			}
			echo "\t</ul>\n</div>\n";
		} else {
			echo "\t\t<div class=\"updated below-h2 fade wphttps-message\" id=\"message\"><p>Settings saved.</p></div>\n";
		}
	} else {
		echo "\t<div class=\"wphttps-message-wrap\"id=\"message-wrap\"><div id=\"message-body\"></div></div>\n";
	}
?>

	<div id="wphttps-sidebar">

<?php if ( sizeof($this->warnings()) > 0 ) { ?>
		<div class="wphttps-widget" id="wphttps-warnings">
			<h3 class="wphttps-widget-title">Warnings</h3>
			<div class="wphttps-widget-content inside">
<?php
	foreach( $this->warnings() as $warning ) {
		$warning_id = 'warnings-' . strtolower(str_replace(' ', '-', $warning['label']));
		echo "\t\t\t\t\t<strong>" . $warning['label'] . "</strong><a class=\"warning-help wphttps-icon\" href=\"#" . $warning_id . "-tooltip\">Help</a>\n";
		echo "\t\t\t\t\t<ul id=\"" . $warning_id . "\">";
		foreach ( $warning['warnings'] as $warning ) {
			echo "\t\t\t\t\t\t<li><span class=\"warning-url\">" . $warning . "</span></li>\n";
		}
		echo "\t\t\t\t\t</ul>\n\n";
	}
?>
			</div>
		</div>

<?php } ?>

		<div class="wphttps-widget" id="wphttps-updates">
			<h3 class="wphttps-widget-title">Developer Updates</h3>
			<div class="wphttps-widget-content inside">
				<img alt="Loading..." src="<?php echo parse_url($this->plugin_url, PHP_URL_PATH); ?>/css/images/wpspin_light.gif" class="loading" id="updates-loading" />
			</div>
		</div>

		<div class="wphttps-widget" id="wphttps-support">
			<h3 class="wphttps-widget-title">Support</h3>
			<div class="wphttps-widget-content inside">
				<p>Having problems getting your site secure? If you haven't already, check out the <a href="http://wordpress.org/extend/plugins/wordpress-https/faq/" target="_blank">Frequently Asked Questions</a>.</p>
				<p>Still not fixed? Please <a href="http://wordpress.org/tags/wordpress-https#postform" target="_blank">start a support topic</a> and I'll do my best to assist you.</p>
			</div>
		</div>

		<div class="wphttps-widget" id="wphttps-donate">
			<h3 class="wphttps-widget-title">Donate</h3>
			<div class="wphttps-widget-content inside">
				<p>If you found this plugin useful, or I've already helped you, please considering buying me a <a href="http://en.wikipedia.org/wiki/Newcastle_Brown_Ale" target="_blank">beer</a> or two.</p>
				<p>Donations help alleviate the time spent developing and supporting this plugin and are greatly appreciated.</p>

				<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=N9NFVADLVUR7A" target="_blank" id="wphttps-donate-link">
					<img alt="Donate" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" width="74" height="21" />
				</a>
			</div>
		</div>

	</div>

	<div id="wphttps-main">
		<div id="post-body">
			<form name="form" id="wordpress-https" action="options-general.php?page=wordpress-https" method="post">
			<?php settings_fields('wordpress-https'); ?>

			<input type="hidden" name="wordpress-https_ssl_host_subdomain" value="<?php echo ((get_option('wordpress-https_ssl_host_subdomain') != 1) ? 0 : 1); ?>" />

			<h3 class="title">General Settings</h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">SSL Host</th>
					<td>
						<fieldset>
							<label for="wordpress-https_ssl_host">
								<input name="wordpress-https_ssl_host" type="text" id="wordpress-https_ssl_host" class="regular-text code" value="<?php echo str_replace('https://', '', $this->remove_port($this->https_url)); ?>" />
							</label>
							<label for="wordpress-https_ssl_port">Port
								<input name="wordpress-https_ssl_port" type="text" id="wordpress-https_ssl_port" class="small-text" value="<?php echo $this->ssl_port; ?>" />
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Force SSL Exclusively</th>
					<td>
						<fieldset>
							<input name="wordpress-https_exclusive_https" type="checkbox" id="wordpress-https_exclusive_https" value="1"<?php echo ((get_option('wordpress-https_exclusive_https')) ? ' checked="checked"' : ''); ?> />
							<label for="wordpress-https_exclusive_https">
								Posts and pages without <a href="<?php echo parse_url($this->plugin_url, PHP_URL_PATH); ?>/screenshot-2.png" class="thickbox">Force SSL</a> enabled will be redirected to HTTP.
							</label>
						</fieldset>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Force SSL Administration</th>
					<td>
						<fieldset>
							<label for="wordpress-https_ssl_admin">
								<input name="wordpress-https_ssl_admin" type="checkbox" id="wordpress-https_ssl_admin" value="1"<?php echo (($this->ssl_admin) ? ' checked="checked"' : ''); ?><?php echo ((force_ssl_admin()) ? ' disabled="disabled" title="FORCE_SSL_ADMIN is true in wp-config.php"' : ''); ?> />
							</label>
						</fieldset>
					</td>
				</tr>

<?php if ( get_option('show_on_front') == 'posts' ) { ?>
				<tr valign="top">
					<th scope="row">HTTPS Front Page</th>
					<td>
						<fieldset>
							<label for="wordpress-https_frontpage">
								<input name="wordpress-https_frontpage" type="checkbox" id="wordpress-https_frontpage" value="1"<?php echo ((get_option('wordpress-https_frontpage')) ? ' checked="checked"' : ''); ?> />
							</label>
						</fieldset>
					</td>
				</tr>

<?php } ?>
			</table>

			<p class="button-controls">
				<input type="submit" name="Submit" value="Save Changes" class="button-primary" id="settings-save" />
				<input type="submit" name="Reset" value="Reset" class="button-secondary" id="settings-reset" />
				<img alt="Waiting..." src="<?php echo parse_url($this->plugin_url, PHP_URL_PATH); ?>/css/images/wpspin_light.gif" class="waiting" id="submit-waiting" />
			</p>
			</form>
		</div>
	</div>

	<div class="wphttps-tooltip-body" id="warnings-unsecure-external-content-tooltip">Unsecure External Content are URL's being loaded on secure pages that can not be loaded securely. It is recommended that you remove these elements by disabling or editing the plugin or theme that requires them.</div>

<?php
		}
	} // End WordPressHTTPS Class
}

// Instantiate class if we're in WordPress
if ( class_exists('WordPressHTTPS') && function_exists('get_bloginfo') ) {
	$wordpress_https = new WordPressHTTPS();
	register_activation_hook(__FILE__, array($wordpress_https, 'install'));
}