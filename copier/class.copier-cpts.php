<?php

// Include the parent class
include_once( 'class.copier-post-types.php' );

/**
 * Copy Posts from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Posts' ) ) {
	class Site_Copier_CPTs extends Site_Copier_Post_Types {

		public function __construct( $source_blog_id, $template, $args = array(), $user_id = 0 ) {
			global $wpdb;

			parent::__construct( $source_blog_id, $template, $args, $user_id );

			// Get all posts types except menus, attachments, pages and posts
			$exclude_post_types = '("' . implode( '","', array( 'page', 'post', 'attachment', 'nav_menu_item', 'revision' ) ) . '")';

			switch_to_blog( $this->source_blog_id );
			$post_types = $wpdb->get_col( "SELECT DISTINCT post_type FROM $wpdb->posts WHERE post_type NOT IN $exclude_post_types" );
			restore_current_blog();

			$post_types = apply_filters( 'wpmudev_copier_copy_post_types', $post_types, $source_blog_id, $args, $user_id, $template );

			$this->log( 'Copying Post Types:' );
			$this->log( $post_types );

			if ( ! empty( $post_types ) ) {
				$this->type = $post_types;
			}

			add_action( 'wpmudev_copier-copied-posts', array( $this, 'copy_network_tables_opts' ), 10, 5 );

		}

		/**
         * Fires after all CPTs have been copied. 
         * Useful for copying options from custom network-wide tables that associate blog ids, such as Hustle
         *
         * @param Integer $source_blog_id Blog ID from where we are copying the post.
         * @param Integer $posts_mapping Map of posts [source_post_id] => $new_post_id.
         * @param Integer $user_id Post Author.
         * @param Array $template Only applies when using New Blog Templates. Includes the template attributes
         * @param String $type Post Type ( post, page or cpt )
         */
		public function copy_network_tables_opts( $source_blog_id, $posts_mapping, $user_id, $template, $type ) {
			
			if ( $type != $this->type ) {
				return;
			}

			global $wpdb;

			/*
			* Clone Hustle modules and modules meta
			*/
			$hustle_plugin	 			= 'hustle' . DIRECTORY_SEPARATOR . 'opt-in.php';
			$hustle_modules_table 		= $wpdb->base_prefix . 'hustle_modules';
			$hustle_modules_meta_table 	= $wpdb->base_prefix . 'hustle_modules_meta';
			$new_blog_id 				= get_current_blog_id();

			/*
			* No need to continue if Huslte plugin is not active in source blog
			* Also make sure these DB tables exist. May seem like a waste of resources, but these tables appeared after Hustle v 3.x
			*/
			switch_to_blog( $source_blog_id );
			if ( ! is_plugin_active( $hustle_plugin ) ||
				$wpdb->get_var("SHOW TABLES LIKE '{$hustle_modules_table}'") != $hustle_modules_table ||
				$wpdb->get_var("SHOW TABLES LIKE '{$hustle_modules_meta_table}'") != $hustle_modules_meta_table
			) {
				restore_current_blog();
				return;
			}
			restore_current_blog();

			$hustle_modules = $wpdb->get_results( 
				$wpdb->prepare(
					"SELECT * FROM {$hustle_modules_table} WHERE blog_id=%d" 
					, $source_blog_id
				)
			);

			if ( ! empty( $hustle_modules ) ) {

				// Foreach modules get module meta and insert with new module id
				foreach ($hustle_modules as $key => $hustle_module ) {

					$module_meta = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$hustle_modules_meta_table} WHERE module_id=%d" 
							, $hustle_module->module_id
						)
					);

					$wpdb->insert( $hustle_modules_table, 
						array(
						    'blog_id' 		=> $new_blog_id,
						    'module_name' 	=> $hustle_module->module_name,
						    'module_type' 	=> $hustle_module->module_type,
						    'active'		=> $hustle_module->active,
						    'test_mode'		=> $hustle_module->test_mode
						),
						array(
							'%d',
							'%s',
							'%s',
							'%d',
							'%d'
						)
					);

					$new_module_id = $wpdb->insert_id;

					if ( ! is_wp_error( $new_module_id ) && ! empty( $module_meta ) ){
						foreach ( $module_meta as $key => $meta ) {

							$meta = apply_filters( 'wpmudev_copier_copy_hustle_meta', $meta );
							if ( $meta ){
								$wpdb->insert( $hustle_modules_meta_table, 
									array(
									    'module_id' 	=> $new_module_id,
									    'meta_key' 		=> $meta->meta_key,
									    'meta_value' 	=> $meta->meta_value								    
									),
									array(
										'%d',
										'%s',
										'%s'
									)
								);
							}
						}
					}

				} // END Foreach module
			}// END if ( ! empty( $hustle_modules ) )

		}

		public function get_default_args() {
			return array(
				'update_date' => false
			);
		}

	}
}
