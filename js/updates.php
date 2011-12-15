<?php

require_once('../wordpress-https.php');

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
 
$url = 'http://mvied.com/wphttps-updates.html';

$content = WordPressHTTPS::get_file_contents($url);

if ($content) {
	echo $content;
} else {
	echo "<p class=\"error\">Unable to retrieve updates.</p>";
}
?>