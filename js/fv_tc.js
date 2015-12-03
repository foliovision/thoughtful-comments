/*  approve/disapprove comment  */
function fv_tc_approve(id) { 
    jQuery("#comment-"+id+"-approve").text(fv_tc_translations.wait + ' | '); 
    jQuery.ajax({
        type: 'POST',
        url: fv_tc_ajaxurl,
        data: {"action": "fv_tc_approve", 'id': id},
        success: function(data){
            jQuery("#comment-body-"+id).children(":first").text('');
            jQuery("#comment-"+id+"-approve").remove();
            jQuery("#comment-"+id+"-unapproved").removeClass("tc_highlight");
            jQuery("#comment-"+id+"-unapproved").removeClass("tc_highlight_spam");
        }
    });
    return false;  
}




/*  delete comment  */
function fv_tc_delete(id) {
    if(confirm(fv_tc_translations.comment_delete)) {
        jQuery.ajax({
            type: 'POST',
            url: fv_tc_ajaxurl,
            data: {"action": "fv_tc_delete", 'id': id},
            success: function(data){
                if(data.search(/db error/)==-1) {
                    var item = jQuery("[id^='comment'][id$='"+id+"']");
                    item.slideUp();
                } else {
                    alert(fv_tc_translations.delete_error);
                }
            }
        });
        return false;
    }
}



/*  delete comment and ban ip */
function fv_tc_delete_ban(id,ip) {
    if(confirm(fv_tc_translations.comment_delete_ban_ip)) {
        jQuery.ajax({
            type: 'POST',
            url: fv_tc_ajaxurl,
            data: {"action": "fv_tc_delete", 'id': id, 'ip': ip},
            success: function(data){
                if(data.search(/db error/)==-1) {
                    var item = jQuery("[id^='comment'][id$='"+id+"']");
                    item.slideUp();
                } else {
                    alert(fv_tc_translations.delete_error);
                }
            }
        });
        return false;
    }
}



/*  delete thread */
function fv_tc_delete_thread(id) {
    if(confirm(fv_tc_translations.comment_delete_replies)) {
        jQuery.ajax({
            type: 'POST',
            url: fv_tc_ajaxurl,
            data: {"action": "fv_tc_delete", 'id': id, 'thread': 'yes'},
            success: function(data){
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
        });
        return false;
    }
}




/*  delete thread and ban */
function fv_tc_delete_thread_ban(id, ip) {
    if(confirm(fv_tc_translations.comment_delete_replies_ban_ip)) {
        jQuery.ajax({
            type: 'POST',
            url: fv_tc_ajaxurl,
            data: {"action": "fv_tc_delete", 'id': id, 'ip': ip, 'thread': 'yes'},
            success: function(data){
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
                    alert(fv_tc_translations.delete_error);
                }
            }
        });
        return false;
    }
}



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




jQuery('.comment').each( function() {
  if( jQuery(this).find('ul.children').length == 0 ) {
    jQuery(this).find('.fv-tc-delthread, .fc-tc-banthread').remove();
  }
});
  
  
  

jQuery( function($) {

  if( typeof(fv_tc_count) != "undefined" ) {    
    setInterval( function() {
      $.ajax({
        type: 'POST',
        url: fv_tc_ajaxurl,
        data: {"action": "fv_tc_count", 'id': fv_tc_count.id},
        success: function(data){
          if( parseInt(data) > fv_tc_count.count ){
            var count = parseInt(data) - fv_tc_count.count;
            if( count > 1 ){
              var message = count+' new comments!'
            } else {
              var message = count+' new comment!'
            }
            $('#fv_tc_ticker a').text(message)
            $('#fv_tc_ticker').show();            
          } else {
            $('#fv_tc_ticker').hide(); 
          }
        }
      });
      $('#fv_tc_ticker a').text('Checking for new comments...');
      $('#fv_tc_ticker').show();
    }, 5000 );
  }

});




var fv_cp_classes = ".comment-body, .reply";

jQuery('.fv-cp-comment-show').click( function(e) {
  e.preventDefault();
  jQuery(this).parents('li.comment').eq(0).toggleClass('fv_cp_hidden').toggleClass('fv_cp_hidden_previously');
});
jQuery('.fv-cp-comment-hide').click( function(e) {
  e.preventDefault();
  jQuery(this).parents('li.comment').eq(0).toggleClass('fv_cp_hidden').toggleClass('fv_cp_hidden_previously');
});

var fv_comments_pink_hidden = true;

function fv_comments_pink_process() {
  var fv_cp_top_element;
  jQuery("#comments .commentlist li").each(function(){      
    if (jQuery(this).offset().top >= jQuery(window).scrollTop()) {
      fv_cp_top_element = jQuery(this);
      return false; 
    }
  })
      
  if( fv_comments_pink_hidden ) {
    jQuery('#fv-comments-pink-toggle').html('Show all comments');
    fv_comments_pink_hidden = false;
    jQuery(".fv_cp_hidden_previously").each(function() {
      jQuery(this).toggleClass('fv_cp_hidden').toggleClass('fv_cp_hidden_previously');
    });
    
  } else {   
    jQuery('#fv-comments-pink-toggle').html('Show only new comments');
    fv_comments_pink_hidden = true;
    jQuery(".fv_cp_hidden").each(function() {
      jQuery(this).toggleClass('fv_cp_hidden_previously').toggleClass('fv_cp_hidden');
    });
    
  }
  
  if( typeof(fv_cp_top_element) !== 'undefined' ) {
    jQuery(window).scrollTop(Math.round(fv_cp_top_element.offset().top) - jQuery("#wpadminbar").height());
  }
  return false;
}

jQuery('#fv-comments-pink-toggle').click( function(e) {
  if( fv_comments_pink_hidden ) {
    document.cookie="fv_comments_pink_hidden=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
  } else {
    document.cookie="fv_comments_pink_hidden=no";
  }
        
  e.preventDefault();
  fv_comments_pink_process();
} );



jQuery(document).ready(function($) {     
  
  if( typeof(fv_tc_new_comments) == "undefined" ){
    return;
  }
  
  jQuery('#fv-comments-pink-toggle').show();
  
  var fv_cp_id_prefix = '#li-comment-';
  var response = fv_tc_new_comments;
  if (response != -1) {	//	user has visited the post before
    jQuery('#fv-comments-pink-toggle').show();
    jQuery('#sharebox').addClass('with_comments');
    jQuery("#comments .commentlist li").each(function(){
      var found = false;
      var exploded = jQuery(this).attr("id").split(fv_cp_id_prefix.substr(1));
      var id = exploded[1]; 
      for (i = 0; i < response.length; i++) {        
        jQuery(fv_cp_id_prefix + response[i]).addClass('fv_cp_new_comment');
        if (response[i] == id) {
          found = true;
          break;
        }                    
      }
      if (!found) {
        if (jQuery(this).children("div").children(".comment-awaiting-moderation").length) {
          //unapproved comments
        }
        else {
          jQuery(this).addClass("fv_cp_hidden");
          ///jQuery(this).children("div").children(".comment-body").before("<a href='#' class='comment-show' onclick='toggleCommentContent(this, 1); return false;'><small>+ Show content</small></a>");
          /*if (document.location.hash == '') {                                              
            jQuery(this).children("div").children(fv_cp_classes).hide();                              
          }
          else {*/
            fv_comments_pink_hidden = false;

           ///jQuery(this).children("div").children(".comment-show").hide();
          /*}   */                         
        }                        
      }        
    })
    jQuery('#fv-comments-pink-toggle').attr('rel', 'show-new');
    jQuery('#fv-comments-pink-toggle').addClass('new');
    jQuery('#fv-comments-pink-toggle').html('Show all comments');        
  } else {	//	user if visiting the post for the first time
    jQuery('#fv-comments-pink-toggle').attr('onclick', '');
    jQuery('#fv-comments-pink-toggle').attr('href', '#comments');      	
    jQuery('#fv-comments-pink-toggle').attr('title', 'First visit. When you return, you can use this button to see just new comments.');
    jQuery('#fv-comments-pink-toggle').show();      	
  }
  
  fv_comments_pink_hidden = (document.cookie.match(/fv_comments_pink_hidden-DISABLED/)) ? false : true;
  fv_comments_pink_process();    
  
  
});


var fv_tc_ticker_scroll_prime = false;   
jQuery(document).ready( function() {
  fv_tc_ticker_scroll_prime = true;   
  
  jQuery(window).scroll( function() {
    fv_tc_ticker_scroll_prime =  true;
  } );
  
});

setInterval( function() {
  if( fv_tc_ticker_scroll_prime ) {
    jQuery('#fv_tc_ticker').each( function() {
      if(
         !jQuery(this).data('fv_ad_position') && jQuery(this).offset().top < jQuery(window).scrollTop() ||
         jQuery(this).data('fv_ad_position') < jQuery(window).scrollTop()
         ) {
        
        if( !jQuery(this).data('fv_ad_position') ) {                
          jQuery(this).data('fv_ad_position',jQuery(this).offset().top );
        }
        jQuery(this).css('position','fixed');
        jQuery(this).css('top','0');
        jQuery(this).addClass('floating');
      } else {
        jQuery(this).removeData('fv_ad_position');
        jQuery(this).css('position','static');
        jQuery(this).removeClass('floating');
      }
    });
  }
  fv_tc_ticker_scroll_prime = false;
}, 100 );