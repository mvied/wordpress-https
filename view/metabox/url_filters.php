<?php
if ( !defined('ABSPATH') ) exit;
?>
<form name="<?php echo $this->getSlug(); ?>_url_filters_form" id="<?php echo $this->getSlug(); ?>_url_filters_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php wp_nonce_field($this->getSlug()); ?>
<input type="hidden" name="action" id="action" value="" />

<p><?php printf( __('URL Filters allow you to specify what content should always be secure or unsecure using simple string comparisons or %s.','wordpress-https'),'<a href="#TB_inline?height=155&width=350&inlineId=regex-help" class="thickbox" title="' . __('Regular Expressions Help','wordpress-https') . '">'.__('Regular Expressions','wordpress-https').'</a>'); ?></p>

<table class="form-table url_filters" id="secure_url_filters">
	<tr valign="top">
		<td colspan="2"><h4><?php _e('Secure Filters','wordpress-https'); ?></h4></td>
	</tr>
<?php if ( sizeof($this->getSetting('secure_filter')) > 0 ) : foreach ( (array)$this->getSetting('secure_filter') as $filter ) : ?>
	<tr valign="top" class="secure_url_filters_row">
		<td>
			<input type="text" name="secure_url_filters[]" value="<?php echo $filter; ?>" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Filter','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Filter','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>
<?php endforeach; else: ?>
	<tr valign="top" class="secure_url_filters_row">
		<td>
			<input type="text" name="secure_url_filters[]" value="" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Filter','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Filter','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>
<?php endif; ?>
</table>

<table class="form-table url_filters" id="unsecure_url_filters">
	<tr valign="top">
		<td colspan="2"><h4><?php _e('Unsecure Filters','wordpress-https'); ?></h4></td>
	</tr>
<?php if ( sizeof($this->getSetting('unsecure_filter')) > 0 ) : foreach ( (array)$this->getSetting('unsecure_filter') as $filter ) : ?>
	<tr valign="top" class="unsecure_url_filters_row">
		<td>
			<input type="text" name="unsecure_url_filters[]" value="<?php echo $filter; ?>" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Filter','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Filter','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>
<?php endforeach; else: ?>
	<tr valign="top" class="unsecure_url_filters_row">
		<td>
			<input type="text" name="unsecure_url_filters[]" value="" />
		</td>
		<td class="controls">
			<a class="remove" href="#" title="<?php _e('Remove URL Filter','wordpress-https'); ?>"><?php _e('Remove','wordpress-https'); ?></a>
			<a class="add" href="#" title="<?php _e('Add URL Filter','wordpress-https'); ?>"><?php _e('Add','wordpress-https'); ?></a>
		</td>
	</tr>
<?php endif; ?>
</table>

<p class="button-controls">
	<input type="submit" name="url-filters-save" value="<?php _e('Save Changes'); ?>" class="button-primary" id="url-filters-save" />
	<input type="submit" name="url-filters-reset" value="<?php _e('Reset'); ?>" class="button-secondary" id="url-filters-reset" />
	<img alt="<?php _e('Waiting...','wordpress-https'); ?>" src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting submit-waiting" />
</p>
</form>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var form = $('#<?php echo $this->getSlug(); ?>_url_filters_form').first();
	$('#url-filters-save').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_url_filters_save');
	});
	$('#url-filters-reset').click(function() {
		$(form).find('input[name="action"]').val('<?php echo $this->getSlug(); ?>_url_filters_reset');
	});
	$(form).submit(function(e) {
		e.preventDefault();
		$(form).find('.submit-waiting').show();
		$.post(ajaxurl, $(form).serialize(), function(response) {
			$(form).find('.submit-waiting').hide();
			$('#message-body').html(response).fadeOut(0).fadeIn().delay(5000).fadeOut();
		});
	});

	$('#url-filters-reset').click(function(e, el) {
	   if ( ! confirm('<?php _e('Are you sure you want to reset all WordPress HTTPS URL Filters?','wordpress-https'); ?>') ) {
			e.preventDefault();
			return false;
	   }
	});

	$('#secure_url_filters').on('click', '.secure_url_filters_row .add', function(e) {
		e.preventDefault();
		var row = $(this).parents('tr').clone();
		row.find('input').val('');
		$(this).parents('table').append(row);
		$(this).hide();
		$('#secure_url_filters .remove').show();
		return false;
	});

	$('#secure_url_filters').on('click', '.secure_url_filters_row .remove', function(e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		if ( $('#secure_url_filters tr').length <= 2 ) {
			$('#secure_url_filters .remove').hide();
		} else {
			$('#secure_url_filters .remove').show();
		}
		$('#secure_url_filters .add').hide();
		$('#secure_url_filters tr:last-child .add').show();
		return false;
	});

	if ( $('#secure_url_filters tr').length <= 2 ) {
		$('#secure_url_filters .remove').hide();
	} else {
		$('#secure_url_filters .remove').show();
		$('#secure_url_filters .add').hide();
		$('#secure_url_filters tr:last-child .add').show();
	}

	$('#unsecure_url_filters').on('click', '.unsecure_url_filters_row .add', function(e) {
		e.preventDefault();
		var row = $(this).parents('tr').clone();
		row.find('input').val('');
		$(this).parents('table').append(row);
		$(this).hide();
		$('#unsecure_url_filters .remove').show();
		return false;
	});

	$('#unsecure_url_filters').on('click', '.unsecure_url_filters_row .remove', function(e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		if ( $('#unsecure_url_filters tr').length <= 2 ) {
			$('#unsecure_url_filters .remove').hide();
		} else {
			$('#unsecure_url_filters .remove').show();
		}
		$('#unsecure_url_filters .add').hide();
		$('#unsecure_url_filters tr:last-child .add').show();
		return false;
	});

	if ( $('#unsecure_url_filters tr').length <= 2 ) {
		$('#unsecure_url_filters .remove').hide();
	} else {
		$('#unsecure_url_filters .remove').show();
		$('#unsecure_url_filters .add').hide();
		$('#unsecure_url_filters tr:last-child .add').show();
	}
});
</script>