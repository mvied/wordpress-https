<form name="<?php echo $this->getPlugin()->getSlug(); ?>_filters_form" id="<?php echo $this->getPlugin()->getSlug(); ?>_filters_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php settings_fields($this->getPlugin()->getSlug()); ?>

<table class="form-table">
	<tr valign="top" id="secure_filter_row">
		<th scope="row">
			Secure Filters
			<p class="description">Example: If you have an E-commerce shop and all of the URL's begin with /store/, you could secure all store links by entering '/store/' on one line.</p>
		</th>
		<td>
			<textarea name="secure_filter" id="secure_filter"><?php echo implode("\n", $this->getPlugin()->getSetting('secure_filter')); ?></textarea>
		</td>
	</tr>
</table>

<input type="hidden" name="action" value="save" />

<p class="button-controls">
	<input type="submit" name="filters-save" value="Save Changes" class="button-primary" id="filters-save" />
	<input type="submit" name="filters-reset" value="Reset" class="button-secondary" id="filters-reset" />
	<img alt="Waiting..." src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting submit-waiting" />
</p>
</form>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#<?php echo $this->getPlugin()->getSlug(); ?>_filters_form').submit(function() {
		$('#<?php echo $this->getPlugin()->getSlug(); ?>_filters_form .submit-waiting').show();
	}).ajaxForm({
		data: { ajax: '1'},
		success: function(responseText, textStatus, XMLHttpRequest) {
			$('#<?php echo $this->getPlugin()->getSlug(); ?>_filters_form .submit-waiting').hide();
			$('#message-body').html(responseText).fadeOut(0).fadeIn().delay(5000).fadeOut();
		}
	});

	$('#filters-reset').click(function(e, el) {
	   if ( ! confirm('Are you sure you want to reset all WordPress HTTPS filters?') ) {
			e.preventDefault();
			return false;
	   }
	});
});
</script>