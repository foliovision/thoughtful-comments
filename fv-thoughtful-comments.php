<?php
/*
Plugin Name: FV Thoughtful Comments
Plugin URI: http://foliovision.com/
Description: Manage incomming comments more effectively by using frontend comment moderation system provided by this plugin.
Version: 0.2.4.
Author: Foliovision
Author URI: http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/

The users cappable of moderate_comments are getting all of these features and are not blocked 
*/

/*  Copyright 2011  Foliovision  (email : programming@foliovision.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * @package foliovision-tc
 * @author Foliovision <programming@foliovision.com>
 * version 0.2.4
 */  

include( 'fp-api.php' );

if( class_exists('fv_tc_Plugin') ) :

class fv_tc extends fv_tc_Plugin {
    /**
     * Plugin directory URI
     * @var string
     */              
    var $url;
    
    /**
     * Class contructor. Sets all basic variables.
     */         
    function __construct(){ 
        $this->url = trailingslashit( get_bloginfo('wpurl') ).PLUGINDIR.'/'. dirname( plugin_basename(__FILE__) );
        $this->readme_URL = 'http://plugins.trac.wordpress.org/browser/thoughtful-comments/trunk/readme.txt?format=txt';    
          add_action( 'in_plugin_update_message-thoughtful-comments/fv-thoughtful-comments.php', array( &$this, 'plugin_update_message' ) );    
        add_action( 'activate_' .plugin_basename(__FILE__), array( $this, 'activate' ) );   
    }


    function activate() {
        if( !get_option('thoughtful_comments') ) update_option( 'thoughtful_comments', array( 'shorten_urls' => true, 'reply_link' => false ) );
    }
    
    
    function admin_init() {
      /*
      Simple text field  which is sanitized to fit into YYYY-MM-DD and only >= editors are able to edit it for themselves
      */
      x_add_metadata_field( 'fv_tc_moderated', 'user', array(
      	'field_type' => 'text',
      	'label' => 'Moderation queue',	
      	'display_column' => true,
      	'display_column_callback' => 'fv_tc_x_add_metadata_field'
        )
      );
    }

    
    function admin_menu(){
        add_options_page( 'FV Thoughtful Comments', 'FV Thoughtful Comments', 'manage_options', 'manage_fv_thoughtful_comments', array($this, 'options_panel') );
    } 
  
     
    /**
     * Adds the plugin functions into Comment Moderation in backend. Hooked on comment_row_actions.
     * 
     * @param array $actions Array containing all the actions associated with each of the comments
     * 
     * @global object Current comment object
     * @global object Post object associated with the current comment
     * 
     * @todo Delete thread options should be displayed only fif the comment has some children, but that may be too much for the SQL server 
     *          
     * @return array Comment actions array with our new items in it.               
     */              
    function admin($actions) {
        global $comment, $post;/*, $_comment_pending_count;*/
        
        if ( current_user_can('edit_post', $post->ID) ) {
            /*  If the IP isn't on the blacklist yet, display delete and ban ip link  */
            $banned = stripos(trim(get_option('blacklist_keys')),$comment->comment_author_IP);
            $child = $this->comment_has_child($comment->comment_ID, $comment->comment_post_ID);
            if($banned===FALSE)
                $actions['delete_ban'] = $this->get_t_delete_ban($comment);
            else
                $actions['delete_ban'] = '<a href="#">Already banned!</a>';
            if($child>0) {
              $actions['delete_thread'] = $this->get_t_delete_thread($comment);
              if($banned===FALSE)            
                  $actions['delete_thread_ban'] = $this->get_t_delete_thread_ban($comment);
              /*else
                  $actions['delete_banned'] = '<a href="#">Already banned!</a>';*/
            }
            
            //  blacklist email address
            /*if(stripos(trim(get_option('blacklist_keys')),$comment->comment_author_email)!==FALSE)
                $actions['blacklist_email'] = "Email Already Blacklisted";
            else
                $actions['blacklist_email'] = "<a href='$blacklist_email' target='_blank' class='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved vim-a' title='" . __( 'Blacklist Email' ) . "'>" . __( 'Blacklist Email' ) . '</a>';*/
        } 
        return $actions;
    }


    /**
     * Filter for manage_users_columns to add new column into user management table
     * 
     * @param array $columns Array of all the columns
     * 
     * @return array Array with added columns
     */                             
    function column($columns) {
        $columns['fv_tc_moderated'] = "Moderation queue";
        return $columns;
    }


    /**
     * Filter for manage_users_custom_column inserting the info about comment moderation into the right column
     * 
     * @return string Column content
     */          
    function column_content($content) {
        /* $args[0] = column content (empty), $args[1] = column name, $args[2] = user ID */    
        $args = func_get_args();
        
        /* Check the custom column name */
        if($args[1] == 'fv_tc_moderated') {
            /* output Allow user to comment without moderation/Moderate future comments by this user by using user ID in $args[2] */
            return $this->get_t_moderated($args[2],false).'<!--fvtc-->';
        }
        return $content.'<!--fvtc-->';
    }
    
    
    /**
     * Check if comment has any child
     * 
     * @param int $id Comment ID
     * 
     * @global object Wordpress db object
     * 
     * @return number of child comments
     */                                            
    function comment_has_child($id, $postid) {
        global $wp_query;
        
        ///  addition  2010/06/02 - check if you have comments filled in
        if ($wp_query->comments != NULL ) {
          foreach( $wp_query->comments AS $comment ) {
            if( $comment->comment_parent == $id ) {
              return true; 
            }
          }
        }
        return false;
        
        //  forget about the database!
        /*global  $wpdb;
        return $wpdb->get_var("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_id = '{$postid}' AND comment_parent = '{$id}' LIMIT 1");
        */
    } 

    /**
     * Replace url of reply link only with #
     * functionality is done only by JavaScript     
     */ 
    function comment_reply_links ($link = null) {
        $options = get_option('thoughtful_comments');
        if ($options['reply_link']) {        
            $noscript = '<noscript>Reply link does not work in your browser because JavaScript is disabled.<br /></noscript>';
            $link_script = preg_replace( '~href.*onclick~' , 'href="#" onclick' , $link );
            return $noscript .  $link_script;               
        }         
        return $link;
    }
        
    
    /**
     * Clear the URI for use in onclick events.
     * 
     * @param string The original URI
     * 
     * @return string Cleaned up URI
     */                             
    function esc_url($url) {
        if(function_exists('esc_url'))
            return esc_url($url);
        /*  Legacy WP support */
        else
            return clean_url($url);
    }    
      
    
    /**
    * Filter for comment_text. Displays frontend moderation options if user can edit posts.    
    *
    * @param string $content Comment text.
    *
    * @global int Current user ID
    * @global object Current comment object         
    *        
    * @return string Comment text with added features. 
    */
    function frontend ($content) {
        global  $user_ID, $comment, $post;
        $user_info = get_userdata($comment->user_id);

        if($user_ID && current_user_can('edit_post', $post->ID) && !is_admin()) { 
          $child = $this->comment_has_child($comment->comment_ID, $comment->comment_post_ID);
          /*  Container   */
        	$out = '<p class="tc-frontend">';
        	/* Approve comment */
        	if($comment->comment_approved == '0')
            $out .= '<span id="comment-'.$comment->comment_ID.'-approve">'.$this->get_t_approve($comment).' | </span>';
            /*  Delete comment  */
            $out .= $this->get_t_delete($comment).' | ';
            /*  Delete thread   */
            if($child>0)
                $out .= $this->get_t_delete_thread($comment).' | ';
            /*  If IP isn't banned  */
            if(stripos(trim(get_option('blacklist_keys')),$comment->comment_author_IP)===FALSE) {
                /*  Delete and ban  */
                $out .= $this->get_t_delete_ban($comment);//.' | ';
                /*  Delete thread and ban   */
                if($child>0)
                    $out .= ' | '.$this->get_t_delete_thread_ban($comment);
            }
            else {
                $out .= 'IP '.$comment->comment_author_IP.' already banned! ';
            }
            /*  Moderation status   */
            if($comment->user_id !=0 && $user_info->user_level < 3) {
                $out .= '<br />'.$this->get_t_moderated($comment->user_id);
            }
            $out .= '</p>';
            $out .= '<span id="fv-tc-comment-'.$comment->comment_ID.'"></span>';   

        	return $content . $out;	
    	}
    	return $content;
    }

    
    /**
     * Generate the anchor for approve function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */   
    function get_t_approve($comment) {
        return '<a href="#" onclick="fv_tc_approve('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-approve_' . $comment->comment_ID)).'\'); return false">Approve</a>';
    }
    
    
    /**
     * Generate the anchor for delete function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete($comment) {
        return '<a href="#" onclick="fv_tc_delete('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-delete_' . $comment->comment_ID)).'\'); return false">Delete</a>';
    }
    
    
    /**
     * Generate the anchor for delete and ban IP function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_ban($comment) {
        return '<a href="#" onclick="fv_tc_delete_ban('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-delete_' . $comment->comment_ID)).'\',\''.$comment->comment_author_IP.'\'); return false">Delete & Ban IP</a>';
    }
    
    
    /**
     * Generate the anchor for delete thread function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_thread($comment) {
        return '<a href="#" onclick="fv_tc_delete_thread('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-delete_' . $comment->comment_ID)).'\'); return false">Delete Thread</a>';
    }

    
    /**
     * Generate the anchor for delete thread and ban IP function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_thread_ban($comment) {
        return '<a href="#" onclick="fv_tc_delete_thread_ban('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-delete_' . $comment->comment_ID)).'\',\''.$comment->comment_author_IP.'\'); return false">Delete Thread & Ban IP</a>';
    }
    
    
    /**
     * Generate the anchor for auto approving function
     * 
     * @param object $comment Comment object
     * @param bool $frontend Alters the anchor text if the function is used in backend.     
     * 
     * @return string HTML of the anchor
     */ 
    function get_t_moderated($user_ID, $frontend = true) {
        if($frontend)
            $frontend2 = 'true';
        else
            $frontend2 = 'false';
            
        $out = '<a href="#" class="commenter-'.$user_ID.'-moderated" onclick="fv_tc_moderated('.$user_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-moderated_' . $user_ID)).'\','.$frontend2.'); return false">'; 
        if(!get_usermeta($user_ID,'fv_tc_moderated'))
            if($frontend)
                $out .= 'Allow user to comment without moderation</a>';
            else
                $out .= 'Moderated</a>';
        else
            if($frontend)
                $out .= 'Moderate future comments by this user</a>';
            else
                $out .= 'Unmoderated</a>';
        return  $out;
    }    

    
    /**
     * Filter for pre_comment_approved. Skip moderation queue if the user is allowed to comment without moderation
     * 
     * @params string $approved Current moderation queue status
     * 
     * @global int Comment author user ID
     * 
     * @return string New comment status                               
     */         
    function moderate($approved) {
        global  $user_ID;
        
        ///////////////////////////
        
        /*global  $wp_filter;
        
        var_dump($wp_filter['pre_comment_approved']);
        
        echo '<h3>before: </h3>';
        
        var_dump($approved);
        
        echo '<h3>fv_tc actions: </h3>';
        
        if(get_usermeta($user_ID,'fv_tc_moderated')) {
            echo '<p>putting into approved</p>';
        }
        else {
            echo '<p>putting into unapproved</p>';
        }
            
        die('end');*/
        /////////////////////////
        
        if(get_usermeta($user_ID,'fv_tc_moderated'))    
            return  true;
        return  $approved;
    }
        
    
    function options_panel() {
        if (!empty($_POST)) :
            check_admin_referer('thoughtful_comments');
            $options = array(
                'shorten_urls' => ( $_POST['shorten_urls'] ) ? true : false,            
                'reply_link' => ( $_POST['reply_link'] ) ? true : false
            );
            if( update_option( 'thoughtful_comments', $options ) ) :
            ?>
            <div id="message" class="updated fade">
                <p>
                    <strong>
                        Settings saved
                    </strong>
                </p>
            </div>
            <?php
            endif;  //  update_option
        endif;  //  $_POST
        $options = get_option('thoughtful_comments');
        ?>
        <div class="wrap">
            <div style="position: absolute; right: 20px; margin-top: 5px">
                <a href="http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments" target="_blank" title="Documentation"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
            </div>
            <div>
                <div id="icon-options-general" class="icon32"><br /></div>
                <h2>FV Thoughtful Comments</h2>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field('thoughtful_comments') ?>
                <div id="poststuff" class="ui-sortable">
                    <div class="postbox">
                        <h3>
                            <?php _e('Comment Tweaks') ?>
                        </h3>
                        <div class="inside">
                            <table class="optiontable form-table">
                                <tr valign="top">
                                    <th scope="row"><?php _e('Link shortening', 'wp_mail_smtp'); ?> </th>  
                                    <td><fieldset><legend class="screen-reader-text"><span><?php _e('Link shortening', 'wp_mail_smtp'); ?></span></legend>                                  
                                    <input id="shorten_urls" type="checkbox" name="shorten_urls" value="1" 
                                        <?php if( $options['shorten_urls'] ) echo 'checked="checked"'; ?> />
                                    <label for="shorten_urls"><span><?php _e('Shortens the plain URL link text in comments to  ČĽlink to: domain.com ČÝ. Prevents display issues if the links have too long URL.', 'wp_mail_smtp'); ?></span></label><br />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Reply link', 'wp_mail_smtp'); ?> </th> 
                                    <td><fieldset><legend class="screen-reader-text"><span><?php _e('Reply link', 'wp_mail_smtp'); ?></span></legend>                              
                                    <input id="reply_link" type="checkbox" name="reply_link" value="1" 
                                        <?php if( $options['reply_link'] ) echo 'checked="checked"'; ?> />                                     
                                    <label for="reply_link"><span><?php _e('Check to make comment reply links use JavaScript only. Useful if your site has a lot of comments and web crawlers are browsing through all of their reply links.', 'wp_mail_smtp'); ?></span></label><br />
                                    </td>
                                </tr>                               
                            </table>
                            <p>
                                <input type="submit" name="fv_feedburner_replacement_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
          
        
    /**
    * Action for wp_print_scripts - enqueues plugin js which is dependend on jquery. Improved in 0.2.3  ////
    * 
    * @global int Current user ID        
    */
    function scripts() {
        global  $user_ID, $post;
        $user_info = get_userdata( $user_ID );
        if($user_ID && ( current_user_can('edit_post', $post->ID) || $user_info->wp_user_level > 5 ) ) {
            wp_enqueue_script('fv_tc',$this->url. '/js/fv_tc.js',array('jquery'));
        }
    }
    
    
    /**
    * Filter for comments_number. Shows number of unapproved comments for every article in the frontend if the user can edit the post. In WP, all the unapproved comments are shown both to contributors and authors in wp-admin, but we don't do that in frontend.
    *
    * @global int Current user ID
    * @global object Current post object
    *           
    * @param string $content Text containing the number of comments.
    *     
    * @return string Number of comments with inserted number of unapproved comments. 
    */
    function show_unapproved_count($content) {
        global  $user_ID;
        global  $post;
        
        if($user_ID && current_user_can('edit_post', $post->ID)) {
            if(function_exists('get_comments'))
                $comments = get_comments( array('post_id' => $post->ID, 'order' => 'ASC', 'status' => 'hold') );
            /*  Legacy WP support */
            else {
                global  $wpdb;
                $comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = {$post->ID} AND comment_approved = '0' ORDER BY comment_date ASC");
            }
            $count = count($comments);
            if($count!= 0) {
                //return '<span class="tc_highlight"><abbr title="This post has '.$count.' unapproved comments">'.str_ireplace(' comm','/'.$count.'</abbr></span> comm',$content).'';
                $content = preg_replace( '~(\d+)~', '<span class="tc_highlight"><abbr title="This post has '.$count.' unapproved comments">$1</abbr></span>', $content );
                return $content;
                }
        }
        return $content;
    }
    
    
    /**
     * Styling for the plugin
     */
    function styles() {
    		global $post;
    		//	this is executed in the header, so we can't do the check for every post on index/archive pages, so we better load styles if there are any unapproved comments to show. it's loaded even for contributors which don't need it.
    		if(!is_admin() && current_user_can('edit_posts')) {
          echo '<link rel="stylesheet" href="'.$this->url.'/css/frontend.css" type="text/css" media="screen" />'; 
        }
    }        
    

    /**
     * Thesis is not using comment_text filter. It uses thesis_hook_after_comment action, so this outputs our links
     *
     * @param string $new_status Empty string.
     */    
    function thesis_frontend_show($content) {
        echo $this->frontend($content);
    }
         
    
    /**
     * Call hooks for when a comment status transition occurs.
     *
     * @param string $new_status New comment status.
     * @param string $old_status Previous comment status.
     * @param object $comment Comment data.
     */
    function transition_comment_status( $new_status, $old_status, $comment ) {
      global $wpdb;
      
      if( $old_status == 'trash' && $new_status != 'spam' ) { //  restoring comment
          $children = get_comment_meta( $comment->comment_ID, 'children', true );
          if( $children && is_array( $children ) ) {
            $children = implode( ',', $children );
            $wpdb->query( "UPDATE $wpdb->comments SET comment_parent = '{$comment->comment_ID}' WHERE comment_ID IN ({$children}) " );
          }
          delete_comment_meta( $comment->comment_ID, 'children' );
      }
      
      if( $new_status == 'trash' ) {  //  trashing comment
        if( function_exists( 'update_comment_meta' ) ) {  //  store children in meta
          $children = $wpdb->get_col( "SELECT comment_ID FROM $wpdb->comments WHERE comment_parent = '{$comment->comment_ID}' " );
          if( $children ) {
            update_comment_meta( $comment->comment_ID, 'children', $children );
          }
        } //  assign new parents
        $wpdb->query( "UPDATE $wpdb->comments SET comment_parent = '{$comment->comment_parent}' WHERE comment_parent = '{$comment->comment_ID}' " );
  
        /*var_dump( $old_status );
        echo ' -> ';
        var_dump( $new_status );  //  approved
        die();*/
      }
    }
    
    
    /**
     * Shows unapproved comments bellow posts if user can moderate_comments. Hooked to comments_array. In WP, all the unapproved comments are shown both to contributors and authors in wp-admin, but we don't do that in frontend.
     * 
     * @param array $comments Original array of the post comments, that means only the approved comments.
     * @global int Current user ID.
     * @global object Current post object.                
     * 
     * @return array Array of both approved and unapproved comments.
     */               
    function unapproved($comments) {
        global  $user_ID;
        global  $post;
        
        /*  Check user permissions */
        if($user_ID && current_user_can('edit_post', $post->ID)) { 
            /*  Use the standard WP function to get the comments  */
            if(function_exists('get_comments'))
                $comments = get_comments( array('post_id' => $post->ID, 'order' => 'ASC') );
            /*  Use DB query for older WP versions  */
            else {
                global  $wpdb;
                $comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = {$post->ID} AND comment_approved != 'spam' ORDER BY comment_date ASC");
            }
            
            /*  Target array where both approved and unapproved comments are added  */
            $new_comments = array();
            foreach($comments AS $comment) {
                /*  Don't display the spam comments */ 
                if($comment->comment_approved == 'spam')
                    continue;
                /*  Highlight the comment author in case the comment isn't approved yet */    
                if($comment->comment_approved == '0') {
                    /*  Alternative - highlight the comment content */
                    //$comment->comment_content = '<div id="comment-'.$comment->comment_ID.'-unapproved" style="background: #ffff99;">'.$comment->comment_content.'</div>';
                    $comment->comment_author = '<span id="comment-'.$comment->comment_ID.'-unapproved" class="tc_highlight">'.$comment->comment_author.'</span>';
                }
                $new_comments[] = $comment;
            }
            return $new_comments;
        }
        return $comments;
    }
    
    
    /*  Experimental stuff  */
    
    /*  mess with the WP blacklist mechanism */
    function blacklist($author) {
        $args = func_get_args();
        
        echo '<p>'.$args[0].', '.$args[1].', '.$args[2].', '.$args[3].', '.$args[4].', '.$args[5].'</p>';
            
        //die('blacklist dies');
    } 
    
    function comment_moderation_headers( $message_headers ) {
        $options = get_option('thoughtful_comments');
        if( $options['enhance_notify'] == false && isset( $options['enhance_notify'] ) ) return $message_headers;      
        $message_headers .= "\r\n"."Content-Type: text/html"; //  this should add up
        return $message_headers;
    }
    
    function comment_moderation_text( $notify_message ) {
        $options = get_option('thoughtful_comments');
        if( $options['enhance_notify'] == false && isset( $options['enhance_notify'] ) ) return $notify_message;        
        global $wpdb;        
        preg_match( '~&c=(\d+)~', $notify_message, $comment_id ); //  we must get the comment ID somehow
        $comment_id = $comment_id[1];        
        if( intval( $comment_id ) > 0 ) {          
          /// all links until now are non-html, so we add it now
      		$notify_message = preg_replace( '~([^"\'])(http://\S*)([^"\'])~', '$1<a href="$2">$2</a>$3', $notify_message );      		      
          $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_ID=%d LIMIT 1", $comment_id));
          $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID=%d LIMIT 1", $comment->comment_post_ID));
          $rows = explode( "\n", $comment->comment_content );
          foreach( $rows AS $key => $value ) {
            $rows[$key] = '> '.$value;  
          }
          $content = "\r\n\r\n".implode( "\n", $rows );
      		$replyto = 'Reply to comment via email: <a href="mailto:'.rawurlencode('"'.$comment->comment_author.'" ').'<'.$comment->comment_author_email.'>'.'?subject='.rawurlencode( 'Your comment on "'.$post->post_title.'"' ).'&body='.rawurlencode( $content ).'&bcc='.$options['reply_bcc'].'">Email reply</a>'."\r\n";
      		$linkto .= 'Link to comment: <a href="'.get_permalink($comment->comment_post_ID) . '#comment-'.$comment_id.'">Comment link</a>'."\r\n";
      		$notify_message = str_replace( 'Approve it:', $replyto."Approve it:", $notify_message );
      		$notify_message = str_replace( 'Approve it:', $linkto."Approve it:", $notify_message );
      		$notify_message = wpautop( $notify_message );
        }
    		//echo $notify_message; die();
    		return $notify_message;
    } 
    
    /**
     * Callback for plain link replacement in links
     * 
     * @param string Link
     * 
     * @return string New link
     */ 
    function comment_links_replace( $link ) {
      //echo '<!--link'.var_export( $link, true ).'-->';
      /*if( !stripos( $link[1], '://' ) ) {
        return $link[0];
      }*/
      $match_domain = $link[2];
      $match_domain = str_replace( '://www.', '://', $match_domain );
      preg_match( '!//(.+?)/!', $match_domain, $domain );
      //var_dump( $domain );
      $link = $link[1].'<a href="'.esc_url($link[2]).'">link to '.$domain[1].'</a><br />'.$link[3];
      return $link;
    } 
    
    
    /**
     * Callback for <a href="LINK">LINK</a> replacement in comments
     * 
     * @param string Link
     * 
     * @return string New link
     */     
    function comment_links_replace_2( $link ) {

      preg_match( '~href=["\'](.*?)["\']~', $link[0], $href );
      preg_match( '~>(.*?)</a>~', $link[0], $text );
      if( $href[1] == $text[1] ) {
        preg_match( '!//(.+?)/!', $text[1], $domain );
        if( $domain[1] ) {
          $domain[1] = preg_replace( '~^www\.~', '', $domain[1] );
          $link[0] = str_replace( $text[1].'</a>', 'link to '.$domain[1].'</a>', $link[0] );
        }
      }
      return $link[0];
    } 
    
    
    /**
     * Replace long links with shorter versions
     * 
     * @param string Comment text
     * 
     * @return string New comments text
     */         
    function comment_links( $content ) {
        $options = get_option('thoughtful_comments');
        if( $options['shorten_urls'] == false && isset( $options['shorten_urls'] ) ) return $content;      
        $content = ' ' . $content;        

        $content = preg_replace_callback( '!<a[\s\S]*?</a>!', array(get_class($this), 'comment_links_replace_2' ), $content );

        return $content; 
    }        
    
    function stc_comment_deleted() {
        global $wp_subscribe_reloaded;
        if( !is_admin() && $wp_subscribe_reloaded ) {           
            add_action('deleted_comment', array( $wp_subscribe_reloaded, 'comment_deleted'));
        }
    }


    function stc_comment_status_changed() {
        global $wp_subscribe_reloaded;
        if( !is_admin() && $wp_subscribe_reloaded ) {
            add_action('wp_set_comment_status', array( $wp_subscribe_reloaded, 'comment_status_changed'));
        }
    }
    
    
}

$fv_tc = new fv_tc;


/*
Special for 'Custom Metadata Manager' plugin
*/
function fv_tc_x_add_metadata_field( $field_slug, $field, $object_type, $object_id, $value ) {        echo '<!--fvtc-column-->';
  global $fv_tc;
  return $fv_tc->column_content( $field, $field_slug, $object_id );
}



/* Add extra backend moderation options */
add_filter( 'comment_row_actions', array( $fv_tc, 'admin' ) );

if( function_exists( 'x_add_metadata_field' ) ) {
  /*
  Special for 'Custom Metadata Manager' plugin
  */
  add_filter( 'admin_init', array( $fv_tc, 'admin_init' ) );
} else {
  /* Add new column into Users management */
  add_filter( 'manage_users_columns', array( $fv_tc, 'column' ) );
  /* Put the content into the new column in Users management; there are 3 arguments passed to the filter */
  add_filter( 'manage_users_custom_column', array( $fv_tc, 'column_content' ), 10, 3 );
}

/* Add frontend moderation options */
add_filter( 'comment_text', array( $fv_tc, 'frontend' ) );
/* Shorten plain links */
add_filter( 'comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Thesis theme fix */
add_action( 'thesis_hook_after_comment', array( $fv_tc, 'thesis_frontend_show' ), 1 );
/* Thesis theme fix */
add_filter( 'thesis_comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Approve comment if user is set out of moderation queue */
add_filter( 'pre_comment_approved', array( $fv_tc, 'moderate' ) );

/* Load js */
add_action( 'wp_print_scripts', array( $fv_tc, 'scripts' ) );

/* Show number of unapproved comments in frontend */
add_filter( 'comments_number', array( $fv_tc, 'show_unapproved_count' ) );
//add_filter( 'get_comments_number', array( $fp_ecm, 'show_unapproved_count' ) );

/* Styles */
add_action('wp_print_styles', array( $fv_tc, 'styles' ) );

/* Show unapproved comments bellow posts */
add_filter( 'comments_array', array( $fv_tc, 'unapproved' ) ); 

/* Bring back children of deleted comments */
add_action( 'transition_comment_status', array( $fv_tc, 'transition_comment_status' ), 1000, 3 );

/*  Experimental stuff  */

/* Override Wordpress Blacklisting */
//add_action( 'wp_blacklist_check', array( $fv_tc, 'blacklist' ), 10, 7 );

endif;

add_filter( 'comment_moderation_headers', array( $fv_tc, 'comment_moderation_headers' ) ); 

add_filter( 'comment_moderation_text', array( $fv_tc, 'comment_moderation_text' ) );


/* Fix for Subscribe to Comments Reloaded */
add_action('deleted_comment', array( $fv_tc, 'stc_comment_deleted'), 0, 1);
add_action('wp_set_comment_status', array( $fv_tc, 'stc_comment_status_changed'), 0, 1);


add_action( 'admin_menu', array($fv_tc, 'admin_menu') ); 

add_filter('comment_reply_link', array($fv_tc, 'comment_reply_links'));

?>
