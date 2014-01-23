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
