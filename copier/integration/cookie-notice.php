<?php

add_action( 'wpmudev_copier_before_copy_settings', 'copier_cookie_notice_remove_actions' );
function copier_cookie_notice_remove_actions() {
	global $cookie_notice;
	if ( class_exists( 'Cookie_Notice' ) && is_object( $cookie_notice ) ) {
		unregister_setting( 'cookie_notice_options', 'cookie_notice_options', array( $cookie_notice, 'validate_options' ) );
	}
}