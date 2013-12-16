/*  approve/disapprove comment  */

function fv_tc_approve (id,url,sWait) { 
        jQuery("#comment-"+id+"-approve").text(sWait + ' | ');   

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
    if(confirm(translations.comment_delete)) {

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

                    alert(translations.delete_error);

            });

    }

}



/*  delete comment and ban ip */

function fv_tc_delete_ban (id,url,ip) {
    if(confirm(translations.comment_delete_ban_ip)) {

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

                    alert(translations.delete_error);

            });

    }

}



/*  delete thread */

function fv_tc_delete_thread (id,url) {
    if(confirm(translations.comment_delete_replies)) {

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

                    alert(translations.comment_delete_replies);

            });

    }

}



/*  delete thread and ban */

function fv_tc_delete_thread_ban (id,url, ip) {
    if(confirm(translations.comment_delete_replies_ban_ip)) {

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

                    alert(translations.delete_error);

            });

    }

}



/*  manage user moderation  */

function fv_tc_moderated (id,url,frontend) {
    jQuery.get(url, { id: id, cmd: "moderated" },

        function(data) {

            if(data.search(/user non-moderated/)!=-1)

                if(frontend)

                    jQuery(".commenter-"+id+"-moderated").text(translations.moderate_future);

                else

                    jQuery(".commenter-"+id+"-moderated").text(translations.unmoderate);

            else if (data.search(/user moderated/)!=-1)

                if(frontend)

                    jQuery(".commenter-"+id+"-moderated").text(translations.without_moderation);

                else

                    jQuery(".commenter-"+id+"-moderated").text(translations.moderate);

            else

                jQuery(".commenter-"+id+"-moderated").text(translations.mod_error);

    });

}