<?php

/**
 * Copy comments from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Comments' ) ) {

    class Site_Copier_Comments extends Site_Copier {

        public function get_default_args() {
            return array( 
                'posts_mapping' => array()
            );
        }

        public function copy() {
            global $wpdb;

            // Delete current comments in the new blog
            $current_comments = get_comments();
            foreach ( $current_comments as $comment ) {
                wp_delete_comment( $comment->comment_ID, true );
            }

            // Get the source comments and their metadata
            switch_to_blog( $this->source_blog_id );
            $_source_comments = get_comments();
            $source_comments = array();
            foreach ( $_source_comments as $source_comment ) {
                $item = $source_comment;
                $item->meta = get_comment_meta( $source_comment->comment_ID );
                $source_comments[] = $item;
            }
            restore_current_blog();

            // Deprecated
            $source_comments = apply_filters( 'blog_templates_source_comments', $source_comments, $this->source_blog_id, $this->user_id );

            /**
             * Filter the comments got from the source Blog ID.
             * 
             * These comments will be those that we will copy to the detsination blog ID.
             * 
             * @param Array $source_comments Source comments and their attributes.
             * @param Integer $source_blog_id Source Blog ID.
             */
            $source_comments = apply_filters( 'wpmudev_copier_source_comments', $source_comments, $this->source_blog_id );

            // This array saves the relationships between the old comments and the new
            $comments_remap = array();

            foreach ( $source_comments as $source_comment ) {
                $comment = (array)$source_comment;

                $source_comment_id = $comment['comment_ID'];
                unset( $comment['comment_ID'] );

                // Remap the post ID
                if ( ! isset( $this->args['posts_mapping'][ $comment['comment_post_ID'] ] ) )
                    continue;

                $comment['comment_post_ID'] = $this->args['posts_mapping'][ $comment['comment_post_ID'] ];

                // Insert the new comment
                $new_comment_id = wp_insert_comment( $comment );

                // And add it to the mapping array
                if ( $new_comment_id )
                    $comments_remap[ $source_comment_id ] = $new_comment_id;
            }

            unset( $source_comments );

            // Now, let's remap the parent comments
            $comments = get_comments();
            foreach ( $comments as $_comment ) {
                $comment = (array)$_comment;

                if ( $comment['comment_parent'] && isset( $comments_remap[ $comment['comment_parent'] ] ) ) {
                    $comment['comment_parent'] = $comments_remap[ $comment['comment_parent'] ];
                    wp_update_comment( $comment );
                }
            }

            return true;
        }

    }
}