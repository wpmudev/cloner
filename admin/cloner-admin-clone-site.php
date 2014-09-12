<?php

/**
 * Based on Tom McFarlin's Plugin Boilerplate https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate
 */
class WPMUDEV_Cloner_Admin_Clone_Site {

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
		add_action( 'network_admin_menu', array( $this, 'add_plugin_clone_site_menu' ) );

		add_filter( 'manage_sites_action_links', array( &$this, 'add_site_action_link' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'add_javascript' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_css' ) );

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
	 * Add a new action link in the Network Sites Page that clones a site
	 */
	public function add_site_action_link( $links, $blog_id ) {
		$clone_url = add_query_arg( 'blog_id', $blog_id, network_admin_url( 'index.php?page=clone_site' ) );

		$links['clone'] = '<span class="clone"><a href="' . $clone_url . '">' . __( 'Clone', WPMUDEV_CLONER_LANG_DOMAIN ) . '</a></span>';

		return $links;
	}

	function add_javascript() {
		if ( get_current_screen()->id == $this->plugin_screen_hook_suffix . '-network' ) {
			wp_enqueue_script( 'jquery-ui-autocomplete' );
			wp_enqueue_script( 'jquery-multi-select-css', WPMUDEV_CLONER_PLUGIN_URL . 'admin/assets/jquery-multi-select/js/jquery-multi-select.js', array( 'jquery' ), WPMUDEV_CLONER_VERSION );
			wp_enqueue_script('post');
		}
	}

	function add_css() {
		if ( get_current_screen()->id == $this->plugin_screen_hook_suffix . '-network' )
			wp_enqueue_style( 'jquery-multi-select-css', WPMUDEV_CLONER_PLUGIN_URL . 'admin/assets/jquery-multi-select/css/multi-select.css', array(), WPMUDEV_CLONER_VERSION );
	}


	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_clone_site_menu() {

		$this->plugin_screen_hook_suffix = add_submenu_page( 
			null, 
			__( 'Clone Site', WPMUDEV_CLONER_LANG_DOMAIN ), 
			__( 'Clone Site', WPMUDEV_CLONER_LANG_DOMAIN ), 
			'manage_network', 
			'clone_site', 
			array( &$this, 'display_admin_page' ) 
		);

		// Sanitize the form when the menu is loaded
		add_action( 'load-' . $this->plugin_screen_hook_suffix, array( $this, 'sanitize_clone_form' ) );

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_admin_page() {
		global $current_site;

		$blog_id = absint( $_REQUEST['blog_id'] );
		$blog_details = get_blog_details( $blog_id );

		if ( ! $blog_details )
			wp_die( __( 'The blog that you are trying to copy does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );

		$domain = '';
		$subdomain = '';
		if ( is_subdomain_install() ) {
			if ( $blog_id == 1 ) {
				$domain = $blog_details->domain;
			}
			else {
				$_domain = explode( '.', $blog_details->domain, 2 ); 		
				$subdomain = $_domain[0] . '.';
				$domain = $_domain[1];
			}
		}
		else {
			$domain = $blog_details->domain;
			$subdomain = $blog_details->path;
		}


		$selected_array = json_encode( array() );
		if ( $blog_id === 1 ) {
			$additional_tables = copier_get_additional_tables( $blog_id );
			$additional_tables_previous_selection = get_site_option( 'cloner_main_site_tables_selected', array() );
			$selected_array = json_encode( $additional_tables_previous_selection );
		}

			

		$form_url = add_query_arg(
			array(
				'action' => 'clone'
			)
		);

		add_meta_box( 'cloner-destination', __( 'Destination', WPMUDEV_CLONER_LANG_DOMAIN), array( $this, 'destination_meta_box' ), 'cloner', 'normal' );
		add_meta_box( 'cloner-blog-title', __( 'Blog Title', WPMUDEV_CLONER_LANG_DOMAIN), array( $this, 'blog_title_meta_box' ), 'cloner', 'normal' );

		if ( ! empty( $additional_tables ) && $blog_id == 1 )
			add_meta_box( 'cloner-advanced', __( 'Advanced Options', WPMUDEV_CLONER_LANG_DOMAIN), array( $this, 'advanced_options_meta_box' ), 'cloner', 'normal' );

		include_once( 'views/clone-site.php' );
	}

	public function destination_meta_box() {
		global $current_site;

		include_once( 'views/meta-boxes/destination.php' );
	}

	public function blog_title_meta_box() {

		include_once( 'views/meta-boxes/blog-title.php' );
	}

	public function advanced_options_meta_box() {
		$blog_id = absint( $_REQUEST['blog_id'] );
		$additional_tables = copier_get_additional_tables( $blog_id );
		$additional_tables_previous_selection = get_site_option( 'cloner_main_site_tables_selected', array() );

		include_once( 'views/meta-boxes/advanced.php' );
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
    	


	/**
	 * Sanitize the clone form
	 */
	function sanitize_clone_form() {

		if ( empty( $_REQUEST['clone-site-submit'] ) )
			return;		

		$blog_id = ! empty( $_REQUEST['blog_id'] ) ? absint( $_REQUEST['blog_id'] ) : 0;
		$blog_details = get_blog_details( $blog_id );

		check_admin_referer( 'clone-site-' . $blog_id, '_wpnonce_clone-site' );

		// Does the source blog exists?
		if ( ! $blog_id || empty( $blog_details ) ) {
			add_settings_error( 'cloner', 'source_blog_not_exist', __( 'The blog that you are trying to copy does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );
			return;
		}

		$selection = empty( $_REQUEST['cloner-clone-selection'] ) ? false : $_REQUEST['cloner-clone-selection'];
		$blog_title_selection = empty( $_REQUEST['cloner_blog_title'] ) ? 'clone' : $_REQUEST['cloner_blog_title'];
		$new_blog_title = ! empty( $_REQUEST['replace_blog_title'] ) ? $_REQUEST['replace_blog_title'] : 0;

		$args = array();

		$settings = wpmudev_cloner_get_settings();

		if ( $blog_id === 1 ) {
			$additional_tables_selected = empty( $_REQUEST['additional_tables'] ) ? array() : $_REQUEST['additional_tables'];
			update_site_option( 'cloner_main_site_tables_selected', $additional_tables_selected );
		}

		switch ( $selection ) {
			case 'create': {
				// Checking if the blog already exists
				// Sanitize the domain/subfolder
				$blog = ! empty( $_REQUEST['blog_create'] ) ? $_REQUEST['blog_create'] : false;

				if ( ! $blog ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Please, insert a site name', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}

				$domain = '';
				if ( preg_match( '|^([a-zA-Z0-9-])+$|', $blog ) )
					$domain = strtolower( $blog );

				if ( empty( $domain ) ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Missing or invalid site address.', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}

				$destination_blog_details = get_blog_details( $domain );

				if ( ! empty( $destination_blog_details ) ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'The blog already exists', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}

				if ( 'clone' == $blog_title_selection ) {
					$new_blog_title = $blog_details->blogname;
				}
				

				do_action( 'wpmudev_cloner_pre_clone_actions', $selection, $blog_id, $args, false );
				$errors = get_settings_errors( 'cloner' );
				if ( ! empty( $errors ) )
					return;

				break;
			}
			case 'replace': {
				$destination_blog_id = isset( $_REQUEST['blog_replace'] ) ? absint( $_REQUEST['blog_replace'] ) : false;

				if ( ! $destination_blog_id ) {
					// try to check the blog name
					$blog_name = isset( $_REQUEST['blog_replace_autocomplete'] ) ? $_REQUEST['blog_replace_autocomplete'] : '';
					$destination_blog_details = get_blog_details( $blog_name );

					if ( empty( $destination_blog_details ) ) {
						$destination_blog_id = false;
					}
					else {
						$destination_blog_id = $destination_blog_details->blog_id;
					}
					
				}

				if ( ! $destination_blog_id ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'The site you are trying to replace does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}

				if ( $destination_blog_id == $blog_id ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'You cannot copy a blog to itself', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}

				$destination_blog_details = get_blog_details( $destination_blog_id );

				if ( empty( $destination_blog_details ) ) {
					add_settings_error( 'cloner', 'source_blog_not_exist', __( 'The site you are trying to replace does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );
					return;
				}


				// The blog must be overwritten because it already exists
		        $args['override'] = absint( $destination_blog_details->blog_id );

		        if ( is_subdomain_install() ) {
		        	$domain = explode( '.', $destination_blog_details->domain );
		        	$domain = $domain[0];
		        }
		        else {
		        	$domain = str_replace( '/', '', $destination_blog_details->path );
		        }

		        do_action( 'wpmudev_cloner_pre_clone_actions', $selection, $blog_id, $args, $destination_blog_id );
				$errors = get_settings_errors( 'cloner' );
				if ( ! empty( $errors ) )
					return;

				if ( 'clone' == $blog_title_selection ) {
					$new_blog_title = $blog_details->blogname;
				}
				elseif ( 'keep' == $blog_title_selection ) {
					$new_blog_title = $destination_blog_details->blogname;
				}


		        if ( ! isset( $_REQUEST['confirm'] ) ) {
		        	// Display a confirmation screen.
		        	$back_url = add_query_arg(
			    		array(
			    			'page' => 'clone_site',
			    			'blog_id' => $blog_id
			    		),
			    		network_admin_url( 'admin.php' )
			    	);

		        	include_once( 'views/confirmation.php' );

					wp_die();
		        }

				break;
			}
			default: {
				add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Please, select an option', WPMUDEV_CLONER_LANG_DOMAIN ) );
				return;
				break;
			}
		}

		$args['new_blog_title'] = empty( $new_blog_title ) ? false : $new_blog_title;

	
		// New Blog Templates integration
		if ( class_exists( 'blog_templates' ) ) {
			$action_order = defined('NBT_APPLY_TEMPLATE_ACTION_ORDER') && NBT_APPLY_TEMPLATE_ACTION_ORDER ? NBT_APPLY_TEMPLATE_ACTION_ORDER : 9999;
			// Set to *very high* so this runs after every other action; also, accepts 6 params so we can get to meta
			remove_action( 'wpmu_new_blog', array( 'blog_templates', 'set_blog_defaults'), apply_filters('blog_templates-actions-action_order', $action_order), 6); 
		}

		$current_site = get_current_site();

		if ( is_subdomain_install() ) {
			$domain = $domain . '.' . $current_site->domain;
			$path = '';
		}
		else {
			$path = $current_site->path . $domain . '/'; //$path = '/' . $domain; // Do NOT assume the root to be server root
			$domain = $current_site->domain;
		}


		// Set everything needed to clone the site
		$result = $this->pre_clone_actions( $blog_id, $domain, $path, $args );

		if ( is_integer( $result ) ) {
			$redirect_to = get_admin_url( $result );
			wp_redirect( $redirect_to );	
			exit;
		}

	}

	/**
	 * Set everything needed to clone a site:
	 * 
	 * Create a new empty site if we are not overrriding an existsing blog
	 * Change the blog Name
	 */
	public function pre_clone_actions( $source_blog_id, $domain, $path, $args ) {
        global $wpdb;

        $defaults = array(
            'override' => false,
            'new_blog_title' => false
        );
        $args = wp_parse_args( $args, $defaults );
        extract( $args );

        $blog_details = get_blog_details( $override );
        if ( empty( $blog_details ) )
            $override = false;

        $new_blog_id = $override;
        if ( ! $override ) {
        	// Not overrriding, let's create an  empty blog
            $new_blog_id = create_empty_blog( $domain, $path, '' );            
        }


        if ( ! is_integer( $new_blog_id ) )
            return new WP_Error( 'create_empty_blog', strip_tags( $new_blog_id ) );

        $source_blog_details = get_blog_details( $source_blog_id );

        $blog_title = empty( $new_blog_title ) ? $source_blog_details->blogname : $new_blog_title;

        // Update the blog name
        update_blog_option( $new_blog_id, 'blogname', $blog_title );

        if ( is_main_site( $source_blog_id ) || $source_blog_id === 1 )
        	add_action( 'copier_set_copier_args', array( $this, 'set_copier_tables_for_main_site' ), 1 );

        // And set copier arguments
        $result = copier_set_copier_args( $source_blog_id, $new_blog_id );

        return $new_blog_id;
    }

    /**
     * If we are copying the main site, we need to exclude network tables
     * but this is up to the user, so let's make whatever we can.
     * 
     * @param Array $option The current options to clone
     * @return Array new clone options
     */
    function set_copier_tables_for_main_site( $option ) {
    	if ( isset( $option['to_copy']['tables'] ) ) {
    		// Get the tables selected, they should be saved already
    		$option['to_copy']['tables'] = array( 'tables' => get_site_option( 'cloner_main_site_tables_selected', array() ) );
    	}

    	return $option;
    }

}
