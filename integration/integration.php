<?php

add_action( 'wpmudev_cloner_clone_site_screen', 'cloner_multi_domains_tweak_destination_meta_box' );
function cloner_multi_domains_tweak_destination_meta_box() {
    if ( ! class_exists( 'multi_domain' ) )
        return;
    remove_meta_box( 'cloner-destination', 'cloner', 'normal' );
    add_meta_box( 'cloner-destination', __( 'Destination', WPMUDEV_CLONER_LANG_DOMAIN), 'cloner_multi_domains_destination_meta_box', 'cloner', 'normal' );
}

function cloner_multi_domains_destination_meta_box() {
    include_once( WPMUDEV_CLONER_PLUGIN_DIR . 'integration/views/multi-domains-destination-meta-box.php' );
}

add_filter( 'wpmudev_cloner_pre_clone_actions_switch_default', 'cloner_multi_domains_process_clone_site_form', 10, 2 );
function cloner_multi_domains_process_clone_site_form( $result, $selection ) {
    if ( $selection === 'create_md' ) {
        // Checking if the blog already exists
        // Sanitize the domain/subfolder
        $blog = ! empty( $_REQUEST['blog_create'] ) ? $_REQUEST['blog_create'] : false;

        if ( ! $blog ) {
            add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Please, insert a site name', WPMUDEV_CLONER_LANG_DOMAIN ) );
            return false;
        }

        $domain = $_REQUEST['domain'];

        if ( empty( $domain ) ) {
            add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Missing or invalid site address.', WPMUDEV_CLONER_LANG_DOMAIN ) );
            return false;
        }

        return false;

        $domain = '';
        if ( preg_match( '|^([a-zA-Z0-9-])+$|', $blog ) )
            $domain = strtolower( $blog );

        if ( empty( $domain ) ) {
            add_settings_error( 'cloner', 'source_blog_not_exist', __( 'Missing or invalid site address.', WPMUDEV_CLONER_LANG_DOMAIN ) );
            return;
        }

        $destination_blog_details = get_blog_details( $domain );

        if ( ! empty( $destination_blog_details ) ) {
            add_settings_error( 'cloner', 'source_blog_not_exist', __( 'The blog already exists', WPMUDEV_CLONER_LANG_DOMAIN ) );
            return;
        }

        if ( 'clone' == $blog_title_selection ) {
            $new_blog_title = $blog_details->blogname;
        }


        do_action( 'wpmudev_cloner_pre_clone_actions', $selection, $blog_id, $args, false );
        $errors = get_settings_errors( 'cloner' );
        if ( ! empty( $errors ) )
            return;

    }
    return false;
}


