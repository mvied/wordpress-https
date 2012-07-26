<?php
require_once('includes/template.php'); // WordPress Dashboard Functions
?>

<div class="wphttps-message-wrap" id="message-wrap"><div id="message-body"></div></div>

<div class="wrap" id="wphttps-main">
	<div id="icon-options-https" class="icon32"><br /></div>
	<h2>HTTPS</h2>

<?php
	wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );
?>
	<div id="poststuff" class="columns metabox-holder">
		<div class="postbox-container column-primary">
<?php do_meta_boxes('toplevel_page_' . $this->getPlugin()->getSlug(), 'main', $this); ?>
		</div>
		<div class="postbox-container column-secondary">
<?php do_meta_boxes('toplevel_page_' . $this->getPlugin()->getSlug(), 'side', $this); ?>
		</div>
	</div>
	
	<div id="regex-help">
		<h3>Expressions</h3>
		<table class="regex-help">
			<tr>
				<td>[abc]</td>
				<td>A single character: a, b, or c</td>
			</tr>
			<tr>
				<td>[^abc]</td>
				<td>Any single character <em>but</em> a, b, or c</td>
			</tr>
			<tr>
				<td>[a-z]</td>
				<td>Any character in the range a-z</td>
			</tr>
			<tr>
				<td>[a-zA-Z]</td>
				<td>Any character in the range a-z or A-Z (any alphabetical character)</td>
			</tr>
			<tr>
				<td>\s</td>
				<td>Any whitespace character [ \t\n\r\f\v]</td>
			</tr>
			<tr>
				<td>\S</td>
				<td>Any non-whitespace character [^ \t\n\r\f\v]</td>
			</tr>
			<tr>
				<td>\d</td>
				<td>Any digit [0-9]</td>
			</tr>
			<tr>
				<td>\D</td>
				<td>Any non-digit [^0-9]</td>
			</tr>
			<tr>
				<td>\w</td>
				<td>Any word character [a-zA-Z0-9_]</td>
			</tr>
			<tr>
				<td>\W</td>
				<td>Any non-word character [^a-zA-Z0-9_]</td>
			</tr>
			<tr>
				<td>\b</td>
				<td>A word boundary between \w and \W</td>
			</tr>
			<tr>
				<td>\B</td>
				<td>A position that is not a word boundary</td>
			</tr>
			<tr>
				<td>|</td>
				<td>Alternation: matches either the subexpression to the left or to the right</td>
			</tr>
			<tr>
				<td>()</td>
				<td>Grouping: group all together for repetition operators</td>
			</tr>
			<tr>
				<td>^</td>
				<td>Beginning of the string</td>
			</tr>
			<tr>
				<td>$</td>
				<td>End of the string</td>
			</tr>
		</table>
		<h3>Repetition&#160;Operators</h3>
		<table class="regex-help">
			<tr>
				<td>{n,m}</td>
				<td>Match the previous item at least <em>n</em> times but no more than <em>m</em>
					times</td>
			</tr>
			<tr>
				<td>{n,}</td>
				<td>Match the previous item <em>n</em> or more times</td>
			</tr>
			<tr>
				<td>{n}</td>
				<td>Match exactly <em>n</em> occurrences of the previous item</td>
			</tr>
			<tr>
				<td>?</td>
				<td>Match 0 or 1 occurrences of the previous item {0,1}</td>
			</tr>
			<tr>
				<td>+</td>
				<td>Match 1 or more occurrences of the previous item {1,}</td>
			</tr>
			<tr>
				<td>*</td>
				<td>Match 0 or more occurrences of the previous item {0,}</td>
			</tr>
		</table>
	</div>
</div>