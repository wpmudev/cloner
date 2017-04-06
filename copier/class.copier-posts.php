<?php

// Include the parent class
include_once( 'class.copier-post-types.php' );

/**
 * Copy Posts from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Posts' ) ) {
	class Site_Copier_Posts extends Site_Copier_Post_Types {

		public function __construct( $source_blog_id, $template, $args = array(), $user_id = 0 ) {
			parent::__construct( $source_blog_id, $template, $args, $user_id );
			$this->type = 'post';
			add_filter( 'wpmudev_copier_get_source_posts_args', array( $this, 'set_get_source_posts_args' ) );
		}

		public function get_default_args() {
			return array(
				'categories' => 'all',
				'update_date' => false,
				'batch_size' => false, // Allows to copy posts in batches
                'batch_page' => false // Indicates what number of batch are we trying to copy
			);
		}

		/**
		 * Set WP_Query arguments in the parent class
		 * 
		 * @param Array $args Current arguments
		 * @return Array New arguments
		 */
		public function set_get_source_posts_args( $args ) {
			global $wpdb;

			if ( isset( $this->args['categories'] ) && is_array( $this->args['categories'] ) && count( $this->args['categories'] ) > 0 ) {
				// Are we only copying a few categories?
				$args['category__in'] = $this->args['categories'];
			}
			
			return $args;
		}	


	}
}