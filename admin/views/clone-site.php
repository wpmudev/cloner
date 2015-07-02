<div class="wrap">
	<h2><?php echo esc_html( __( 'Clone Site', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?></h2>

		<?php settings_errors( 'cloner' ); ?>
		<?php if ( ! empty( $blog_details ) ): ?>
			<p id="cloner-blog-details">
				<span class="cloner-head"><?php _e( 'Cloning', WPMUDEV_CLONER_LANG_DOMAIN ); ?></span> 
				<?php if ( is_subdomain_install() ): ?>
					<span class="cloner-subdomain"><?php echo $subdomain; ?></span><span class="cloner-domain"><?php echo $domain; ?></span>
				<?php else: ?>
					<span class="cloner-domain"><?php echo $domain; ?></span><span class="cloner-subdomain"><?php echo $subdomain; ?></span>
				<?php endif; ?>

				<span class="cloner-back-link"> <a href="<?php echo network_admin_url( 'sites.php' ); ?>"><?php _e( 'Choose a different site', WPMUDEV_CLONER_LANG_DOMAIN ); ?></a></span>
			</p>
		<?php endif; ?>
		<hr/>


		<form method="post" action="<?php echo $form_url; ?>">

			<?php wp_nonce_field( 'clone-site-' . $blog_id, '_wpnonce_clone-site' ) ?>
			<input type="hidden" name="blog_id" value="<?php echo $blog_id; ?>" />

			<div id="poststuff" class="metabox-holder">
				<?php do_meta_boxes( 'cloner', 'normal', null ); ?>
			</div>

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

	.multiselect-header {
		background: transparent;
		text-align: center;
		font-weight: bold;
	}

	.ms-container {
		width:70%
	}

	#additional-tables-checkboxes li {
		float:left;
		width: 30%;
	}
	.cloner-head {
		font-size: 1.2em;
		color: #666;
		font-weight: bold;
		display: inline-block;
		margin-right: 25px;
	}
	.cloner-domain {
		color:#666;
	}
	.cloner-subdomain {
		font-weight: bold;
	}
	.cloner-back-link:before {
		font: normal 14px/1 'dashicons';
		content: "\f171";
		color:#A2A2A2;
	}
	.cloner-back-link a {
		font-size:1.1em;
	}
	.cloner-back-link {
		display: inline-block;
		margin-left: 25px;
	}
	#cloner-blog-details {
		margin:1.5em 0;
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

			$( 'input#blog_create' ).click( function (e ) {
				$('input[name="cloner-clone-selection"]').attr('checked', false );
				$('#cloner-create' ).attr('checked', true );
				set_cloner_blog_title_option( 'create' );
			});

			$( 'input[name="blog_replace_autocomplete"]' ).click( function (e ) {
				$('input[name="cloner-clone-selection"]').attr('checked', false );
				$('#cloner-replace' ).attr('checked', true );
				set_cloner_blog_title_option( 'replace' );
			});

			$('input[name="replace_blog_title"]').click(function() {
				$('#cloner-replace-blog-title').attr('checked',true);
			});

			$('input[name="cloner-clone-selection"]').change(function() {
				set_cloner_blog_title_option( $(this).val() );
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
						console.log(data);
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

			<?php if ( ! empty( $selected_array ) ): ?>
				// Multiselect
				cloner_load_multiselector();
			<?php endif; ?>
		});
	})( jQuery );

	function set_cloner_blog_title_option( selection ) {
		if ( 'create' == selection ) {
			if ( jQuery('#cloner-keep-blog-title').attr( 'checked' ) ) {
				jQuery('#cloner-keep-blog-title').attr( 'checked', false );
				jQuery('#cloner-clone-blog-title').attr( 'checked', true );
			}
			jQuery('#cloner-keep-blog-title').attr('disabled', true );

		}
		else {
			jQuery('#cloner-keep-blog-title').attr('disabled', false );
		}
	}


	function cloner_load_multiselector() {
		jQuery('#additional-tables-checkboxes').css({
			'visibility': 'hidden',
			'height': 0,
			'clear': 'both',
			'overflow':'hidden'
		});

		jQuery('#additional-tables-selector').multiSelect({
			'selectableHeader': '<p class="multiselect-header"><?php _e( "Ignore these tables", WPMUDEV_CLONER_LANG_DOMAIN ); ?></p>',
			'selectionHeader': '<p class="multiselect-header"><?php _e( "Copy these tables", WPMUDEV_CLONER_LANG_DOMAIN ); ?></p>',
			afterSelect: function(value) {
				jQuery('#table-' + value).attr('checked',true);
			},
			afterDeselect: function(value){
				jQuery('#table-' + value).attr('checked',false);
			}
		});


		jQuery('#additional-tables-selector').multiSelect('deselect_all');
		jQuery('#additional-tables-selector').multiSelect('select', <?php echo $selected_array; ?>);
		jQuery('#additional-tables').show();

		// This should make it work even if we click back in the browser
		var checked_elements = jQuery('.ms-selected');
		var all_checkboxes = jQuery('#additional-tables-checkboxes input[type="checkbox"]');
		all_checkboxes.attr('checked', false);
		jQuery.each( checked_elements, function( i, element ) {
			var value = jQuery(element).find('span').html();
			jQuery('#table-' + value).attr('checked', true );
		});
		
	}

</script>