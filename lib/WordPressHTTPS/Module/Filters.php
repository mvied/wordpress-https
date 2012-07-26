<?php
/**
 * Filters Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Module_Filters extends Mvied_Plugin_Module implements Mvied_Plugin_Module_Interface {

	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->isSsl() ) {
			// Prevent WordPress' canonical redirect when using a different SSL Host
			remove_filter('template_redirect', 'redirect_canonical');
			// Add SSL Host path to rewrite rules
			add_filter('rewrite_rules_array', array(&$this, 'rewrite_rules'), 10, 1);
		}

		// Add SSL Host to allowed redirect hosts
		add_filter('allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10, 1);

		// Filter get_avatar
		add_filter('get_avatar', array(&$this, 'get_avatar'), 10, 5);

		// Filter URL's
		add_filter('bloginfo_url', array(&$this, 'secure_url'), 10);
		add_filter('includes_url', array(&$this, 'secure_url'), 10);
		add_filter('plugins_url', array(&$this, 'secure_url'), 10);
		add_filter('logout_url', array(&$this, 'secure_url'), 10);
		add_filter('login_url', array(&$this, 'secure_url'), 10);
		add_filter('wp_get_attachment_url', array(&$this, 'secure_url'), 10);
		add_filter('template_directory_uri', array(&$this, 'secure_url'), 10);
		add_filter('stylesheet_directory_uri', array(&$this, 'secure_url'), 10);
		add_filter('network_admin_url', array(&$this, 'secure_url'), 10);

		// Custom filter https_external_url
		add_filter('https_external_url', array(&$this, 'domain_mapping'), 10);

		// Filter admin_url
		add_filter('admin_url', array(&$this, 'admin_url'), 10, 3);

		// Filter site_url
		add_filter('site_url', array(&$this, 'site_url'), 10, 4);

		// Filter force_ssl
		add_filter('force_ssl', array(&$this, 'secure_wordpress_forms'), 20, 3);
		add_filter('force_ssl', array(&$this, 'secure_different_host_admin'), 20, 3);
		add_filter('force_ssl', array(&$this, 'secure_child_post'), 30, 3);
		add_filter('force_ssl', array(&$this, 'secure_post'), 40, 3);
		add_filter('force_ssl', array(&$this, 'secure_exclusive'), 50, 3);

		$filters = array('page_link', 'preview_page_link', 'post_link', 'preview_page_link', 'post_type_link', 'attachment_link', 'day_link', 'month_link', 'year_link', 'comment_reply_link', 'category_link', 'author_link', 'archives_link', 'tag_link', 'search_link');
		foreach( $filters as $filter ) {
			add_filter($filter, array(&$this, 'secure_post_link'), 10);
		}
	}

	/**
	 * Admin URL
	 * WordPress Filter - admin_url
	 *
	 * @param string $url
	 * @param string $path
	 * @param int $blog_id
	 * @return string $url
	 */
	public function admin_url( $url, $path, $blog_id ) {
		if ( ( $this->getPlugin()->getSetting('ssl_admin') || ( ( is_admin() || ( isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'wp-login.php') ) && $this->getPlugin()->isSsl() ) ) && ( ! is_multisite() || ( is_multisite() && parse_url($url, PHP_URL_HOST) == $this->getPlugin()->getHttpsUrl()->getHost() ) ) ) {
			$url = $this->getPlugin()->makeUrlHttps($url);
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
		if ( $scheme == 'https' || ( $scheme != 'http' && $this->getPlugin()->isSsl() ) ) {
			$url = $this->getPlugin()->makeUrlHttps($url);
		}
		return $url;
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
	 * Domain Mapping
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function domain_mapping( $url ) {
		if ( is_array($this->getPlugin()->getSetting('ssl_host_mapping')) && sizeof($this->getPlugin()->getSetting('ssl_host_mapping')) > 0 ) {
			foreach( $this->getPlugin()->getSetting('ssl_host_mapping') as $http_domain => $https_domain ) {
				preg_match('/' . $http_domain . '/', $url, $matches);
				if ( sizeof($matches) > 0 ) {
					$url = preg_replace('/' . $http_domain . '/', $https_domain, $url);
				}
			}
		}
		return $url;
	}

	/**
	 * Get Avatar
	 * WordPress Filter - get_avatar
	 *
	 * @param string $avatar
	 * @param string $id_or_email
	 * @param int $size
	 * @param string $alt
	 * @return string $avatar
	 */
	public function get_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		if ( $this->getPlugin()->isSsl() ) {
			// Set host to https://secure.gravatar.com
			if ( $avatar = preg_replace('/\d\.gravatar\.com/', 'secure.gravatar.com', $avatar) ) {
				$avatar = str_replace('http', 'https', str_replace('https', 'http', $avatar));
			}
		}
		
		return $avatar;
	}

	/**
	 * Secure URL
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function secure_url( $url = '' ) {
		if ( $this->getPlugin()->isSsl() || ( $this->getPlugin()->getSetting('ssl_admin') && ( strpos($url, 'wp-admin') !== false || strpos($url, 'wp-login') !== false ) ) ) {
			$url = rtrim($this->getPlugin()->makeUrlHttps(rtrim($url, '/') . '/'), '/');
		} else if ( strpos(get_option('home'), 'https') !== 0 ) {
			$url = rtrim($this->getPlugin()->makeUrlHttp(rtrim($url, '/') . '/'), '/');
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
		$requestPath = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $_SERVER['REQUEST_URI']);
		if ( $this->getPlugin()->getHttpUrl()->getPath() != '/' ) {
			$httpsPath = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $this->getPlugin()->getHttpsUrl()->getPath());
		} else {
			$httpsPath = $this->getPlugin()->getHttpsUrl()->getPath();
		}
		if ( $httpsPath != '/' ) {
			$rules['^'  . $httpsPath . '([^\'"]+)'] = 'index.php?pagename=$matches[1]';
		}
		return $rules;
	}

	/**
	 * Secure Post Link
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function secure_post_link( $url ) {
		$force_ssl = apply_filters('force_ssl', null, 0, $url);
		if ( $force_ssl ) {
			$url = $this->getPlugin()->makeUrlHttps($url);
		} else if ( $this->getPlugin()->getSetting('exclusive_https') ) {
			$url = $this->getPlugin()->makeUrlHttp($url);
		}
		return $url;
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

		if ( $url != '' ) {
			$url_parts = parse_url($url);
			if ( $this->getPlugin()->isUrlLocal($url) ) {
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

				// Check secure filters
				if ( sizeof((array)$this->getPlugin()->getSetting('secure_filter')) > 0 ) {
					foreach( $this->getPlugin()->getSetting('secure_filter') as $filter ) {
						if ( preg_match('/' . str_replace('/', '\/', $filter) . '/', $url) === 1 ) {
							$force_ssl = true;
						}
					}
				}

				if ( isset($post_id) && $post_id > 0 ) {
					$post = $post_id;
				} else if ( preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
					$post = $postID[1];
				} else if ( $url_parts['path'] == '' || $url_parts['path'] == '/' ) {
					if ( get_option('show_on_front') == 'page' ) {
						$post = get_option('page_on_front');
					}
				} else if ( $post = get_page_by_path($url_parts['path']) ) {
					$post = $post->ID;
				//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
				} else if ( ( $this->getPlugin()->isSsl() || $this->getPlugin()->getSetting('ssl_admin') ) && ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) ) {
					$force_ssl = true;
				}
			}

			if ( is_multisite() ) {
				$url_path = '/';
				$url_path_segments = explode('/', $url_parts['path']);
				if ( sizeof($url_path_segments) > 1 ) {
					foreach( $url_path_segments as $url_path_segment ) {
						if ( !$blog_id && $url_path_segment != '' ) {
							$url_path .= '/' . $url_path_segment . '/';
							if ( $blog_id = get_blog_id_from_url( $url_parts['host'], $url_path) ) {
								break;
							}
						}
					}
				}
				if ( !$blog_id ) {
					$blog_id = get_blog_id_from_url( $url_parts['host'], '/');
				}

				if ( $blog_id && $blog_id != $wpdb->blogid ) {
					if ( $this->getPlugin()->getSetting('ssl_admin', $blog_id) && ( ! $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) || ( $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) && is_user_logged_in() ) ) ) {
						$force_ssl = true;
					} else {
						$force_ssl = false;
					}
				}
			}
		}
		if ( isset($post) && (int) $post > 0 ) {
			$force_ssl = (( get_post_meta($post, 'force_ssl', true) == 1 ) ? true : $force_ssl);
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
		if ( is_null($force_ssl) && strpos(get_option('home'), 'https') !== 0 && $this->getPlugin()->getSetting('exclusive_https') ) {
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
		if ( $post_id > 0 || ( $url != '' && $this->getPlugin()->isUrlLocal($url) ) ) {
			if ( ! $this->getPlugin()->getSetting('exclusive_https') && ! $this->getPlugin()->getSetting('ssl_host_subdomain') && $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && is_user_logged_in() ) {
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
		if ( $this->getPlugin()->isSsl() && $this->getPlugin()->isUrlLocal($url) && ( strpos($url, 'wp-pass.php') !== false || strpos($url, 'wp-comments-post.php') !== false ) ) {
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

}