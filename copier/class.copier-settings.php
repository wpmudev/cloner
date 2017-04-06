<?php

/**
 * Copy Settings from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Settings' ) ) {
    class Site_Copier_Settings extends Site_Copier {

    	public function get_default_args() {}

    	public function copy() {
    		global $wpdb;

            $start_time = $this->_get_microtime();

            wp_cache_delete( 'notoptions', 'options' );
            wp_cache_delete( 'alloptions', 'options' );

            do_action( 'wpmudev_copier_before_copy_settings', $this->source_blog_id );

            $source_blog_user_roles = $wpdb->get_blog_prefix( $this->source_blog_id ) . 'user_roles';
    		$exclude_settings = array(
                'siteurl',
                'blogname',
                'admin_email',
                'new_admin_email',
                'home',
                'upload_path',
                'db_version',
                'secret',
                'fileupload_url',
                'nonce_salt',
                'copier-pending',
                'stylesheet',
                'active_plugins',
                $source_blog_user_roles
            );

            /**
             * Filter the excclude settings Array.
             * 
             * Those settings names included in the array will not
             * be copied to the destination blog.
             *
             * @param Array $exclude_settings Exclude Settings list.
             */
            $exclude_settings = apply_filters( 'wpmudev_copier_exclude_settings', $exclude_settings );

            $the_options = $wpdb->get_col( "SELECT option_name FROM $wpdb->options" );
            $the_options = apply_filters( 'wpmudev_copier_delete_options', $the_options );
            $this->log( 'class.copier-post-types.php. Deleting ' . count( $the_options ) . ' options' );
            foreach ( $the_options as $option_name ) {
                if ( ! in_array( $option_name, $exclude_settings ) ) {
                    // Better use delete_option instead of doing it directly in DB
                    // This will clean  cache if needed
                    $deleted = delete_option( $option_name );
                }
            }

            $exclude_settings = esc_sql( $exclude_settings );
            $exclude_settings_where = "`option_name` != '" . implode( "' AND `option_name` != '", $exclude_settings ) . "'";
            
            //$exclude_settings = apply_filters( 'blog_template_exclude_settings', $exclude_settings_where );
            //$wpdb->query( "DELETE FROM $wpdb->options WHERE $exclude_settings_where" );

            if ( $wpdb->last_error ) {
                $this->log( 'class.copier-settings.php. Error copying settings: ' . $wpdb->last_error );
                return new WP_Error( 'settings_error', __( 'Error copying settings', WPMUDEV_COPIER_LANG_DOMAIN ) );
            }

            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            switch_to_blog( $this->source_blog_id );
            $src_blog_settings = $wpdb->get_results( "SELECT * FROM $wpdb->options WHERE $exclude_settings_where" );
            $template_prefix = $wpdb->prefix;

            // Get the source theme mods
            $themes_mods = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'theme_mods_%' ");

            // Get the source active theme
            $template_theme = wp_get_theme();

            // List of active plugins
            $all_plugins = get_plugins();
            $source_plugins = array();
            foreach( $all_plugins as $plugin_slug => $plugin ) {
                if ( is_plugin_active( $plugin_slug ) )
                    $source_plugins[] = $plugin_slug;
            }

            restore_current_blog();       


            $new_prefix = $wpdb->prefix;

            $this->log( 'class.copier-post-types.php. Copyng ' . count( $src_blog_settings ) . ' options' );
            foreach ( $src_blog_settings as $row ) {
                
                //Make sure none of the options are using wp_X_ convention, and if they are, replace the value with the new blog ID
                $row->option_name = str_replace( $template_prefix, $new_prefix, $row->option_name );
                //if ( 'sidebars_widgets' != $row->option_name ) /* <-- Added this to prevent unserialize() call choking on badly formatted widgets pickled array */
                    //$row->option_value = str_replace( $template_prefix, $new_prefix, $row->option_value );

                // Deprecated
                $row = apply_filters( 'blog_templates-copy-options_row', $row, $this->template, get_current_blog_id(), $this->user_id );

                /**
                 * Filter a single setting row for database insertion.
                 * 
                 * @param Array $row Setting row prepared for database.
                 * @param Integer $source_blog_id Source Blog ID.
                 */
                $row = apply_filters( 'wpmudev_copier-copy-options_row', $row, $this->source_blog_id );

                if ( ! $row )
                    continue; // Prevent empty row insertion

                wp_cache_delete( $row->option_name, 'options' );

                $added = add_option( $row->option_name, maybe_unserialize( $row->option_value ), null, $row->autoload );

                if ( ! $added )
                   $updated = update_option( $row->option_name, maybe_unserialize( $row->option_value ) );               
                

            }

            // Now the user roles
            $user_roles = get_blog_option( $this->source_blog_id, $source_blog_user_roles );
            if ( $user_roles ) {
                $destination_user_roles = $wpdb->prefix . 'user_roles';
                update_option( $destination_user_roles, $user_roles );
            }
            
            // Activate plugins
            $deactivate_plugins = array();
            foreach( $all_plugins as $plugin_slug => $plugin ) {
                if ( ! in_array( $plugin_slug, $source_plugins ) && is_plugin_active( $plugin_slug ) )
                    $deactivate_plugins[] = $plugin_slug;
            }
            deactivate_plugins( $deactivate_plugins, false, false );

            foreach ( $source_plugins as $plugin_slug ) {
                if ( ! is_plugin_active( $plugin_slug ) )
                    activate_plugin( $plugin_slug, null, false, true );
            }

            // We are going to switcth the theme manually
            switch_theme( $template_theme->get_stylesheet() );

            // Themes mods
            foreach ( $themes_mods as $mod ) {
                $theme_slug = str_replace( 'theme_mods_', '', $mod->option_name );
                $mods = maybe_unserialize( $mod->option_value );

                if ( isset( $mods['nav_menu_locations'] ) )
                    unset( $mods['nav_menu_locations'] );

                if ( 
                    apply_filters( 'nbt_change_attachments_urls', true ) // Deprecated
                    /** This filter is documented in class.copier-attachment.php */
                    && apply_filters( 'wpmudev_copier_change_attachments_urls', true )
                )
                    array_walk_recursive( $mods, array( &$this, 'set_theme_mods_url' ), array( $this->source_blog_id, get_current_blog_id() ) );
                
                update_option( "theme_mods_$theme_slug", $mods );  
            }

            // Set blog status
            $source_blog_details = get_blog_details( $this->source_blog_id );

            if ( ! empty( $source_blog_details ) ) {
                $source_blog_details = (array)$source_blog_details;
                extract( $source_blog_details );

                update_blog_status( get_current_blog_id(), 'public', $public );
                update_blog_status( get_current_blog_id(), 'archived', $archived );
                update_blog_status( get_current_blog_id(), 'mature', $mature );
                update_blog_status( get_current_blog_id(), 'spam', $spam );
                update_blog_status( get_current_blog_id(), 'deleted', $deleted );
            }        

			if ( in_array( 'admin_email', $exclude_settings ) ) {
				$source_admin_email = get_blog_option( $this->source_blog_id, 'admin_email' );
				update_option( 'admin_email', $source_admin_email );
				delete_option( 'new_admin_email' );
			}
            
            // Deprecated
            do_action( 'blog_templates-copy-options', $this->template,$this->source_blog_id, $this->user_id );

            /**
             * Fires before menus are copied.
             *
             * @param Integer $source_blog_id Source Blog ID from where we are copying the settings
             * @param Integer $user_id User ID that created the blog.
             * @param Array $template Only applies when using New Blog Templates. Includes the template attributes
             */
            do_action( 'wpmudev_copier-copy-options', $this->source_blog_id, $this->user_id, $this->template );

            $this->log( 'Settings copy. Elapsed time: ' . ( $this->_get_microtime() - $start_time ) );
            return true;
    	}

        function set_theme_mods_url( &$item, $key, $userdata = array() ) {
            $template_blog_id = $userdata[0];
            $new_blog_id = $userdata[1];


            if ( is_object( $item ) && ! empty( $item->attachment_id ) ) {
                // Let's copy this attachment and replace it
                $args = array(
                    'attachment_id' => $item->attachment_id
                );
                $attachment_copier = copier_get_copier( 'attachment', $this->source_blog_id, $args, $this->user_id, $this->template );
                $result = $attachment_copier->copy();
                if ( ! is_wp_error( $result ) ) {
                    $attachment_id = $result['new_attachment_id'];

                    add_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
                    $url = wp_get_attachment_url( $attachment_id );
                    remove_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
                    
                    $item->attachment_id = $attachment_id;
                    $item->url = $url;
                    $item->thumbnail_url = $url;
                }
            }


        }

    }
}