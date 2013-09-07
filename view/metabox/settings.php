<?php

if ( !defined('ABSPATH') ) exit;

$count = 1; // Used to restrict str_replace count
$ssl_host = clone $this->getHttpsUrl();
$ssl_host = $ssl_host->setScheme('')->toString();
if ( $this->getHttpUrl()->getPath() != '/' ) {
	$ssl_host = str_replace($this->getHttpUrl()->getPath(), '', $ssl_host, $count);
}
$ssl_host = rtrim($ssl_host, '/');
?>
<form name="<?php echo $this->getSlug(); ?>_settings_form" id="<?php echo $this->getSlug(); ?>_settings_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php wp_nonce_field($this->getSlug()); ?>
<input type="hidden" name="action" id="action" value="" />

<table class="form-table">
	<tr valign="top" id="ssl_host_row">
		<th scope="row"><?php _e('SSL Host','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="ssl_host" id="ssl_host_label">
					<input name="ssl_host" type="text" id="ssl_host" class="regular-text code" value="<?php echo $ssl_host; ?>" />
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="ssl_admin_row">
		<th scope="row"><?php _e('Force SSL Administration','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="ssl_admin">
					<input type="hidden" name="ssl_admin" value="<?php echo ((force_ssl_admin() && $this->getSetting('ssl_admin') == 1) ? 1 : 0); ?>" />
					<input name="ssl_admin" type="checkbox" id="ssl_admin" value="1"<?php echo ((force_ssl_admin()) ? ' checked="checked" disabled="disabled" title="FORCE_SSL_ADMIN is true in wp-config.php"' : (($this->getSetting('ssl_admin')) ? ' checked="checked"' : '') ); ?> />
					<p class="description"><?php printf( __('Always use HTTPS while in the admin panel. This setting is identical to %s.','wordpress-https'),'<a href="http://codex.wordpress.org/Administration_Over_SSL#Example_2" target="_blank">' . 'FORCE_SSL_ADMIN' . '</a>'); ?></p>
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="exclusive_https_row">
		<th scope="row"><?php _e('Force SSL Exclusively','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="exclusive_https">
					<input type="hidden" name="exclusive_https" value="0" />
					<input name="exclusive_https" type="checkbox" id="exclusive_https" value="1"<?php echo (($this->getSetting('exclusive_https')) ? ' checked="checked"' : ''); ?> />
					<p class="description"><?php printf( __('Any page that is not secured via %s or URL Filters will be redirected to HTTP.','wordpress-https'),'<a href="' . parse_url($this->getPluginUrl(), PHP_URL_PATH) . '/screenshot-2.png" class="thickbox">' . __('Force SSL','wordpress-https') . '</a>'); ?></a></p>
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="remove_unsecure_row">
		<th scope="row"><?php _e('Remove Unsecure Elements','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="remove_unsecure">
					<input type="hidden" name="remove_unsecure" value="0" />
					<input name="remove_unsecure" type="checkbox" id="remove_unsecure" value="1"<?php echo (($this->getSetting('remove_unsecure')) ? ' checked="checked"' : ''); ?> />
					<p class="description"><?php _e("Remove elements inaccessible over HTTPS. May break other plugins' functionality.",'wordpress-https'); ?></p>
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="debug_row">
		<th scope="row"><?php _e('Debug Mode','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="debug">
					<input type="hidden" name="debug" value="0" />
					<input name="debug" type="checkbox" id="debug" value="1"<?php echo (($this->getSetting('debug')) ? ' checked="checked"' : ''); ?> />
					<p class="description"><?php _e('Outputs debug information to the browser\'s console.','wordpress-https'); ?></p>
				</label>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="ssl_proxy_row">
		<th scope="row"><?php _e('Proxy','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="ssl_proxy" class="label-radio">
					<input type="radio" name="ssl_proxy" value="0"<?php echo ((! $this->getSetting('ssl_proxy')) ? ' checked="checked"' : ''); ?>> <span><?php _e('No'); ?></span>
					<input type="radio" name="ssl_proxy" value="auto"<?php echo (($this->getSetting('ssl_proxy') === 'auto') ? ' checked="checked"' : ''); ?>> <span><?php _e('Auto','wordpress-https'); ?></span>
					<input type="radio" name="ssl_proxy" value="1"<?php echo (($this->getSetting('ssl_proxy') == 1) ? ' checked="checked"' : ''); ?>> <span><?php _e('Yes'); ?></span>
				</label>
				<p class="description"><?php _e('If you think you may behind a proxy, set to Auto. Otherwise, leave the setting on No.','wordpress-https'); ?></p>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" id="admin_menu_row">
		<th scope="row"><?php _e('Admin Menu Location','wordpress-https'); ?></th>
		<td>
			<fieldset>
				<label for="admin_menu_side" class="label-radio">
					<input type="radio" name="admin_menu" id="admin_menu_side" value="side"<?php echo (($this->getSetting('admin_menu') === 'side') ? ' checked="checked"' : ''); ?>> <span><?php _e('Sidebar','wordpress-https'); ?></span>
				</label>
				<label for="admin_menu_settings" class="label-radio">
					<input type="radio" name="admin_menu" id="admin_menu_settings" value="settings"<?php echo (($this->getSetting('admin_menu') === 'settings') ? ' checked="checked"' : ''); ?>> <span><?php _e('Settings','wordpress-https'); ?></span>
				</label>
			</fieldset>
		</td>
	</tr>
</table>

<input type="hidden" name="ssl_host_subdomain" value="<?php echo (($this->getSetting('ssl_host_subdomain') != 1) ? 0 : 1); ?>" />
<input type="hidden" name="ssl_host_diff" value="<?php echo (($this->getSetting('ssl_host_diff') != 1) ? 0 : 1); ?>" />

<p class="button-controls">
	<input type="submit" name="settings-save" value="<?php _e('Save Changes'); ?>" class="button-primary" id="settings-save" />
	<input type="submit" name="settings-reset" value="<?php _e('Reset'); ?>" class="button-secondary" id="settings-reset" />
	<input type="submit" name="settings-cache-reset" value="<?php _e('Purge Cache','wordpress-https'); ?>" class="button-secondary" id="settings-cache-reset" />
	<img alt="<?php _e('Waiting...','wordpress-https'); ?>" src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting submit-waiting" />
</p>
</form>
<script type="text/javascript">
jQuery(document).ready(function($) {
	var form = $('#<?php echo $this->getSlug(); ?>_settings_form').first();
	var ssl_host_value = $('#ssl_host').val();
	$('#settings-save').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_settings_save');
	});
	$('#settings-cache-reset').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_settings_cache_reset');
	});
	$('#settings-reset').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_settings_reset');
	});
	$(form).submit(function(e) {
		e.preventDefault();
		if ( $('#ssl_host').val() != ssl_host_value && ! confirm("<?php _e("Are you sure you want to set your SSL Host to '%s'? An incorrect value here may render your admin dashboard unusable until you reset the plugin's settings following the directions in the FAQ.",'wordpress-https'); ?>".replace('%s', $('#ssl_host').val())) ) {
			e.preventDefault();
			return false;
		}
		$(form).find('.submit-waiting').show();
		$.post(ajaxurl, $(form).serialize(), function(response) {
			$(form).find('.submit-waiting').hide();
			$('#message-body').html(response).fadeOut(0).fadeIn().delay(5000).fadeOut();
		});
	});

	$('#settings-reset').click(function(e, el) {
		if ( ! confirm('<?php _e('Are you sure you want to reset all WordPress HTTPS settings?','wordpress-https'); ?>') ) {
			e.preventDefault();
			return false;
		}
	});
});
</script>