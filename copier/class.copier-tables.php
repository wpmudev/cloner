<?php

if ( ! class_exists( 'Site_Copier_Tables' ) ) {
    class Site_Copier_Tables extends Site_Copier {

    	public function get_default_args() {
    		return array(
                'tables' => array(),
                'create_tables' => false
            );
    	}

    	public function copy() {
    		global $wpdb;

            // Prefixes
            $new_prefix = $wpdb->prefix;
            $template_prefix = $wpdb->get_blog_prefix( $this->source_blog_id );

            $tables_to_copy = $this->args['tables'];

            // If create_tables = true, we'll need at least to create all the tables
            // Empty or not
            if ( $this->args['create_tables'] )
                $all_source_tables = wp_list_pluck( copier_get_additional_tables( $this->source_blog_id ), 'prefix.name' );
            else
                $all_source_tables = $tables_to_copy;

            // Deprecated
            $all_source_tables = apply_filters( 'nbt_copy_additional_tables', $all_source_tables );

            /**
             * Filter the source tables list.
             *
             * This list includes all the source tables that we are going to copy except
             * for the native WordPress tables.
             *
             * @param Array $all_source_tables Source tables list.
             */
            $all_source_tables = apply_filters( 'wpmudev_copier_copy_additional_tables', $all_source_tables );

            foreach ( $all_source_tables as $table ) {
                // Copy content too?
                $add = in_array( $table, $tables_to_copy );
                $table = esc_sql( $table );

                // MultiDB Hack
                if ( is_a( $wpdb, 'm_wpdb' ) )
                    $tablebase = end( explode( '.', $table, 2 ) );
                else
                    $tablebase = $table;

                $new_table = $new_prefix . substr( $tablebase, strlen( $template_prefix ) );

                $result = $wpdb->get_results( "SHOW TABLES LIKE '{$new_table}'", ARRAY_N );
                if ( ! empty( $result ) ) {
                    // The table is already present in the new blog
                    // Clear it
                    $this->clear_table( $new_table );

                    if ( $add ) {
                        // And copy the content if needed
                        $result = $this->copy_table( $new_table );
                        if ( is_wp_error( $result ) ) {
                            $wpdb->query( "ROLLBACK;" );
                            return $result;
                        }
                    }
                }
                else {
                    // The table does not exist in the new blog
                    // Let's create it
                    $create_script = current( $wpdb->get_col( 'SHOW CREATE TABLE ' . $table, 1 ) );

                    if ( $create_script && preg_match( '/\(.*\)/s', $create_script, $match ) ) {
                        $table_body = $match[0];
                        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$new_table} {$table_body}" );

                        if ( $add ) {
                            // And copy the content if needed
                            if ( is_a( $wpdb, 'm_wpdb' ) ) {
                                $rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
                                foreach ( $rows as $row ) {
                                    $wpdb->insert( $new_table, $row );
                                }
                            } else {
                                $wpdb->query( "INSERT INTO {$new_table} SELECT * FROM {$table}" );
                            }
                        }

                    }

                    if ( ! empty( $wpdb->last_error ) ) {
                        $error = new WP_Error( 'insertion_error', sprintf( __( 'Insertion Error: %s', WPMUDEV_COPIER_LANG_DOMAIN ), $wpdb->last_error ) );
                        $wpdb->query("ROLLBACK;");
                        return $error;
                    }
                }

            }

            $wpdb->query("COMMIT;");
            return true;
    	}

        function copy_table( $dest_table ) {
            global $wpdb;

            // Deprecated
            do_action( 'blog_templates-copying_table', $dest_table, $this->source_blog_id );

            /**
             * Fires before menus are copied.
             *
             * @param String $dest_table Destination table name
             * @param Integer $source_blog_id Source Blog ID from where we are copying the table.
             */
            do_action( 'wpmudev_copier-copying_table', $dest_table, $this->source_blog_id );

            $destination_prefix = $wpdb->prefix;

            //Switch to the template blog, then grab the values
            switch_to_blog( $this->source_blog_id );
            $template_prefix = $wpdb->prefix;
            $source_table = str_replace( $destination_prefix, $template_prefix, $dest_table );
            $templated = $wpdb->get_results( "SELECT * FROM {$source_table}" );
            restore_current_blog(); //Switch back to the newly created blog

            if ( count( $templated ) )
                $to_remove = $this->get_fields_to_remove($dest_table, $templated[0]);

            //Now, insert the templated settings into the newly created blog
            foreach ($templated as $row) {
                $row = (array)$row;

                foreach ( $row as $key => $value ) {
                    if ( in_array( $key, $to_remove ) )
                        unset( $row[ $key ] );
                }

                // Deprecated
                $row = apply_filters('blog_templates-process_row', $row, $dest_table, $this->source_blog_id);

                /**
                 * Filter a table row.
                 *
                 * This filters helps to modify any row of a table that we are copying.
                 *
                 * @param Array $row Table Row.
                 * @param String $dest_table Destination table name.
                 * @param Integer $source_blog_id Source blog ID from where we are copying the table.
                 */
                $row = apply_filters('wpmudev_copier-process_row', $row, $dest_table, $this->source_blog_id );

                if ( ! $row )
                    continue;

                $wpdb->insert( $dest_table, $row );
                if ( ! empty( $wpdb->last_error ) ) {
                    return new WP_Error( 'copy_table', __( 'Error copying table: ' . $dest_table, WPMUDEV_COPIER_LANG_DOMAIN ) );
                }
            }

            return true;
        }


        public function clear_table( $table ) {
            global $wpdb;

            // Deprecated
            do_action( 'blog_templates-clearing_table', $table );

            /**
             * Fires before a table is cleared.
             *
             * @param String $table Destination table name
             */
            do_action( 'wpmudev_copier-clearing_table', $table );

            // Deprecated
            $where = apply_filters( 'blog_templates-clear_table_where', "", $table );
            $where = apply_filters( 'wpmudev_copier-clear_table_where', "", $table );

            $wpdb->query( "DELETE FROM $table $where" );

            if ( $wpdb->last_error )
                return new WP_Error( 'deletion_error', sprintf( __( 'Deletion Error: %1$s - The template was not applied. (New Blog Templates - While clearing %2$s)', WPMUDEV_COPIER_LANG_DOMAIN ), $wpdb->last_error, $table ) );

            return true;
        }

        /**
        * Added to automate comparing the two tables, and making sure no old fields that have been removed get copied to the new table
        *
        * @param mixed $new_table_name
        * @param mixed $old_table_row
        *
        * @since 1.0
        */
        function get_fields_to_remove( $new_table_name, $old_table_row ) {
            //make sure we have something to compare it to
            if ( empty( $old_table_row ) )
                return false;

            //We need the old table row to be in array format, so we can use in_array()
            $old_table_row = (array)$old_table_row;

            global $wpdb;

            //Get the new table structure
            $new_table = (array)$wpdb->get_results( "SHOW COLUMNS FROM {$new_table_name}" );

            $new_fields = array();
            foreach( $new_table as $row ) {
                $new_fields[] = $row->Field;
            }

            $results = array();

            //Now, go through the columns in the old table, and check if there are any that don't show up in the new table
            foreach ( $old_table_row as $key => $value ) {
                if ( ! in_array( $key,$new_fields ) ) { //If the new table doesn't have this field
                    //There's a column that isn't in the new one, make note of that
                    $results[] = $key;
                }
            }

            //Return the results array, which should contain all of the fields that don't appear in the new table
            return $results;
        }


    }
}