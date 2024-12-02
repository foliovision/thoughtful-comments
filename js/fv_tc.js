function fv_tc_approve(id) { 
	jQuery("#comment-"+id+"-approve").text(fv_tc_translations.wait + ' | '); 
	jQuery.ajax({
		type: 'POST',
		url: fv_tc_conf.ajax_url,
		data: {"action": "fv_tc_approve", 'id': id, nonce: fv_tc_conf.nonce},
		success: function(data){
			if( data.success ) {
				jQuery("#comment-body-"+id).children(":first").text('');
				jQuery("#comment-"+id+"-approve").remove();
				jQuery("#comment-"+id).removeClass("unapproved");
				jQuery("#comment-"+id).find(".comment-awaiting-moderation").remove();

			} else {
				alert(fv_tc_translations.approval_error);
			}
		}
	});
	return false;  
}

function fv_tc_delete(id) {
	if(confirm(fv_tc_translations.comment_delete)) {
		jQuery.ajax({
			type: 'POST',
			url: fv_tc_conf.ajax_url,
			data: {"action": "fv_tc_delete", 'id': id, nonce: fv_tc_conf.nonce},
			success: fv_tc_check_deletion_response
		});
		return false;
	}
}

function fv_tc_delete_ban(id,ip) {
	if(confirm(fv_tc_translations.comment_delete_ban_ip)) {
		jQuery.ajax({
			type: 'POST',
			url: fv_tc_conf.ajax_url,
			data: {"action": "fv_tc_delete", 'id': id, 'ip': ip, nonce: fv_tc_conf.nonce},
			success: fv_tc_check_deletion_response
		});
		return false;
	}
}

function fv_tc_delete_thread(id) {
	if(confirm(fv_tc_translations.comment_delete_replies)) {
		jQuery.ajax({
			type: 'POST',
			url: fv_tc_conf.ajax_url,
			data: {"action": "fv_tc_delete", 'id': id, 'thread': 'yes', nonce: fv_tc_conf.nonce},
			success: fv_tc_check_deletion_response
		});
		return false;
	}
}

function fv_tc_delete_thread_ban(id, ip) {
	if(confirm(fv_tc_translations.comment_delete_replies_ban_ip)) {
		jQuery.ajax({
			type: 'POST',
			url: fv_tc_conf.ajax_url,
			data: {"action": "fv_tc_delete", 'id': id, 'ip': ip, 'thread': 'yes', nonce: fv_tc_conf.nonce},
			success: fv_tc_check_deletion_response
		});
		return false;
	}
}

function fv_tc_check_deletion_response( data ) {
	if( data.success ) {
		// iterate over data.deleted_comment_ids array and hide them
		for ( let id of data.deleted_comment_ids ) {
			fv_tc_hide_comment_keep_replies( id );
		}

	} else {
		alert(fv_tc_translations.delete_error);
	}
}

function fv_tc_hide_comment_keep_replies( id ) {
	let item = jQuery("[id^='comment'][id$='-"+id+"']"),
		children = item.find( '.children' ).eq(0);

	children.hide();
	item.slideUp().removeClass( 'fv_tc-do-not-hide' );

	children.find('[class*=depth-]').each( function() {
		let comment = jQuery(this),
		depth = comment.attr('class').match( /depth-(\d+)/ );

		if ( depth ) {
			comment.removeClass( depth[0] );
			comment.addClass( 'depth-' + ( depth[1] - 1) );
		}
	});

	children.insertBefore( item );

	children.slideDown();
}

function fv_tc_moderated(id, frontend) {
	jQuery.ajax({
		type: 'POST',
		url: fv_tc_conf.ajax_url,
		data: {"action": "fv_tc_moderated", 'id': id, nonce: fv_tc_conf.nonce},
		success: function(data){
			if( data.success ) {
				var link = jQuery(".commenter-"+id+"-moderated"),
					result = '';

				if ( data.user_not_moderated ) {
					if ( frontend ) {
						result = fv_tc_translations.moderate_future;
					} else {
						result = fv_tc_translations.unmoderate;
					}

				} else if (data.user_moderated ) {
					if ( frontend ) {
						result = fv_tc_translations.without_moderation;
					} else {
						result = fv_tc_translations.moderate;
					}

				} else {
					result = fv_tc_translations.mod_error;
				}

				link.text( result );

			} else {
				alert( fv_tc_translations.moderation_error );
			}
		}
	});
	return false;
}

// Remove thread delete and ban buttons if there are no replies
jQuery('.comment').each( function() {
	if( jQuery(this).find('ul.children, ol > li.comment').length == 0 ) {
		jQuery(this).find('.fv-tc-delthread, .fc-tc-banthread').remove();
	}
});
