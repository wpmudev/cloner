<?php

/**
 * Copy Menus from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Menus' ) ) {

    class Site_Copier_Menus extends Site_Copier {

        private static $origin_menu_item_id;

        public function get_default_args() {
            return array(
                'posts_mapping' => array(),
                'menu_id' => false,
            );
        }

        public function change_insert_post_ID( $data, $postarr ) {
            if ( !empty( self::$origin_menu_item_id ) && 'nav_menu_item' === $data['post_type'] ) {
                $data['ID'] = self::$origin_menu_item_id;
            }

            return $data;
        }



        public function copy() {

            if ( $this->args['menu_id'] === false )
                return new WP_Error( 'wrong_menu', __( 'No Menus to Copy', WPMUDEV_COPIER_LANG_DOMAIN ) );

            // Deprecated
            do_action( 'blog_templates-copying_menu', $this->source_blog_id, get_current_blog_id() );

            /**
             * Fires before menus are copied.
             *
             * @param Integer $this->source_blog_id Source Blog ID from where we are copying the menu
             * @param Integer $current_blog_id Blog ID where we are copying the menu
             * @param Integer $menu_id. The key of the array got from wp_get_nav_menus()
             */
            do_action( 'wpmudev_copier-copying_menu', $this->source_blog_id, get_current_blog_id(), $this->args['menu_id'] );

            // Get the source menus and their menu items
            switch_to_blog( $this->source_blog_id );
            $source_menus = wp_get_nav_menus();

            $source_menu = false;
            foreach ( $source_menus as $_source_menu ) {
                if ( $_source_menu->term_id == $this->args['menu_id'] ) {
                    $source_menu = $_source_menu;
                    $source_menu->items = wp_get_nav_menu_items( $source_menu->term_id );
                    $source_site_url = home_url();
                }
            }

            restore_current_blog();

            if ( ! $source_menu )
                return new WP_Error( 'wrong_menu', sprintf( __( 'There was an error trying to copy the menu. ID: ', WPMUDEV_COPIER_LANG_DOMAIN ), $this->args['menu_id'] ) );
            
            // Array that saves relationships to remap parents later
            $menu_items_remap = array();

            // Now copy the menu

            // Create a new menu object
            $menu_args = array(
                'menu-name' => $source_menu->name,
                'description' => $source_menu->description
            );

            // Insert a new menu
            $new_menu_id = wp_update_nav_menu_object( 0, $menu_args );

            if ( ! $new_menu_id || is_wp_error( $new_menu_id ) )
                return new WP_Error( 'insert_menu_error', sprintf( __( 'There was an error trying to copy the menu. ID: ', WPMUDEV_COPIER_LANG_DOMAIN ), $this->args['menu_id'] ) );

            add_filter( 'wp_insert_post_data', array( $this, 'change_insert_post_ID' ), 10 ,2 );

            foreach ( $source_menu->items as $menu_item ) {
                self::$origin_menu_item_id = $menu_item->ID;

                $new_item_args = array(
                    'menu-item-object' => $menu_item->object,
                    'menu-item-type' => $menu_item->type,
                    'menu-item-title' => $menu_item->title,
                    'menu-item-description' => $menu_item->description,
                    'menu-item-attr-title' => $menu_item->attr_title,
                    'menu-item-position' => $menu_item->menu_order,
                    'menu-item-target' => $menu_item->target,
                    'menu-item-classes' => $menu_item->classes,
                    'menu-item-xfn' => $menu_item->xfn,
                    'menu-item-status' => $menu_item->post_status,
                    'menu-item-url' => $menu_item->url
                );

                if ( is_array( $new_item_args['menu-item-classes'] ) )
                    $new_item_args['menu-item-classes'] = implode( ' ', $new_item_args['menu-item-classes'] );


                if ( 'custom' != $menu_item->type ) {
                    // If not custom, try to link the real object (post/page/whatever)
                    if ( 'post_type' == $new_item_args['menu-item-type'] ) {
                        $new_item_args['menu-item-object-id'] = 0;
                        if ( isset( $this->args['posts_mapping'][ $menu_item->object_id ] ) )
                            $new_item_args['menu-item-object-id'] = $this->args['posts_mapping'][ $menu_item->object_id ];                            
                    }
                    elseif ( 'taxonomy' == $new_item_args['menu-item-type'] ) {
                        // Let's grab the source term slug. We might have copied it, who knows?
                        switch_to_blog( $this->source_blog_id );
                        $term = get_term( $menu_item->object_id, $menu_item->object );
                        restore_current_blog();

                        if ( ! $term )
                            continue;

                        $new_blog_term = get_term_by( 'slug', $term->slug, $menu_item->object );

                        if ( ! $new_blog_term )
                            continue;

                        // We have found the term in the new blog
                        $new_item_args['menu-item-object-id'] = $new_blog_term->term_id;

                    }
                }
                else {
                    $new_item_args['menu-item-url'] = str_replace( $source_site_url, home_url(), $menu_item->url );
                }

                // And insert/update the menu item
                $new_menu_item_id = @wp_update_nav_menu_item( $new_menu_id, 0, $new_item_args );

                if ( ! $new_menu_item_id || is_wp_error( $new_menu_item_id ) )
                    continue;

                // Also, map the menu item
                $menu_items_remap[ $menu_item->ID ] = $new_menu_item_id;
            }

            remove_filter( 'wp_insert_post_data', array( $this, 'change_insert_post_ID' ) );

            // Now remap the parents
            $items = wp_get_nav_menu_items( $new_menu_id, 'nav_menu' );

            if ( ! empty( $items ) ) {
                foreach ( $source_menu->items as $source_menu_item ) {
                    
                    if ( empty( $source_menu_item->menu_item_parent ) )
                        continue;

                    // Search the new menu item that is mapped to the source menu item
                    $item_correspondence = false;
                    foreach ( $items as $item ) {
                        if ( $item->ID == $menu_items_remap[ $source_menu_item->ID ] )
                            $item_correspondence = $item;
                    }
                    
                    if ( ! $item_correspondence )
                        continue;

                    if ( ! isset( $menu_items_remap[ $source_menu_item->menu_item_parent ] ) )
                        continue;

                    $item_args = array(
                        'menu-item-object-id' => $item_correspondence->object_id,
                        'menu-item-object' => $item_correspondence->object,
                        'menu-item-type' => $item_correspondence->type,
                        'menu-item-title' => $item_correspondence->title,
                        'menu-item-description' => $item_correspondence->description,
                        'menu-item-position' => $item_correspondence->menu_order,
                        'menu-item-attr-title' => $item_correspondence->attr_title,
                        'menu-item-target' => $item_correspondence->target,
                        'menu-item-classes' => $item_correspondence->classes,
                        'menu-item-xfn' => $item_correspondence->xfn,
                        'menu-item-status' => $item_correspondence->post_status,
                        'menu-item-parent-id' => $menu_items_remap[ $source_menu_item->menu_item_parent ],
                        'menu-item-url' => $item_correspondence->url
                    );

                    @wp_update_nav_menu_item( $new_menu_id, $item_correspondence->db_id, $item_args );
                }
            }

            $new_menu = wp_get_nav_menu_object( $new_menu_id );

            // If there's a menu widget in the sidebar we may need to set the new category ID
            $widget_menu_settings = get_option( 'widget_nav_menu' );

            if ( is_array( $widget_menu_settings ) ) {

                $new_widget_menu_settings = $widget_menu_settings;

                foreach ( $widget_menu_settings as $widget_key => $widget_settings ) {
                    if ( ! empty( $widget_settings['nav_menu'] ) && $this->args['menu_id'] == $widget_settings['nav_menu'] ) {
                        $new_widget_menu_settings[ $widget_key ]['nav_menu'] = $new_menu_id;
                    }
                }

                update_option( 'widget_nav_menu', $new_widget_menu_settings );
            }


            /**
             * Fired after a menu has been cloned
             *
             * @param array $args Array of arguments passed to this class
             * @param integer $new_menu_id ID of the new menu
             */
            do_action( 'wpmudev_copier-copied_menu', $this->args, $new_menu_id, $this->source_blog_id );


            return array(
                'menu_name' => $new_menu->name,
                'menu_id' => $new_menu->term_id
            );
        }

        /**
         * Set the menu locations
         * 
         * As menus IDs have changed we need to remap the menu locations
         * 
         * @param Integer $source_blog_id 
         * @param Array $menu_mapping Relationships between source menu ID and destination menu ID
         */
        public static function set_menu_locations( $source_blog_id, $menu_mapping ) {

            // Set menu locations    
            switch_to_blog( $source_blog_id );
            $source_menu_locations = get_theme_mod( 'nav_menu_locations', array() );
            restore_current_blog();        

            $new_menu_locations = $source_menu_locations;
            foreach ( $source_menu_locations as $location => $menu_id ) {
                if ( ! isset( $menu_mapping[ $menu_id ] ) )
                    continue;

                $new_menu_locations[ $location ] = $menu_mapping[ $menu_id ];
            }
            set_theme_mod( 'nav_menu_locations', $new_menu_locations );
        }

        /**
         * Set the menu options
         * 
         * As menus IDs have changed we need to remap the menu options
         * 
         * @param Integer $source_blog_id 
         * @param Array $menu_mapping Relationships between source menu ID and destination menu ID
         */
        public static function set_menu_options( $source_blog_id, $menu_mapping ) {

            switch_to_blog( $source_blog_id );
            $source_menu_options = get_option( 'nav_menu_options', array() );
            restore_current_blog();    

            // Set menu options
            $new_menu_options = $source_menu_options;
            if ( isset( $source_menu_options['auto_add'] ) ) {
                foreach ( $source_menu_options['auto_add'] as $key => $menu_id ) {
                    if ( ! isset( $menu_mapping[ $menu_id ] ) )
                        continue;

                    $new_menu_options['auto_add'][ $key ] = $menu_mapping[ $menu_id ];
                }
            }
            update_option( 'nav_menu_options', $new_menu_options );
        }

    }
    
}