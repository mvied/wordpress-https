<?php

$include_paths = array(
	get_include_path(),
	realpath(dirname(__FILE__) . '/../../../../..'),
	realpath(dirname(__FILE__) . '/../../lib')
);
set_include_path(implode(PATH_SEPARATOR, $include_paths));
require_once('wp-load.php');
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

if ( ! wp_verify_nonce($_POST['nonce'], $_POST['id']) ) {
	exit;
}

$content = WordPressHTTPS_Url::fromString( $_POST['url'] )->getContent();

if ( $content ) {
	echo $content;
}
?>