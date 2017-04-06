<?php

if ( ! defined( 'WPMUDEV_COPIER_LANG_DOMAIN' ) )
    define( 'WPMUDEV_COPIER_LANG_DOMAIN', 'wpmudev-cloner' );

include_once( dirname( __FILE__ ) . '/integration.php' );


if ( ! function_exists( 'copier_get_copier' ) ) {
    /**
     * Get a copier class and return the instance
     *
     * @param String $type Type of content to copy
     * @param Array $variables Array of variables to pass to the copier class
     *
     * @return Object/False
     */
    function copier_get_copier( $type, $source_blog_id, $args = array(), $user_id = 0, $template = array() ) {
        $type = strtolower( $type );

        include_once( dirname( __FILE__ ) . "/class.copier.php" );

        $file = dirname( __FILE__ ) . "/class.copier-$type.php";

        if ( is_file( $file ) )
            include_once( $file );

        $type = ucfirst( $type );

        $classname = "Site_Copier_$type";

        /**
         * Filter the copier class name.
         *
         * Allows to include our own copier class.
         *
         * @since 1.0.
         *
         * @param String $classname Current Class Name.
         * @param String $type Copier Type.
         */
        $classname = apply_filters( 'copier_get_copier_class', $classname, $type );

        $variables = compact( 'source_blog_id', 'template', 'args', 'user_id' );
        if ( class_exists( $classname ) ) {
            $r = new ReflectionClass( $classname );
            return $r->newInstanceArgs( $variables );
        }

        return false;
    }
}


if ( ! function_exists( 'copier_set_copier_args' ) ) {
    /**
     * Set the copier arguments
     *
     * It saves an new option in the destination blog that includes
     * everything to be copied. For New Blog Templates, it also allows a
     * template array as argument.
     *
     * @param Integer $source_blog_id
     * @param Integer $destination_blog_id
     * @param Integer $user_id The user that created the new blog
     * @param Array $args  Copier Arguments. Can be a New Blog Templates Temlate
     *
     * @return Boolean if everything was correctly set up
     */
    function copier_set_copier_args( $source_blog_id, $destination_blog_id, $user_id = 0, $args = array() ) {

        $option = array(
            'source_blog_id' => $source_blog_id,
            'user_id' => $user_id,
            'template' => $args
        );

        if ( empty( $args ) ) {
            // We are not using custom arguments, let's copy everything
            //Tabls first
            $to_copy = array(
                'tables' => array( 'tables' => wp_list_pluck( copier_get_additional_tables( $source_blog_id ), 'prefix.name' ) ),
                'settings' => array(),
                'widgets' => array(),
                'posts' => array(),
                'pages' => array(),
                'cpts' => array(),
                'terms' => array( 'update_relationships' => true ),
                'menus' => array(),
                'users' => array(),
                'comments' => array(),
                'attachment' => array()
                
            );
            $option['to_copy'] = $to_copy;
        }
        else {
            // Additional tables
            // Tables need to be set first before anything else
            $tables_args = array();
            if ( in_array( 'settings', $args['to_copy'] ) ) {
                $option['to_copy']['widgets'] = array();
                $tables_args['create_tables'] = true;
                $option['to_copy']['tables'] = $tables_args;
            }

            if ( isset( $args['additional_tables'] ) && is_array( $args['additional_tables'] ) ) {
                $tables_args['tables'] = $args['additional_tables'];
                $option['to_copy']['tables'] = $tables_args;
            }
            
            foreach( $args['to_copy'] as $value ) {
                $to_copy_args = array();

                if ( $value === 'posts' ) {
                    $to_copy_args['categories'] = isset( $args['post_category'] ) && in_array( 'all-categories', $args['post_category'] ) ? 'all' : $args['post_category'];
                    $to_copy_args['update_date'] = isset( $args['update_dates'] ) && $args['update_dates'] === true ? true : false;
                }
                elseif ( $value === 'pages' ) {
                    $to_copy_args['pages_ids'] = isset( $args['pages_ids'] ) && in_array( 'all-pages', $args['pages_ids'] ) ? 'all' : $args['pages_ids'];
                    $to_copy_args['block'] = isset( $args['block_posts_pages'] ) && $args['block_posts_pages'] === true ? true : false;
                    $to_copy_args['update_date'] = isset( $args['update_dates'] ) && $args['update_dates'] === true ? true : false;
                }
                elseif ( 'terms' === $value ) {
                    $to_copy_args['update_relationships'] = true;
                }
                elseif ( 'files' === $value ) {
                    $value = 'attachment';
                }

                $option['to_copy'][ $value ] = $to_copy_args;

                if ( $value === 'posts' && isset( $args['post_category'] ) && in_array( 'all-categories', $args['post_category'] ) ) {
                    $option['to_copy'][ 'cpts' ] = $to_copy_args;
                }
            }

            if ( array_key_exists( 'posts', $option['to_copy'] ) || array_key_exists( 'pages', $option['to_copy'] ) )
                $option['to_copy']['comments'] = array();

            
        }

        if ( isset( $option['to_copy']['attachment'] ) ) {
            $option['attachment_ids'] = copier_get_blog_attachments( $source_blog_id );
        }

        if ( isset( $option['to_copy']['menus'] ) )
            $option['menus_ids'] = copier_get_menus_ids( $source_blog_id );

        $option['prepare_environment'] = true;

        /**
         * Filter the copier arguments.
         *
         * @since 1.0.
         *
         * @param String $option Copier to-do list.
         * @param String $destination_blog_id Destination Blog ID.
         * @param Array $args Only applies when using New Blog Templates. Includes the template attributes.
         */
        $option = apply_filters( 'copier_set_copier_args', $option, $destination_blog_id, $args );

        switch_to_blog( $destination_blog_id );
        delete_option( 'copier-pending' );
        add_option( 'copier-pending', $option, null, 'no' );
        restore_current_blog();

        return true;
    }
}


if ( ! function_exists( 'copier_get_blog_attachments' ) ) {
    /**
     * Get a blog attachments IDs
     *
     * @param Integer $blog_id
     *
     * @return Array of attachment IDs
     */
    function copier_get_blog_attachments( $blog_id ) {
        switch_to_blog( $blog_id );

        $attachment_ids = get_posts( array(
            'posts_per_page' => -1,
            'post_type' => 'attachment',
            'fields' => 'ids',
            'ignore_sticky_posts' => true
        ) );

        $attachments = array();

        add_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );
        foreach ( $attachment_ids as $id ) {

            $url = wp_get_attachment_url( $id );

            if ( ! $url )
                continue;

            $item = array(
                'attachment_url' => $url,
                'attachment_id' => $id,
                'date' => false
            );

            $attached_file = get_post_meta( $id, '_wp_attached_file', true );
            if ( $attached_file ) {
                if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $attached_file, $matches ) )
                    $item['date'] = $matches[0];
            }

            $attachments[] = $item;

        }
        remove_filter( 'wp_get_attachment_url', 'copier_set_correct_wp_get_attachment_url' );

        restore_current_blog();

        return $attachments;
    }
}

if ( ! function_exists( 'copier_get_menus_ids' ) ) {
    function copier_get_menus_ids( $source_blog_id ) {
        switch_to_blog( $source_blog_id );
        $menus = wp_get_nav_menus();
        restore_current_blog();

        $menus_ids = wp_list_pluck( $menus, 'term_id' );
        return $menus_ids;
    }
}


if ( ! function_exists( 'copier_get_additional_tables' ) ) {
    /**
     * Get a list of WordPress DB tables in a blog (not the default ones)
     *
     * @param Integer $blog_id
     * @return Array of tables attributes:
            Array(
                'name' => Table name
                'prefix.name' => Table name and Database if MultiDB is activated. Same than 'name' in other case.
            )
     */
    function copier_get_additional_tables( $blog_id ) {
        global $wpdb;

        $blog_id = absint( $blog_id );
        $blog_details = get_blog_details( $blog_id );

        if ( ! $blog_details )
            return array();


        switch_to_blog( $blog_id );

        // MultiDB Plugin hack
        $pfx = class_exists( "m_wpdb" ) ? $wpdb->prefix : str_replace( '_', '\_', $wpdb->prefix );

        // Get all the tables for that blog
        $results = $wpdb->get_results("SHOW TABLES LIKE '{$pfx}%'", ARRAY_N);

        $default_tables = array(
            'posts',
            'comments',
            'links',
            'options',
            'postmeta',
            'terms',
            'termmeta',
            'term_taxonomy',
            'term_relationships',
            'commentmeta',
            'blogs',
            'blog_versions',
            'registration_log',
            'signups',
            'site',
            'sitemeta',
            'sitecategories',
            'users',
            'usermeta'
        );

        $tables = array();
        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                if ( ! in_array( str_replace( $wpdb->prefix, '', $result['0'] ), $default_tables ) ) {
                    if ( class_exists( 'm_wpdb' ) ) {
                        // MultiDB Plugin
                        $db = $wpdb->analyze_query( "SHOW TABLES LIKE '{$pfx}%'" );
                        $dataset = $db['dataset'];
                        $current_db = '';

                        foreach ( $wpdb->dbh_connections as $connection ) {
                            if ( $connection['ds'] == $dataset ) {
                                $current_db = $connection['name'];
                                break;
                            }
                        }

                        $val = $current_db . '.' . $result[0];

                    } else {
                        $val =  $result[0];
                    }

                    if ( stripslashes_deep( $pfx ) == $wpdb->base_prefix ) {
                        // If we are on the main blog, we'll have to avoid those tables from other blogs
                        $pattern = '/^' . stripslashes_deep( $pfx ) . '[0-9]/';
                        if ( preg_match( $pattern, $result[0] ) )
                            continue;
                    }

                    $tables[] = array(
                        'name' => $result[0] ,
                        'prefix.name' => $val
                    );
                }
            }
        }

        restore_current_blog();

        return $tables;
        // End changed
    }
}


if ( ! function_exists( 'copier_process_copy' ) ) {
    /**
     * Process the cloning based on the option in database
     *
     * @param Array $option
     */
    function copier_process_copy( $option ) {

        if ( $option === false ) {
            return array(
                'error' => true,
                'message' => __( "Error getting option", WPMUDEV_COPIER_LANG_DOMAIN )
            );
        }

        if ( ! empty( $option['prepare_environment'] ) ) {
            copier_prepare_environment( $option );
            unset( $option['prepare_environment'] );
            update_option( 'copier-pending', $option );
        }

        // Nothing to copy
        if ( empty( $option['to_copy'] ) ) {

            // Deprecated
            do_action( "blog_templates-copy-after_copying", $option['template'], $option['source_blog_id'], $option['user_id'] );

            /**
             * Fires after the copy proccess has finished.
             *
             * @param Integer $source_blog_id Source Blog ID from where we are copying the terms.
             * @param Integer $user_id Blog Administrator ID.
             * @param Array $option All options.
             * @param Array $template Only applies when using New Blog Templates. Includes the template attributes.
             */
            do_action( "wpmudev_copier-copy-after_copying", $option['source_blog_id'], $option['user_id'], $option, $option['template'] );

            delete_option( 'copier-pending' );

            return array(
                'error' => true,
                'message' => __( "Process Finished", WPMUDEV_COPIER_LANG_DOMAIN )
            );
        }

        extract( $option );

        // We need to erase the stuff done from the list
        // So next reload it does not copy teh same things again
        $copy = key( $option['to_copy'] );
        if ( $copy != 'attachment' && $copy != 'menus' )
            unset( $option['to_copy'][ $copy ] );

        // Arguments
        if ( 'attachment' == $copy ) {
            $attachment_key = key( $attachment_ids );
            if ( $attachment_key === null ) {
                // No attachments to copy
                unset( $option['to_copy']['attachment'] );
                update_option( 'copier-pending', $option );
                return array(
                    'error' => false,
                    'message' => __( 'No attachments to copy', WPMUDEV_COPIER_LANG_DOMAIN )
                );
            }

            $args = array(
                'date' => $attachment_ids[ $attachment_key ]['date']
            );

            if ( isset( $attachment_ids[ $attachment_key ]['attachment_id'] ) )
                $args['attachment_id'] = $attachment_ids[ $attachment_key ]['attachment_id'];

            if ( isset( $attachment_ids[ $attachment_key ]['attachment_url'] ) )
                $args['attachment_url'] = $attachment_ids[ $attachment_key ]['attachment_url'];

            unset( $option['attachment_ids'][ $attachment_key ] );

            if ( empty( $option['attachment_ids'] ) ) {
                // We have finished with attachments
                unset( $option['to_copy']['attachment'] );
                unset( $option['attachment_ids'] );
                update_option( 'copier-pending', $option );
            }
            else {
                update_option( 'copier-pending', $option );
            }

        }
        elseif ( 'menus' == $copy ) {
            $menu_key = key( $menus_ids );

            if ( $menu_key === null ) {
                // No menus to copy
                unset( $option['to_copy']['menus'] );
                update_option( 'copier-pending', $option );
                return array(
                    'error' => false,
                    'message' => __( 'No menus to copy', WPMUDEV_COPIER_LANG_DOMAIN )
                );
            }

            $args = array(
                'menu_id' => $menus_ids[ $menu_key ],
                'posts_mapping' => isset( $option['posts_mapping'] ) ? $option['posts_mapping'] : array()
            );

            unset( $option['menus_ids'][ $menu_key ] );

            if ( empty( $option['menus_ids'] ) ) {
                // We have finished with menus
                unset( $option['to_copy']['menus'] );
                unset( $option['menus_ids'] );
                update_option( 'copier-pending', $option );
            }
            else {
                update_option( 'copier-pending', $option );
            }
        }
        else {
            $args = empty( $to_copy[ $copy ] ) ? array() : $to_copy[ $copy ];
            update_option( 'copier-pending', $option );
        }

        if ( isset( $option['posts_mapping'] ) )
            $args['posts_mapping'] = $option['posts_mapping'];

        // Get the copier object
        $copier = copier_get_copier( $copy, $source_blog_id, $args, $user_id, $template );

        if ( ! $copier ) {
            return array(
                'error' => false,
                'message' => sprintf( __( "Error getting class (%s)", WPMUDEV_COPIER_LANG_DOMAIN ), $copy )
            );
        }

        /**
         * Fires before start the copy
         *
         * @param String $copy The type of data that we are copying (post,page,attachment...)
         * @param Object $copier The copier class that we are going to use
         */
        do_action( 'wpmudev_copier-before-copy', $copy, $copier );

        // Copy!
        $copier_result = $copier->copy();

        if ( is_wp_error( $copier_result ) ) {
            $message = $copier_result->get_error_message();
        }
        else {
            if ( $copy == 'posts' || $copy == 'pages' || $copy == 'cpts' ) {
                // Save the posts mapping, we'll need it later
                $posts_mapping_now = $copier_result;

                if ( isset( $option['posts_mapping'] ) ) {
                    $option['posts_mapping'] = $option['posts_mapping'] + $posts_mapping_now;
                }
                else {
                    $option['posts_mapping'] = $posts_mapping_now;
                }

                update_option( 'copier-pending', $option );

                if ( $copy == 'cpts' )
                    $message = __( 'Custom Post Types Copied', WPMUDEV_COPIER_LANG_DOMAIN );
                else
                    $message = sprintf( __( '%s Copied', WPMUDEV_COPIER_LANG_DOMAIN ), ucfirst( $copy ) );
            }
            elseif ( 'menus' == $copy ) {
                $message = sprintf( __( '%s Menu Copied', WPMUDEV_COPIER_LANG_DOMAIN ), $copier_result['menu_name'] );
                $menus_mapping_now = array( $args['menu_id'] => $copier_result['menu_id'] );

                if ( isset( $option['menus_mapping'] ) ) {
                    $option['menus_mapping'] = $option['menus_mapping'] + $menus_mapping_now;
                }
                else {
                    $option['menus_mapping'] = $menus_mapping_now;
                }

                update_option( 'copier-pending', $option );

                // If we have finished with menus, let's remap the locations and options
                if ( empty( $option['menus_ids'] ) && isset( $option['menus_mapping'] ) ) {
                    $copier = copier_get_copier( $copy, $source_blog_id, array() );
                    call_user_func_array( array( $copier, 'set_menu_locations' ), array( $source_blog_id, $option['menus_mapping'] ) );
                    call_user_func_array( array( $copier, 'set_menu_options' ), array( $source_blog_id, $option['menus_mapping'] ) );
                }
            }
            elseif ( 'attachment' != $copy ) {
                $message = sprintf( __( '%s Copied', WPMUDEV_COPIER_LANG_DOMAIN ), ucfirst( $copy ) );
            }
            else {
                $message = sprintf( __( '%s Copied', WPMUDEV_COPIER_LANG_DOMAIN ), basename( $copier_result['url'] ) );
            }
        }

        /**
         * Allows to change the success message
         *
         * @param String $message
         * @param String $copy What are we copying now (attachment, posts, pages, menus...)
         */
        $message = apply_filters( 'copier_success_message', $message, $copy );

        return array(
            'error' => false,
            'message' => $message
        );
    }
}

if ( ! function_exists( 'copier_prepare_environment' ) ) {
    /**
     * Do some stuff before starting to clone
     *
     * @param Array $option The current list of args saved
     */
    function copier_prepare_environment( $option ) {
        if ( isset( $option['to_copy']['attachment'] ) ) {
            // Let's delete all attachments in the destination blog
            $attachments = get_posts( array(
                'posts_per_page' => -1,
                'ignore_sticky_posts' => true,
                'post_type' => 'attachment',
                'post_status' => 'any',
                'fields' => 'ids',
                'order' => 'ASC'
            ));

            foreach ( $attachments as $id ) {
                wp_delete_attachment( $id, true );
            }
        }

        if ( isset( $option['to_copy']['menus'] ) ) {
            // First, let's delete the current menus that the new site has already, just in case
            $current_menus = wp_get_nav_menus();
            foreach ( $current_menus as $menu )
                $deletion = wp_delete_nav_menu( $menu->term_id );

        }
    }
}

if ( ! function_exists( 'copier_maybe_copy' ) ) {
    /**
     * If there's something pending to template for the current blog
     * here's when everything will be copied
     */
    function copier_maybe_copy() {

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
            return;

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) )
            return;

        if ( ! $option = get_option( 'copier-pending' ) )
            return;

        extract( $option );

        if ( ! is_admin() ) {
            // We'll try to avoid problems with AJAX this way
            wp_redirect( admin_url() );
            exit;
        }

        if ( isset( $_REQUEST['copier_step'] ) ) {
            $result = copier_process_copy( $option );
            $message = $result['message'];
            $error = $result['error'];
        }


        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce = wp_create_nonce( 'copier_process_copy' );


        $is_debugging = false;
        $is_xdebug_activated = false;

        /**
         * Filters the Copier Page Title
         *
         * @param String $title
         */
        $title = apply_filters( 'wpmudev_cloner_copy_page_title', __( 'We\'re setting up your new blog. Please wait...', WPMUDEV_COPIER_LANG_DOMAIN ) );

        // In order to prevent JS broken responses, we'll use buffers unless the user specifies it in wp-config.php
        if ( copier_allow_buffered_response() ) {
            $js_process = true;
        }
        else {
            // The user has disabled the buffers, let's check if the installation is displaying errors
            $is_debugging = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY );
            $is_xdebug_activated = function_exists( 'var_dump' );

            // If warnings, errors... are not displayed then the process will use JS
            $js_process = ! $is_debugging && ! $is_xdebug_activated;
        }

        do_action( 'copier_init_maybe_copy' );

        nocache_headers();
        @header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
        ?>
            <!DOCTYPE html>
            <html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width" />
                <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php echo get_option( 'blog_charset' ); ?>" />
                <script type="text/javascript" src="<?php echo includes_url(  'js/jquery/jquery.js' ); ?>"></script>
                <title><?php _e( 'New blog Setup', WPMUDEV_COPIER_LANG_DOMAIN ); ?></title>
                <style type="text/css">
                    #steps {
                        max-height:300px;
                        height:300px;
                        overflow-y: scroll;
                        padding-left: 0;
                    }
                    #steps li {
                        margin-bottom:0;
                        list-style:none;
                        border-bottom:1px solid #DEDEDE;
                        border-top:1px solid #FEFEFE;
                        padding: 0.8em 0 0.8em 0.5em;
                    }
                    #steps li:first-child {
                        border-top:none;
                    }
                    #steps li:nth-child(even) {
                        background:#F7F7F7;
                    }
                </style>
                <?php
                wp_admin_css( 'install', true );
                wp_admin_css( 'ie', true );
                ?>
            </head>
            <body class="wp-core-ui">
                <h1><?php echo esc_html( $title ); ?> <span id="spinner" style="display:none;"><img style="width:15px;height:15px;" src="<?php echo admin_url( 'images/spinner.gif' ); ?>" /></span></h1>
                <?php if ( $is_debugging ): ?>
                    <p class="js_alert" style="color:#a00"><?php _e( 'Please, set WP_DEBUG and WP_DEBUG_DISPLAY to false if you want this screen to work automatically instead of manually', WPMUDEV_COPIER_LANG_DOMAIN ); ?></p>
                <?php endif; ?>
                <?php if ( $is_xdebug_activated ): ?>
                    <p class="js_alert" style="color:#a00"><?php _e( 'XDebug is activated, turn it off if you want this screen to work automatically instead of manually', WPMUDEV_COPIER_LANG_DOMAIN ); ?></p>
                <?php endif; ?>
                <p class="redirect" style="display:none"><?php _e( 'Redirecting to dashboard...', WPMUDEV_COPIER_LANG_DOMAIN ); ?></p>
                <ul id="steps">
                    <?php if ( ! empty( $message ) ): ?>
                        <li><?php echo $message; ?></li>
                    <?php endif; ?>
                </ul>
                <p class="redirect" style="display:none"><?php _e( 'Redirecting to dashboard...', WPMUDEV_COPIER_LANG_DOMAIN ); ?></p>
                <?php if ( empty( $message ) ): ?>
                    <a class="button button-primary next_step_link" href="<?php echo esc_url( add_query_arg( 'copier_step', 'true', admin_url() ) ); ?>"><?php _e('Start', WPMUDEV_COPIER_LANG_DOMAIN ); ?></a>
                <?php elseif ( ! empty( $message ) && ! $error ): ?>
                    <a class="button next_step_link" href="<?php echo esc_url( add_query_arg( 'copier_step', 'true', admin_url() ) ); ?>"><?php _e('Next step', WPMUDEV_COPIER_LANG_DOMAIN ); ?></a>
                <?php else: ?>
                    <a class="button button-primary next_step_link" href="<?php echo esc_url( admin_url() ); ?>"><?php _e('Return to dashboard', WPMUDEV_COPIER_LANG_DOMAIN ); ?></a>
                <?php endif; ?>


            </body>
            <?php if ( $js_process ): ?>
                <script>
                    jQuery(document).ready(function($) {

                        $( document ).ajaxError( function( e, jqXHR, ajaxSettings, thrownError ) {
                            console.log( thrownError );
                            copier_process_template();
                        });

                        copier_process_template();

                        $('.next_step_link').detach();
                        $('.js_alert').detach();
                        $('#spinner').show();

                        function copier_process_template() {
                            $.ajax({
                                url: '<?php echo $ajax_url; ?>',
                                type: 'POST',
                                data: {
                                    action: 'copier_process_copy_action',
                                    security: '<?php echo $nonce; ?>'
                                }
                            })
                            .done(function( data ) {
                                if ( typeof data == 'object') {

                                    copier_append_to_list( data.data.message );

                                    if ( ! data.success ) {
                                        $('#spinner').hide();
                                        $('.redirect').show();
                                        location.href = '<?php echo admin_url(); ?>';
                                    }
                                    else {
                                        copier_process_template();
                                    }
                                }
                                else {
                                    console.log(data);
                                    copier_append_to_list( '<?php _e( "An error has occured", WPMUDEV_COPIER_LANG_DOMAIN ); ?>' );
                                    copier_process_template();
                                }

                            });
                        }

                        function copier_append_to_list( message ) {
                            var list = $('#steps');
                            var list_item = $('<li></li>').text( message );
                            list_item.appendTo(list);

                            // Scroll the list to bottom
                            list.scrollTop(list.find( 'li' ).length*50);
                        }
                    });
                </script>
            <?php endif; ?>

        <?php
        wp_die();



    }
    add_action( 'wp_loaded', 'copier_maybe_copy' );
}

if ( ! function_exists( 'copier_allow_buffered_response' ) ) {
    /**
     * Check if errors while executing AJAX can be buffered.
     *
     * The user can change this using WPMUDEV_COPIER_DISABLE_BUFFER constant in wp-config.php
     * If buffer is allowed, JSON answers will not appear broken if a warning/notice/error appear
     * during code execution.
     *
     * @return Boolean
     */
    function copier_allow_buffered_response() {
        return ! ( defined( 'WPMUDEV_COPIER_DISABLE_BUFFER' ) && WPMUDEV_COPIER_DISABLE_BUFFER );
    }
}


if ( ! function_exists( 'copier_process_ajax_template' ) ) {
    /**
     * If we are using AJAX, this is the function that will manage the process
     *
     * @return JSON
     */
    function copier_process_ajax_template() {

        if ( ! current_user_can( 'manage_options' ) )
            wp_send_json_error( array( 'message' => __( "Security Error", WPMUDEV_COPIER_LANG_DOMAIN ) ) );

        $check_nonce = check_ajax_referer( 'copier_process_copy', 'security', false );
        if ( ! $check_nonce )
            wp_send_json_error( array( 'message' => __( "Security Error", WPMUDEV_COPIER_LANG_DOMAIN ) ) );

        $option = get_option( 'copier-pending' );

        $result = copier_process_copy( $option );

        if ( $result['error'] )
            wp_send_json_error( array( 'message' => $result['message'] ) );
        else
            wp_send_json_success( array( 'message' => $result['message'] ) );

    }

    add_action( 'wp_ajax_copier_process_copy_action', 'copier_process_ajax_template' );
}


if ( ! function_exists( 'copier_set_correct_wp_upload_dir' ) ) {
    function copier_set_correct_wp_upload_dir( $upload_dir ) {
        $wrong_UPLOADS = UPLOADS;
        $UPLOADS = apply_filters( 'wpmudev_copier_UPLOADS_const', UPLOADBLOGSDIR . "/" . get_current_blog_id() . "/files/" );

        $_upload_dir = $upload_dir;
        foreach ( $upload_dir as $key => $value ) {
            $new_value = $value;
            if ( is_string( $value ) )
                $new_value = str_replace( $wrong_UPLOADS, $UPLOADS, $new_value );

            $_upload_dir[ $key ] = $new_value;

        }

        return $_upload_dir;
    }
}

if ( ! function_exists( 'copier_set_correct_wp_get_attachment_url' ) ) {
    function copier_set_correct_wp_get_attachment_url( $url ) {
        if ( defined( 'UPLOADS' ) && defined( 'UPLOADBLOGSDIR' ) ) {
            // Old uploads folder
            $wrong_UPLOADS = UPLOADS;
            $UPLOADS = apply_filters( 'wpmudev_copier_UPLOADS_const', UPLOADBLOGSDIR . "/" . get_current_blog_id() . "/files/" );

            return str_replace( $wrong_UPLOADS, $UPLOADS, $url );
        }
        else {

        }

        return $url;
    }
}
