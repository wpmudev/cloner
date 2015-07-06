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

add_filter( 'wpmudev_cloner_pre_clone_actions_switch_default', 'cloner_multi_domains_process_clone_site_form', 10, 6 );
/**
 * @param $result
 * @param $selection
 * @param $blog_title_selection
 * @param $new_blog_title
 * @param $blog_id
 * @param $blog_details
 * @return array|bool|WP_Error
 */
function cloner_multi_domains_process_clone_site_form( $result, $selection, $blog_title_selection, $new_blog_title, $blog_id, $blog_details ) {
    global $wpdb;
    if ( $selection === 'create_md' ) {
        // Checking if the blog already exists
        // Sanitize the domain/subfolder
        $blog = ! empty( $_REQUEST['blog_create'] ) ? $_REQUEST['blog_create'] : false;

        if ( ! $blog ) {
            return new WP_Error( 'source_blog_not_exist', __( 'Please, insert a site name', WPMUDEV_CLONER_LANG_DOMAIN ) );
        }

        $domain = $_REQUEST['domain'];

        if ( empty( $domain ) ) {
            return new WP_Error( 'source_blog_not_exist', __( 'Please, insert a site name', WPMUDEV_CLONER_LANG_DOMAIN ) );
        }

        $all_domains = get_site_option( 'md_domains' );
        $search_domain_results = wp_list_filter( array( 'domain_name' => $domain ) );

        if ( empty( $search_domain_results ) ) {
            return new WP_Error( 'source_blog_not_exist', __( 'Missing or invalid site address.', WPMUDEV_CLONER_LANG_DOMAIN ) );
        }


        $subdomain = '';
        if ( preg_match( '|^([a-zA-Z0-9-])+$|', $blog ) )
            $subdomain = strtolower( $blog );

        if ( empty( $subdomain ) ) {
            return new WP_Error( 'source_blog_not_exist', __( 'Missing or invalid site address.', WPMUDEV_CLONER_LANG_DOMAIN ) );
        }

        $full_address = '';

        // Check if the blog exists
        if ( is_subdomain_install() ) {
            $full_address = $subdomain . '.' . $domain;
            $blog_exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->blogs WHERE domain LIKE %s", '%' . $full_address . '%' ) );
        }
        else {

        }

        if ( ! empty( $blog_exists ) ) {
            return new WP_Error( 'blog_already_exists', __( 'The blog already exists', WPMUDEV_CLONER_LANG_DOMAIN ) );
        }


        if ( 'clone' == $blog_title_selection ) {
            $new_blog_title = $blog_details->blogname;
        }


        return array(
            'new_blog_title' => $new_blog_title,
            'new_domain' => $full_address,
            'new_path' => ''
        );
    }
    return false;
}


