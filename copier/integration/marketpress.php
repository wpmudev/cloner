<?php

if ( ! class_exists( 'Marketpress_Cloner_Integration' ) ) {

	class Marketpress_Cloner_Integration {

		private static $intance = null;

		public static function get_instance() {

			if ( is_null( self::$intance ) ) {
				self::$intance = new Marketpress_Cloner_Integration();
			}

			return self::$intance;
		}

		private function __construct() {
			add_filter( 'wpmudev_copier_get_source_posts_args', array( $this, 'mp_orders_status_args' ), 10 );				
		}

		/**
		* @param array $args The default args
		*
		* @return array
        */
		public function mp_orders_status_args( $args ) {

			if ( 
				apply_filters( 'wpmudev_copier_include_mp_orders', true, $args ) && 
				isset( $args['post_type'] ) &&
				$this->post_type_included( $args['post_type'], 'mp_order' ) )
			{	
				$post_status = isset( $args['post_status'] ) ? $args['post_status'] : '';
				$args['post_status'] = $this->extend_post_statuses( $post_status );

			}

			return $args;
		}

		/**
		* @param array|string $current_types The post types included in this clone
		* @param string $search_type The post type we want to check if exists in this clone
		*
		* @return bool
        */
		public function post_type_included( $current_types, $search_type ) {

			$type_included = false;

			if ( is_array( $current_types ) ) {
				$type_included = in_array( $search_type, $current_types );	
			}
			else {
				$type_included = ( $type_included == $current_types );
			}

			return $type_included;
		}

		/**
		* @param array $_statuses Default statuses used in query to fetch posts
		*
		* @return array
        */
		public function extend_post_statuses( $_statuses ) {

			$statuses = array(
				'order_received',
				'order_paid',
				'order_shipped',
				'order_closed'
			);

			if ( ! is_array( $_statuses ) ) {
				array_unshift( $statuses, $_statuses );
			}
			else {
				$statuses = array_merge( $statuses, $_statuses );
			}

			return $statuses;
		}

	}

	if ( ! function_exists( 'marketpress_cloner_integration' ) ) {
		
		function marketpress_cloner_integration() {
			return Marketpress_Cloner_Integration::get_instance();
		}

		function marketpress_maybe_load_cloner_integration( $copy, $copier ) {

			if ( 'cpts' == $copy ) {
				marketpress_cloner_integration();
			}
			
		}

		add_action( 'wpmudev_copier-before-copy', 'marketpress_maybe_load_cloner_integration', 10, 2 );
	}

}
