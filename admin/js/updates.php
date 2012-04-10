<?php
$include_paths = array(
	realpath(dirname(__FILE__) . '/../../lib'),
	get_include_path()
);
set_include_path(implode(PATH_SEPARATOR, $include_paths));
require_once('WordPressHTTPS.php');

// Disable errors
error_reporting(0);

// Set headers
header("Status: 200");
header("HTTP/1.1 200 OK");
header('Content-Type: text/html');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');
header("Vary: Accept-Encoding");
 
$content = WordPressHTTPS::factory('Url')->fromString('http://mvied.com/wphttps-updates.html')->getContent();

if ( $content ) {
	echo $content;
} else {
	echo "<p class=\"error\">Unable to retrieve updates.</p>";
}
?>