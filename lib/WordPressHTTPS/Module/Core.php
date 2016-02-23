<?php
/**
 * Core Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Module_Core extends Mvied_Plugin_Module {

	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		$plugin = $this->getPlugin();
		if ( $plugin->getSetting('ssl_host_diff') && $plugin->isSsl() ) {
			// Prevent WordPress' canonical redirect when using a different SSL Host
			remove_filter('template_redirect', 'redirect_canonical');
			// Add SSL Host path to rewrite rules
			add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules'), 10, 1);
		}

		// Add SSL Host to allowed redirect hosts
		add_filter('allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10, 1);

		// Filter URL's
		add_filter('bloginfo_url', array(&$this, 'secure_url'), 10);
		add_filter('logout_url', array(&$this, 'secure_url'), 10);
		add_filter('login_url', array(&$this, 'secure_url'), 10);
		add_filter('network_admin_url', array(&$this, 'secure_url'), 10);

		// Filter Element URL's
		add_filter('get_avatar', array(&$this, 'element_url'), 10);
		add_filter('wp_get_attachment_url', array(&$this, 'element_url'), 10);
		add_filter('template_directory_uri', array(&$this, 'element_url'), 10);
		add_filter('stylesheet_directory_uri', array(&$this, 'element_url'), 10);
		add_filter('plugins_url', array(&$this, 'element_url'), 10);
		add_filter('includes_url', array(&$this, 'element_url'), 10);

		// Filter admin_url in admin
		if ( is_admin() ) {
			add_filter('admin_url', array(&$this, 'admin_url'), 10, 2);
		// Filter site_url publicly
		} else {
			add_filter('site_url', array(&$this, 'site_url'), 10, 4);
		}

		// Filter force_ssl
		add_filter('force_ssl', array(&$this, 'secure_wordpress_forms'), 20, 3);
		add_filter('force_ssl', array(&$this, 'secure_different_host_admin'), 20, 3);
		add_filter('force_ssl', array(&$this, 'secure_child_post'), 30, 3);
		add_filter('force_ssl', array(&$this, 'secure_admin'), 30, 3);
		add_filter('force_ssl', array(&$this, 'secure_login'), 30, 3);
		add_filter('force_ssl', array(&$this, 'secure_element'), 30, 3);
		add_filter('force_ssl', array(&$this, 'secure_post'), 40, 3);
		add_filter('force_ssl', array(&$this, 'secure_exclusive'), 50, 3);

		//add filters that provide the post or post id
		$filters_that_use_post = array('page_link', 'preview_page_link', 'post_link', 'preview_page_link', 'post_type_link', 'attachment_link',  'search_link');
		foreach( $filters_that_use_post as $filter ) {
			add_filter($filter, array(&$this, 'secure_post_link'), 10, 3);
		}

		//add filters that don't provide a post id
		$filters_without_post = array('comment_reply_link', 'day_link', 'month_link', 'year_link','category_link', 'author_link', 'archives_link', 'tag_link',);
		foreach( $filters_without_post as $filter ) {
			add_filter($filter, array(&$this, 'secure_post_link'), 10);
		}

		// Run install when new blog is created
		add_action('wpmu_new_blog', array($plugin, 'install'), 10, 0);

		// Set response headers
		add_action($plugin->getSlug() . '_init', array(&$this, 'set_headers'), 9, 1);

		if ( $plugin->getSetting('ssl_host_diff') ) {
			// Remove SSL Host authentication cookies on logout
			add_action('clear_auth_cookie', array(&$this, 'clear_cookies'));

			// Set authentication cookie
			if ( $plugin->isSsl() ) {
				add_action('set_auth_cookie', array(&$this, 'set_cookie'), 10, 5);
				add_action('set_logged_in_cookie', array(&$this, 'set_cookie'), 10, 5);
			}
		}

		// Filter scripts
		add_action('wp_print_scripts', array(&$this, 'fix_scripts'), 100, 0);
		add_action('admin_print_scripts', array(&$this, 'fix_scripts'), 100, 0);

		// Filter styles
		add_action('wp_print_styles', array(&$this, 'fix_styles'), 100, 0);
		add_action('admin_print_styles', array(&$this, 'fix_styles'), 100, 0);

		// Run proxy check
		if ( $plugin->getSetting('ssl_proxy') === 'auto' ) {
			// If page is not SSL and no proxy cookie is detected, run proxy check
			if ( ! $plugin->isSsl() && ! isset($_COOKIE['wp_proxy']) ) {
				add_action('init', array(&$this, 'proxy_check'), 1);
				add_action('admin_init', array(&$this, 'proxy_check'), 1);
			// Update ssl_proxy setting if a proxy has been detected
			} else if ( $plugin->getSetting('ssl_proxy') !== true && isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] == 1 ) {
				$plugin->setSetting('ssl_proxy', 1);
			// Update ssl_proxy if proxy is no longer detected
			} else if ( $plugin->getSetting('ssl_proxy') !== false && isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] != 1 ) {
				$plugin->setSetting('ssl_proxy', 0);
			}
		}

		// Check if the page needs to be redirected
		if ( is_admin() || preg_match('/wp-login\.php/', $_SERVER['REQUEST_URI']) === 1 ) {
			add_action($plugin->getSlug() . '_init', array(&$this, 'redirect_check'));
			add_action($plugin->getSlug() . '_init', array(&$this, 'clear_redirect_count_cookie'), 9, 1);
		} else {
			add_action('template_redirect', array(&$this, 'redirect_check'));
			add_action('template_redirect', array(&$this, 'clear_redirect_count_cookie'), 9, 1);
		}
	}

	/**
	 * Allowed Redirect Hosts
	 * WordPress Filter - aloowed_redirect_hosts
	 *
	 * @param array $content
	 * @return array $content
	 */
	public function allowed_redirect_hosts( $content ) {
		$content[] = $this->getPlugin()->getHttpsUrl()->getHost();
		return $content;
	}

	/**
	 * Secure URL
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function secure_url( $url = '' ) {
		$plugin = $this->getPlugin();
		$force_ssl = apply_filters('force_ssl', null, 0, $url);
		if ( $force_ssl ) {
			$url = $plugin->makeUrlHttps($url);
		} else if ( !is_null($force_ssl) && !$force_ssl ) {
			$url = $plugin->makeUrlHttp($url);
		}
		return $url;
	}

	/**
	 * Secure Element URL
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function element_url( $url = '' ) {
		$plugin = $this->getPlugin();
		$force_ssl = apply_filters('force_ssl', null, 0, $url);
		if ( $plugin->isSsl() || $force_ssl ) {
			$url = $plugin->makeUrlHttps($url);
		} else if ( !is_null($force_ssl) && !$force_ssl ) {
			$url = $plugin->makeUrlHttp($url);
		}
		return $url;
	}

	/**
	 * Add rewrite rule to recognize additional path information on SSL Host
	 *
	 * @param array $rules
	 * @return array $rules
	 */
	public function rewrite_rules( $rules = array() ) {
		$plugin = $this->getPlugin();
		$requestPath = str_replace($plugin->getHttpsUrl()->getPath(), '', $_SERVER['REQUEST_URI']);
		if ( $plugin->getHttpUrl()->getPath() != '/' ) {
			$httpsPath = str_replace($plugin->getHttpUrl()->getPath(), '', $plugin->getHttpsUrl()->getPath());
		} else {
			$httpsPath = $plugin->getHttpsUrl()->getPath();
		}
		if ( $httpsPath != '/' ) {
			$rules['^'  . $httpsPath . '([^\'"]+)'] = 'index.php?pagename=$matches[1]';
		}
		return $rules;
	}

	/**
	 * Admin URL
	 * WordPress Filter - admin_url
	 *
	 * @param string $url
	 * @param string $scheme
	 * @return string $url
	 */
	public function admin_url( $url, $scheme ) {
		$plugin = $this->getPlugin();
		$force_ssl = apply_filters('force_ssl', null, 0, $url);

		// Catches base URL's used by low-level WordPress code
		if ( is_null($force_ssl) && is_admin() && $plugin->isSsl() && ($url_parts = parse_url($url)) && ( !isset($url_parts['path']) || trim($url_parts['path'], '/') == '' ) ) {
			$force_ssl = true;
		}

		if ( $scheme != 'http' && $force_ssl ) {
			$url = $plugin->makeUrlHttps($url);
		} else if ( !is_null($force_ssl) && !$force_ssl ) {
			$url = $plugin->makeUrlHttp($url);
		}
		return $url;
	}

	/**
	 * Site URL
	 * WordPress Filter - site_url
	 *
	 * @param string $url
	 * @param string $path
	 * @param string $scheme
	 * @param int $blog_id
	 * @return string $url
	 */
	public function site_url( $url, $path, $scheme, $blog_id ) {
		$plugin = $this->getPlugin();
		$force_ssl = apply_filters('force_ssl', null, 0, $url);

		if ( $scheme != 'http' && $force_ssl ) {
			$url = $plugin->makeUrlHttps($url);
		} else if ( !is_null($force_ssl) && !$force_ssl ) {
			$url = $plugin->makeUrlHttp($url);
		}
		return $url;
	}

	/**
	 * Secure Post Link
	 *
	 * @param string $url
	 * @param WP_Post|int $post if one is provided
	 * @return string $url
	 */
	public function secure_post_link( $url, $post = null) {
		$plugin = $this->getPlugin();
		if( $post instanceof WP_Post){
			$post = $post->ID;
		}
		$force_ssl = apply_filters('force_ssl', null, $post, $url);
		if ( $force_ssl ) {
			$url = $plugin->makeUrlHttps($url);
		} else if ( !is_null($force_ssl) && !$force_ssl ) {
			$url = $plugin->makeUrlHttp($url);
		}
		return $url;
	}

	/**
	 * Secure Admin
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_admin( $force_ssl, $post_id = 0, $url = '' ) {
		$plugin = $this->getPlugin();
		if ( $url != '' && $plugin->isUrlLocal($url) && ( strpos($url, 'wp-admin') !== false || strpos($url, 'wp-login') !== false ) ) {
			if ( $plugin->getSetting('exclusive_https') && !( ( defined('FORCE_SSL_ADMIN') && constant('FORCE_SSL_ADMIN') ) || $plugin->getSetting('ssl_admin') ) ) {
				$force_ssl = false;
			//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
			} else if ( ( $plugin->isSsl() && !$plugin->getSetting('exclusive_https') ) || ( ( defined('FORCE_SSL_ADMIN') && constant('FORCE_SSL_ADMIN') ) || $plugin->getSetting('ssl_admin') ) ) {
				$force_ssl = true;
			}
			if ( strpos($url, 'admin-ajax.php') !== false ) {
				if ( $plugin->isSsl() ) {
					$force_ssl = true;
				} else {
					$force_ssl = false;
				}
			}
		}

		return $force_ssl;
	}

	/**
	 * Secure Login
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_login( $force_ssl, $post_id = 0, $url = '' ) {
		/**
		 * Forward compatibility for Wordpress 4.4+
		 */
		if ( version_compare(get_bloginfo('version'), '4.4', '>') ) {
			if ( $url != '' && $this->getPlugin()->isUrlLocal($url) ) {
				if ( force_ssl_admin() && preg_match('/wp-login\.php$/', $url) === 1 ) {
					$force_ssl = true;
				}
			}
		} else {
			if ( $url != '' && $this->getPlugin()->isUrlLocal($url) ) {
				if ( force_ssl_login() && preg_match('/wp-login\.php$/', $url) === 1 ) {
					$force_ssl = true;
				}
			}
		}
		return $force_ssl;
	}

	/**
	 * Secure Element by Extension
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_element( $force_ssl, $post_id = 0, $url = '' ) {
		$plugin = $this->getPlugin();
		if ( $url != '' && $plugin->isUrlLocal($url) ) {
			$filename = basename($url);
			foreach( $plugin->getFileExtensions() as $type => $extensions ) {
				foreach( $extensions as $extension ) {
					if ( preg_match('/\.' . $extension . '(\?|$)/', $filename) ) {
						if ( $plugin->isSsl() ) {
							$force_ssl = true;
						} else {
							$force_ssl = false;
						}
						break 2;
					}
				}
			}
		}
		return $force_ssl;
	}

	/**
	 * Secure Post
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_post( $force_ssl, $post_id = 0, $url = '' ) {
		global $wpdb;

		if ( $post_id > 0 ) {
			$force_ssl = ( get_post_meta($post_id, 'force_ssl', true) == 1  ? true : $force_ssl);
		}
		return $force_ssl;
	}

	/**
	 * Always secure pages when using a different SSL Host.
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_exclusive( $force_ssl, $post_id = 0, $url = '' ) {
		$plugin = $this->getPlugin();
		if ( is_null($force_ssl) && $plugin->isUrlLocal($url) && $plugin->getSetting('exclusive_https') ) {
			$force_ssl = false;
		}
		return $force_ssl;
	}

	/**
	 * Always secure pages when using a different SSL Host.
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_different_host_admin( $force_ssl, $post_id = 0, $url = '' ) {
		$plugin = $this->getPlugin();
		if ( $post_id > 0 || ( $url != '' && $plugin->isUrlLocal($url) ) ) {
			if ( !$plugin->getSetting('exclusive_https') && !$plugin->getSetting('ssl_host_subdomain') && $plugin->getSetting('ssl_host_diff') && $plugin->getSetting('ssl_admin') && function_exists('is_user_logged_in') && is_user_logged_in() ) {
				$force_ssl = true;
			}
		}
		return $force_ssl;
	}

	/**
	 * Secure WordPress forms
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_wordpress_forms( $force_ssl, $post_id = 0, $url = '' ) {
		$plugin = $this->getPlugin();
		if ( $plugin->isSsl() && $plugin->isUrlLocal($url) && ( strpos($url, 'wp-pass.php') !== false || strpos($url, 'wp-login.php?action=') !== false || strpos($url, 'wp-comments-post.php') !== false ) ) {
			$force_ssl = true;
		}
		return $force_ssl;
	}

	/**
	 * Secure Child Post
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @param string $url
	 * @return boolean $force_ssl
	 */
	public function secure_child_post( $force_ssl, $post_id = 0, $url = '' ) {
		if ( $post_id > 0 ) {
			$postParent = get_post($post_id);
			while ( $postParent->post_parent ) {
				$postParent = get_post( $postParent->post_parent );
				if ( get_post_meta($postParent->ID, 'force_ssl_children', true) == 1 ) {
					$force_ssl = true;
					break;
				}
			}
		}
		return $force_ssl;
	}

	/**
	 * Fix Enqueued Scripts
	 *
	 * @param none
	 * @return void
	 */
	public function fix_scripts() {
		global $wp_scripts;
		$plugin = $this->getPlugin();
		if ( isset($wp_scripts) && sizeof($wp_scripts->registered) > 0 ) {
			foreach ( $wp_scripts->registered as $script ) {
				if ( in_array($script->handle, $wp_scripts->queue) ) {
					if ( strpos($script->src, 'http') === 0 ) {
						if ( $plugin->isSsl() ) {
							$updated = $plugin->makeUrlHttps($script->src);
							$script->src = $updated;
						} else {
							$updated = $plugin->makeUrlHttp($script->src);
							$script->src = $updated;
						}
						if ( $script->src != $updated ) {
							$log = '[FIXED] Element: <script> - ' . $url . ' => ' . $updated;
							if ( ! in_array($log, $plugin->getLogger()->getLog()) ) {
								$plugin->getLogger()->log($log);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Fix Enqueued Styles
	 *
	 * @param none
	 * @return void
	 */
	public function fix_styles() {
		global $wp_styles;
		$plugin = $this->getPlugin();
		if ( isset($wp_styles) && sizeof($wp_styles->registered) > 0 ) {
			foreach ( (array)$wp_styles->registered as $style ) {
				if ( in_array($style->handle, $wp_styles->queue) ) {
					if ( strpos($style->src, 'http') === 0 ) {
						if ( $plugin->isSsl() ) {
							$updated = $plugin->makeUrlHttps($style->src);
							$style->src = $updated;
						} else {
							$updated = $plugin->makeUrlHttp($style->src);
							$style->src = $updated;
						}
						if ( $style->src != $updated ) {
							$log = '[FIXED] Element: <link> - ' . $url . ' => ' . $updated;
							if ( ! in_array($log, $plugin->getLogger()->getLog()) ) {
								$plugin->getLogger()->log($log);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Proxy Check
	 *
	 * If the server is on a proxy and not correctly reporting HTTPS, this
	 * JavaScript makes sure that the correct redirect takes place.
	 *
	 * @param none
	 * @return void
	 */
	public function proxy_check() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$cookie_expiration = gmdate('D, d-M-Y H:i:s T', strtotime('now + 10 years'));
		echo '<!-- WordPress HTTPS Proxy Check -->' . "\n";
		echo '<script type="text/javascript">function getCookie(a){var b=document.cookie;var c=a+"=";var d=b.indexOf("; "+c);if(d==-1){d=b.indexOf(c);if(d!=0)return null}else{d+=2;var e=document.cookie.indexOf(";",d);if(e==-1){e=b.length}}return unescape(b.substring(d+c.length,e))}if(getCookie("wp_proxy")!=true){if(window.location.protocol=="https:"){document.cookie="wp_proxy=1; path=/; expires=' . $cookie_expiration . '"}else if(getCookie("wp_proxy")==null){document.cookie="wp_proxy=0; path=/; expires=' . $cookie_expiration . '"}if(getCookie("wp_proxy")!=null){window.location.reload()}else{document.write("You must enable cookies.")}}</script>' . "\n";
		echo '<noscript>Your browser does not support JavaScript.</noscript>' . "\n";
		exit();
	}

	/**
	 * Redirect Check
	 *
	 * Checks if the current page needs to be redirected
	 *
	 * @param none
	 * @return void
	 */
	public function redirect_check() {
		global $post;

		$plugin = $this->getPlugin();
		$force_ssl = apply_filters('force_ssl', null, ( $post ? $post->ID : null ), ( $plugin->isSsl() ? 'https' : 'http' ) . '://' . ( isset($_SERVER['HTTP_X_FORWARDED_SERVER']) ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['HTTP_HOST'] ) . $_SERVER['REQUEST_URI'] );

		// Domain mapping check
		if ( function_exists('domain_mapping_siteurl') && $force_ssl ) {
			$mapped_domain = domain_mapping_siteurl(false);
			if ( strpos($plugin->getHttpsUrl(), $mapped_domain) === false ) {
				if ( remove_action('template_redirect', 'redirect_to_mapped_domain') ) {
					$plugin->getLogger()->log('[FIXED] Domain mapping redirect removed.');
				} else {
					$plugin->getLogger()->log('[WARNING] Unable to remove domain mapping redirect.');
				}
			}
		}

		if ( ! $plugin->isSsl() && isset($force_ssl) && $force_ssl ) {
			$scheme = 'https';
		} else if ( $plugin->isSsl() && isset($force_ssl) && ! $force_ssl ) {
			$scheme = 'http';
		}

		if ( isset($scheme) ) {
			$plugin->redirect($scheme);
		}
	}

	/**
	 * Add Access-Control-Allow-Origin header to AJAX calls
	 *
	 * @param none
	 * @return void
	 */
	public function set_headers() {
		if ( !headers_sent() && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ) {
			header("Access-Control-Allow-Origin: " . $this->getPlugin()->getHttpsUrl());
		}
	}

	/**
	 * Set Cookie
	 * WordPress Hook - set_auth_cookie, set_logged_in_cookie
	 *
	 * @param string $cookie
	 * @param string $expire
	 * @param int $expiration
	 * @param int $user_id
	 * @param string $scheme
	 * @return void
	 */
	public function set_cookie($cookie, $expire, $expiration, $user_id, $scheme) {
		$plugin = $this->getPlugin();
		$secure = null;
		if ( ( $scheme == 'secure_auth' && $plugin->isSsl() ) || ( ( ( defined('FORCE_SSL_ADMIN') && constant('FORCE_SSL_ADMIN') ) || $plugin->getSetting('ssl_admin') ) && ! $plugin->getSetting('ssl_host_subdomain') ) ) {
			$secure = true;
		}
		$secure = apply_filters('secure_auth_cookie', $secure, $user_id);

		if( $scheme == 'logged_in' ) {
			$cookie_name = LOGGED_IN_COOKIE;
		} elseif ( $secure ) {
			$cookie_name = SECURE_AUTH_COOKIE;
			$scheme = 'secure_auth';
		} else {
			$cookie_name = AUTH_COOKIE;
			$scheme = 'auth';
			$secure = false;
		}

		//$cookie_domain = COOKIE_DOMAIN;
		$cookie_path = COOKIEPATH;
		$cookie_path_site = SITECOOKIEPATH;
		$cookie_path_plugins = PLUGINS_COOKIE_PATH;
		$cookie_path_admin = ADMIN_COOKIE_PATH;

		if ( $plugin->isSsl() ) {
			// If SSL Host is a subdomain, make cookie domain a wildcard
			if ( $plugin->getSetting('ssl_host_subdomain') ) {
				$cookie_domain = '.' . $plugin->getHttpsUrl()->getBaseHost();
			// Otherwise, cookie domain set for different SSL Host
			} else {
				$cookie_domain = $plugin->getHttpsUrl()->getHost();
			}

			if ( $plugin->getHttpsUrl()->getPath() != '/' ) {
				$cookie_path = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path);
				$cookie_path_site = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path_site);
				$cookie_path_plugins = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path_plugins);
			}

			if ( $plugin->getHttpUrl()->getPath() != '/' ) {
				$cookie_path = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path);
				$cookie_path_site = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path_site);
				$cookie_path_plugins = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path_plugins);
			}

			$cookie_path = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path;
			$cookie_path_site = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path_site;
			$cookie_path_plugins = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path_plugins;
			$cookie_path_admin = rtrim($cookie_path_site, '/') . '/wp-admin';
		}

		if ( $scheme == 'logged_in' ) {
			setcookie($cookie_name, $cookie, $expire, $cookie_path, $cookie_domain, $secure, true);
			if ( $cookie_path != $cookie_path_site ) {
				setcookie($cookie_name, $cookie, $expire, $cookie_path_site, $cookie_domain, $secure, true);
			}
		} else {
			setcookie($cookie_name, $cookie, $expire, $cookie_path_plugins, $cookie_domain, false, true);
			setcookie($cookie_name, $cookie, $expire, $cookie_path_admin, $cookie_domain, false, true);
		}
	}

	/**
	 * Removes redirect_count cookie.
	 *
	 * @param none
	 * @return void
	 */
	public function clear_redirect_count_cookie() {
		if ( !headers_sent() && isset($_COOKIE['redirect_count']) ) {
			setcookie('redirect_count', null, -time(), '/');
		}
	}

	/**
	 * Clear Cookies
	 * WordPress Hook - clear_auth_cookie
	 *
	 * @param none
	 * @return void
	 */
	public function clear_cookies() {
		$plugin = $this->getPlugin();
		if ( $plugin->getSetting('ssl_host_subdomain') ) {
			$cookie_domain = '.' . $plugin->getHttpsUrl()->getBaseHost();
		} else {
			$cookie_domain = $plugin->getHttpsUrl()->getHost();
		}

		$cookie_path = COOKIEPATH;
		$cookie_path_site = SITECOOKIEPATH;
		$cookie_path_plugins = PLUGINS_COOKIE_PATH;

		if ( $plugin->getHttpsUrl()->getPath() != '/' ) {
			$cookie_path = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path);
			$cookie_path_site = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path_site);
			$cookie_path_plugins = str_replace($plugin->getHttpsUrl()->getPath(), '', $cookie_path_plugins);
		}

		if ( $plugin->getHttpUrl()->getPath() != '/' ) {
			$cookie_path = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path);
			$cookie_path_site = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path_site);
			$cookie_path_plugins = str_replace($plugin->getHttpUrl()->getPath(), '', $cookie_path_plugins);
		}

		$cookie_path = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path;
		$cookie_path_site = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path_site;
		$cookie_path_plugins = rtrim($plugin->getHttpsUrl()->getPath(), '/') . $cookie_path_plugins;
		$cookie_path_admin = $cookie_path_site . 'wp-admin';

		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin, $cookie_domain);
		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins, $cookie_domain);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin, $cookie_domain);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins, $cookie_domain);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path, $cookie_domain);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site, $cookie_domain);

		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site);
	}

}
