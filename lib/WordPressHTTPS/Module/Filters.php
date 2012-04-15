<?php
/**
 * Filters Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */
class WordPressHTTPS_Module_Filters extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

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

		// Filter site_url in admin panel
		if ( $this->getPlugin()->isSsl() ) {
			add_filter('template_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('stylesheet_directory_uri', array($this->getPlugin(), 'makeUrlHttps'), 10);
		}

		// Filter HTTPS from links in WP 3.0+
		if ( version_compare(get_bloginfo('version'), '3.0', '>') && !is_admin() && $this->getPlugin()->getHttpUrl()->getScheme() != 'https' ) {
			$filters = array('page_link', 'post_link', 'category_link', 'get_archives_link', 'tag_link', 'search_link');
			foreach( $filters as $filter ) {
				add_filter($filter, array($this->getPlugin(), 'makeUrlHttp'), 10);
			}

			add_filter('bloginfo', array(&$this, 'bloginfo'), 10, 2);
			add_filter('bloginfo_url', array(&$this, 'bloginfo'), 10, 2);

		// If the whole site is not HTTPS, set links to the front-end to HTTP from within the admin panel
		} else if ( is_admin() && $this->getPlugin()->isSsl() && $this->getPlugin()->getHttpUrl()->getScheme() != 'https' ) {
			$filters = array('page_link', 'post_link', 'category_link', 'get_archives_link', 'tag_link', 'search_link');
			foreach( $filters as $filter ) {
				add_filter($filter, array($this->getPlugin(), 'makeUrlHttp'), 10);
			}
		}

		// Change all page and post links to HTTPS in the admin panel when using different SSL Host
		if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_host_subdomain') == 0 && is_admin() && $this->getPlugin()->isSsl() ) {
			add_filter('page_link', array($this->getPlugin(), 'makeUrlHttps'), 10);
			add_filter('post_link', array($this->getPlugin(), 'makeUrlHttps'), 10);
		}
	}

	/**
	 * Runs when the plugin settings are reset.
	 *
	 * @param none
	 * @return void
	 */
	public function reset() {
		
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
				$avatar = str_replace('https', 'http', str_replace('http', 'https', $avatar));
			}
		}
		
		return $avatar;
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
		if ( ( $scheme == 'https' || $this->ssl_admin || ( ( is_admin() || $GLOBALS['pagenow'] == 'wp-login.php' ) && $this->getPlugin()->isSsl() ) ) && ( ! is_multisite() || ( is_multisite() && $url_parts['host'] == $this->getPlugin()->getHttpsUrl()->getHost() ) ) ) {
			$url = $this->getPlugin()->makeUrlHttps($url);
		}

		return $url;
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
			$result = $this->getPlugin()->makeUrlHttp($result);
		}
		return $result;
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

}