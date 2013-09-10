<?php
if ( !defined('ABSPATH') ) exit;
$http_url = clone $this->getHttpUrl();
$http_url = rtrim($http_url->setScheme('')->toString(), '/');
$https_url = clone $this->getHttpsUrl();
$https_url = rtrim($https_url->setScheme('')->toString(), '/');
?>
<form name="<?php echo $this->getSlug(); ?>_url_mapping_form" id="<?php echo $this->getSlug(); ?>_url_mapping_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php wp_nonce_field($this->getSlug()); ?>
<input type="hidden" name="action" id="action" value="" />

<p><?php printf( __('URL Mapping allows you to map urls that host their HTTPS content on a different url. You may use %s for the URL on the left side of the mapping.','wordpress-https'),'<a href="#TB_inline?height=155&width=350&inlineId=regex-help" class="thickbox" title="' . __('Regular Expressions Help','wordpress-https') . '">'.__('Regular Expressions','wordpress-https').'</a>') ; ?></p>

<table class="form-table" id="url_mapping">
	<thead>
	</thead>
	<tbody>
	<tr valign="top" class="url_mapping_row url_mapping_row">
		<td class="scheme">
			<select name="http_scheme" disabled="disabled">
				<option>http</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input id="http_url" type="text" name="http_host" value="<?php echo $http_url ?>" disabled="disabled" title="<?php echo _e('You can modify this mapping by changing your Home URL.','wordpress-https'); ?>" />
		</td>
		<td class="sep url-sep">
			<span class="label">&raquo;</span>
		</td>
		<td class="scheme">
			<select name="https_scheme" disabled="disabled">
				<option>https</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input id="https_url" type="text" name="https_host" value="<?php echo $https_url ?>" disabled="disabled" title="<?php echo _e('You can modify this mapping by changing your SSL Host in the settings above.','wordpress-https'); ?>" />
		</td>
		<td class="controls">
		</td>
	</tr>
<?php
	$ssl_host_mapping = ( is_array($this->getSetting('ssl_host_mapping')) ? $this->getSetting('ssl_host_mapping') : array() );
	if ( sizeof($ssl_host_mapping) > 0 ) {
		foreach( $ssl_host_mapping as $mapping ) {
			if ( !is_array($mapping) ) {
				continue;
			}
?>
	<tr valign="top" class="url_mapping_row">
		<td class="scheme">
			<select name="url_mapping[scheme][]">
				<option<?php echo isset($mapping[0]['scheme']) && $mapping[0]['scheme'] == 'http' ? ' selected="selected"' : ''; ?>>http</option>
				<option<?php echo isset($mapping[0]['scheme']) && $mapping[0]['scheme'] == 'https' ? ' selected="selected"' : ''; ?>>https</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input type="text" name="url_mapping[host][]" value="<?php echo @$mapping[0]['host']; ?>" />
		</td>
		<td class="sep url-sep">
			<span class="label">&raquo;</span>
		</td>
		<td class="scheme">
			<select name="url_mapping[scheme][]">
				<option<?php echo isset($mapping[1]['scheme']) && $mapping[1]['scheme'] == 'https' ? ' selected="selected"' : ''; ?>>https</option>
				<option<?php echo isset($mapping[1]['scheme']) && $mapping[1]['scheme'] == 'http' ? ' selected="selected"' : ''; ?>>http</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input type="text" name="url_mapping[host][]" value="<?php echo @$mapping[1]['host']; ?>" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Mapping','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Mapping','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>

<?php
		}
	}
?>
	<tr valign="top" class="url_mapping_row">
		<td class="scheme">
			<select name="url_mapping[scheme][]">
				<option>http</option>
				<option>https</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input type="text" name="url_mapping[host][]" value="" />
		</td>
		<td class="sep url-sep">
			<span class="label">&raquo;</span>
		</td>
		<td class="scheme">
			<select name="url_mapping[scheme][]">
				<option>https</option>
				<option>http</option>
			</select>
		</td>
		<td class="sep">
			<span class="label">://</span>
		</td>
		<td class="host">
			<input type="text" name="url_mapping[host][]" value="" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Mapping','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Mapping','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>
	</tbody>
</table>

<p class="button-controls">
	<input type="submit" name="url-mapping-save" value="<?php _e('Save Changes'); ?>" class="button-primary" id="url-mapping-save" />
	<input type="submit" name="url-mapping-reset" value="<?php _e('Reset'); ?>" class="button-secondary" id="url-mapping-reset" />
	<img alt="<?php _e('Waiting...','wordpress-https'); ?>" src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting submit-waiting" />
</p>
</form>
<script type="text/javascript">
jQuery(document).ready(function($) {
	var form = $('#<?php echo $this->getSlug(); ?>_url_mapping_form').first();
	$('#url-mapping-save').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_url_mapping_save');
	});
	$('#url-mapping-reset').click(function() {
	   if ( ! confirm('<?php _e('Are you sure you want to reset all WordPress HTTPS url mappings?','wordpress-https'); ?>') ) {
			e.preventDefault();
			return false;
	   }
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_url_mapping_reset');
	});
	$(form).submit(function(e) {
		e.preventDefault();
		$(form).find('.submit-waiting').show();
		$.post(ajaxurl, $(form).serialize(), function(response) {
			$(form).find('.submit-waiting').hide();
			$('#message-body').html(response).fadeOut(0).fadeIn().delay(5000).fadeOut();
		});
	});

	var ssl_host;
	$('#ssl_host').keyup(function() {
		var value = $(this).val();
		$('#https_url').val($('#https_url').val().replace(ssl_host, value));
		ssl_host = value;
	}).keyup();

	$('#url_mapping').on('change', '.url_mapping_row .scheme select', function(e) {
		e.preventDefault();
		var thisSelect = this;
		$(thisSelect).parents('tr').find('select').each(function(i, otherSelect) {
			if ( i > 0 && thisSelect != otherSelect ) {
				if ( $(thisSelect).val() == 'http' ) {
					$(otherSelect).val('https');
				} else {
					$(otherSelect).val('http');
				}
			}
		});
		return false;
	});

	$('#url_mapping').on('click', '.url_mapping_row .add', function(e) {
		e.preventDefault();
		var row = $(this).parents('tr').clone();
		row.find('input').val('');
		$(this).parents('table').append(row);
		$(this).hide();
		$('#url_mapping .remove').show();
		return false;
	});

	$('#url_mapping').on('click', '.url_mapping_row .remove', function(e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		if ( $('#url_mapping tr').length <= 2 ) {
			$('#url_mapping .remove').hide();
		} else {
			$('#url_mapping .remove').show();
		}
		$('#url_mapping .add').hide();
		$('#url_mapping tr:last-child .add').show();
		return false;
	});

	if ( $('#url_mapping tr').length <= 2 ) {
		$('#url_mapping .remove').hide();
	} else {
		$('#url_mapping .remove').show();
		$('#url_mapping .add').hide();
		$('#url_mapping tr:last-child .add').show();
	}
});
</script>