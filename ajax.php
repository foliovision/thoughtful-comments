<?php

/**
 * @package foliovision-tc
 * @author Foliovision <programming@foliovision.com>
 * version 0.1
 */  

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

require (preg_replace('/wp-content.*$/','',__FILE__).'/wp-config.php');


/**
 * Delete whole thread of comments recursively. It echoes all the IDs in the tree which are handled by js.
 * 
 * @param int $id Comment ID
 * 
 * @global object Wordpress db object  
 */ 
function fv_tc_delete_recursive($id) {
    global  $wpdb;
    
    echo $id.' ';
    $comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE `comment_parent` ='{$id}'",ARRAY_A);
    if(strlen($wpdb->last_error)>0)
        die('db error');
    if(!wp_delete_comment($id))
        die('db error');
                
    /*  If there are no more children */
    if(count($comments)==0)
        return;
    foreach($comments AS $comment) {
        fv_tc_delete_recursive($comment['comment_ID']);
    }
}

if($_GET['cmd'] == 'approve') {
    check_admin_referer('fv-tc-approve_' . $_GET['id']);
    if(!wp_set_comment_status( $_GET['id'], 'approve' ))
        die('db error');
}

if($_GET['cmd'] == 'delete') {
    check_admin_referer('fv-tc-delete_' . $_GET['id']);
    
    //  delete whole thread
    if($_GET['thread'] == 'yes') {
        fv_tc_delete_recursive($_GET['id']);
    }
    //  simple delete
    else {
        if(!wp_delete_comment($_GET['id']))
            die('db error');
    }
        
    if(isset($_GET['ban']) && stripos(trim(get_option('blacklist_keys')),$_GET['ban'])===FALSE) {
        $blacklist_keys = trim(stripslashes(get_option('blacklist_keys')));
        
        $blacklist_keys_update = $blacklist_keys."\n".$_GET['ban'];
        update_option('blacklist_keys', $blacklist_keys_update);
    }
}

if($_GET['cmd'] == 'moderated') {
    check_admin_referer('fv-tc-moderated_' . $_GET['id']);
    
    if(get_usermeta($_GET['id'],'fv_tc_moderated')) {
        if(!delete_usermeta($_GET['id'],'fv_tc_moderated'))
            die('meta error');
        echo 'user moderated';
    }
    else {
        if(!update_usermeta($_GET['id'],'fv_tc_moderated','no'))
            die('meta error');
        echo 'user non-moderated';
    }
    
}
 
?>