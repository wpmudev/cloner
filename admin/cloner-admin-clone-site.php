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

		if ( ! is_main_site( $blog_id ) && $blog_id !== 1 )
			$links['clone'] = '<span class="clone"><a href="' . $clone_url . '">' . __( 'Clone', WPMUDEV_CLONER_LANG_DOMAIN ) . '</a></span>';

		return $links;
	}

	function add_javascript() {
		if ( get_current_screen()->id == $this->plugin_screen_hook_suffix . '-network' ) {
			wp_enqueue_script( 'jquery-ui-autocomplete' );
		}
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

		include_once( 'views/clone-site.php' );
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
		if ( ! $blog_id || empty( $blog_details ) )
			wp_die( __( 'The blog that you are trying to copy does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );

    	// Do not clone the main site
        if ( $blog_details->blog_id === 1 || is_main_site( $blog_details->blog_id ) )
            wp_die( __( 'Sorry, main site cannot be cloned', WPMUDEV_CLONER_LANG_DOMAIN ) );

		$selection = empty( $_REQUEST['cloner-clone-selection'] ) ? false : $_REQUEST['cloner-clone-selection'];

		$args = array();

		switch ( $selection ) {
			case 'create': {
				// Checking if the blog already exists
				// Sanitize the domain/subfolder
				$blog = ! empty( $_REQUEST['blog_create'] ) ? $_REQUEST['blog_create'] : false;

				if ( ! $blog )
					wp_die( __( 'Please, insert a site name', WPMUDEV_CLONER_LANG_DOMAIN ) );

				$domain = '';
				if ( preg_match( '|^([a-zA-Z0-9-])+$|', $blog ) )
					$domain = strtolower( $blog );

				if ( empty( $domain ) )
					wp_die( __( 'Missing or invalid site address.' ) );

				$destination_blog_details = get_blog_details( $domain );

				if ( ! empty( $destination_blog_details ) )
					wp_die( __( 'The blog already exists', WPMUDEV_CLONER_LANG_DOMAIN ) );

				break;
			}
			case 'replace': {
				$destination_blog_id = isset( $_REQUEST['blog_replace'] ) ? absint( $_REQUEST['blog_replace'] ) : false;

				if ( ! $destination_blog_id ) {
					// try to check the blog name
					$blog_name = isset( $_REQUEST['blog_replace_autocomplete'] ) ? $_REQUEST['blog_replace_autocomplete'] : '';
					$destination_blog_details = get_blog_details( $blog_name );

					if ( empty( $destination_blog_details ) || $destination_blog_details->blog_id == 1 ) {
						$destination_blog_id = false;
					}
					else {
						$destination_blog_id = $destination_blog_details->blog_id;
					}
					
				}

				if ( ! $destination_blog_id )
					wp_die( __( 'The site you are trying to replace does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );

				if ( $destination_blog_id == $blog_id )
					wp_die( __( 'You cannot copy a blog to itself', WPMUDEV_CLONER_LANG_DOMAIN ) );

				$destination_blog_details = get_blog_details( $destination_blog_id );

				if ( empty( $destination_blog_details ) )
					wp_die( __( 'The site you are trying to replace does not exist', WPMUDEV_CLONER_LANG_DOMAIN ) );

				// Do not clone the main site
        		if ( $destination_blog_details->blog_id === 1 || is_main_site( $destination_blog_details->blog_id ) )
            		wp_die( __( 'Sorry, main site cannot be overwritten', WPMUDEV_CLONER_LANG_DOMAIN ) );

				// The blog must be overwritten because it already exists
		        $args['override'] = absint( $destination_blog_details->blog_id );

		        if ( is_subdomain_install() ) {
		        	$domain = explode( '.', $destination_blog_details->domain );
		        	$domain = $domain[0];
		        }
		        else {
		        	$domain = str_replace( '/', '', $destination_blog_details->path );
		        }

		        if ( ! isset( $_REQUEST['confirm'] ) ) {
		        	// Display a confirmation screen.

		        	$clone_link = add_query_arg( 
						array(
							'action' => 'clone',
							'blog_replace' => $destination_blog_id,
							'blog_id' => $blog_id,
							'confirm' => 'true',
							'clone-site-submit' => 'true',
							'cloner-clone-selection' => 'replace'
						), 
						network_admin_url( 'index.php?page=clone_site' ) 
					);

					$clone_link = wp_nonce_url( $clone_link, 'clone-site-' . $blog_id, '_wpnonce_clone-site' );
					?>
						<p>
							<?php 
								printf( 
									__( 'You have chosen a URL that already exists. If you choose ‘Continue’, all existing site content and settings on %s will be completely overwritten with content and settings from %s. This change is permanent and can’t be undone, so please be careful. ', WPMUDEV_CLONER_LANG_DOMAIN ), 
									'<strong>' . get_site_url( $destination_blog_details->blog_id ) . '</strong>', 
									'<strong>' . get_site_url( $blog_details->blog_id ) . '</strong>' 
								); 
							?>
						</p>
						<a href="<?php echo $clone_link; ?>" class="button button-primary"><?php _e( 'Continue', WPMUDEV_CLONER_LANG_DOMAIN ); ?></a>
					<?php

					wp_die();
		        }

				break;
			}
			default: {
				wp_die( __( 'Please, select an option', WPMUDEV_CLONER_LANG_DOMAIN ) );
				break;
			}
		}

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
            'override' => false
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

        // Update the blog name
        update_blog_option( $new_blog_id, 'blogname', $source_blog_details->blogname );

        // And set copier arguments
        $result = copier_set_copier_args( $source_blog_id, $new_blog_id );

        return $new_blog_id;
    }

}
