<?php

if ( ! function_exists( 'copier_remap_ubermenu_id' ) ) {
	add_action( 'wpmudev_copier-copied_menu', 'copier_remap_ubermenu_id', 10, 2 );
	function copier_remap_ubermenu_id( $args, $new_menu_id ) {
		if ( $uber_options = get_option( 'ubermenu_main' ) ) {
			$source_menu_id = $args['menu_id'];
			if ( isset( $uber_options['nav_menu_id'] ) && $uber_options['nav_menu_id'] == $source_menu_id ) {
				// Remap the menu
				$uber_options['nav_menu_id'] = $new_menu_id;
				update_option( 'ubermenu_main', $uber_options );
			}
		}
	}
}
