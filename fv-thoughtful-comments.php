<?php
/*
Plugin Name: FV Thoughtful Comments
Plugin URI: http://foliovision.com/
Description: Manage incomming comments more effectively by using frontend comment moderation system provided by this plugin. 
Version: 0.3.5
Author: Foliovision
Author URI: http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/

The users cappable of moderate_comments are getting all of these features and are not blocked 
*/

/*  Copyright 2009 - 2015  Foliovision  (email : programming@foliovision.com)

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
 * version 0.3.5
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
     * Plugin version
     * @var string
     */
    var $strVersion = '4';
    
    /**
     * Decide if scripts will be loaded on current page
     * True if array( $fv_tc, 'frontend' ) filter was aplied on current page
     * @bool
     */
    var $loadScripts = false; 
    
    /**
     * Comment author name obtained from cookie - if it has unapproved comments being shown
     * @string
     */
    var $cache_comment_author;
    
    /**
     * Current comments count
     * @int
     */    
    var $cache_comment_count;
    
    /**
     * Comment cache data
     * @array
     */     
    var $cache_data;
    
    /**
     * Comment cache filename
     * @string
     */     
    var $cache_filename;
     
    
    /**
     * Class contructor. Sets all basic variables.
     */         
    function __construct(){
        $this->url = trailingslashit( site_url() ).PLUGINDIR.'/'. dirname( plugin_basename(__FILE__) );
        $this->readme_URL = 'http://plugins.trac.wordpress.org/browser/thoughtful-comments/trunk/readme.txt?format=txt';    
        add_action( 'in_plugin_update_message-thoughtful-comments/fv-thoughtful-comments.php', array( &$this, 'plugin_update_message' ) );    
        add_action( 'admin_init', array( $this, 'option_defaults' ) );
    }


    function option_defaults() {
      $options = get_option('thoughtful_comments');
      if( !$options ){
        update_option( 'thoughtful_comments', array( 'shorten_urls' => true, 'reply_link' => true, 'comment_autoapprove_count' => 1 ) );
      }
      else{
        //make autoapprove count 1 by default
        if( !isset($options['comment_autoapprove_count']) || (intval($options['comment_autoapprove_count']) < 1) ){
          $options['comment_autoapprove_count'] = 1;
          update_option( 'thoughtful_comments', $options );
        }
      }
    }
	
	
	function admin_css(){
		
		if( !isset($_GET['page']) || $_GET['page'] != 'manage_fv_thoughtful_comments' ) {
			return; 
		}
		?>
		<link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/admin.css',__FILE__); ?>" />
		<?php 
	}
	

    function ap_action_init()
    {
        // Localization
        load_plugin_textdomain('fv_tc', false, dirname(plugin_basename(__FILE__)) . "/languages");
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
        add_management_page( 'FV Thoughtful Comments', 'FV Thoughtful Comments', 'moderate_comments', 'fv_thoughtful_comments', array($this, 'tools_panel') );
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
        
        if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) {
          $this->loadScripts = true;
          
          /*  If the IP isn't on the blacklist yet, display delete and ban ip link  */
          $banned = $this->is_commenter_banned($comment);
          $child = $this->comment_has_child($comment->comment_ID, $comment->comment_post_ID);
          if($banned===FALSE)
              $actions['delete_ban'] = $this->get_t_delete_ban($comment);
          else
              $actions['delete_ban'] = '<a href="#">' . __('Already banned!', 'fv_tc') . '</a> '.$banned;
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
    
    
    
    
    function cache_purge() {
      if( !isset($_POST['action']) || !isset($_POST['option_page'])  || $_POST['action'] != 'update' || $_POST['option_page'] != 'discussion' ) {
        return;
      }
      
      global $blog_id;
      if( !file_exists(WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/') ) {
        return;
      }
      
      $files = @glob(WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/*'); //
      foreach($files as $file){ // iterate files
        if(is_file($file))
          unlink($file); // delete file
      }

    }
    
    
    
    
    function cache_start( $args ) {
      
      $options = get_option('thoughtful_comments');
      if( empty($options['comment_cache']) ) {
        return $args;
      }
      
      // cache is only supported if your PHP is 5.6 or later
      if(version_compare(phpversion(),'5.6.0','<')){
        return $args;
      }
      
      require_once( dirname(__FILE__).'/walkers.php' );
      
      global $wp_query, $post, $blog_id, $wptouch_pro;
      
      $this->cache_comment_count = get_comments_number();
      $this->cache_comment_author = false;
      
      foreach ($_COOKIE as $n => $v) {
        if (substr($n, 0, 20) == 'comment_author_email') {
          $this->cache_comment_author = $v;    
        }
      }
      
      $bCommenterUnapproved = false;
      if( $this->cache_comment_author && $wp_query->comments ) {
        foreach( $wp_query->comments as $objComment ) {
          if( $objComment->comment_author_email == $this->cache_comment_author && $objComment->comment_approved == 0 ) {
            $bCommenterUnapproved = true;
          }
        }
      }
      
      
      if( !$bCommenterUnapproved ) {
        $this->cache_comment_author = false;        
      } else {
        echo "<!--fv comments cache - unapproved comments for $this->cache_comment_author - not serving cached data -->\n";
      }
            
      $sType = ( ( empty($_COOKIE['wptouch-pro-view']) || strcmp($_COOKIE['wptouch-pro-view'],'desktop') != 0 ) && !empty($wptouch_pro->is_mobile_device) && $wptouch_pro->is_mobile_device ) ? '-wptouch' : '-desktop';
      if( function_exists('is_amp_endpoint') && is_amp_endpoint() ) {
        $sType = '-amp';
      }
      
      $cpage = !empty($wp_query->query_vars['cpage']) ? intval($wp_query->query_vars['cpage']) : 0;
      
      $this->cache_data = false;
      $this->cache_filename = $post->ID.'-'.$post->post_name.$sType.'-cpage'.$cpage.'.tmp';
      if( !file_exists(WP_CONTENT_DIR.'/cache/') ) {
        mkdir(WP_CONTENT_DIR.'/cache/');
      }
      if( !file_exists(WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/') ) {
        mkdir(WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/');
      }
      $this->cache_filename = 'cache/thoughtful-comments-'.$blog_id.'/'.$this->cache_filename;  //  check if exists!

      if( file_exists( WP_CONTENT_DIR.'/'.$this->cache_filename ) ) {
        $this->cache_data = unserialize( file_get_contents( WP_CONTENT_DIR.'/'.$this->cache_filename ) );
      }
      
      if ( !is_array($this->cache_data) ) {
        $this->cache_data = array();
      }
      
      $aCache = $this->cache_data;
            
      if( !is_user_logged_in() && !$this->cache_comment_author && isset($aCache['html']) && ($aCache['date'] + 7200) > date( 'U' ) && isset($aCache['comments']) && $aCache['comments'] == $this->cache_comment_count && !isset( $_COOKIE['fv-debug'] ) ) {
        echo "<!--fv comments cache from $this->cache_filename @ ".$aCache['date']."-->\n";
        echo $aCache['html'];        

        //  skip the rest of the output!
        $args['walker'] = new FV_TC_Walker_Comment_blank;
      } else {
        $args['walker'] = new FV_TC_Walker_Comment_capture;        
      }
      
      return $args;
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
            return $this->get_t_moderated($args[2],false);
        }
        return $content;
    }
    
    
    /**
     * Remove the esc_html filter for admins so that the comment highlight is visible
     * 
     * @param string $contnet Comment author name
     * 
     * @return string Comment author name
     */     
    function comment_author_no_esc_html( $content ) {
      if( current_user_can('manage_options') ) {
        remove_filter( 'comment_author', 'esc_html' );
      }
      return $content;
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
    function comment_reply_links ($strLink = null) {
      $options = get_option('thoughtful_comments');
      $strReplyKeyWord = 'comment-';
      if( isset( $options['tc_replyKW'] ) && !empty( $options[ 'tc_replyKW' ] ) ) {
         $strReplyKeyWord = $options['tc_replyKW'];
      }

      $strLink = preg_replace(
         '~href="([^"]*)"~' ,
         'href="$1' . urlencode( '#' . $strReplyKeyWord . get_comment_ID() ) . '"',
         $strLink
      );

      if ($options['reply_link']) {        
         $noscript = '<noscript>' . __('Reply link does not work in your browser because JavaScript is disabled.', 'fv_tc') . '<br /></noscript>';
         $link_script = preg_replace( '~href.*(onclick|data-commentid)~' , 'href="#" $1' , $strLink );
         return $noscript .  $link_script;
      }
      return $strLink;
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
        if( is_admin() ) {
          return $content;
        }
        
        global  $user_ID, $comment, $post;
        
        if( !isset($this->can_edit) ) { // for performance reasons only check once!
          if( current_user_can('edit_posts') && current_user_can( 'edit_comment', $comment->comment_ID ) ) {
            $this->can_edit = true;
          } else {
            $this->can_edit = false;
          }
        }
                
        if( $this->can_edit ) {
        
          $this->loadScripts = true;
          //$child = $this->comment_has_child($comment->comment_ID, $comment->comment_post_ID);
          /*  Container   */
          $out = '<p class="tc-frontend">';
          /* Approve comment */
          if($comment->comment_approved == '0') {
            $out .= '<span id="comment-'.$comment->comment_ID.'-approve">'.$this->get_t_approve($comment).' </span>';
          }
          /*  Delete comment  */
          $out .= $this->get_t_delete($comment).' ';
          /*  Delete thread   */
          //if($child>0) {
            $out .= $this->get_t_delete_thread($comment).' ';
          //}
          $whats_banned = $this->is_commenter_banned($comment);
          if( !$whats_banned ) {
		// Only show ban link if allowed.
		if ( true === (bool) apply_filters( 'fv_show_trash_and_ban_link', true ) ) {
			/*  Delete and ban  */
			$out .= $this->get_t_delete_ban($comment);//.' | ';

			/*  Delete thread and ban   */
			//if($child>0)
			$out .= ' | '.$this->get_t_delete_thread_ban($comment);
		}
          } else {
              $out .= "<a class='fv-tc-ban' href='" . admin_url( 'tools.php?page=fv_thoughtful_comments' ) . "'>" . __('Already banned', 'fv_tc' ) . "</a>: ";
              $out .= $whats_banned; 
          }
          /*  Moderation status   */
          $user_info = ( isset($comment->user_id) && $comment->user_id > 0 ) ? get_userdata($comment->user_id) : false;
          if( $user_info && $user_info->user_level < 3) {
              $out .= '<br />'.$this->get_t_moderated($comment->user_id);
          } else if( $user_info && $user_info->user_level >= 3 ) {
              $out .= '<br /><abbr title="' . __('Comments from this user level are automatically approved', 'fv_tc') . '">' . __('Power user', 'fv_tc') . '</a>';
          }
          $out .= '</p>';
          $out .= '<span id="fv-tc-comment-'.$comment->comment_ID.'"></span>';   

          return $content . $out;  
        }
        return $content;
    }

    function get_js_translations() {
        $aStrings = Array(
            'comment_delete' => __('Do you really want to trash this comment?', 'fv_tc'),
            'delete_error' => __('Error deleting comment', 'fv_tc'),
            'comment_delete_ban_ip' => __('Do you really want to trash this comment and ban the IP and email address?', 'fv_tc'),
            'comment_delete_replies' => __('Do you really want to trash this comment and all the replies?', 'fv_tc'),
            'comment_delete_replies_ban_ip' => __('Do you really want to trash this comment with all the replies and ban the IP?', 'fv_tc'),
            'moderate_future' => __('Moderate future comments by this user','fv_tc'),
            'unmoderate' => __('Unmoderated','fv_tc'),
            'without_moderation' => __('Allow user to comment without moderation','fv_tc'),
            'moderate' => __('Moderated','fv_tc'),
            'mod_error' => __('Error','fv_tc'),
            'wait' => __('Wait...', 'fv_tc'),
        );
        return $aStrings;
    }

    
    /**
     * Generate the anchor for approve function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */   
    function get_t_approve($comment) {
        return '<a href="#" onclick="fv_tc_approve('.$comment->comment_ID.'); return false">' . __('Approve', 'fv_tc') . '</a>';
        //return '<a href="#" onclick="fv_tc_approve('.$comment->comment_ID.',\''.$this->esc_url( wp_nonce_url($this->url.'/ajax.php','fv-tc-approve_' . $comment->comment_ID)).'\', \''. __('Wait...', 'fv_tc').'\'); return false">' . __('Approve', 'fv_tc') . '</a>';
    }
    
    
    /**
     * Generate the anchor for delete function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete($comment) {
        return '<a href="#" class="fv-tc-del" onclick="fv_tc_delete('.$comment->comment_ID.'); return false">' . __('Trash', 'fv_tc') . '</a>';
    }
    
    
    /**
     * Generate the anchor for delete and ban function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_ban($comment) {
        return '<a href="#" class="fv-tc-ban" onclick="fv_tc_delete_ban('.$comment->comment_ID.',\''.$comment->comment_author_IP.'\'); return false">' . __('Trash & Ban', 'fv_tc') . '</a>';
    }
    
    
    /**
     * Generate the anchor for delete thread function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_thread($comment) {
        return '<a href="#" class="fv-tc-delthread" onclick="fv_tc_delete_thread('.$comment->comment_ID.'); return false">' . __('Trash Thread', 'fv_tc') . '</a>';
    }

    
    /**
     * Generate the anchor for delete thread and ban function
     * 
     * @param object $comment Comment object
     * 
     * @return string HTML of the anchor
     */
    function get_t_delete_thread_ban($comment) {
        return '<a href="#" class="fc-tc-banthread" onclick="fv_tc_delete_thread_ban('.$comment->comment_ID.',\''.$comment->comment_author_IP.'\'); return false">' . __('Trash Thread & Ban','fv_tc') . '</a>';
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
            
        $out = '<a href="#" class="commenter-'.$user_ID.'-moderated" onclick="fv_tc_moderated('.$user_ID.', '. $frontend2 .'); return false">'; 
        if(!get_user_meta($user_ID,'fv_tc_moderated'))
            if($frontend)
                $out .= __('Allow user to comment without moderation','fv_tc') . '</a>';
            else
                $out .= __('Moderated', 'fv_tc') . '</a>';
        else
            if($frontend)
                $out .= __('Moderate future comments by this user', 'fv_tc') . '</a>';
            else
                $out .= __('Unmoderated', 'fv_tc') . '</a>';
        return  $out;
    }  
    
    
    function is_commenter_banned( $comment ) {
      $blacklist = trim( get_option( $this->get_option_blacklist() ) );
      $banned = array();
      if( stripos($blacklist,$comment->comment_author_email) !== false ) $banned[] = $comment->comment_author_email;
      if( stripos($blacklist,$comment->comment_author_IP) !== false ) $banned[] = $comment->comment_author_IP;
      return count($banned) ? implode( ', ', $banned ) : false;
    }


    function load_unapproved( $comment_args) {
        global $user_ID, $post;

        /*  Check user permissions */
        if($user_ID && current_user_can('edit_post', $post->ID)) { 
          $comment_args['status'] = array('approve','hold');
        }
        return $comment_args;
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
        
        if(get_user_meta($user_ID,'fv_tc_moderated')) {
            echo '<p>putting into approved</p>';
        }
        else {
            echo '<p>putting into unapproved</p>';
        }
            
        die('end');*/
        /////////////////////////
        
        if(get_user_meta($user_ID,'fv_tc_moderated'))    
            return  true;
        return  $approved;
    }
        
    
    function fv_tc_admin_description(){
      _e('Thoughtful Comments supercharges comment moderation by moving it into the front end (i.e. in context). It also allows banning by IP, email address or domain.', 'fv_tc');
    }
    
    function fv_tc_admin_comment_tweaks(){
      $options = get_option('thoughtful_comments');

      ?>
      <table class="optiontable form-table">
          <tr valign="top">
              <th scope="row"><?php _e('Automatic link shortening', 'fv_tc'); ?>: 
              <br/>
              <select type="select" id="shorten_urls" name="shorten_urls">
                <option value="0" <?php if($options['shorten_urls'] === true) echo "selected"; ?> >link to domain.com</option>
                <option value="50" <?php if($options['shorten_urls'] === 50) echo "selected"; ?> >Shorten to 50 characters</option>
                <option value="100" <?php if($options['shorten_urls'] === false) echo "selected"; ?> >Shorten to 100 characters</option>
              </select>
              </th>  
              <td style="margin-bottom: 0; width: 11px; padding-right: 2px;"><fieldset><legend class="screen-reader-text"><span><?php _e('Link shortening', 'fv_tc'); ?></span></legend>                                  
              
              <td><label for="shorten_urls"><span><?php _e('Shortens the plain URL link text in comments to "link to: domain.com" or strip URL after N characters and add &hellip; at the end. Hides long ugly URLs', 'fv_tc'); ?></span></label><br />
              </td>
          </tr>
          <tr valign="top">
              <th scope="row"><?php _e('Reply link', 'fv_tc'); ?> </th> 
              <td style="margin-bottom: 0; width: 11px; padding-right: 2px;"><fieldset><legend class="screen-reader-text"><span><?php _e('Reply link', 'fv_tc'); ?></span></legend>                              
              <input id="reply_link" type="checkbox" name="reply_link" value="1" <?php if( $options['reply_link'] ) echo 'checked="checked"'; ?> /></td>                                    
              <td><label for="reply_link"><span><?php _e('Disable HTML replies. <br /><small>(Lightens your server load. Reply function still works, but through JavaScript.)</small>', 'fv_tc'); ?></span></label><br />
              </td>
          </tr>
          <?php if( get_option('comment_whitelist') ): ?>
            <tr valign="top">
                <th scope="row"><?php _e('Comments before auto-approval', 'fv_tc'); ?> </th> 
                <td colspan="2"><fieldset><legend class="screen-reader-text"><span><?php _e('Comments before auto-approval', 'fv_tc'); ?></span></legend>                              
                <input id="comment_autoapprove_count" type="text" name="comment_autoapprove_count" value="<?php echo ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : 1; ?>" />                               
                <label for="reply_link"><span><?php _e('Number of approved comments before auto-approval', 'fv_tc'); ?></span></label><br />
                </td>
            </tr>
          <?php endif; ?>
          <tr valign="top">     
            <th scope="row"><?php _e('Allow nicename change', 'fv_tc'); ?> </th> 
            <td style="margin-bottom: 0; width: 11px; padding-right: 2px;"><fieldset><legend class="screen-reader-text"><span><?php _e('Allow nicename editing', 'fv_tc'); ?></span></legend>                              
              <input id="user_nicename_edit" type="checkbox" name="user_nicename_edit" value="1" 
              <?php if( isset($options['user_nicename_edit']) && $options['user_nicename_edit'] ) echo 'checked="checked"'; ?> /></td>                               
              <td><label for="user_nicename_edit"><span><?php _e('Allow site administrators to change user nicename (author URL) on the "Edit user" screen.', 'fv_tc'); ?></span></label><br />
            </td>
          </tr>
          <?php
          $bCommentReg = get_option( 'comment_registration' );
          if( isset( $bCommentReg ) && 1 == $bCommentReg ) { ?>
          <tr valign="top">
              <th scope="row"><?php _e('Reply link Keyword', 'fv_tc'); ?> </th> 
              <td style="margin-bottom: 0; width: 11px; padding-right: 2px;"><fieldset><legend class="screen-reader-text"><span><?php _e('Reply link', 'fv_tc'); ?></span></legend>                              
              <input id="tc_replyKW" type="text" name="tc_replyKW" size="10" value="<?php if( isset( $options['tc_replyKW'] ) ) echo $options['tc_replyKW']; else echo 'comment-'; ?>" /></td>
              <td><label for="tc_replyKW"><span><?php _e('<strong>Advanced!</strong> Only change this if your "Log in to Reply" link doesn\'t bring the commenter back to the comment they wanted to comment on after logging in.', 'fv_tc'); ?></span></label><br />
              </td>
          </tr>
          <?php } ?>
          <tr valign="top">     
            <th scope="row"><?php _e('Comment cache (advanced)', 'fv_tc'); ?> </th> 
            <td style="margin-bottom: 0; width: 11px; padding-right: 2px;"><fieldset><legend class="screen-reader-text"><span><?php _e('Comment cache (advanced)', 'fv_tc'); ?></span></legend>                              
              <input id="comment_cache" type="checkbox" name="comment_cache" value="1" 
              <?php if( isset($options['comment_cache']) && $options['comment_cache'] ) echo 'checked="checked"'; ?> /></td>                               
              <td><label for="comment_cache"><span><?php _e('Caches the comments section of your posts into HTML files. If your posts have hundreds of comments and you don\'t want to use comment paging, this feature speeds up the PHP processing time considerably. Useful even if you use a WP cache plugin, as users see cached comments if they don\'t have an unapproved comment in the list.', 'fv_tc'); ?></span></label><br />
            </td>
          </tr>
          <?php if( isset($options['comment_cache']) && $options['comment_cache'] ) : ?>          
          <tr valign="top">     
            <th scope="row"></th>            
            <td style="margin-bottom: 0; width: 11px; padding-right: 2px;">
            </td>
            <td>
              <?php global $blog_id; ?>
              <p><?php _e('Current cache directory: ', 'fv_tc'); echo WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/'; ?></p>
              <p><?php _e('Cache files: ', 'fv_tc'); echo count( @glob(WP_CONTENT_DIR.'/cache/thoughtful-comments-'.$blog_id.'/*') ); ?></p>
              <p>Hint: save Settings -> Discussion to purge the cache</p>
            </td>
          </tr>
          <?php endif; ?>          
      </table>
      <p>
          <input type="submit" name="fv_feedburner_replacement_submit" class="button-primary" value="<?php _e('Save Changes', 'fv_tc') ?>" />
      </p>
      <?php
    }
    
    function fv_tc_admin_comment_instructions(){
      ?>
      <table class="optiontable form-table">
        <tr valign="top">
          <th scope="row"></th>
          <td><p><?php _e('After install with comments held up for moderation, you will notice several things on your site frontend:', 'fv_tc'); ?><br />
          <?php _e('- comments held up for moderation appear with highlighted commenters name,', 'fv_tc'); ?><br />
          <?php _e('- comments count in single posts or archives is highlighted if there are comments held up for moderation,', 'fv_tc'); ?><br />
          <?php _e('- all comments have additional buttons for moderation.', 'fv_tc'); ?></p></td>
        </tr>
        <tr valign="top">
          <th scope="row">Comment Moderation</th>
          <td><img src="<?php echo $this->url; ?>/screenshot-1.png" alt="FV Thoughtful Comments frontend" style="max-width: 100%; height: auto;"></td>
        </tr>
        <tr valign="top">
          <th scope="row">User Moderation</th>
          <td>
          <img src="<?php echo $this->url; ?>/screenshot-3.png" alt="FV Thoughtful Comments frontend" style="max-width: 100%; height: auto;"></td>
        </tr>							
      </table>
      <?php
    }
    
    function fv_tc_admin_blacklist() {
      ?>
      <table class="optiontable form-table">
          <tr>
            <th scope="row"><?php _e('Comment Blacklist'); ?></th>
            <td style="margin-bottom: 0; width: 11px; padding-right: 2px;" colspan="2">
              <fieldset><legend class="screen-reader-text"><span><?php _e('Comment Blacklist'); ?></span></legend>
                <p><label for="blacklist_keys"><?php _e('When a comment contains any of these words in its content, name, URL, email, or IP, it will be put in the trash. One word or IP per line. It will match inside words, so &#8220;press&#8221; will match &#8220;WordPress&#8221;.'); ?></label></p>
                <p>
                  <textarea name="blacklist_keys" rows="30" cols="50" id="blacklist_keys" class="large-text code"><?php echo esc_textarea( get_option( $this->get_option_blacklist() ) ); ?></textarea>
                </p>
              </fieldset>
            </td>
          </tr>
      </table>
      <p>
          <input type="submit" name="fv_thoughtful_comments_submit" class="button-primary" value="<?php _e('Save Changes', 'fv_tc') ?>" />
      </p>
      <?php
    }    
    
    function fv_tc_admin_enqueue_scripts(){
      if( !isset($_GET['page']) || $_GET['page'] != 'manage_fv_thoughtful_comments' ) {
        return;
      }
      
      wp_enqueue_script('postbox');
    }
    
    
    function options_panel() {
      add_meta_box( 'fv_tc_description', 'Description', array( $this, 'fv_tc_admin_description' ), 'fv_tc_settings', 'normal' );
      add_meta_box( 'fv_tc_comment_tweaks', 'Comment Tweaks', array( $this,'fv_tc_admin_comment_tweaks' ), 'fv_tc_settings', 'normal' );
      add_meta_box( 'fv_tc_comment_instructions', 'Instructions', array( $this,'fv_tc_admin_comment_instructions' ), 'fv_tc_settings', 'normal' );
      
      if (!empty($_POST)) :
          check_admin_referer('thoughtful_comments');
          
          $shorten_urls = false;
          switch( $_POST['shorten_urls'] ){
            case '0':
              $shorten_urls = true;
              break;
            case '50':
              $shorten_urls = 50;
              break;
            case '100':
              $shorten_urls = false;
              break;
          }
          
          $options = array(
              'shorten_urls' => $shorten_urls,            
              'reply_link' => ( isset($_POST['reply_link']) && $_POST['reply_link'] ) ? true : false,
              'comment_autoapprove_count' => ( isset($_POST['comment_autoapprove_count']) && intval($_POST['comment_autoapprove_count']) > 0 ) ? intval($_POST['comment_autoapprove_count']) : 1,
              'tc_replyKW' => isset( $_POST['tc_replyKW'] ) ? $_POST['tc_replyKW'] : 'comment-',
              'user_nicename_edit' => ( isset($_POST['user_nicename_edit']) && $_POST['user_nicename_edit'] ) ? true : false,
              'comment_cache' => ( isset($_POST['comment_cache']) && $_POST['comment_cache'] ) ? true : false,
          );
          if( update_option( 'thoughtful_comments', $options ) ) :
          ?>
          <div id="message" class="updated fade">
              <p>
                  <strong>
                      <?php _e('Settings saved', 'fv_tc'); ?>
                  </strong>
              </p>
          </div>
          <?php
          endif;  //  update_option
      endif;  //  $_POST
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
                  <?php
                    do_meta_boxes('fv_tc_settings', 'normal', false );
                    wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
                    wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
                  ?>
                </div>
            </form>
        </div>
        
        <style>
          #refresh-result{
            margin-top: 20px;
          }
          #refresh-resultt td{
            padding: 5px;
            border: solid 1px #ccc;
          }
        </style>
        
        <script type="text/javascript">
          //<![CDATA[
          jQuery(document).ready( function($) {
            // close postboxes that should be closed
            $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            // postboxes setup
            postboxes.add_postbox_toggles('fv_tc_settings');
          });

        //]]>
        </script>
        
        <?php
    }
    
    
    function tools_panel() {
      add_meta_box( 'fv_tc_description', 'Blacklist', array( $this, 'fv_tc_admin_blacklist' ), 'fv_tc_tools', 'normal' );

      if (!empty($_POST)) :
          check_admin_referer('thoughtful_comments');

          if( update_option( $this->get_option_blacklist(), trim( $_POST['blacklist_keys'] ) ) ) :
          ?>
          <div id="message" class="updated fade">
              <p>
                  <strong>
                      <?php _e('Blacklist saved', 'fv_tc'); ?>
                  </strong>
              </p>
          </div>
          <?php
          endif;  //  update_option
      endif;  //  $_POST
      ?>

      <div class="wrap">
          <div style="position: absolute; right: 20px; margin-top: 5px">
              <a href="http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments" target="_blank" title="Documentation"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
          </div>
          <div>
              <div id="icon-options-general" class="icon32"><br /></div>
              <h2>FV Thoughtful Comments</h2>
          </div>

          <?php if( current_user_can('manage_options') ): ?>
          <div class="notice notice-info">
            <p><?php _e( 'Note: This screen is a copy of the Settings -> Discussion -> Comment Blacklist box to allow Editors to unban commenters.', 'fv_tc' ); ?></p>
          </div>
          <?php endif; ?>

          <form method="post" action="">
              <?php wp_nonce_field('thoughtful_comments') ?>
              <div id="poststuff" class="ui-sortable">
                <?php
                  do_meta_boxes('fv_tc_tools', 'normal', false );
                  wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
                  wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
                ?>
              </div>
          </form>
      </div>

      <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) {
          // close postboxes that should be closed
          $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
          // postboxes setup
          postboxes.add_postbox_toggles('fv_tc_settings');
        });

      //]]>
      </script>

      <?php
    }    
    
        
    /**
    * Action for wp_print_scripts - enqueues plugin js which is dependend on jquery. Improved in 0.2.3  ////
    * 
    * @global int Current user ID        
    */
    function scripts() {
        if( $this->loadScripts ) {
            wp_enqueue_script('fv_tc',$this->url. '/js/fv_tc.js',array('jquery'), $this->strVersion, true);
            wp_localize_script('fv_tc', 'fv_tc_translations', $this->get_js_translations());
            wp_localize_script('fv_tc', 'fv_tc_ajaxurl', admin_url('admin-ajax.php'));
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
                
                $content = preg_replace( '~(\d+|\S+ replies)~', '<span class="tc_highlight"><abbr title="' . sprintf( _n( 'This post has one unapproved comment.', 'This post has %d unapproved comments.', $count, 'fv_tc' ), $count ) . '">$1</abbr></span>', $content );
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
        //  this is executed in the header, so we can't do the check for every post on index/archive pages, so we better load styles if there are any unapproved comments to show. it's loaded even for contributors which don't need it.
        if(!is_admin() && current_user_can('edit_posts')) {
          echo '<link rel="stylesheet" href="'.$this->url.'/css/frontend.css?ver='.$this->strVersion.'" type="text/css" media="screen" />'; 
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
     * Hilights unapproved comments bellow posts.
     * 
     * @param string[]    $classes    An array of comment classes.
     * @param string      $class      A comma-separated list of additional classes added to the list.
     * @param int         $comment_id The comment ID.
     * @param WP_Comment  $comment    The comment object.           
     * 
     * @return array Array of both comments with unapproved ones hilighted
     */               
    function hilight_unapproved( $classes, $class, $comment_ID, $comment ) {
        if($comment->comment_approved == '0') {
          $classes[] = 'unapproved';
        }

        return $classes;
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
        if( isset( $options['enhance_notify'] ) && $options['enhance_notify'] == false ) return $message_headers;      
        $message_headers .= "\r\n"."Content-Type: text/html"; //  this should add up
        return $message_headers;
    }
    
    function comment_moderation_text( $notify_message ) {
        $options = get_option('thoughtful_comments');
        if( isset( $options['enhance_notify'] ) && $options['enhance_notify'] == false  ) return $notify_message;        
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
          $sApproveTranslated = substr(__('Approve it: %s'), 0, strlen(__('Approve it: %s')) - 3);
            $replyto = __('Reply to comment via email', 'fv_tc') . ': <a href="mailto:'.rawurlencode('"'.$comment->comment_author.'" ').'<'.$comment->comment_author_email.'>'.'?subject='.rawurlencode( __('Your comment on', 'fv_tc') . ' "'.$post->post_title.'"' ).'&body='.rawurlencode( $content ).'">' . __('Email reply', 'fv_tc') . '</a>'."\r\n";
            $linkto = __('Link to comment', 'fv_tc') . ': <a href="'.get_permalink($comment->comment_post_ID) . '#comment-'.$comment_id.'">' . __('Comment link', 'fv_tc') . '</a>'."\r\n";
            $notify_message = str_replace(  $sApproveTranslated, $replyto.$sApproveTranslated, $notify_message );
            $notify_message = str_replace( $sApproveTranslated, $linkto.$sApproveTranslated, $notify_message );
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
      $link = $link[1].'<a href="'.esc_url($link[2]).'">' . __('link to', 'fv_tc') . ' '.$domain[1].'</a><br />'.$link[3];
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
      if( !isset($href[1]) ) {
        return $link[0];
      }
      
      if( $href[1] == $text[1] ) {
        preg_match( '!//(.+?)/!', $text[1], $domain );
        if( $domain[1] ) {
          
          $options = get_option('thoughtful_comments');
          if( $options['shorten_urls'] === true ){
              $domain[1] = preg_replace( '~^www\.~', '', $domain[1] );
              $link[0] = str_replace( $text[1].'</a>', __('link to', 'fv_tc') . ' '.$domain[1].'</a>', $link[0] );
          }
          else{
          
            if( $options['shorten_urls'] === 50 ){
              $length = 50;
            }
            else{
              $length = 100;
            }
            
            preg_match( '!//(.+?)$!', $text[1], $striped_link );
            $striped_link[1] = preg_replace( '~^www\.~', '', $striped_link[1] );
            $sub_str_link = substr( $striped_link[1], 0, $length );
            if( $sub_str_link != $striped_link[1] ){
              $sub_str_link .= "&hellip;";
            }
            
            $link[0] = str_replace( $text[1].'</a>', $sub_str_link.'</a>', $link[0] );
          }
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
    
    
    function users_cache( $comments ) {
      // Do not preload anything if the cache is on as it causes more harm than good
      $options = get_option('thoughtful_comments');
      if( !empty($options['comment_cache']) ) {
        return $comments;
      }
      
      global $wpdb;
      
      if( $comments !== NULL && count( $comments ) > 0 ) {
    
        $all_IDs = array();
        foreach( $comments AS $comment ) {
          $all_IDs[] = $comment->user_id;
        }      
    
        $all_IDs = array_unique( $all_IDs );
        $all_IDs_string = implode (',', $all_IDs );
    
        $all_IDs_users = $wpdb->get_results( "SELECT * FROM `{$wpdb->users}` WHERE ID IN ({$all_IDs_string}) " );
        $all_IDs_meta = $wpdb->get_results( "SELECT * FROM `{$wpdb->usermeta}` WHERE user_id IN ({$all_IDs_string}) ORDER BY user_id " );
        //echo '<!--meta'.var_export( $all_IDs_meta, true ).'-->';
        
        $meta_cache = array();
        foreach( $all_IDs_meta AS $all_IDs_meta_item ) {
          $meta_cache[$all_IDs_meta_item->user_id][] = $all_IDs_meta_item;
        }
    
        foreach( $all_IDs_users AS $all_IDs_users_item ) {
          foreach( $meta_cache[$all_IDs_users_item->ID] AS $meta ) {
            $value = maybe_unserialize($meta->meta_value);
            // Keys used as object vars cannot have dashes.
            $key = str_replace('-', '', $meta->meta_key);
            $all_IDs_users_item->{$key} = $value;          
          }
          
          wp_cache_set( $all_IDs_users_item->ID, $all_IDs_users_item, 'users' );
          wp_cache_add( $all_IDs_users_item->user_login, $all_IDs_users_item->ID, 'userlogins');
          wp_cache_add( $all_IDs_users_item->user_email, $all_IDs_users_item->ID, 'useremail');
          wp_cache_add( $all_IDs_users_item->user_nicename, $all_IDs_users_item->ID, 'userslugs');        
        }
        
        $column = esc_sql( 'user_id');
        $cache_key = 'user_meta';    
        if ( !empty($all_IDs_meta) ) {
          foreach ( $all_IDs_meta as $metarow) {
            $mpid = intval($metarow->{$column});
            $mkey = $metarow->meta_key;
            $mval = $metarow->meta_value;
      
            // Force subkeys to be array type:
            if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
              $cache[$mpid] = array();
            if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
              $cache[$mpid][$mkey] = array();
      
            // Add a value to the current pid/key:
            $cache[$mpid][$mkey][] = $mval;
          }
        }
      
        foreach ( $all_IDs as $id ) {
          if ( ! isset($cache[$id]) )
            $cache[$id] = array();
          wp_cache_add( $id, $cache[$id], $cache_key );
        }    
        
      }
      return $comments;
    }
  
        
    function mysql2date_lite($dateformatstring, $mysqlstring, $use_b2configmonthsdays = 1) {
      global $month, $weekday;
      $m = $mysqlstring;
      if (empty($m)) {
        return false;
      }
      $i = mktime(substr($m,11,2),substr($m,14,2),substr($m,17,2),substr($m,5,2),substr($m,8,2),substr($m,0,4)); 
      if (!empty($month) && !empty($weekday) && $use_b2configmonthsdays) {
        $datemonth = $month[date('m', $i)];
        $dateweekday = $weekday[date('w', $i)];
        $dateformatstring = ' '.$dateformatstring;
        $dateformatstring = preg_replace("/([^\\\])D/", "\\1".backslashit(substr($dateweekday, 0, 3)), $dateformatstring);
        $dateformatstring = preg_replace("/([^\\\])F/", "\\1".backslashit($datemonth), $dateformatstring);
        $dateformatstring = preg_replace("/([^\\\])l/", "\\1".backslashit($dateweekday), $dateformatstring);
        $dateformatstring = preg_replace("/([^\\\])M/", "\\1".backslashit(substr($datemonth, 0, 3)), $dateformatstring);
        $dateformatstring = substr($dateformatstring, 1, strlen($dateformatstring)-1);
      }
      $j = @date($dateformatstring, $i);
      if (!$j) {
      // for debug purposes
      //  echo $i." ".$mysqlstring;
      }
      return $j;
    }    
        
    
    function wpautop_lite( $comment_text ) {
      if( stripos($comment_text,'<p') === false ) {
        //$aParagraphs = explode( "\n", $comment_text );
              
        $pee = $comment_text;
        $br = 1;
        
        /*
        Taken from WP 1.0.1-miles
        */
        $pee = $pee . "\n"; // just to make things a little easier, pad the end
        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        $pee = preg_replace('!(<(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)!', "\n$1", $pee); // Space things out a little
        $pee = preg_replace('!(</(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])>)!', "$1\n", $pee); // Space things out a little
        $pee = preg_replace("/(\r\n|\r)/", "\n", $pee); // cross-platform newlines 
        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
        $pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "\t<p>$1</p>\n", $pee); // make paragraphs, including one at the end 
        $pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace 
        $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
        $pee = preg_replace('!<p>\s*(</?(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)!', "$1", $pee);
        $pee = preg_replace('!(</?(?:table|tr|td|th|div|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*</p>!', "$1", $pee); 
        if ($br) $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
        $pee = preg_replace('!(</?(?:table|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|p|h[1-6])[^>]*>)\s*<br />!', "$1", $pee);
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|th|pre|td|ul|ol)>)!', '$1', $pee);
        $pee = preg_replace('/&([^#])(?![a-z]{1,8};)/', '&#038;$1', $pee);
        
        
              
        $comment_text = $pee;
      }
      return $comment_text;
    }

    function fv_tc_approve() {
        if(!wp_set_comment_status( $_REQUEST['id'], 'approve' ))
            die('db error');
    }
    

    function fv_tc_delete() {
        global $wpdb;
        $blacklist_keys = trim( get_option( $this->get_option_blacklist() ) );

        if(isset($_REQUEST['ip']) && stripos( $blacklist_keys, $_REQUEST['ip'] )===FALSE) {
          
          $objComment = get_comment( $_REQUEST['id'] );
          $commentStatus = $objComment->comment_approved;
          $blacklist_keys_update = $blacklist_keys."\n".$_REQUEST['ip'];
          if( is_email($objComment->comment_author_email) ) {
            $blacklist_keys_update = $blacklist_keys_update."\n".$objComment->comment_author_email;
          }
          update_option( $this->get_option_blacklist(), $blacklist_keys_update);

          $wpdb->update( 'wp_comments', array( 'comment_approved' => 'spam' ), array( 'comment_ID' => intval($_REQUEST['id']) ) );
          do_action('transition_comment_status','spam','unapproved', $objComment );
          $wpdb->update( 'wp_comments', array( 'comment_approved' => $commentStatus ), array( 'comment_ID' => intval($_REQUEST['id']) ) );
        }

      //check_admin_referer('fv-tc-delete_' . $_GET['id']);
        if (isset($_REQUEST['thread'])) {
          if($_REQUEST['thread'] == 'yes') {
        $this->fv_tc_delete_recursive($_REQUEST['id']);
          } 
        }
        else {
      if(!wp_delete_comment($_REQUEST['id']))
          die('db error');
        }       

    }

    function fv_tc_moderated() {
        if(get_user_meta($_REQUEST['id'],'fv_tc_moderated')) {
           if(!delete_user_meta($_REQUEST['id'],'fv_tc_moderated'))
                die('meta error');
            echo 'user moderated';
        }
        else {
            if(!update_user_meta($_REQUEST['id'],'fv_tc_moderated','no'))
                die('meta error');
            echo 'user non-moderated';
        }
    }

    function fv_tc_delete_recursive($id) {
        global  $wpdb;  
        echo ' '.$id.' ';
        $comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE `comment_parent` ='{$id}'",ARRAY_A);
        if(strlen($wpdb->last_error)>0)
            die('db error');
        if(!wp_delete_comment($id))
            die('db error');             
        /*  If there are no more children */
        if(count($comments)==0)
            return;
        foreach($comments AS $comment) {
            $this->fv_tc_delete_recursive($comment['comment_ID']);
        }
    }

    function get_comment_link( $link ) {
        $link = preg_replace( '~/comment-page-1[$/]~', '', $link );  //  todo: make this an option, I guess!
        return $link;
    }
    
    function get_comments_pagenum_link( $link ) {
        if ( 'newest' == get_option('default_comments_page') ) {
          //  todo: how do we get the maximum page number?
        } else {
          $link = preg_replace( '~/comment-page-1[$/]~', '', $link );  //  todo: make this an option, I guess!
        }
        return $link;
    }

    function get_option_blacklist() {
        global $wp_version;
        // Use disallowed_keys on WP 5.5.0 and later
        return version_compare( $wp_version, '5.5.0' ) != -1 ? 'disallowed_keys' : 'blacklist_keys';
    }
    
    
    function fv_tc_auto_approve_comment( $approved, $commentdata ){
      
      //edit: whitelist has to be on to trigger this functionality
      if( !get_option('comment_whitelist') ){
        return $approved;
      }

      
      if( !empty( $commentdata['user_id'] ) ) {
        global $wpdb;
      
        $user = get_userdata( $commentdata['user_id'] );
        $post_author = $wpdb->get_var( $wpdb->prepare(
          "SELECT post_author FROM $wpdb->posts WHERE ID = %d LIMIT 1",
          $commentdata['comment_post_ID']
        ) );
      }

      if( isset( $user ) && ( $commentdata['user_id'] == $post_author || $user->has_cap( 'moderate_comments' ) ) ) {
        return 1;
      }

      //stop processing if comment is SPAM
      //stop processing if white_list is on and comment is already unapproved
      //stop processing if comments author email is empty
      if( $approved == 'spam' || $approved == 0 || empty($commentdata['comment_author_email']) ){
        return $approved;
      }

      $options = get_option('thoughtful_comments');
      $auto_approve_count = ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : false;
      
      //stop if auto-approve count is not set OR is less or equal 1 (comment whitelist already handle this)
      if( !$auto_approve_count || $auto_approve_count <= 1 ){
        return $approved;
      }

      global $wpdb;
      $dbCount = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}comments WHERE comment_author = '".$commentdata['comment_author']."' AND comment_author_email = '".$commentdata['comment_author_email']."' AND comment_approved = 1");
      
      if( $dbCount >= $auto_approve_count ) {
        return 1;
      } else {
        return 0;
      }
    }
    

    function fv_tc_auto_approve_comment_override_notification(){
      if( !is_admin() ){
        return;
      }
      
      //do not add warning if this setting is disabled
      if( !get_option('comment_whitelist') ){
        return;
      }
      
      $options = get_option('thoughtful_comments');
      //do not add warning if option is not set or is set to 1
      $auto_approve_count = ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : false;
      if( !$auto_approve_count || $auto_approve_count <= 1 ){
        return;
      }

      add_filter('thread_comments_depth_max', array($this,'fv_tc_override_notification_ob_start') );
      add_filter('avatar_defaults', array($this,'fv_tc_override_notification_ob_end') );
      
    }
    
    function fv_tc_override_notification_ob_start( $maxdeep ){
      ob_start();
      return $maxdeep;
    }
    
    function fv_tc_override_notification_ob_end( $avatar_defaults ){
      $discussion_settings = ob_get_clean();
      $fv_tc_link = admin_url('options-general.php?page=manage_fv_thoughtful_comments#comment_autoapprove_count');
      
      $discussion_settings = preg_replace( '~(<input[^>]*id="comment_whitelist"[^>]*>[^<]*)~', '$1 <br/><strong>WARNING:</strong> This setting is overridden by <a href="'.$fv_tc_link.'">FV Thoughtful Comments</a> plugin.', $discussion_settings );
      
      echo $discussion_settings;
      return $avatar_defaults;
    }
    

    function fv_tc_user_nicename_change(){
      if( !is_admin() || !current_user_can('manage_options') ){
        return;
      }

      $options = get_option('thoughtful_comments');
      //is user nicename editing on?
      $allow_nicename_edit = ( isset($options['user_nicename_edit']) && $options['user_nicename_edit'] ) ? true : false;
      if( !$allow_nicename_edit ){
        return;
      }

      add_filter('personal_options', array($this,'fv_tc_nicename_personal_options') );    //ob start
      add_filter('edit_user_profile', array($this,'fv_tc_nicename_edit_user_profile') );  //ob modified + echo
      add_filter('pre_user_nicename', array($this,'fv_tc_nicename_pre_user_nicename') );  //saving nicename

    }

    function fv_tc_nicename_personal_options( $profileuser ){
      ob_start();
      return $profileuser;
    }

    function fv_tc_nicename_edit_user_profile( $profileuser ){
      $user_edit_page = ob_get_clean();

      $user_nicename_field = '<tr class="user-user-login-wrap">
  <th><label for="user_nicename">Nicename</label></th>
      <td><input type="text" name="user_nicename" id="user_nicename" value="'.$profileuser->user_nicename.'" class="regular-text" /></td>
  </tr>';
      $user_edit_page = preg_replace('~(<tr[^>]*user-role-wrap[^>]*>)~', $user_nicename_field.'$1', $user_edit_page);

      echo $user_edit_page;
      return $profileuser;
    }
    
    function fv_tc_nicename_pre_user_nicename( $user_nicename ){
      if( isset($_POST['user_nicename']) && !empty($_POST['user_nicename']) ){
        $new_user_nicename = trim($_POST['user_nicename']);
        return $new_user_nicename;
      }
      else{
        return $user_nicename;
      }
    }


}
$fv_tc = new fv_tc;

add_action( 'wp_ajax_fv_tc_approve', array( $fv_tc,'fv_tc_approve'));
add_action( 'wp_ajax_fv_tc_delete', array( $fv_tc,'fv_tc_delete'));
add_action( 'wp_ajax_fv_tc_moderated', array( $fv_tc,'fv_tc_moderated'));


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
add_filter( 'comment_text', array( $fv_tc, 'frontend' ), PHP_INT_MAX );
/* Shorten plain links */
add_filter( 'comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Thesis theme fix */
add_action( 'thesis_hook_after_comment', array( $fv_tc, 'thesis_frontend_show' ), 1 );
/* Thesis theme fix */
add_filter( 'thesis_comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Approve comment if user is set out of moderation queue */
add_filter( 'pre_comment_approved', array( $fv_tc, 'moderate' ) );

/* Load js */
add_action( 'wp_footer', array( $fv_tc, 'scripts' ) );
add_action( 'admin_footer', array( $fv_tc, 'scripts' ) );

/* Show number of unapproved comments in frontend */
add_filter( 'comments_number', array( $fv_tc, 'show_unapproved_count' ) );
//add_filter( 'get_comments_number', array( $fp_ecm, 'show_unapproved_count' ) );

/* Styles */
add_action('wp_print_styles', array( $fv_tc, 'styles' ) );

/* Hilight unapproved comments bellow posts */
add_filter( 'comment_class', array( $fv_tc, 'hilight_unapproved' ), PHP_INT_MAX, 4 ); 

add_filter( 'comments_template_query_args', array( $fv_tc, 'load_unapproved' ) );

/* Cache users */
if( !function_exists('apc_fetch') && !function_exists('memcache_get') ) add_filter( 'comments_array', array( $fv_tc, 'users_cache' ) );

/* Bring back children of deleted comments */
add_action( 'transition_comment_status', array( $fv_tc, 'transition_comment_status' ), 1000, 3 );

/* Admin's won't get the esc_html filter */
add_filter( 'comment_author', array( $fv_tc, 'comment_author_no_esc_html' ), 0 );

/* Whitelist commenters: Auto-apporove comments from authors, which have N comments already approved. */
add_filter( 'pre_comment_approved', array( $fv_tc, 'fv_tc_auto_approve_comment' ), 10, 2 );


/* Notification about overriding whitelist settings */
add_action('admin_init', array( $fv_tc, 'fv_tc_auto_approve_comment_override_notification' ) );

/*user nicename change*/
add_action('admin_init', array( $fv_tc, 'fv_tc_user_nicename_change' ) );

/*  Experimental stuff  */

/* Override Wordpress Blacklisting */
//add_action( 'wp_blacklist_check', array( $fv_tc, 'blacklist' ), 10, 7 );


add_filter( 'comment_moderation_headers', array( $fv_tc, 'comment_moderation_headers' ) ); 

add_filter( 'comment_moderation_text', array( $fv_tc, 'comment_moderation_text' ) );


/* Fix for Subscribe to Comments Reloaded */
add_action('deleted_comment', array( $fv_tc, 'stc_comment_deleted'), 0, 1);
add_action('wp_set_comment_status', array( $fv_tc, 'stc_comment_status_changed'), 0, 1);

add_action( 'admin_head', array($fv_tc, 'admin_css' )) ;
add_action( 'admin_menu', array($fv_tc, 'admin_menu') );
add_action( 'admin_enqueue_scripts', array( $fv_tc, 'fv_tc_admin_enqueue_scripts' ) );

add_filter('comment_reply_link', array($fv_tc, 'comment_reply_links'));

add_action('init', array($fv_tc, 'ap_action_init'));

add_filter('get_comment_link', array($fv_tc, 'get_comment_link'));
add_filter('get_comments_pagenum_link', array($fv_tc, 'get_comments_pagenum_link'));  //  todo: test!
add_filter('paginate_links', array($fv_tc, 'get_comments_pagenum_link'));

//  comments html caching
add_filter( 'wp_list_comments_args', array($fv_tc, 'cache_start') );
add_filter( 'admin_init', array($fv_tc, 'cache_purge') );


endif;  //  class_exists('fv_tc_Plugin')
