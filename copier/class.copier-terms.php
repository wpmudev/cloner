<?php

if ( ! class_exists( 'Site_Copier_Terms' ) ) {
	class Site_Copier_Terms extends Site_Copier {

		public function get_default_args() {
			return array(
				'update_relationships' => false, // all or array with post, page or any post_type
				'posts_mapping' => array()
			);
		}


		public function copy() {
			global $wpdb;

			$start_time = $this->_get_microtime();

			if ( ! function_exists( 'wp_delete_link' ) )
				include_once( ABSPATH . 'wp-admin/includes/bookmark.php' );

			// Remove current terms
			$taxonomies = get_taxonomies();
			if ( isset( $taxonomies['nav_menu'] ) )
				unset( $taxonomies['nav_menu'] );

			$all_terms = get_terms( $taxonomies, array( 'hide_empty' => false ) );
			$this->log( 'class.copier-terms.php. Deleting ' . count( $all_terms ) . ' terms' );
			foreach ( $all_terms as $term ) {
				$result = wp_delete_term( $term->term_id, $term->taxonomy );
			}


			unset( $all_terms );

			// Remove current links
			$all_links = get_bookmarks();
			foreach ( $all_links as $link ) {
				wp_delete_link( $link->link_id );
			}

			unset( $all_links );

			switch_to_blog( $this->source_blog_id );

			// Need to check what post types are in the source blog
			$exclude_post_types = '("' . implode( '","', array( 'nav_menu_item' ) ) . '")';
			$post_types = $wpdb->get_col( "SELECT DISTINCT post_type FROM $wpdb->posts WHERE post_type NOT IN $exclude_post_types" );

			//Filter with our custom post types
			$source_posts_ids = get_posts( apply_filters( 'wpmudev_copier_get_source_posts_args', array(
				'ignore_sticky_posts' => true,
				'posts_per_page' => -1,
				'post_type' => $post_types,
				'fields' => 'ids',
				'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ),
			) ) );

			$_taxonomies = $wpdb->get_col( "SELECT DISTINCT taxonomy FROM $wpdb->term_taxonomy" );
	        $taxonomies = array();
	        foreach ( $_taxonomies as $taxonomy ) {
		        $taxonomies[ $taxonomy ] = $taxonomy;

		        if ( ! taxonomy_exists( $taxonomy ) ) {
			        // Register the taxonomy if it does not exit so we can insert terms
			        register_taxonomy( $taxonomy, $post_types );
		        }

	        }

	        if ( isset( $taxonomies['nav_menu'] ) )
				unset( $taxonomies['nav_menu'] );

			$taxonomies = apply_filters( 'wpmudev_copier_copy_taxonomies', $taxonomies, $this );
			$source_terms = get_terms( $taxonomies, array( 'orderby' => 'id', 'get' => 'all' ) );

			$this->log( 'class.copier-terms.php. Taxonomies to copy:' );
			$this->log( $taxonomies );

			$this->log( 'class.copier-terms.php. Copying ' . count( $source_terms ) . ' terms' );

			$_source_links = get_bookmarks();
			$source_links = array();
			foreach ( $_source_links as $source_link ) {
				$item = $source_link;
				$object_terms = wp_get_object_terms( $source_link->link_id, array( 'link_category' ) );
				if ( ! empty( $object_terms ) && ! is_wp_error( $object_terms ) )
					$item->terms = $object_terms;

				$source_links[] = $item;

			}

			restore_current_blog();

			// Now insert the links
			$mapped_links = array();
			foreach ( $source_links as $link ) {
				$new_link = (array)$link;
				unset( $new_link['link_id'] );

				$new_link_id = wp_insert_link( $new_link );
				if ( ! is_wp_error( $new_link_id ) )
					$mapped_links[ $link->link_id ] = $new_link_id;
				
			}

			// Deprecated
			do_action( 'blog_templates-copy-links', $this->template, get_current_blog_id(), $this->user_id );

			/**
             * Fires after the links have been copied.
             *
             * @param Integer $user_id Blog Administrator ID.
             * @param Integer $source_blog_id Source Blog ID from where we are copying the links.
             * @param Array $template Only applies when using New Blog Templates. Includes the template attributes.
             */
			do_action( 'wpmudev_copier-copy-links', $this->user_id, $this->source_blog_id, $this->template );
			
		
			// Insert the terms
			$mapped_terms = array();
			foreach ( $source_terms as $term ) {

				$term_args = array(
					'description' => $term->description,
					'slug' => $term->slug,
					'import_id' => $term->term_id
				);

				$new_term = $this->wp_insert_term( $term->name, $term->taxonomy, $term_args );
				do_action( 'wpmudev_copier_insert_term', $new_term, $term );
				
				if ( is_wp_error( $new_term ) ) {
					// Usually Uncategorized cannot be deleted, we need to check if it's already in the destination blog and map it
					$error_code = $new_term->get_error_code();
					if ( 'term_exists' === $error_code ) {
						$new_term = get_term_by( 'slug', $term->slug, $term->taxonomy );
						if ( ! is_object( $new_term ) || is_wp_error( $new_term ) )
							continue;

						$new_term = array(
							'term_id' => $new_term->term_id
						);
					}

				}

				if ( ! is_wp_error( $new_term ) ) {
					// Check if the term has a parent
					$mapped_terms[ $term->term_id ] = absint( $new_term['term_id'] );
				}

			}

			// Now update terms parents
			foreach ( $source_terms as $term )
				if ( ! empty( $term->parent ) && isset( $mapped_terms[ $term->parent ] ) && isset( $mapped_terms[ $term->term_id ] ) )
					wp_update_term( $mapped_terms[ $term->term_id ], $term->taxonomy, array( 'parent' => $mapped_terms[ $term->parent ] ) );

			unset( $source_terms );

			// Deprecated
			do_action( 'blog_templates-copy-terms', $this->template, get_current_blog_id(), $this->user_id, $mapped_terms );

			// Update posts term relationships
			if ( $this->args['update_relationships'] ) {

				// Assign link categories
				if ( isset( $taxonomies['link_category'] ) ) {
					// There are one or more link categories
					// Let's assigned them					
					if ( ! empty( $source_links ) )
						$this->assign_terms_to_links( $source_links, $mapped_terms, $mapped_links );
				}


				if ( is_array( $this->args['update_relationships'] ) )
					$args['post_type'] = $this->args['update_relationships'];

				if ( ! empty( $source_posts_ids ) ) {

					// Remove the link categories for posts
					$posts_taxonomies = $taxonomies;
					if ( isset( $posts_taxonomies['link_category'] ) )
						unset( $posts_taxonomies['link_category'] );

					$this->assign_terms_to_objects( $source_posts_ids, $posts_taxonomies, $mapped_terms );

				}

				unset( $source_posts_ids );

				
				// Deprecated
				do_action( 'blog_templates-copy-term_relationships', $this->template, get_current_blog_id(), $this->user_id );
				
			}

			/**
             * Fires after the terms have been copied.
             *
             * @param Integer $user_id Blog Administrator ID.
             * @param Integer $source_blog_id Source Blog ID from where we are copying the terms.
             * @param Array $template Only applies when using New Blog Templates. Includes the template attributes.
			 * @param Array $mapped_terms Relationship between source term IDs and new term IDs
             */
			do_action( 'wpmudev_copier-copy-terms', $this->user_id, $this->source_blog_id, $this->template, $mapped_terms );

			// If there's a links widget in the sidebar we may need to set the new category ID
	        $widget_links_settings = get_blog_option( $this->source_blog_id, 'widget_links' );

	        if ( is_array( $widget_links_settings ) ) {

		        $new_widget_links_settings = $widget_links_settings;

		        foreach ( $widget_links_settings as $widget_key => $widget_settings ) {
		            if ( ! empty( $widget_settings['category'] ) && isset( $mapped_terms[ $widget_settings['category'] ] ) ) {

		                $new_widget_links_settings[ $widget_key ]['category'] = $mapped_terms[ $widget_settings['category'] ];
		            }
		        }

	        	$updated = update_option( 'widget_links', $new_widget_links_settings );
	        }

			$this->log( 'Terms Copy. Elapsed time: ' .  ( $this->_get_microtime() - $start_time ) );

	        return true;
		}

		public function assign_terms_to_objects( $source_objects_ids, $taxonomies, $mapped_terms ) {

			$objects_ids = array();
			foreach ( $source_objects_ids as $source_object_id ) {
				if ( isset( $this->args['posts_mapping'][$source_object_id] ) )
					$objects_ids[] = $this->args['posts_mapping'][$source_object_id];
			}

			if ( empty( $objects_ids ) )
				return;

			$source_objects_terms = array();
			switch_to_blog( $this->source_blog_id );
			foreach ( $source_objects_ids as $object_id ) {
				$object_terms = wp_get_object_terms( $object_id, $taxonomies );
				if ( ! empty( $object_terms ) && ! is_wp_error( $object_terms ) )
					$source_objects_terms[ $object_id ] = $object_terms;
			}
			restore_current_blog();
			if ( ! empty( $source_objects_terms ) ) {
				// We just need to set the object terms with the remapped terms IDs
				foreach ( $source_objects_terms as $object_id => $source_object_terms ) {
					if ( ! isset( $this->args['posts_mapping'][ $object_id ] ) )
						continue;

					$new_object_id = $this->args['posts_mapping'][ $object_id ];
					
					$taxonomies = array_unique( wp_list_pluck( $source_object_terms, 'taxonomy' ) );

					foreach ( $taxonomies as $taxonomy ) {
						$source_terms_ids = wp_list_pluck( wp_list_filter( $source_object_terms, array( 'taxonomy' => $taxonomy ) ), 'term_id' );

						$new_terms_ids = array();
						foreach ( $source_terms_ids as $source_term_id ) {
							if ( isset( $mapped_terms[ $source_term_id ] ) )
								$new_terms_ids[] = $mapped_terms[ $source_term_id ];
						}

						// Set post terms
						wp_set_object_terms( $new_object_id, $new_terms_ids, $taxonomy );
					}
					
				}
			}
		}

		public function assign_terms_to_links( $source_links, $mapped_terms, $mapped_links ) {

			foreach ( $source_links as $source_link ) {
				$source_link_id = $source_link->link_id;
				$source_terms_ids = wp_list_pluck( $source_link->terms, 'term_id' );

				if ( ! isset( $mapped_links[ $source_link_id ] ) )
					continue;

				$new_link_id = $mapped_links[ $source_link_id ];

				$new_terms_ids = array();
				foreach ( $source_terms_ids as $source_term_id ) {
					if ( isset( $mapped_terms[ $source_term_id ] ) )
						$new_terms_ids[] = $mapped_terms[ $source_term_id ];
				}

				wp_set_object_terms( $new_link_id, $new_terms_ids, 'link_category' );
			}

		}


		function wp_insert_term( $term, $taxonomy, $args = array() ) {
			global $wp_version;

			if ( version_compare( $wp_version, '4.3', '>=' ) ) {
				return $this->wp_insert_term_wp_43( $term, $taxonomy, $args );
			}
			else {
				return $this->wp_insert_term_wp_pre_43( $term, $taxonomy, $args );
			}
		}

		function wp_insert_term_wp_43( $term, $taxonomy, $args = array() ) {
			global $wpdb;

			if ( ! taxonomy_exists($taxonomy) ) {
				return new WP_Error('invalid_taxonomy', __('Invalid taxonomy'));
			}
			/**
			 * Filter a term before it is sanitized and inserted into the database.
			 *
			 * @since 3.0.0
			 *
			 * @param string $term     The term to add or update.
			 * @param string $taxonomy Taxonomy slug.
			 */
			$term = apply_filters( 'pre_insert_term', $term, $taxonomy );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			if ( is_int($term) && 0 == $term ) {
				return new WP_Error('invalid_term_id', __('Invalid term ID'));
			}
			if ( '' == trim($term) ) {
				return new WP_Error('empty_term_name', __('A name is required for this term'));
			}
			$defaults = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
			$args = wp_parse_args( $args, $defaults );

			if ( $args['parent'] > 0 && ! term_exists( (int) $args['parent'] ) ) {
				return new WP_Error( 'missing_parent', __( 'Parent term does not exist.' ) );
			}
			$args['name'] = $term;
			$args['taxonomy'] = $taxonomy;
			$args = sanitize_term($args, $taxonomy, 'db');

			// expected_slashed ($name)
			$name = wp_unslash( $args['name'] );
			$description = wp_unslash( $args['description'] );
			$parent = (int) $args['parent'];

			$slug_provided = ! empty( $args['slug'] );
			if ( ! $slug_provided ) {
				$slug = sanitize_title( $name );
			} else {
				$slug = $args['slug'];
			}

			$term_group = 0;
			if ( $args['alias_of'] ) {
				$alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
				if ( ! empty( $alias->term_group ) ) {
					// The alias we want is already in a group, so let's use that one.
					$term_group = $alias->term_group;
				} elseif ( ! empty( $alias->term_id ) ) {
					/*
					 * The alias is not in a group, so we create a new one
					 * and add the alias to it.
					 */
					$term_group = $wpdb->get_var("SELECT MAX(term_group) FROM $wpdb->terms") + 1;

					wp_update_term( $alias->term_id, $taxonomy, array(
						'term_group' => $term_group,
					) );
				}
			}

			/*
			 * Prevent the creation of terms with duplicate names at the same level of a taxonomy hierarchy,
			 * unless a unique slug has been explicitly provided.
			 */
			if ( $name_match = get_term_by( 'name', $name, $taxonomy ) ) {
				$slug_match = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $slug_provided || $name_match->slug === $slug || $slug_match ) {
					if ( is_taxonomy_hierarchical( $taxonomy ) ) {
						$siblings = get_terms( $taxonomy, array( 'get' => 'all', 'parent' => $parent ) );

						$existing_term = null;
						if ( $name_match->slug === $slug && in_array( $name, wp_list_pluck( $siblings, 'name' ) ) ) {
							$existing_term = $name_match;
						} elseif ( $slug_match && in_array( $slug, wp_list_pluck( $siblings, 'slug' ) ) ) {
							$existing_term = $slug_match;
						}

						if ( $existing_term ) {
							return new WP_Error( 'term_exists', __( 'A term with the name provided already exists with this parent.' ), $existing_term->term_id );
						}
					} else {
						return new WP_Error( 'term_exists', __( 'A term with the name provided already exists in this taxonomy.' ), $name_match->term_id );
					}
				}
			}

			$slug = wp_unique_term_slug( $slug, (object) $args );

			$import_id = false;
			if ( isset( $args['import_id'] ) ) {
				// Try to insert the term with this ID
				$_result = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE term_id = %d", $args['import_id'] ) );

				if ( ! $_result )
					$import_id = absint( $args['import_id'] );

			}

			if ( $import_id ) {
				$term_id = $import_id;
				$result = $wpdb->insert( $wpdb->terms, compact( 'term_id', 'name', 'slug', 'term_group' ) );
			}
			else {
				$result = $wpdb->insert( $wpdb->terms, compact( 'name', 'slug', 'term_group' ) );
			}

			if ( false === $result ) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert term into the database' ), $wpdb->last_error );
			}

			$term_id = (int) $wpdb->insert_id;

			// Seems unreachable, However, Is used in the case that a term name is provided, which sanitizes to an empty string.
			if ( empty($slug) ) {
				$slug = sanitize_title($slug, $term_id);

				/** This action is documented in wp-includes/taxonomy.php */
				do_action( 'edit_terms', $term_id, $taxonomy );
				$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );

				/** This action is documented in wp-includes/taxonomy.php */
				do_action( 'edited_terms', $term_id, $taxonomy );
			}

			$tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

			if ( !empty($tt_id) ) {
				return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
			}
			$wpdb->insert( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent') + array( 'count' => 0 ) );
			$tt_id = (int) $wpdb->insert_id;

			/*
			 * Sanity check: if we just created a term with the same parent + taxonomy + slug but a higher term_id than
			 * an existing term, then we have unwittingly created a duplicate term. Delete the dupe, and use the term_id
			 * and term_taxonomy_id of the older term instead. Then return out of the function so that the "create" hooks
			 * are not fired.
			 */
			$duplicate_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id, tt.term_taxonomy_id FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON ( tt.term_id = t.term_id ) WHERE t.slug = %s AND tt.parent = %d AND tt.taxonomy = %s AND t.term_id < %d AND tt.term_taxonomy_id != %d", $slug, $parent, $taxonomy, $term_id, $tt_id ) );
			if ( $duplicate_term ) {
				$wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ) );
				$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );

				$term_id = (int) $duplicate_term->term_id;
				$tt_id   = (int) $duplicate_term->term_taxonomy_id;

				clean_term_cache( $term_id, $taxonomy );
				return array( 'term_id' => $term_id, 'term_taxonomy_id' => $tt_id );
			}

			// Clone term meta for WP >= 4.4
			if ( function_exists( 'get_term_meta' ) ) {
				switch_to_blog( $this->source_blog_id );
				$term_meta = get_term_meta( $term_id );
				restore_current_blog();
				foreach ( $term_meta as $meta_key => $meta_value ) {
					if ( is_array( $meta_value ) && count( $meta_value ) > 1 ) {
						foreach ( $meta_value as $value ) {
							add_term_meta( $term_id, $meta_key, maybe_unserialize( $value ), false );
						}
					}
					elseif ( is_array( $meta_value ) && count( $meta_value ) <= 1 ) {
						update_term_meta( $term_id, $meta_key, maybe_unserialize( $meta_value ) );
					}

				}
			}

			/**
			 * Fires immediately after a new term is created, before the term cache is cleaned.
			 *
			 * @since 2.3.0
			 *
			 * @param int    $term_id  Term ID.
			 * @param int    $tt_id    Term taxonomy ID.
			 * @param string $taxonomy Taxonomy slug.
			 */
			do_action( "create_term", $term_id, $tt_id, $taxonomy );

			/**
			 * Fires after a new term is created for a specific taxonomy.
			 *
			 * The dynamic portion of the hook name, `$taxonomy`, refers
			 * to the slug of the taxonomy the term was created for.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Term taxonomy ID.
			 */
			do_action( "create_$taxonomy", $term_id, $tt_id );

			/**
			 * Filter the term ID after a new term is created.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Taxonomy term ID.
			 */
			$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

			clean_term_cache($term_id, $taxonomy);

			/**
			 * Fires after a new term is created, and after the term cache has been cleaned.
			 *
			 * @since 2.3.0
			 *
			 * @param int    $term_id  Term ID.
			 * @param int    $tt_id    Term taxonomy ID.
			 * @param string $taxonomy Taxonomy slug.
			 */
			do_action( 'created_term', $term_id, $tt_id, $taxonomy );

			/**
			 * Fires after a new term in a specific taxonomy is created, and after the term
			 * cache has been cleaned.
			 *
			 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Term taxonomy ID.
			 */
			do_action( "created_$taxonomy", $term_id, $tt_id );

			return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
		}

		function wp_insert_term_wp_pre_43( $term, $taxonomy, $args = array() ) {
			global $wpdb;

			if ( ! taxonomy_exists($taxonomy) ) {
				return new WP_Error('invalid_taxonomy', __('Invalid taxonomy'));
			}
			/**
			 * Filter a term before it is sanitized and inserted into the database.
			 *
			 * @since 3.0.0
			 *
			 * @param string $term     The term to add or update.
			 * @param string $taxonomy Taxonomy slug.
			 */
			$term = apply_filters( 'pre_insert_term', $term, $taxonomy );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			if ( is_int($term) && 0 == $term ) {
				return new WP_Error('invalid_term_id', __('Invalid term ID'));
			}
			if ( '' == trim($term) ) {
				return new WP_Error('empty_term_name', __('A name is required for this term'));
			}
			$defaults = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
			$args = wp_parse_args( $args, $defaults );

			if ( $args['parent'] > 0 && ! term_exists( (int) $args['parent'] ) ) {
				return new WP_Error( 'missing_parent', __( 'Parent term does not exist.' ) );
			}
			$args['name'] = $term;
			$args['taxonomy'] = $taxonomy;
			$args = sanitize_term($args, $taxonomy, 'db');

			// expected_slashed ($name)
			$name = wp_unslash( $args['name'] );
			$description = wp_unslash( $args['description'] );
			$parent = (int) $args['parent'];

			$slug_provided = ! empty( $args['slug'] );
			if ( ! $slug_provided ) {
				$_name = trim( $name );
				$existing_term = get_term_by( 'name', $_name, $taxonomy );
				if ( $existing_term ) {
					$slug = $existing_term->slug;
				} else {
					$slug = sanitize_title( $name );
				}
			} else {
				$slug = $args['slug'];
			}

			$term_group = 0;
			if ( $args['alias_of'] ) {
				$alias = $wpdb->get_row( $wpdb->prepare( "SELECT term_id, term_group FROM $wpdb->terms WHERE slug = %s", $args['alias_of'] ) );
				if ( $alias->term_group ) {
					// The alias we want is already in a group, so let's use that one.
					$term_group = $alias->term_group;
				} else {
					// The alias isn't in a group, so let's create a new one and firstly add the alias term to it.
					$term_group = $wpdb->get_var("SELECT MAX(term_group) FROM $wpdb->terms") + 1;

					/**
					 * Fires immediately before the given terms are edited.
					 *
					 * @since 2.9.0
					 *
					 * @param int    $term_id  Term ID.
					 * @param string $taxonomy Taxonomy slug.
					 */
					do_action( 'edit_terms', $alias->term_id, $taxonomy );
					$wpdb->update($wpdb->terms, compact('term_group'), array('term_id' => $alias->term_id) );

					/**
					 * Fires immediately after the given terms are edited.
					 *
					 * @since 2.9.0
					 *
					 * @param int    $term_id  Term ID
					 * @param string $taxonomy Taxonomy slug.
					 */
					do_action( 'edited_terms', $alias->term_id, $taxonomy );
				}
			}

			if ( $term_id = term_exists($slug) ) {
				$existing_term = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM $wpdb->terms WHERE term_id = %d", $term_id), ARRAY_A );
				// We've got an existing term in the same taxonomy, which matches the name of the new term:
				if ( is_taxonomy_hierarchical($taxonomy) && $existing_term['name'] == $name && $exists = term_exists( (int) $term_id, $taxonomy ) ) {
					// Hierarchical, and it matches an existing term, Do not allow same "name" in the same level.
					$siblings = get_terms($taxonomy, array('fields' => 'names', 'get' => 'all', 'parent' => $parent ) );
					if ( in_array($name, $siblings) ) {
						if ( $slug_provided ) {
							return new WP_Error( 'term_exists', __( 'A term with the name and slug provided already exists with this parent.' ), $exists['term_id'] );
						} else {
							return new WP_Error( 'term_exists', __( 'A term with the name provided already exists with this parent.' ), $exists['term_id'] );
						}
					} else {
						$slug = wp_unique_term_slug($slug, (object) $args);
						if ( false === $wpdb->insert( $wpdb->terms, compact( 'name', 'slug', 'term_group' ) ) ) {
							return new WP_Error('db_insert_error', __('Could not insert term into the database'), $wpdb->last_error);
						}
						$term_id = (int) $wpdb->insert_id;
					}
				} elseif ( $existing_term['name'] != $name ) {
					// We've got an existing term, with a different name, Create the new term.
					$slug = wp_unique_term_slug($slug, (object) $args);
					if ( false === $wpdb->insert( $wpdb->terms, compact( 'name', 'slug', 'term_group' ) ) ) {
						return new WP_Error('db_insert_error', __('Could not insert term into the database'), $wpdb->last_error);
					}
					$term_id = (int) $wpdb->insert_id;
				} elseif ( $exists = term_exists( (int) $term_id, $taxonomy ) )  {
					// Same name, same slug.
					return new WP_Error( 'term_exists', __( 'A term with the name and slug provided already exists.' ), $exists['term_id'] );
				}
			} else {
				// This term does not exist at all in the database, Create it.
				$import_id = false;
				if ( isset( $args['import_id'] ) ) {
					// Try to insert the term with this ID
					$_result = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE term_id = %d", $args['import_id'] ) );

					if ( ! $_result )
						$import_id = absint( $args['import_id'] );

				}

				$slug = wp_unique_term_slug($slug, (object) $args);

				if ( $import_id ) {
					$term_id = $import_id;
					$result = $wpdb->insert( $wpdb->terms, compact( 'term_id', 'name', 'slug', 'term_group' ) );
				}
				else {
					$result = $wpdb->insert( $wpdb->terms, compact( 'name', 'slug', 'term_group' ) );
				}

				if ( false === $result ) {
					return new WP_Error('db_insert_error', __('Could not insert term into the database'), $wpdb->last_error);
				}
				$term_id = (int) $wpdb->insert_id;
			}

			// Seems unreachable, However, Is used in the case that a term name is provided, which sanitizes to an empty string.
			if ( empty($slug) ) {
				$slug = sanitize_title($slug, $term_id);

				/** This action is documented in wp-includes/taxonomy.php */
				do_action( 'edit_terms', $term_id, $taxonomy );
				$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );

				/** This action is documented in wp-includes/taxonomy.php */
				do_action( 'edited_terms', $term_id, $taxonomy );
			}

			$tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

			if ( !empty($tt_id) ) {
				return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
			}
			$wpdb->insert( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent') + array( 'count' => 0 ) );
			$tt_id = (int) $wpdb->insert_id;

			/**
			 * Fires immediately after a new term is created, before the term cache is cleaned.
			 *
			 * @since 2.3.0
			 *
			 * @param int    $term_id  Term ID.
			 * @param int    $tt_id    Term taxonomy ID.
			 * @param string $taxonomy Taxonomy slug.
			 */
			do_action( "create_term", $term_id, $tt_id, $taxonomy );

			/**
			 * Fires after a new term is created for a specific taxonomy.
			 *
			 * The dynamic portion of the hook name, $taxonomy, refers
			 * to the slug of the taxonomy the term was created for.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Term taxonomy ID.
			 */
			do_action( "create_$taxonomy", $term_id, $tt_id );

			/**
			 * Filter the term ID after a new term is created.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Taxonomy term ID.
			 */
			$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

			clean_term_cache($term_id, $taxonomy);

			/**
			 * Fires after a new term is created, and after the term cache has been cleaned.
			 *
			 * @since 2.3.0
			 */
			do_action( "created_term", $term_id, $tt_id, $taxonomy );

			/**
			 * Fires after a new term in a specific taxonomy is created, and after the term
			 * cache has been cleaned.
			 *
			 * @since 2.3.0
			 *
			 * @param int $term_id Term ID.
			 * @param int $tt_id   Term taxonomy ID.
			 */
			do_action( "created_$taxonomy", $term_id, $tt_id );

			return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
		}


	}

}