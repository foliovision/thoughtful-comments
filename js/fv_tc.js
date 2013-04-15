/*  approve/disapprove comment  */
function fv_tc_approve (id,url) { 
        jQuery("#comment-"+id+"-approve").text('Wait... | ');   
        jQuery.get(url, { id: id, cmd: "approve" },
            function(data){
                jQuery("#comment-body-"+id).children(":first").text('');
                jQuery("#comment-"+id+"-approve").remove();
                jQuery("#comment-"+id+"-unapproved").removeClass("tc_highlight");
                /*  todo    - update to new select system   */
            });
}

/*  delete comment  */
function fv_tc_delete (id,url) {
    if(confirm("Do you really want to delete this comment?")) {
        jQuery.get(url, { id: id, cmd: "delete" },
            function(data){
                if(data.search(/db error/)==-1) {
                    //jQuery("#comment-"+id).remove();
                    //jQuery("#comment-body-"+id).remove();
                    var item = jQuery("[id^='comment'][id$='"+id+"']");
                    item.slideUp();
                    //jQuery("#fv-tc-comment-"+id).parent().parent().remove();
                }
                else
                    alert("Error deleting comment");
            });
    }
}

/*  delete comment and ban ip */
function fv_tc_delete_ban (id,url,ip) {
    if(confirm("Do you really want to delete this comment and ban the IP?")) {
        jQuery.get(url, { id: id, cmd: "delete", ban: ip },
            function(data){
                if(data.search(/db error/)==-1) {
                    //jQuery("#comment-"+id).remove();
                    //jQuery("#comment-body-"+id).remove();
                    //jQuery("[id$='"+id+"']").remove();
                    var item = jQuery("[id^='comment'][id$='"+id+"']");
                    item.slideUp();
                    //jQuery("#fv_tc_comments-"+id).remove();
                }
                else
                    alert("Error deleting comment");
            });
    }
}

/*  delete thread */
function fv_tc_delete_thread (id,url) {
    if(confirm("Do you really want to delete this comment and all the replies?")) {
        jQuery.get(url, { id: id, cmd: "delete", thread: "yes" },
            function(data){
                if(data.search(/db error/)==-1) {
                    var posts = data.split(" ");
                    var i = 0;
                    while (i < posts.length) {
                        //jQuery("#comment-"+posts[i]).remove();
                        //jQuery("#comment-body-"+posts[i]).remove();
                        if(posts[i]!='') {
                            //jQuery("[id$='"+posts[i]+"']").remove();
                            var item = jQuery("[id^='comment'][id$='"+posts[i]+"']");
                            item.slideUp();
                            //Query("#fv_tc_comments-"+posts[i]).remove();
                        }
                        i+=1;
                    }
                }
                else
                    alert("Error deleting comment");
            });
    }
}

/*  delete thread and ban */
function fv_tc_delete_thread_ban (id,url, ip) {
    if(confirm("Do you really want to delete this comment with all the replies and ban the IP?")) {
        jQuery.get(url, { id: id, cmd: "delete", thread: "yes", ban: ip },
            function(data){
                if(data.search(/db error/)==-1) {
                    var posts = data.split(" ");
                    var i = 0;
                    while (i < posts.length) {
                        //jQuery("#comment-"+posts[i]).remove();
                        //jQuery("#comment-body-"+posts[i]).remove();
                        if(posts[i]!='') {
                            //jQuery("[id$='"+posts[i]+"']").remove();
                            var item = jQuery("[id^='comment'][id$='"+posts[i]+"']");
                            item.slideUp();
                            //jQuery("#fv_tc_comments-"+posts[i]).remove();
                        }
                        i+=1;
                    }
                }
                else
                    alert("Error deleting comment");
            });
    }
}

/*  manage user moderation  */
function fv_tc_moderated (id,url,frontend) {
    //alert("ID: "+id+" url: "+url);
    jQuery.get(url, { id: id, cmd: "moderated" },
        function(data) {
            if(data.search(/user non-moderated/)!=-1)
                if(frontend)
                    jQuery(".commenter-"+id+"-moderated").text("Moderate future comments by this user");
                else
                    jQuery(".commenter-"+id+"-moderated").text("Unmoderated");
            else if (data.search(/user moderated/)!=-1)
                if(frontend)
                    jQuery(".commenter-"+id+"-moderated").text("Allow user to comment without moderation");
                else
                    jQuery(".commenter-"+id+"-moderated").text("Moderated");
            else
                jQuery(".commenter-"+id+"-moderated").text("Error");
    });
}