<?php 
/**
 * Admin Settings Module
 * 
 * Adds the settings page.
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 * 
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Admin_Settings extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * Initialize Module
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( is_admin() && isset($_GET['page']) && strpos($_GET['page'], $this->get('slug')) !== false ) {
			if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save' ) {
				add_action('plugins_loaded', array(&$this, 'save'), 1);
			}
			
			add_action('toplevel_page_' . $this->get('slug'), array(&$this, 'add_meta_boxes'));

			// Add scripts
			add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
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
	 * Add meta boxes to WordPress HTTPS Settings page.
	 *
	 * @param none
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			$this->get('slug') . '_settings',
			__( 'General Settings', $this->get('slug') ),
			array(&$this, 'meta_box_render'),
			'toplevel_page_' . $this->get('slug'),
			'main',
			'core',
			array( 'metabox' => 'settings' )
		);
		add_meta_box(
			$this->get('slug') . '_updates',
			__( 'Developer Updates', $this->get('slug') ),
			array(&$this, 'meta_box_render'),
			'toplevel_page_' . $this->get('slug'),
			'side',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://mvied.com/wphttps/updates.php' )
		);
		add_meta_box(
			$this->get('slug') . '_donate',
			__( 'Donate', $this->get('slug') ),
			array(&$this, 'meta_box_render'),
			'toplevel_page_' . $this->get('slug'),
			'side',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://mvied.com/wphttps/donate.php' )
		);
		add_meta_box(
			$this->get('slug') . '_support',
			__( 'Support', $this->get('slug') ),
			array(&$this, 'meta_box_render'),
			'toplevel_page_' . $this->get('slug'),
			'side',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://mvied.com/wphttps/support.php' )
		);
		add_meta_box(
			$this->get('slug') . '_donate2',
			__( 'Loading...', $this->get('slug') ),
			array(&$this, 'meta_box_render'),
			'toplevel_page_' . $this->get('slug'),
			'main',
			'core',
			array( 'metabox' => 'ajax', 'url' => 'http://mvied.com/wphttps/donate2.php' )
		);
	}

	/**
	 * Dispatch request for settings page
	 *
	 * @param none
	 * @return void
	 */
	public function dispatch() {
		if ( !current_user_can('manage_options') ) {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		$this->render();
	}

	/**
	 * Adds javascript and stylesheets to settings page in the admin panel.
	 * WordPress Hook - enqueue_scripts
	 *
	 * @param none
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style($this->get('slug') . '-admin-page', $this->get('plugin_url') . '/admin/css/settings.css', $this->get('version'), true);
		wp_enqueue_script('jquery-form');
		wp_enqueue_script('post');
		
		if ( function_exists('add_thickbox') ) {
			add_thickbox();
		}
	}

	/**
	 * Render settings page
	 *
	 * @param none
	 * @return void
	 */
	public function render() {
		require_once('admin/templates/settings.php');
	}
	
	/**
	 * Save Settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function save() {
		$errors = array();
		$reload = false;
		$logout = false;
		if ( @$_POST['Reset'] ) {
			$this->reset();
			$reload = true;
		} else {
			foreach ($this->get('settings') as $key => $default) {
				if ( !array_key_exists($key, $_POST) && $default == 0 ) {
					$_POST[$key] = 0;
					$this->updateSetting($key, $_POST[$key]);
				} else if ( array_key_exists($key, $_POST) ) {
					if ( $key == 'ssl_host' ) {
						if ( $_POST[$key] != '' ) {
							$_POST[$key] = strtolower($_POST[$key]);
							// Add Scheme
							if ( strpos($_POST[$key], 'http://') === false && strpos($_POST[$key], 'https://') === false ) {
								$_POST[$key] = 'https://' . $_POST[$key];
							}
							$ssl_host = $this->factory('Url')->fromString($_POST[$key]);

							// Add Port
							$port = ((isset($_POST['ssl_port']) && is_int($_POST['ssl_port']) ) ? $_POST['ssl_port'] : $ssl_host->port);
							$port = (($port != 80 && $port != 443) ? $port : null);
							$ssl_host->set('port', $port);

							// Add Path
							if ( strpos($ssl_host->toString(), $this->get('http_url')->get('path')) !== true ) {
								$ssl_host->set('path', rtrim($ssl_host->get('path'), '/') . $this->get('http_url')->get('path'));
							}

							if ( $ssl_host->toString() != $this->get('https_url')->toString() ) {
								// Ensure that the WordPress installation is accessible at this host
								if ( $ssl_host->isValid(true) ) {
									$this->log('[SETTINGS] Updated SSL Host: ' . $this->get('https_url') . ' => ' . $ssl_host);

									// If secure domain has changed and currently on SSL, logout user
									if ( $this->is_ssl() ) {
										$logout = true;
									}
									$_POST[$key] = $ssl_host->set('port', '');
								} else {
									$errors[] = '<strong>SSL Host</strong> - Invalid WordPress installation at ' . $ssl_host;
									$_POST[$key] = get_option($key);
								}
							} else {
								$_POST[$key] = $this->get('https_url');
							}
						} else {
							$_POST[$key] = get_option($key);
						}
					} else if ( $key == 'ssl_admin' ) {
						if ( force_ssl_admin() || force_ssl_login() ) {
							$errors[] = '<strong>SSL Admin</strong> - FORCE_SSL_ADMIN and FORCE_SSL_LOGIN can not be set to true in your wp-config.php.';
							$_POST[$key] = 0;
						// If forcing SSL Admin and currently not SSL, logout user
						} else if ( $_POST[$key] == 1 && !$this->is_ssl() ) {
							$logout = true;
						}
					} else if ( $key == 'ssl_host_subdomain' ) {
						// Checks to see if the SSL Host is a subdomain
						$http_domain = $this->get('http_url')->getBaseHost();
						$https_domain = $this->get('https_url')->getBaseHost();

						if ( $ssl_host->set('scheme', 'http') != $this->get('http_url') && $http_domain == $https_domain ) {
							$_POST[$key] = 1;
						} else {
							$_POST[$key] = 0;
						}
					}

					$this->updateSetting($key, $_POST[$key]);
				}
			}
		}

		if ( $logout ) {
			wp_logout();
		}

		if ( array_key_exists('ajax', $_POST) ) {
			error_reporting(0);
			while(@ob_end_clean());
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
	
}
