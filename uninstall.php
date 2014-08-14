<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

delete_site_option( 'wpmudev_cloner_installation_notice_done' );
delete_site_option( 'wpmudev_cloner_settings' );
