<?php
/*
Plugin Name: Cloner
Plugin URI: https://premium.wpmudev.org/project/cloner
Description: Clone sites in a network installation
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Version: 1.0
Network: true
Text Domain: wpmudev-cloner
Domain Path: lang
WDP ID: 910773
*/

/*
Copyright 2007-2014 Incsub (http://incsub.com)
Author – Ignacio Cruz (igmoweb)
Contributors – Vladislav Bailovic

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 – GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

/**
 * We need to put the main file in a separate folder as this plugin contains two different
 * language files: One for the Cloner plugin itself and another for the Copier classes
 */



class WPMUDEV_Cloner {

	public $admin_menu_id;

	public function __construct() {
		$this->set_constants();
		$this->includes();

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_filter( 'copier_set_copier_args', array( $this, 'set_copier_args' ) );

		if ( is_network_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
			require_once( WPMUDEV_CLONER_PLUGIN_DIR . 'admin/cloner-admin-settings.php' );
			add_action( 'plugins_loaded', array( 'WPMUDEV_Cloner_Admin_Settings', 'get_instance' ) );

			require_once( WPMUDEV_CLONER_PLUGIN_DIR . 'admin/cloner-admin-clone-site.php' );
			add_action( 'plugins_loaded', array( 'WPMUDEV_Cloner_Admin_Clone_Site', 'get_instance' ) );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			require_once( WPMUDEV_CLONER_PLUGIN_DIR . 'admin/ajax.php' );
		}

	}

	

	private function set_constants() {
		if ( ! defined( 'WPMUDEV_CLONER_PLUGIN_DIR' ) )
			define( 'WPMUDEV_CLONER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

		if ( ! defined( 'WPMUDEV_CLONER_LANG_DOMAIN' ) )
			define( 'WPMUDEV_CLONER_LANG_DOMAIN', 'wpmudev-cloner' );

		 //Define the same language domain for the copier classes.
		if ( ! defined( 'WPMUDEV_COPIER_LANG_DOMAIN' ) )
			define( 'WPMUDEV_COPIER_LANG_DOMAIN', 'wpmudev-cloner' );
	}

	private function includes() {
		include_once( WPMUDEV_CLONER_PLUGIN_DIR . 'copier/copier.php' );
		include_once( WPMUDEV_CLONER_PLUGIN_DIR . 'helpers/settings.php' );

		//load dashboard notice
		global $wpmudev_notices;
		$wpmudev_notices[] = array( 'id'=> 910773, 'name'=> 'Cloner', 'screens' => array( 'admin_page_clone_site-network' ) );
		include_once( WPMUDEV_CLONER_PLUGIN_DIR . 'externals/wpmudev-dash-notification.php' );
	}

	public function load_plugin_textdomain() {
		$domain = WPMUDEV_CLONER_LANG_DOMAIN;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, basename( WPMUDEV_CLONER_PLUGIN_DIR ) . '/lang/' );
	}

	/**
	 * Remove arguments from copier based on Cloner Settings
	 * 
	 * @param type $args 
	 * @return type
	 */
	public function set_copier_args( $args ) {
		$settings = wpmudev_cloner_get_settings();

		$to_copy = $args['to_copy'];
		foreach ( $to_copy as $to_copy_option => $value ) {
			if ( ! in_array( $to_copy_option, $settings['to_copy'] ) )
				unset( $args['to_copy'][ $to_copy_option ] );
		}

		return $args;
	}     

}

$nbt_cloner = new WPMUDEV_Cloner();