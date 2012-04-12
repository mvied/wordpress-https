<?php
global $post;

if ( $post->ID ) {
	$force_ssl = get_post_meta($post->ID, 'force_ssl', true);
	$force_ssl_children = get_post_meta($post->ID, 'force_ssl_children', true);
}

wp_nonce_field($this->getPlugin()->getSlug(), $this->getPlugin()->getSlug());
?>
<div class="misc-pub-section">
	<label><input type="checkbox" value="1" name="force_ssl" id="force_ssl"<?php echo ( $force_ssl  ? ' checked="checked"' : '' ); ?> /> Secure post</label>
</div>
<div class="misc-pub-section misc-pub-section-last">
	<label><input type="checkbox" value="1" name="force_ssl_children" id="force_ssl_children"<?php echo ( $force_ssl_children  ? ' checked="checked"' : '' ); ?> /> Secure child posts</label>
</div>