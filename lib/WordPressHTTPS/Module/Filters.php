<?php
/**
 * Filters Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('Mvied/Module.php');
require_once('Mvied/Module/Interface.php');

class WordPressHTTPS_Module_Filters extends Mvied_Module implements Mvied_Module_Interface {

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
		}
		
		// Add SSL Host to allowed redirect hosts
		add_filter('allowed_redirect_hosts' , array(&$this, 'allowed_redirect_hosts'), 10, 1);
	
		// Filter get_avatar
		add_filter('get_avatar', array(&$this, 'get_avatar'), 10, 5);
		
		// Filter admin_url
		add_filter('admin_url', array(&$this, 'admin_url'), 10, 3);
		
		// Filter force_ssl
		add_filter('force_ssl', array(&$this, 'secure_child_post'), 10, 2);
		add_filter('force_ssl', array(&$this, 'secure_post'), 9, 2);

		// Filter URL's on SSL pages
		if ( $this->getPlugin()->isSsl() ) {
			add_filter('site_url', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('template_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('stylesheet_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
		}

		// Filter HTTPS from links
		if ( ! is_admin() && WordPressHTTPS_Url::fromString(get_option('home'))->getScheme() != 'https' ) {
			$filters = array('page_link', 'post_link', 'category_link', 'archives_link', 'tag_link', 'search_link');
			foreach( $filters as $filter ) {
				add_filter($filter, array($this->getPlugin(), 'makeUrlHttp'), 10);
			}

			add_filter('bloginfo', array(&$this, 'bloginfo'), 10, 2);
			add_filter('bloginfo_url', array(&$this, 'bloginfo'), 10, 2);

		// If the whole site is not HTTPS, set links to the front-end to HTTP from within the admin panel
		} else if ( is_admin() && $this->getPlugin()->getSetting('ssl_admin') == 0 && $this->getPlugin()->isSsl() && WordPressHTTPS_Url::fromString(get_option('home'))->getScheme() != 'https' ) {
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
			if ( WordPressHTTPS_Url::fromString(get_option('home'))->getScheme() != 'https' ) {
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
	 * Secure Post
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @return boolean $force_ssl
	 */
	public function secure_post( $force_ssl, $post_id ) {
		if ( is_numeric($post_id) ) {
			$force_ssl = (( get_post_meta($post_id, 'force_ssl', true) == 1 ) ? true : $force_ssl);
		}
		return $force_ssl;
	}

	/**
	 * Secure Child Post
	 * WordPress HTTPS Filter - force_ssl
	 *
	 * @param boolean $force_ssl
	 * @param int $post_id
	 * @return boolean $force_ssl
	 */
	public function secure_child_post( $force_ssl, $post_id ) {
		if ( is_numeric($post_id) ) {
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