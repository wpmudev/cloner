<?php

/**
 * Copy Widgets from one blog to another
 */
if ( ! class_exists( 'Site_Copier_Widgets' ) ) {
    class Site_Copier_Widgets extends Site_Copier {

    	public function get_default_args() {}

    	public function copy() {
            switch_to_blog( $this->source_blog_id );
            $sidebars_widgets = wp_get_sidebars_widgets();
            restore_current_blog();

            wp_set_sidebars_widgets( $sidebars_widgets );

            return true;

    	}

    }
}