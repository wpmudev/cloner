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

if ( ! defined( 'WPMUDEV_CLONER_PLUGIN_DIR' ) )
	define( 'WPMUDEV_CLONER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

include_once( 'includes/cloner.php' );