<div class="wrap">
	<h2><?php echo esc_html( __( 'Clone Site', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?></h2>
	
		<form method="post" action="<?php echo add_query_arg( 'action', 'clone', network_admin_url( 'index.php?page=clone_site' ) ); ?>">

			<?php wp_nonce_field( 'clone-site-' . $blog_id, '_wpnonce_clone-site' ) ?>
			<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>" />

			<div class="cloner-clone-option">
				<p>
					<label for="cloner-create">
						<input type="radio" name="cloner-clone-selection" value="create" id="cloner-create" class="clone_clone_option"/> 
						<?php _e( 'Create a new Site', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
					</label>
				</p>
				<?php if ( is_subdomain_install() ): ?>
					<input name="blog_create" type="text" class="regular-text" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Type your site name here...', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?>"/><br/>
					<span class="no-break">.<?php echo preg_replace( '|^www\.|', '', $current_site->domain ); ?></span>
				<?php else: ?>
					<?php echo $current_site->domain . $current_site->path ?><br/>
					<input name="blog_create" class="regular-text" type="text" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Type your site name here...', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?>"/>
				<?php endif; ?>
				<p class="description"><?php _e( 'Only lowercase letters (a-z) and numbers are allowed.' ); ?></p>
			</div>

			<div class="cloner-clone-option">
				<p>
					<label for="cloner-replace">
						<input type="radio" name="cloner-clone-selection" value="replace" id="cloner-replace" class="clone_clone_option"/> 
						<?php _e( 'Replace existing Site', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
					</label>
				</p>
				<input name="blog_replace_autocomplete" type="text" class="regular-text ui-autocomplete-input" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Start writing to search an existing site' ) ); ?>"/>
				<input name="blog_replace" type="hidden" value=""/>
			</div>
			<div class="clear"></div>

			<?php submit_button( __( 'Clone Site', WPMUDEV_CLONER_LANG_DOMAIN ), 'primary', 'clone-site-submit' ); ?>
		</form>
</div>
<style>
	.cloner-clone-option {
		float:left;
		margin-right:50px;
	}
	.cloner-clone-option label {
		font-size:1.3em;
	}
</style>
<script>
	(function( $ ) {

	$(document).ready( function() {
		var position = { offset: '0, -1' };
		if ( typeof isRtl !== 'undefined' && isRtl ) {
			position.my = 'right top';
			position.at = 'right bottom';
		}

		$( 'input[name="blog_create"]' ).click( function (e ) {
			$('input[type="radio"]').attr('checked', false );
			$('#cloner-create' ).attr('checked', true );
		});

		$( 'input[name="blog_replace_autocomplete"]' ).click( function (e ) {
			$('input[type="radio"]').attr('checked', false );
			$('#cloner-replace' ).attr('checked', true );
		});

		$( 'input[name="blog_replace_autocomplete"]' ).autocomplete({
			source: function( request, response ) {
			    var term = request.term;
	    		var blog_id = $( 'input[name="blog_id"]' ).val();

				var data = {
					action: 'cloner_autocomplete_site',
					term: request.term,
					blog_id: blog_id
				};


			    $.ajax({
					url: ajaxurl,
					data: data,
					type: 'post',
					dataType: 'json'
				}).done(function( data ) {
					response( data );
				});

			},
			delay:     200,
			minLength: 2,
			position:  position,
			response: function( event, ui ) {
			  	for ( var i = 0; i < ui.content.length; i++ ) {
			  		ui.content[i].label = ui.content[i].domain + ' [' + ui.content[i].blog_name + ']';
			  		ui.content[i].value = ui.content[i].domain;
			  	}
			},
			open: function() {
				$( this ).addClass( 'open' );
			},
			close: function( event, ui) {
				$( this ).removeClass( 'open' );
			},
			select: function( event, ui ) {
				$( 'input[name="blog_replace_autocomplete"]' ).val( ui.item.domain );
				$( 'input[name="blog_replace"]' ).val( ui.item.blog_id );
			},
			search: function() {
				$('input[name="blog_replace"]').val('');
			}
		});

	});
})( jQuery );

</script>