<?php

// Include the parent class
include_once( 'class.copier-post-types.php' );

/**
 * Copy Pages from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Pages' ) ) {
	class Site_Copier_Pages extends Site_Copier_Post_Types {

		public function __construct( $source_blog_id, $template, $args = array(), $user_id = 0 ) {
			parent::__construct( $source_blog_id, $template, $args, $user_id );
			$this->type = 'page';
			add_filter( 'wpmudev_copier_get_source_posts_args', array( $this, 'set_get_source_posts_args' ) );
		}

		public function get_default_args() {
			return array(
				'pages_ids' => 'all',
				'update_date' => false,
				'batch_size' => false, // Allows to copy posts in batches
                'batch_page' => false // Indicates what number of batch are we trying to copy
			);
		}

		public function copy() {
			$posts_mapping = parent::copy();

			// Remap the page on front and page for posts
			$page_on_front = get_option( 'page_on_front' );
			if ( false !== $page_on_front ) {
				if ( isset( $posts_mapping[ $page_on_front ] ) )
					update_option( 'page_on_front', $posts_mapping[ $page_on_front ] );
			}

			$page_for_posts = get_option( 'page_for_posts' );
			if ( false !== $page_for_posts ) {
				if ( isset( $posts_mapping[ $page_for_posts ] ) )
					update_option( 'page_for_posts', $posts_mapping[ $page_for_posts ] );
			}
			
			return $posts_mapping;
		}

		/**
		 * Set WP_Query arguments in the parent class
		 * 
		 * @param Array $args Current arguments
		 * @return Array New arguments
		 */
		public function set_get_source_posts_args( $args ) {
			global $wpdb;

			if ( is_array( $this->args['pages_ids'] ) && count( $this->args['pages_ids'] ) > 0 )
				$args['post__in'] = $this->args['pages_ids'];
			
			return $args;
		}	


	}
}