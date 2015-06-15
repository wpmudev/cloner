<?php

function cloner_is_blog_clonable( $blog_id ) {
	return apply_filters( 'cloner_is_blog_clonable', true, $blog_id );
}