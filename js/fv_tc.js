/*  manage user moderation  */
function fv_tc_moderated(id, frontend) {
    jQuery.ajax({
            type: 'POST',
            url: fv_tc_ajaxurl,
            data: {"action": "fv_tc_moderated", 'id': id},
            success: function(data){
                if(data.search(/user non-moderated/)!=-1)
                    if(frontend)
                        jQuery(".commenter-"+id+"-moderated").text(fv_tc_translations.moderate_future);
                    else
                        jQuery(".commenter-"+id+"-moderated").text(fv_tc_translations.unmoderate);
                else if (data.search(/user moderated/)!=-1)
                    if(frontend)
                        jQuery(".commenter-"+id+"-moderated").text(fv_tc_translations.without_moderation);
                    else
                        jQuery(".commenter-"+id+"-moderated").text(fv_tc_translations.moderate);
                    else
                        jQuery(".commenter-"+id+"-moderated").text(fv_tc_translations.mod_error);
            }
        });
        return false;
}



/* display report wrapper */
function fv_tc_report_display( id ) {
  jQuery("#comment_report_"+id).toggle();
}


/* report comment */
function fv_tc_report_comment( id ) {

  var reason = jQuery( "#report_reason_"+id ).val();
  var nonce = jQuery( "#report_nonce_"+id ).val();
  var message = "";
  var button = jQuery( "#report_button_"+id );

  if( reason.length == 0 ) {
    message = "<span class='fv_tc_warning'>Your report reason cannot be empty.</span>";
  }
  else {
    jQuery.ajax({
      type: 'POST',
      url: fv_tc_ajaxurl,
      async: false,
      data: {
        "action": "fv_tc_report",
        '_ajax_nonce': nonce,
        'id': id,
        'reason': reason
      },
      success: function(data){
        var status = parseInt( data );

        if( status > 0 ) {
          message = "Your report has been submitted.";
          
        }
        else if( status === -1 ) {
          message = "Comment wasn't reported. Something went wrong.";
        }
        else if( status === -2 ) {
          message = "Comment wasn't reported. You are not allowed to report comments.";
        }
        else if( status === -3 ) {
          message = "You have already reported this comment.";
        }

        jQuery( "#report_button_"+id ).prop("disabled", true);
        button.hide();
      }
    });
  }

  jQuery( "#report_response_"+id ).html( message );

}


/**
 * close report
 * @param  int id report id number
 */
function fv_tc_report_close ( id, callback ) {
  jQuery.ajax({
      type: 'POST',
      url: fv_tc_ajaxurl,
      async: false,
      data: {"action": "fv_tc_report_close", 'id': id},
      success: function(data){
        
      }
    });
}

function fv_tc_report_admin( id, type, comment_id ) {
  var text = '';
  if( type == 'close' ) {
    fv_tc_report_close( id );
    text = 'Closed';
  } else if( type == 'trash' ) {
    fv_tc_delete( comment_id, 'true' );
    text = 'Deleted';
  }
  
  var parent = jQuery("#report_row_"+id);
  parent.find(".report_status").text(text);
  parent.find(".report_action a").remove();
  
}

function fv_tc_report_front_close ( id ) {
  fv_tc_report_close( id ); //  TODO: check the response
  jQuery("#report_row_"+id).fadeOut(300, function() {
    var container = jQuery(this).parents('.fv_tc_reports');
    jQuery(this).remove();
    if( container.find('li').length == 0 ) {
      container.parents('.comment').removeClass('comment-has-report');
      container.remove();
    }
  });
}




( function($) {
  if( typeof(fv_tc_html) == "undefined" ) return;
  
  var start = +new Date();
  var div = jQuery(fv_tc_html);
  jQuery('.tc-frontend').append(div);
  console.log( 'FV Thoughtful Comments took '+(+new Date() - start)+' ms' );
  
  $('.comment').each( function() {
    if( $(this).find('.children').length == 0 ) {
      $(this).find('.fv-tc-delthread, .fv-tc-banthread').remove();
    }
  });
  
  
  $(document).on('click','.fv-tc-approve, .fv-tc-unspam, .fv-tc-del, .fv-tc-delthread, .fv-tc-ban, .fv-tc-banthread', function(e) {
    e.preventDefault();
    
    var button = $(this);
    var item = button.parents('[id^=comment-]').eq(0);
    
    var action = button.attr('class').replace(/-/g,'_');
    if( action == 'fv_tc_del' ) {
      if( item.find('.children').length > 0 ) {
        if( !confirm(fv_tc_translations.comment_delete_has_replies) ) return;
      } else if( !confirm(fv_tc_translations.comment_delete) ) return;
      
    }
    if( action == 'fv_tc_ban' && !confirm(fv_tc_translations.comment_delete_ban_ip) ) return;
    if( action == 'fv_tc_delthread' && !confirm(fv_tc_translations.comment_delete_replies) ) return;
    if( action == 'fv_tc_banthread' && !confirm(fv_tc_translations.comment_delete_replies_ban_ip) ) return;
    
    
    var comment_id = item.attr('id').match(/\d+/);
    if( comment_id ) {
      button.text(fv_tc_translations.wait);
      console.log(action,comment_id[0]);
      jQuery.ajax({
          type: 'POST',
          url: fv_tc_ajaxurl,
          data: { "_ajax_nonce": fv_tc_nonce, "action": action, 'id': comment_id[0] },
          success: function(data){
            
            if ( action == 'fv_tc_approve' ) {
              if( data == 1 ) {
                button.parents('.tc-unapproved').removeClass('tc-unapproved');
                $("#comment-"+comment_id+"-unapproved").removeClass("tc_highlight");
                $("#comment-"+comment_id+"-unapproved").removeClass("tc_highlight_spam"); //  todo: what is this?
                $("#comment-"+comment_id).find("p.comment-awaiting-moderation").remove();
              } else {
                alert(fv_tc_translations.approve_error);
              }
              
            } else if( action == 'fv_tc_del' || action == 'fv_tc_ban' ) {
              if( data == 1 ) {                  
                  var item = jQuery("[id^='comment'][id$='"+comment_id+"']");
                  if( item.find(".children [id^='comment-']").length ){
                    item.parent().append( item.find(".children [id^='comment-']") );
                  }
                  item.slideUp();
                  result = true;
              } else {
                  alert(fv_tc_translations.delete_error);
              }
              
            } else if( action == 'fv_tc_delthread' || action == 'fv_tc_banthread' ) {
              if(data.search(/db error/)==-1) {
                var posts = data.split(" ");
                var i = 0;
                while (i < posts.length) {
                  if(posts[i]!='') {
                    var item = jQuery("[id^='comment'][id$='"+posts[i]+"']");
                    item.slideUp();
                  }
                  i+=1;
                }
              } else {
                alert(fv_tc_translations.comment_delete_replies);
              }
              
            }
            
          }
      });      
    }
    
  });  
  

  $(document).on('click','.comment-reply-login', function() {
    var id = $(this).parents('li.comment').attr('id');
    document.cookie="fv_tc_reply="+id;
  });  
  
  $(document).ready( function() {
    var match = document.cookie.match(/fv_tc_reply=(comment-\d+)/);
    if( match && match[1] && $('#'+match[1]).length ) {
      $('html, body').animate({
          scrollTop: $('#'+match[1]).offset().top - 100
      }, 1000);
      $('#'+match[1]+'.fv_cp_hidden').find('.fv-cp-comment-show').click();
      document.cookie="fv_tc_reply=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
    }
  });
  
})(jQuery);

