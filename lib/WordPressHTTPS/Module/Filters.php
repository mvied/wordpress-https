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
		// Prevent WordPress' canonical redirect when using a different SSL Host
		if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->isSsl() ) {
			remove_filter('template_redirect', 'redirect_canonical');

			// Filter SSL Host path out of request
			add_filter('request', array(&$this, 'request'), 10, 1);
		}
		
		// Add SSL Host to allowed redirect hosts
		add_filter('allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10, 1);

		// Filter get_avatar
		add_filter('get_avatar', array(&$this, 'get_avatar'), 10, 5);
		
		// Filter admin_url
		add_filter('admin_url', array(&$this, 'admin_url'), 10, 3);
		
		// Filter force_ssl
		add_filter('force_ssl', array(&$this, 'secure_child_post'), 10, 3);
		add_filter('force_ssl', array(&$this, 'secure_different_host_admin'), 9, 3);
		add_filter('force_ssl', array(&$this, 'secure_post'), 8, 3);
		add_filter('force_ssl', array(&$this, 'secure_exclusive'), 1, 3);

		// Filter URL's on SSL pages
		if ( $this->getPlugin()->isSsl() ) {
			add_filter('site_url', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('template_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('stylesheet_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
		}

		// Filter HTTPS from links
		if ( ! is_admin() && strpos(get_option('home'), 'https') !== 0 ) {
			$filters = array('page_link', 'post_link', 'category_link', 'archives_link', 'tag_link', 'search_link');
			foreach( $filters as $filter ) {
				add_filter($filter, array($this->getPlugin(), 'makeUrlHttp'), 10);
			}

			add_filter('bloginfo', array(&$this, 'bloginfo'), 10, 2);
			add_filter('bloginfo_url', array(&$this, 'bloginfo'), 10, 2);

		// If the whole site is not HTTPS, set links to the front-end to HTTP from within the admin panel
		} else if ( is_admin() && $this->getPlugin()->getSetting('ssl_admin') == 0 && $this->getPlugin()->isSsl() && strpos(get_option('home'), 'https') !== 0 ) {
			$filters = array('page_link', 'post_link', 'category_link', 'get_archives_link', 'tag_link', 'search_link');
			foreach( $filters as $filter ) {
				add_filter($filter, array($this->getPlugin(), 'makeUrlHttp'), 10);
			}
		}

		// Change all page and post links to HTTPS in the admin panel when using different SSL Host
		if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_host_subdomain') == 0 && is_admin() && $this->getPlugin()->isSsl() ) {
			add_filter('page_link', array($this->getPlugin(), 'makeUrlHttps'), 9);
			add_filter('post_link', array($this->getPlugin(), 'makeUrlHttps'), 9);
		}
	}

	/**
	 * Admin URL
	 * WordPress Filter - admin_url
	 *
	 * @param string $url
	 * @param string $path
	 * @param string $scheme
	 * @return string $url
	 */
	public function admin_url( $url, $path, $scheme ) {
		if ( ( $scheme == 'https' || $this->getPlugin()->getSetting('ssl_admin') || ( ( is_admin() || $GLOBALS['pagenow'] == 'wp-login.php' ) && $this->getPlugin()->isSsl() ) ) && ( ! is_multisite() || ( is_multisite() && parse_url($url, PHP_URL_HOST) == $this->getPlugin()->getHttpsUrl()->getHost() ) ) ) {
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
	 * Blog Info
	 * WordPress Filter - get_bloginfo, bloginfo
	 *
	 * @param string $result
	 * @param string $show
	 * @return string $result
	 */
	public function bloginfo( $result = '', $show = '' ) {
		if ( $show == 'stylesheet_url' || $show == 'template_url' || $show == 'wpurl' || $show == 'home' || $show == 'siteurl' || $show == 'Url' ) {
			if ( strpos(get_option('home'), 'https') !== 0 ) {
				$result = $this->getPlugin()->makeUrlHttp($result);
			}
		}
		return $result;
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
	 * Filter Request
	 * WordPress Filter - request
	 *
	 * @param array $request
	 * @return array $request
	 */
	public function request( $request ) {
	    $request['pagename'] = str_replace(trim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . '/', '', $request['pagename']);
	    return $request;
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
		if ( $url != '' ) {
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
				if ( get_option('show_on_front') == 'page' ) {
					$post = get_option('page_on_front');
				}
				if ( $this->getPlugin()->getSetting('frontpage') ) {
					$force_ssl = true;
				}
			} else if ( $this->getPlugin()->isUrlLocal($url) && ($post = get_page_by_path($url_parts['path'])) ) {
				$post = $post->ID;
			//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
			} else if ( ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) && ( $this->getPlugin()->isSsl() || $this->getPlugin()->getSetting('ssl_admin') ) ) {
				if ( ! is_multisite() || ( is_multisite() && strpos($url_parts['host'], $this->getPlugin()->getHttpsUrl()->getHost()) !== false ) ) {
					$force_ssl = true;
				} else if ( is_multisite() ) {
					// get_blog_details returns an object with a property of blog_id
					if ( $blog_details = get_blog_details( array( 'domain' => $url_parts['host'] )) ) {
						// set $blog_id using $blog_details->blog_id
						$blog_id = $blog_details->blog_id;
						if ( $this->getPlugin()->getSetting('ssl_admin', $blog_id) && $url_parts['scheme'] != 'https' && ( ! $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) || ( $this->getPlugin()->getSetting('ssl_host_diff', $blog_id) && is_user_logged_in() ) ) ) {
							$force_ssl = true;
						}
					}
				}
			}
		}
		if ( (int) $post > 0 ) {
			$force_ssl = (( get_post_meta($post_id, 'force_ssl', true) == 1 ) ? true : $force_ssl);
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
		if ( is_null($force_ssl) && strpos(get_option('home'), 'https') != 0 && $this->getPlugin()->getSetting('exclusive_https') ) {
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
		if ( ! $this->getPlugin()->getSetting('ssl_host_subdomain') && $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && is_user_logged_in() ) {
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