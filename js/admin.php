<?php
/** Loads the WordPress Environment */
require('../../../../wp-blog-header.php');

// Disable errors
error_reporting(0);
 
// Set headers
header("Status: 200");
header("HTTP/1.1 200 OK");
header('Content-Type: application/javascript');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');
header("Vary: Accept-Encoding");

?>
jQuery(document).ready(function($) {
	$('#message-body').fadeOut();

	$('#wordpress-https').submit(function() {
		$('#submit-waiting').show();
	});

	var options = {
		data: { ajax: '1'},
		success: function(responseText, textStatus, XMLHttpRequest) {
			$('#submit-waiting').hide();
			$('#message-body').html(responseText);
			$('#message-body').fadeIn().animate({opacity: 1.0}, 5000).fadeOut();
		}
	};
	
	$('#wordpress-https').ajaxForm(options);
	
	$('#settings-reset').click(function(e, el) {
	   if ( confirm('Are you sure you want to reset all WordPress HTTPS settings?') ) {
			$(this).parents('form').submit();
	   } else {
			e.preventDefault();
			return false;
	   }
	});
	
	$('#wphttps-updates .wphttps-widget-content').load('<?php echo parse_url($wordpress_https->plugin_url, PHP_URL_PATH); ?>/js/updates.php');
	
	$.ajax({
		url: '<?php echo parse_url($wordpress_https->plugin_url, PHP_URL_PATH); ?>/js/sidebar.php',
		success: function(response) {
			$('#wphttps-sidebar').append(response);
		}
	});
	
	function resize() {
		$('#wphttps-main').width( $('#wphttps-main').parent().width() - ($('#wphttps-sidebar').width() + 15));
	}
	
	$(window).resize(function() {
		resize();
	});
	resize();
	
	$('#wphttps-warnings .warning-help').tooltip({
		id: 'wphttps-tooltip',
		delay: 0,
		showURL: false,
		positionLeft: true,
		bodyHandler: function() {
			return $($(this).attr("href")).html();
		}
	});
});