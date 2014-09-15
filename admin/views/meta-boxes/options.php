<h3><?php _e( 'Site Title', WPMUDEV_CLONER_LANG_DOMAIN ); ?></h3>
<p><input id="cloner-clone-blog-title" type="radio" checked name="cloner_blog_title" value="clone" /> <label for="cloner-clone-blog-title"><?php _e( 'Clone blog title', WPMUDEV_CLONER_LANG_DOMAIN ); ?></label></p>
<p><input id="cloner-keep-blog-title" type="radio" name="cloner_blog_title" value="keep" /> <label for="cloner-keep-blog-title"><?php _e( 'Keep the destination blog title', WPMUDEV_CLONER_LANG_DOMAIN ); ?></label></p>
<p><input id="cloner-replace-blog-title" type="radio" name="cloner_blog_title" value="replace" /> 
	<label for="cloner-replace-blog-title">
		<?php _e( 'Overwrite blog title with', WPMUDEV_CLONER_LANG_DOMAIN ); ?> 
		<input type="text" name="replace_blog_title" value=""/>
	</label>
	
</p>

<h3><?php _e( 'Search Engine Visibility' ); ?></h3>
<label for="blog_public">
	<input name="cloner_blog_public" type="checkbox" id="blog_public" <?php checked( ! $blog_public ); ?>>
	<?php _e( 'Discourage search engines from indexing the cloned site', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
</label>

