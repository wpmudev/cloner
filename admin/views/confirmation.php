<?php
nocache_headers();
@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
    <title><?php _e( 'Confirm action', WPMUDEV_COPIER_LANG_DOMAIN ); ?></title>
    <?php
    	wp_admin_css( 'install', true );
    	wp_admin_css( 'ie', true );
    	wp_admin_css( 'buttons', true );
        wp_admin_css( 'dashicons', true );
    ?>
    <style>
        .dashicons-megaphone {
            font-size:32px;
            height:32px;
            width:32px;
            vertical-align: middle;
        }
        h1 {
            font-size:32px !important;
        }
        strong {
            font-size:20px;
            display:block;
            margin:20px 0;
        }
    </style>
</head>
<body class="wp-core-ui">
	<form method="post" action="<?php echo network_admin_url( 'index.php?page=clone_site' ); ?>">
        <h1><?php _e( 'Watch Out!', WPMUDEV_COPIER_LANG_DOMAIN ); ?> <span class="dashicons dashicons-megaphone"></span></h1>
		<p>
			<?php 
				printf( 
					__( 'You have chosen a URL <u>that already exists</u>. If you choose ‘Continue’, all existing site content and settings on %s will be <u>completely overwritten</u> with content and settings from %s This change is permanent and can’t be undone, so please be careful. ', WPMUDEV_CLONER_LANG_DOMAIN ),
					'<strong>' . get_site_url( $destination_blog_details->blog_id ) . '</strong>', 
					'<strong>' . get_site_url( $blog_details->blog_id ) . '</strong>' 
				); 
			?>
		</p>

		<input type="hidden" name="action" value="clone" />
		<input type="hidden" name="blog_replace" value="<?php echo $destination_blog_id; ?>" />
		<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>" />
		<input type="hidden" name="clone-site-submit" value="true" />
		<input type="hidden" name="cloner-clone-selection" value="replace" />
		<input type="hidden" name="cloner_blog_title" value="<?php echo $blog_title_selection; ?>" />
		<input type="hidden" name="replace_blog_title" value="<?php echo $new_blog_title; ?>" />

		<?php $i = 0; ?>
		<?php foreach ( $additional_tables_selected as $table ): ?>
			<input type="hidden" name="additional_tables[<?php echo $i; ?>]" value="<?php echo $table; ?>">
			<?php $i++; ?>
		<?php endforeach; ?>

		<?php if ( $blog_public ): ?>
			<input type="hidden" name="cloner_blog_public" value="0" />
		<?php endif; ?>
		<?php wp_nonce_field( 'clone-site-' . $blog_id, '_wpnonce_clone-site' ); ?>

		<?php submit_button( __( 'Continue', WPMUDEV_CLONER_LANG_DOMAIN ), 'primary', 'confirm', false ); ?>
		<a class="button-secondary" href="<?php echo esc_url( $back_url ); ?>"><?php _e( 'No, please I want to go back', WPMUDEV_CLONER_LANG_DOMAIN ); ?></a>

	</form>
</body>