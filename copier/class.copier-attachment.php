<?php

/**
 * This class copy only one attachment
 * 
 * As attachments can take long to copy depending on the size
 * the class will only get and upload one of them
 */
if ( ! class_exists( 'Site_Copier_Attachment' ) ) {

    class Site_Copier_Attachment extends Site_Copier {

        public function get_default_args() {
            return array(
                'attachment_id' => false,
                'posts_mapping' => array(),
                'date' => null,
                'attachment_url' => '',
                'title' => '',
                'content' => '',
                'excerpt' => '',
                'author' => ''
            );
        }

        public function copy() {
            global $wpdb;

            if ( ! empty( $this->args['attachment_url'] ) ) {

                // If we have passed an URL, we better get the attachment this way
                // Usually is more secure to get the attachment by URL instead of ID
                $url = $this->args['attachment_url'];
                if ( preg_match( '|^/[\w\W]+$|', $url ) )
                    $url = rtrim( home_url(), '/' ) . $url;

                $image_alt_text = '';
                $is_custom_header = '';
                $is_custom_background = '';

                $title = $this->args['title'];
                $content = $this->args['content'];
                $excerpt = $this->args['excerpt'];
                $author = $this->args['author'];

                if ( absint( $this->args['attachment_id'] ) ) {

                    // If we have passed also the attachment ID, let's get the attachment properties
                    switch_to_blog( $this->source_blog_id );
                    $source_attachment = get_post( absint( $this->args['attachment_id'] ) );
                    if ( $source_attachment ) {
                        $thumbnail_id = absint( $this->args['attachment_id'] );
                        $title = $source_attachment->post_title;
                        $content = $source_attachment->post_content;
                        $excerpt = $source_attachment->post_excerpt;
                        $author = $source_attachment->post_author;
                        $status = $source_attachment->post_status;
                        $date = $source_attachment->post_date;
                        $post_parent = $source_attachment->post_parent;

                        $image_alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
                        $is_custom_header = get_post_meta( $thumbnail_id, '_wp_attachment_is_custom_header', true );
                        $is_custom_background = get_post_meta( $thumbnail_id, '_wp_attachment_is_custom_background', true );
                        $image_context = get_post_meta( $thumbnail_id, '_wp_attachment_context', true );
                    }
                    restore_current_blog();
                }

                $new_attachment = array(
                    'post_status' => ! empty( $status ) ? $status : 'publish',
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_excerpt' => $excerpt,
                    'post_date' => $date,
                    'post_author' => empty( $author ) ? $this->user_id : absint( $author ),
                    'import_id' => absint( $this->args['attachment_id'] )
                );

                // Remap the post parent ID
                if ( isset( $this->args['posts_mapping'][ $post_parent ] ) )
                    $new_attachment['post_parent'] = $this->args['posts_mapping'][ $post_parent ];

                if ( empty( $source_attachment ) )
                    $source_attachment = $url;

            }
            else {
                // It's an attachment ID
                if ( is_string( $this->args['attachment_id'] ) )
                    $thumbnail_id = absint( $this->args['attachment_id'] );
                else
                    $thumbnail_id = $this->args['attachment_id'];

                if ( ! $thumbnail_id )
                    return new WP_Error( 'attachment_error', __( 'Wrong attachment specified', WPMUDEV_COPIER_LANG_DOMAIN) );

                if ( ! is_string( $thumbnail_id ) )
                    $source_attachment = get_blog_post( $this->source_blog_id, $thumbnail_id );

                if ( ! $source_attachment )
                    return new WP_Error( 'attachment_error', sprintf( __( 'Attachment ( ID= %d ) does not exist in the source blog', WPMUDEV_COPIER_LANG_DOMAIN), $thumbnail_id ) );

                // Setting the new attachment properties
                $new_attachment = (array)$source_attachment;

                switch_to_blog( $this->source_blog_id );

                // Thanks to WordPress Importer plugin
                add_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
                $url = wp_get_attachment_url( $thumbnail_id );
                remove_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );

                $image_alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
                $is_custom_header = get_post_meta( $thumbnail_id, '_wp_attachment_is_custom_header', true );
                $is_custom_background = get_post_meta( $thumbnail_id, '_wp_attachment_is_custom_background', true );
                $image_context = get_post_meta( $thumbnail_id, '_wp_attachment_context', true );

                if ( preg_match( '|^/[\w\W]+$|', $url ) )
                    $url = rtrim( home_url(), '/' ) . $url;
                
                restore_current_blog();
            }

            $upload = $this->fetch_remote_file( $url, $this->args['date'] );

            if ( is_wp_error( $upload ) )
                return $upload;

            if ( $info = wp_check_filetype( $upload['file'] ) )
                $new_attachment['post_mime_type'] = $info['type'];
            else
                return new WP_Error( 'filetype_error', __( 'Filetype error: ' . $url, WPMUDEV_COPIER_LANG_DOMAIN ) );

            $new_attachment['guid'] = $upload['url'];

            if ( isset( $new_attachment['ID'] ) ) {
                $new_attachment['import_id'] = $new_attachment['ID'];
                unset( $new_attachment['ID'] );
            }

            if ( ! function_exists( 'wp_generate_attachment_metadata' ) )
                include( ABSPATH . 'wp-admin/includes/image.php' );

            // Generate the new attachment
            $new_attachment_id = wp_insert_attachment( $new_attachment, $upload['file'] );
            wp_update_attachment_metadata( $new_attachment_id, wp_generate_attachment_metadata( $new_attachment_id, $upload['file'] ) );

            // Update alt text, context and header/background image 
            update_post_meta( $new_attachment_id, '_wp_attachment_image_alt', $image_alt_text );
            update_post_meta( $new_attachment_id, '_wp_attachment_is_custom_header', $is_custom_header );
            update_post_meta( $new_attachment_id, '_wp_attachment_is_custom_background', $is_custom_background );
            update_post_meta( $new_attachment_id, '_wp_attachment_context', $image_context );


            /**
             * Fires after an attachment has been copied
             *
             * @param Integer $new_attachment_id New Attachment ID in the destination blog (current blog)
             * @param Integer $source_attachment_id Source Attachment ID
             * @param Integer $source_blog_id The source blog ID
             */
            do_action( 'wpmudev_copier_copy_attachment', $new_attachment_id, $source_attachment, $this->source_blog_id );

            if ( empty( $thumbnail_id ) ) {
                // We don't need to do anything else if we did not pass the source attachment ID
                return array(
                    'url' => $url,
                    'new_attachment_id' => $new_attachment_id
                );
            }

            // Update featured image in posts
            $posts_ids = get_posts(
                array(
                    'meta_query' => array(
                        array(
                            'key' => '_thumbnail_id',
                            'value' => $thumbnail_id
                        )
                    ),
                    'fields' => 'ids',
                    'post_type' => 'any'
                )
            );

            foreach ( $posts_ids as $post_id )
                update_post_meta( $post_id, '_thumbnail_id', $new_attachment_id );

            if ( 
                apply_filters( 'nbt_change_attachments_urls', true ) // Deprecated
                /**
                 * Filter the option to change the attachments URLs in post contents.
                 * 
                 * If set to true, source attachments URLs will be replaced
                 * with a query in the destination blog
                 *
                 * @param Boolean. Default True.
                 */
                && apply_filters( 'wpmudev_copier_change_attachments_urls', true )
            ) {
                // We first need the source and destination images URLs
                if ( ! empty( $source_attachment ) ) {
                    $images_sizes = get_intermediate_image_sizes();
                    $images_sizes[] = 'full';
                    $srcs = array();
                    foreach ( $images_sizes as $size ) {

                        switch_to_blog( $this->source_blog_id );
                        add_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
                        $source_src = wp_get_attachment_image_src( $source_attachment->ID, $size );
                        remove_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
                        restore_current_blog();

                        // Switching between blogs gives us back a wrong uploads dir in many cases, let's fix it
                        $source_src[0] = preg_replace( '/^https?\:\/\//', '', $source_src[0] );
                        $source_blog_url = preg_replace( '/^https?\:\/\//', '', get_site_url( $this->source_blog_id ) );
                        $destination_blog_url = preg_replace( '/^https?\:\/\//', '', get_site_url( get_current_blog_id() ) );
                        $source_src[0] = str_replace( $destination_blog_url, $source_blog_url, $source_src[0] );
                        
                        $dest_src = wp_get_attachment_image_src( $new_attachment_id, $size );
                        $dest_src[0] = preg_replace( '/^https?\:\/\//', '', $source_src[0] );

                        if ( 
                            $source_src
                            && $dest_src 
                            && $source_src[1] == $dest_src[1]
                            && $source_src[2] == $dest_src[2]
                        )
                            $srcs[ $source_src[0] ] = $dest_src[0];

                    }

                    if ( ! empty( $srcs ) ) {                        
                        // Replace the SRCs in database
                        foreach ( $srcs as $source_src => $dest_src ) {

                            $posts = get_posts(
                                array(
                                    'posts_per_page' => -1,
                                    'ignore_sticky_posts' => true,
                                    's' => $source_src,
                                    'post_type' => 'any',
                                    'post_status' => 'any'
                                )
                            );

                            foreach ( $posts as $post ) {
                                $post_content = str_replace( $source_src, $dest_src, $post->post_content );
                                wp_update_post( array( 'ID' => $post->ID, 'post_content' => $post_content ) );
                            }
                        }

                    }

                }

                // Now remap the uploaded image URL just in case the above code did nothing
                // Again, code extracted from WordPress importer plugin
                $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $url, $upload['url'] ) );
                $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $url, $upload['url'] ) );
            }

            // Update galleries shortcodes

            // Get all posts in the blog
            $all_posts = get_posts( array(
                'post_type' => 'any',
                'posts_per_page' => -1,
                'ignore_sticky_posts' => true
            ) );

            // Shortcode patterns
            $shortcode_pattern = get_shortcode_regex();
            foreach ( $all_posts as $post ) {
                $_post = (array)$post;
                
                if ( 
                    preg_match_all( '/'. $shortcode_pattern .'/s', $_post['post_content'], $matches ) 
                    && array_key_exists( 2, $matches )
                    && in_array( 'gallery', $matches[2] )
                ) {
                    // We have found a post with at least a gallery shortcode in it

                    // do_replace will turn true if we had a shortcode that includes the source attachment in the post
                    $do_replace = false;
                    foreach ( $matches[2] as $key => $shortcode_type ) {
                        if ( 'gallery' == $shortcode_type ) {

                            // Get the gallery attributes
                            $atts = shortcode_parse_atts( $matches[3][ $key ] );
                            
                            if ( isset( $atts['ids'] ) && strpos( $atts['ids'], (string)$source_attachment->ID ) ) {
                                // The shortcode gallery includes our source attachment ID, let's replace it for the new one

                                // First, get the full original shortcode
                                $full_shortcode = $matches[0][ $key ];

                                // Now replace the source attachment ID for the new one in those attributes
                                $new_atts_ids = str_replace( (string)$source_attachment->ID, $new_attachment_id, $atts['ids'] );

                                // Now replace the attributes in the source shortcode
                                $new_full_shortcode = str_replace( $atts['ids'], $new_atts_ids, $full_shortcode );

                                // And finally replace the source shortcode for the new one in the post content
                                $_post['post_content'] = str_replace( $full_shortcode, $new_full_shortcode, $_post['post_content'] );

                                // So we have found a replacement to make, haven't we?
                                $do_replace = true;             
                            }
                        }
                    }

                    if ( $do_replace ) {
                        // Update the post!
                        $postarr = array(
                            'ID' => $_post['ID'],
                            'post_content' => $_post['post_content']
                        );
                        wp_update_post( $postarr );
                    }

                }

                /**
                 * Fires after a post gallery shortcode has been replaced in the post content
                 *
                 * When an attachment has been copied to another blog, the attachment ID
                 * changes. Galleries shortcodes reference attachments using IDs. These
                 * must be changed in the shortcode parameters.
                 * 
                 * This hook allows to process different shortcodes in a post content
                 * that are also referencing an attachment.
                 *
                 *
                 * @param Integer $_post['ID'] Post ID
                 * @param Integer $new_attachment_id The new Attachment ID in the blog that we have copied the attachment
                 * @param Integer $source_attachment->ID The source attachment ID in the source blog
                 */
                do_action( 'wpmudev_copier_replace_post_gallery', $_post['ID'], $new_attachment_id, $source_attachment->ID );
            }

            
            return array(
                'url' => $url,
                'new_attachment_id' => $new_attachment_id
            );

        }

        /**
         * Fetch an image and download it. Then create a new empty file for  it
         * that can be filled later
         * 
         * Code based on WordPress Importer plugin
         * 
         * @return WP_Error/Array Image properties/Error
         */
        public function fetch_remote_file( $url, $date )  {
            $file_name = basename( $url );

            $upload = wp_upload_bits( $file_name, null, 0, $date );

            if ( $upload['error'] )
                return new WP_Error( 'upload_dir_error', $upload['error'] );

            add_filter( 'http_request_args', array( $this, 'unset_verify_ssl_request' ), 999 );
            $headers = $this->wp_get_http( $url, $upload['file'] );
            remove_filter( 'http_request_args', array( $this, 'unset_verify_ssl_request' ), 999 );

            // request failed
            if ( ! $headers ) {
                @unlink( $upload['file'] );
                return new WP_Error( 'import_file_error', sprintf( __('Remote server did not respond for file: %s', WPMUDEV_COPIER_LANG_DOMAIN ), $url ) );
            }

            // make sure the fetch was successful
            if ( $headers['response'] != '200' ) {
                @unlink( $upload['file'] );
                return new WP_Error( 'import_file_error', sprintf( __( 'Remote server returned error response %1$d %2$s - %3$s', WPMUDEV_COPIER_LANG_DOMAIN ), esc_html( $headers['response'] ), get_status_header_desc($headers['response'] ), $url ) );
            }

            $filesize = filesize( $upload['file'] );

            if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
                @unlink( $upload['file'] );
                return new WP_Error( 'import_file_error', __('Remote file is incorrect size', WPMUDEV_COPIER_LANG_DOMAIN ) );
            }

            if ( 0 == $filesize ) {
                @unlink( $upload['file'] );
                return new WP_Error( 'import_file_error', __('Zero size file downloaded', WPMUDEV_COPIER_LANG_DOMAIN ) );
            }

            $max_size = (int) apply_filters( 'wpmudev_copier_attachment_size_limit', 0 );
            if ( ! empty( $max_size ) && $filesize > $max_size ) {
                @unlink( $upload['file'] );
                return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', WPMUDEV_COPIER_LANG_DOMAIN ), size_format($max_size) ) );
            }

            return $upload;
        }

        function wp_get_http( $url, $file_path = false ) {

            $options = array();
            $options['redirection'] = 5;

            if ( false == $file_path )
                $options['method'] = 'HEAD';
            else
                $options['method'] = 'GET';

            $response = wp_safe_remote_request( $url, $options );

            if ( is_wp_error( $response ) )
                return false;

            $headers = wp_remote_retrieve_headers( $response );
            $headers['response'] = wp_remote_retrieve_response_code( $response );

            // WP_HTTP no longer follows redirects for HEAD requests.
            if ( 'HEAD' == $options['method'] && in_array($headers['response'], array(301, 302)) && isset( $headers['location'] ) ) {
                return $this->wp_get_http( $headers['location'], $file_path, ++$red );
            }

            if ( false == $file_path )
                return $headers;

            // GET request - write it to the supplied filename
            $out_fp = fopen($file_path, 'w');
            if ( !$out_fp )
                return $headers;

            fwrite( $out_fp,  wp_remote_retrieve_body( $response ) );
            fclose($out_fp);
            clearstatcache();

            return $headers;
        }

        public function unset_verify_ssl_request( $args ) {
            $args['sslverify'] = false;
            return $args;
        }

    }
}