<?php

if ( ! class_exists( 'Site_Copier_Users' ) ) {
    class Site_Copier_Users extends Site_Copier {

    	public function get_default_args() {
            return array();
        }

    	public function copy() {
            global $wpdb;

            // Removing users from the current blog
            $current_users = get_users();

            
            foreach ( $current_users as $user ) {
                remove_user_from_blog( $user->ID );    
            }
            
            switch_to_blog( $this->source_blog_id );
            $template_users = get_users();        
            $template_users_ids = wp_list_pluck( $template_users, 'ID' );
            if ( ! empty( $this->user_id ) && ! in_array( $this->user_id, $template_users_ids ) ) {
                $template_users[] = get_user_by( 'id', $this->user_id );
            }
            restore_current_blog();

            
            foreach( $template_users as $user ) {
                // Deprecated
                $user = apply_filters( 'blog_templates-copy-user_entry', $user, $this->template, get_current_blog_id(), $this->user_id );

                /**
                 * Filter a user attributes before adding it to the destination blog.
                 * 
                 * @param Array $user User attributes.
                 * @param Integer $user_id Administrator Blog ID.
                 * @param Array $template Only applies when using New Blog Templates. Includes the template attributes.
                 */
                $user = apply_filters( 'wpmudev_copier-copy-user_entry', $user, $this->user_id, $this->template );
                if ( $user->ID == $this->user_id ) {
                    add_user_to_blog( get_current_blog_id(), $user->ID, 'administrator' );
                }
                elseif ( ! empty( $user->roles[0] ) ) {
                    add_user_to_blog( get_current_blog_id(), $user->ID, $user->roles[0] );
                }
            }

            // Deprecated
            do_action( 'blog_templates-copy-users', $this->template, get_current_blog_id(), $this->user_id );

            /**
             * Fires after the users have been copied.
             *
             * @param Integer $user_id Blog Administrator ID.
             * @param Integer $source_blog_id Source Blog ID from where we are copying the users.
             * @param Array $template Only applies when using New Blog Templates. Includes the template attributes.
             */
            do_action( 'wpmudev_copier-copy-users', $this->user_id, $this->source_blog_id, $this->template );

            return true;
    	}


    }
}