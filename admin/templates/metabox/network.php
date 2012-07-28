<form name="<?php echo $this->getPlugin()->getSlug(); ?>_settings_form" id="<?php echo $this->getPlugin()->getSlug(); ?>_settings_form" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
<?php settings_fields($this->getPlugin()->getSlug()); ?>

<input type="hidden" name="action" value="wphttps-network" />

<p class="button-controls">
	<input type="submit" name="settings-save" value="Save Changes" class="button-primary" id="network-settings-save" />
	<input type="submit" name="settings-reset" value="Reset" class="button-secondary" id="network-settings-reset" />
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
	   if ( ! confirm('Are you sure you want to reset all WordPress HTTPS network settings?') ) {
			e.preventDefault();
			return false;
	   }
	});
});
</script>