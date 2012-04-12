<?php 
	$nonce = wp_create_nonce($metabox['id']);
?><script type="text/javascript">
jQuery(document).ready(function($) {
	var loading = $('<img alt="Loading..." src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="loading" />');

	$('#<?php echo $metabox['id']; ?> .handlediv').append( loading );
	$('#<?php echo $metabox['id']; ?> .handlediv .loading').fadeIn('fast');
	$.ajax({
		type: 'post',
		url: '<?php echo parse_url((( $this->getPlugin()->isSsl() ) ? $this->getPlugin()->makeUrlHttps($this->getPlugin()->getPluginUrl()) : $this->getPlugin()->getPluginUrl()), PHP_URL_PATH); ?>/admin/js/metabox.php',
		data: {
			id : '<?php echo $metabox['id']; ?>',
			url : '<?php echo $metabox['args']['url']; ?>',
			nonce : '<?php echo $nonce; ?>'
		},
		success: function(response) {
			$('#<?php echo $metabox['id']; ?> .inside').html(response);
			$('#<?php echo $metabox['id']; ?> .handlediv .loading').fadeIn(0).fadeOut('fast');
		}
	});
});
</script>