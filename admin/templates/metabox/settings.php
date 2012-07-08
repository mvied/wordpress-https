<?php
	$count = 1; // Used to restrict str_replace count
	$ssl_host = clone $this->getPlugin()->getHttpsUrl();
	$ssl_host = $ssl_host->setPort('')->setScheme('')->toString();
	if ( $this->getPlugin()->getHttpUrl()->getPath() != '/' ) {
		$ssl_host = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $ssl_host, $count);
	}
	$ssl_host = rtrim($ssl_host, '/');
?>
<form name="<?php echo $this->getPlugin()->getSlug(); ?>_settings_form" id="<?php echo $this->getPlugin()->getSlug(); ?>_settings_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php settings_fields($this->getPlugin()->getSlug()); ?>

<table class="form-table">
	<tr valign="top" id="ssl_host_row">
		<th scope="row">SSL Host</th>
		<td>
			<fieldset>
				<label for="ssl_host" id="ssl_host_label">
					<input name="ssl_host" type="text" id="ssl_host" class="regular-text code" value="<?php echo $ssl_host; ?>" />
				</label>
				<label for="ssl_port" id="ssl_port_label">Port
					<input name="ssl_port" type="text" id="ssl_port" class="small-text" value="<?php echo $this->getPlugin()->getSetting('ssl_port'); ?>" />
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="exclusive_https_row">
		<th scope="row">Force SSL Exclusively</th>
		<td>
			<fieldset>
				<label for="exclusive_https">
					<input type="hidden" name="exclusive_https" value="0" />
					<input name="exclusive_https" type="checkbox" id="exclusive_https" value="1"<?php echo (($this->getPlugin()->getSetting('exclusive_https')) ? ' checked="checked"' : ''); ?> />
					Posts and pages without <a href="<?php echo parse_url($this->getPlugin()->getPluginUrl(), PHP_URL_PATH); ?>/screenshot-2.png" class="thickbox">Force SSL</a> enabled will be redirected to HTTP.
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="ssl_admin_row">
		<th scope="row">Force SSL Administration</th>
		<td>
			<fieldset>
				<label for="ssl_admin">
					<input type="hidden" name="ssl_admin" value="0" />
					<input name="ssl_admin" type="checkbox" id="ssl_admin" value="1"<?php echo (($this->getPlugin()->getSetting('ssl_admin')) ? ' checked="checked"' : ''); ?><?php echo ((force_ssl_admin()) ? ' disabled="disabled" title="FORCE_SSL_ADMIN is true in wp-config.php"' : ''); ?> />
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="frontpage_row">
		<th scope="row">Secure Front Page</th>
		<td>
			<fieldset>
				<label for="frontpage">
					<input type="hidden" name="frontpage" value="0" />
					<input name="frontpage" type="checkbox" id="frontpage" value="1"<?php echo (($this->getPlugin()->getSetting('frontpage')) ? ' checked="checked"' : ''); ?> />
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="ssl_proxy_row">
		<th scope="row">Proxy</th>
		<td>
			<fieldset>
				<label for="ssl_proxy" class="label-radio">
					<input type="radio" name="ssl_proxy" value="0"<?php echo ((! $this->getPlugin()->getSetting('ssl_proxy')) ? ' checked="checked"' : ''); ?>> <span>No</span>
					<input type="radio" name="ssl_proxy" value="auto"<?php echo (($this->getPlugin()->getSetting('ssl_proxy') === 'auto') ? ' checked="checked"' : ''); ?>> <span>Auto</span>
					<input type="radio" name="ssl_proxy" value="1"<?php echo (($this->getPlugin()->getSetting('ssl_proxy') == 1) ? ' checked="checked"' : ''); ?>> <span>Yes</span>
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="debug_row">
		<th scope="row">Debug Mode</th>
		<td>
			<fieldset>
				<label for="debug">
					<input type="hidden" name="debug" value="0" />
					<input name="debug" type="checkbox" id="debug" value="1"<?php echo (($this->getPlugin()->getSetting('debug')) ? ' checked="checked"' : ''); ?> />
					Outputs debug information to the browser's console.
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="admin_menu_row">
		<th scope="row">Admin Menu Location</th>
		<td>
			<fieldset>
				<label for="admin_menu_side" class="label-radio">
					<input type="radio" name="admin_menu" id="admin_menu_side" value="side"<?php echo (($this->getPlugin()->getSetting('admin_menu') === 'side') ? ' checked="checked"' : ''); ?>> <span>Admin Sidebar</span>
				</label>
				<label for="admin_menu_settings" class="label-radio">
					<input type="radio" name="admin_menu" id="admin_menu_settings" value="settings"<?php echo (($this->getPlugin()->getSetting('admin_menu') === 'settings') ? ' checked="checked"' : ''); ?>> <span>General Settings</span>
				</label>
			</fieldset>
		</td>
	</tr>
</table>

<input type="hidden" name="action" value="save" />
<input type="hidden" name="ssl_host_subdomain" value="<?php echo (($this->getPlugin()->getSetting('ssl_host_subdomain') != 1) ? 0 : 1); ?>" />
<input type="hidden" name="ssl_host_diff" value="<?php echo (($this->getPlugin()->getSetting('ssl_host_diff') != 1) ? 0 : 1); ?>" />

<p class="button-controls">
	<input type="submit" name="settings-save" value="Save Changes" class="button-primary" id="settings-save" />
	<input type="submit" name="settings-reset" value="Reset" class="button-secondary" id="settings-reset" />
	<img alt="Waiting..." src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting submit-waiting" />
</p>
</form>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#<?php echo $this->getPlugin()->getSlug(); ?>_settings_form').submit(function() {
		$('#<?php echo $this->getPlugin()->getSlug(); ?>_settings_form .submit-waiting').show();
	}).ajaxForm({
		data: { ajax: '1'},
		success: function(responseText, textStatus, XMLHttpRequest) {
			$('#<?php echo $this->getPlugin()->getSlug(); ?>_settings_form .submit-waiting').hide();
			$('#message-body').html(responseText).fadeOut(0).fadeIn().delay(5000).fadeOut();
		}
	});

	$('#settings-reset').click(function(e, el) {
	   if ( ! confirm('Are you sure you want to reset all WordPress HTTPS settings?') ) {
			e.preventDefault();
			return false;
	   }
	});
});
</script>