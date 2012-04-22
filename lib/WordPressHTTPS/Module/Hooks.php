<?php
/**
 * Hooks Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Hooks extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( $this->getPlugin()->getSetting('ssl_host_diff') ) {
			// Remove SSL Host authentication cookies on logout
			add_action('clear_auth_cookie', array(&$this, 'clear_cookies'));

			// Set authentication cookie
			if ( $this->getPlugin()->isSsl() ) {
				add_action('set_auth_cookie', array(&$this, 'set_cookie'), 10, 5);
				add_action('set_logged_in_cookie', array(&$this, 'set_cookie'), 10, 5);
			}

			// Filter redirects in admin panel
			if ( is_admin() && $this->getPlugin()->getSetting('ssl_admin') ) {
				add_action('wp_redirect', array($this->getPlugin(), 'redirectAdmin'), 10, 1);
			}
		}

		// Run proxy check
		if ( $this->getPlugin()->getSetting('ssl_proxy') === 'auto' ) {
			// If page is not SSL and no proxy cookie is detected, run proxy check
			if ( ! $this->getPlugin()->isSsl() && ! isset($_COOKIE['wp_proxy']) ) {
				add_action('init', array(&$this, 'proxy_check'), 1);
				add_action('admin_init', array(&$this, 'proxy_check'), 1);
			// Update ssl_proxy setting if a proxy has been detected
			} else if ( $this->getPlugin()->getSetting('ssl_proxy') !== true && isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] == 1 ) {
				$this->getPlugin()->setSetting('ssl_proxy', 1);
			// Update ssl_proxy if proxy is no longer detected
			} else if ( $this->getPlugin()->getSetting('ssl_proxy') !== false && isset($_COOKIE['wp_proxy']) && $_COOKIE['wp_proxy'] != 1 ) {
				$this->getPlugin()->setSetting('ssl_proxy', 0);
			}
		}

		// Check if the page needs to be redirected
		add_action('template_redirect', array(&$this, 'redirect_check'));
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
		
		if ( ! (is_single() || is_page() || is_front_page() || is_home()) ) {
			return false;
		}
		
		if ( $post->ID > 0 ) {
			$force_ssl = apply_filters('force_ssl', null, $post->ID );
		}
		
		// Secure Front Page
		if ( is_front_page() ) {
			if ( $this->getPlugin()->getSetting('frontpage') ) {
				$force_ssl = true;
			} else if ( ! $this->getPlugin()->getSetting('frontpage') && $this->getPlugin()->getSetting('exclusive_https') && ( ! $this->getPlugin()->getSetting('ssl_host_diff') || ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && ! is_user_logged_in() ) ) ) {
				$force_ssl = false;
			}
		}

		// Exclusive HTTPS
		if ( $this->getPlugin()->getSetting('exclusive_https') && $this->getPlugin()->isSsl() && ! isset($force_ssl) ) {
			$force_ssl = false;
		}

		// Force SSL Admin
		if ( is_admin() && $this->getPlugin()->getSetting('ssl_admin') && ! $this->getPlugin()->isSsl() ) {
			$force_ssl = true;
		}
					
		if ( ! $this->getPlugin()->isSsl() && isset($force_ssl) && $force_ssl ) {
			$scheme = 'https';
		} else if ( $this->getPlugin()->isSsl() && isset($force_ssl) && ! $force_ssl ) {
			$scheme = 'http';
		}
		

		if ( isset($scheme) ) {
			$this->getPlugin()->redirect($scheme);
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
		if ( ( $scheme == 'secure_auth' && $this->getPlugin()->isSsl() ) || ( $this->getPlugin()->getSetting('ssl_admin') && ! $this->getPlugin()->getSetting('ssl_host_subdomain') ) ) {
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
		}

		//$cookie_domain = COOKIE_DOMAIN;
		$cookie_path = COOKIEPATH;
		$cookie_path_site = SITECOOKIEPATH;
		$cookie_path_plugins = PLUGINS_COOKIE_PATH;
		$cookie_path_admin = ADMIN_COOKIE_PATH;

		if ( $this->getPlugin()->isSsl() ) {
			// If SSL Host is a subdomain, make cookie domain a wildcard
			if ( $this->getPlugin()->getSetting('ssl_host_subdomain') ) {
				$cookie_domain = '.' . $this->getPlugin()->getHttpsUrl()->getBaseHost();
			// Otherwise, cookie domain set for different SSL Host
			} else {
				$cookie_domain = $this->getPlugin()->getHttpsUrl()->getHost();
			}

			if ( $this->getPlugin()->getHttpsUrl()->getPath() != '/' ) {
				$cookie_path = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path);
				$cookie_path_site = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_site);
				$cookie_path_plugins = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_plugins);
			}
			
			if ( $this->getPlugin()->getHttpUrl()->getPath() != '/' ) {
				$cookie_path = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path);
				$cookie_path_site = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path_site);
				$cookie_path_plugins = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path_plugins);
			}

			$cookie_path = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path;
			$cookie_path_site = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path_site;
			$cookie_path_plugins = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path_plugins;
			$cookie_path_admin = $cookie_path_site . 'wp-admin';
		}

		// Cookie paths defined to accomodate different SSL Host
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
	 * Clear Cookies
	 * WordPress Hook - clear_auth_cookie
	 *
	 * @param none
	 * @return void
	 */
	public function clear_cookies() {
		if ( $this->getPlugin()->getSetting('ssl_host_subdomain') ) {
			$cookie_domain = '.' . $this->getPlugin()->getHttpsUrl()->getBaseHost();
		} else {
			$cookie_domain = $this->getPlugin()->getHttpsUrl()->getHost();
		}

		$cookie_path = COOKIEPATH;
		$cookie_path_site = SITECOOKIEPATH;
		$cookie_path_plugins = PLUGINS_COOKIE_PATH;

		if ( $this->getPlugin()->getHttpsUrl()->getPath() != '/' ) {
			$cookie_path = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path);
			$cookie_path_site = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_site);
			$cookie_path_plugins = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_plugins);
		}
		
		if ( $this->getPlugin()->getHttpUrl()->getPath() != '/' ) {
			$cookie_path = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path);
			$cookie_path_site = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path_site);
			$cookie_path_plugins = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $cookie_path_plugins);
		}

		$cookie_path = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path;
		$cookie_path_site = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path_site;
		$cookie_path_plugins = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . $cookie_path_plugins;
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