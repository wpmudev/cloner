<?php

/**
 * Based on Tom McFarlin's Plugin Boilerplate https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate
 */
class WPMUDEV_Cloner_Admin_Settings {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		if ( ! is_super_admin() )
			return;

		$this->plugin_slug = 'cloner';

		// Add the options page and menu item.
		add_action( 'network_admin_menu', array( $this, 'add_plugin_settings_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		if ( ! defined( 'WPMUDEV_CLONER_ASSETS_URL' ) )
			define( 'WPMUDEV_CLONER_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets' );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		if ( ! is_super_admin() ) {
			return false;
		}

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance )
			self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_settings_menu() {

		$this->plugin_screen_hook_suffix = add_submenu_page(
			'settings.php',
			__( 'Cloner Settings', WPMUDEV_CLONER_LANG_DOMAIN ),
			__( 'Cloner', WPMUDEV_CLONER_LANG_DOMAIN ),
			'manage_network',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

		add_action( 'load-' . $this->plugin_screen_hook_suffix, array( $this, 'sanitize_settings_form' ) );

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$to_copy_labels = wpmudev_cloner_get_settings_labels();
		$settings = wpmudev_cloner_get_settings();

		$errors = get_settings_errors( 'wpmudev_cloner_settings' );

		$updated = false;
		if ( isset( $_GET['updated'] ) )
			$updated = true;

		extract( $settings );

		include_once( 'views/settings.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . network_admin_url( 'settings.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', WPMUDEV_CLONER_LANG_DOMAIN ) . '</a>'
			),
			$links
		);

	}

	public function sanitize_settings_form() {
		if ( empty( $_POST['submit'] ) )
			return;

		check_admin_referer( 'wpmudev_cloner_settings' );

		if ( empty( $_POST['to_copy'] ) ) {
			add_settings_error( 'wpmudev_cloner_settings', 'empty-settings', __( 'You need to check at least one option', WPMUDEV_CLONER_LANG_DOMAIN ) );
			return;
		}

		$settings = wpmudev_cloner_get_settings();

		$to_copy = array_keys( $_POST['to_copy'] );
		$settings['to_copy'] = $to_copy;

		wpmudev_cloner_update_settings( $settings );

		$redirect = add_query_arg( 
			array( 
				'page' => $this->plugin_slug,
				'updated' => 'true'
			),
			network_admin_url( 'settings.php' )
		);

		wp_redirect( $redirect );
		exit();


	}

}
