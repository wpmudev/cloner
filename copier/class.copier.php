<?php

/**
 * Abstract class that stablish an structure for all subclasses
 * Every subclass should copy only one area of WordPress Core
 * 
 */
if ( ! class_exists( 'Site_Copier' ) ) {
    abstract class Site_Copier {

    	public function __construct( $source_blog_id, $template = array(), $args, $user_id = 0 ) {
            // The source blog ID from we are going to copy stuff
    		$this->source_blog_id = $source_blog_id;

            // The template selected. This is only for New Blog Templates
            // It does not make sense with Cloner Plugin
    		$this->template = $template;

            // The user ID that created the new blog
    		$this->user_id = $user_id;

            // Parse the arguments
            $this->args = wp_parse_args( $args, $this->get_default_args() );
    	}

        /**
         * Set default arguments for every subclass
         * 
         * @return Array
         */
    	public abstract function get_default_args();

        /**
         * The main method. This will copy all the stuff that the subclass manages of
         * 
         * @return Mixed Can be a boolean or additional information about what have been done
         */
    	public abstract function copy();

	    protected function log( $message ) {
		    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			    $date = current_time( 'mysql' );
			    if ( ! is_string( $message ) ) {
				    $message = print_r( $message, true );
			    }
			    $message = '[' . $date . '] - ' . $message . "\n";
			    file_put_contents( trailingslashit( WP_CONTENT_DIR ) . 'debug-cloner.log', $message, FILE_APPEND );
		    }
	    }

	    public function _get_microtime() {
		    return microtime( true );
	    }
    	
    }
}