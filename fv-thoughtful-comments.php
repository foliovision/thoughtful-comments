<?php
/*
Plugin Name: FV Thoughtful Comments
Plugin URI: http://foliovision.com/
Description: Manage incomming comments more effectively by using frontend comment moderation system provided by this plugin.
Version: 0.3.6
Author: Foliovision
Author URI: http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/

The users cappable of moderate_comments are getting all of these features and are not blocked
*/

/*  Copyright 2009 - 2017  Foliovision  (email : programming@foliovision.com)

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


/*
 * Limitations of sorting
 *
 *  * the controls have to be added - do_action('fv_tc_controls'); before comments list
 *  * the present styling doesn't work with all themes for sure, only some work ok
 *
 */


/**
 * @package foliovision-tc
 * @author Foliovision <programming@foliovision.com>
 * version 0.3.6
 */

include( 'fp-api.php' );
include( 'fv-comments-reporting.php' );
include( 'fv-comments-blacklist.php' );

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
    var $strVersion = '0.3.6';
  
    /**
     * Decide if scripts will be loaded on current page
     * True if array( $fv_tc, 'frontend' ) filter was aplied on current page
     * @bool
     */
    var $loadScripts = false;
    
    var $hack_comment_wrapper = false;
    
    var $can_edit = false;
    
    var $can_ban = false;
  
  
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
    
    
    function admin_show_setting( $name, $option_key, $title, $help = false, $type = 'checkbox', $class = false ) {
      $name = esc_attr($name);
      $class = 'fv_tc-setting-'.$name.' ' .$class;
      $disabled = stripos($type,'disabled') !== false ? ' disabled' : false;
      
      ?>
        <tr class="<?php echo $class; ?>">
          <th>
            <label for="<?php echo $name; ?>"><?php _e($title, 'fv_tc' ); ?></label>
          </th>
          <td>
            <p class="description">
              <?php if( stripos($type,'-checkbox') !== false ) : ?>
                <input <?php echo $disabled; ?> type="checkbox" id="<?php echo $name; ?>-checkbox" name="<?php echo $name; ?>-checkbox" value="1" <?php if( $this->get_setting($option_key.'-checkbox') ) echo 'checked="checked"'; ?> /> 
              <?php endif; ?>
              
              <?php
              if( stripos($type,'textarea') === 0 ) {
                $input = '<textarea '.$disabled.' id="'.$name.'" name="'.$name.'" class="large-text code" rows="8">'.esc_textarea( $this->get_setting($option_key) ).'</textarea><br />';
              } elseif( stripos($type,'text') === 0 ) {
                $input = '<input '.$disabled.' type="text" id="'.$name.'" name="'.$name.'" value="'.esc_attr( $this->get_setting($option_key) ).'" />';
              } elseif( stripos($type,'number') === 0 ) {
                $input = '<input '.$disabled.' type="number" id="'.$name.'" name="'.$name.'" value="'.esc_attr( $this->get_setting($option_key) ).'" min="1" max="100" />';
              } else {
                $input = '<input '.$disabled.' type="checkbox" id="'.$name.'" name="'.$name.'" value="1" '.( $this->get_setting($option_key) ? 'checked="checked"' : '' ).' />';
              }
              
              if( !$help || stripos($help,'%input%') === false ) echo $input;
              
              if( $help ) : ?>
                <label for="<?php echo $name; ?>"><?php echo str_replace( '%input%', $input, $help ); ?></p>
              <?php endif; ?>
          </td>
        </tr>
      <?php
    }  
    

    function ap_action_init() {
        // Localization
        load_plugin_textdomain('fv_tc', false, dirname(plugin_basename(__FILE__)) . "/languages");
        
        $options = get_option( 'thoughtful_comments' );

        if( is_user_logged_in() ) {
          $this->loadScripts = true;
        }
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
          $banned = stripos(trim(get_option('blacklist_keys')),$comment->comment_author_IP);
          $child = $this->comment_has_child($comment->comment_ID, $comment->comment_post_ID);
          if($banned===FALSE)
              $actions['delete_ban'] = $this->get_t_delete_ban($comment);
          else
              $actions['delete_ban'] = '<a href="#">' . __('Already banned!', 'fv_tc') . '</a>';
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
     *
     * Also put in anchor "Reply link Keyword"
     */
    function comment_reply_link( $sHTML = null ) {
      $options = get_option('thoughtful_comments');
      
      $strReplyKeyWord = 'comment-';
      if( isset( $options['tc_replyKW'] ) && !empty( $options[ 'tc_replyKW' ] ) ) {
         $strReplyKeyWord = $options['tc_replyKW'];
      }

      $sHTML = preg_replace(
         '~href="([^"]*)"~' ,
         'href="$1' . urlencode( '#' . $strReplyKeyWord . get_comment_ID() ) . '"',
         $sHTML
      );

      if( $options['reply_link'] ) {
        $sHTML = preg_replace( '~(<a[^>]*?class=[\'"]comment-reply[^>]*?)href[^>]*?onclick~' , '$1href="#respond" onclick' , $sHTML );
      }
      
      return $sHTML;
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
    * Prepare the DIV for the admin front-end buttons
    *
    * @param string $content Comment text.
    *
    * @global int Current user ID
    * @global object Current comment object
    *
    * @return string Comment text with added features.
    */
    function frontend ($comment_text) {
        $tag = $this->hack_comment_wrapper ? $this->hack_comment_wrapper : 'div';
        $out = '<'.$tag.' class="tc-frontend">'."\n";        
        return $comment_text . $out;
    }
    
    function frontend_start() {
        $this->max_depth = get_option('thread_comments') ? get_option('thread_comments_depth') : -1;
        
        add_filter( 'comment_reply_link', '__return_false', 999 ); //  disabling the standard reply buttons!        
        add_filter( 'wptouch_settings_domain', array( $this, 'wptouch_disable_reply' ) );  //  disabling the WPTouch reply buttons!
        add_filter( 'comment_text', array( $this, 'reply_button' ), 10001, 3 );  //  show the new reply button
        
        //  setup permissions, but don't slow down guests users
        if( is_singular() && is_user_logged_in() ) {
          global $post;
          if( current_user_can('moderate_comments') || $post->post_author == get_current_user_id() && current_user_can('edit_posts') ) {  //  only author of the post or editor can moderate comments
            $this->can_edit = true;
          }
          $this->can_ban = current_user_can('moderate_comments');
        
        }
        
        add_filter( 'get_comment_link', array( $this, 'hack_check_comment_properties' ), 10 ); //  figure out what element is comment_text wrapped in
            
        //  appends the moderation buttons into a new sibling element of where comment_text is
        add_filter( 'comment_text', array( $this, 'hack_html_close_comment_element' ), 20000 );
        add_filter( 'comment_text', array( $this, 'frontend' ), 20001 );        
        
        
    }
    
    function frontend_template() {        
        global  $user_ID, $comment, $post;
        
        // todo: class if comment unapproved, spam, if IP banned, if comment $user_info->user_level >= 3, if already not moderated
        $out = '<a href="#" class="fv-tc-approve">' . __('Approve', 'fv_tc') . '</a>';
        $out .= '<a href="#" class="fv-tc-unspam">' . __('Unspam', 'fv_tc') . '</a>';        
        $out .= '<a href="#" class="fv-tc-del">' . __('Trash', 'fv_tc') . '</a>';
        $out .= '<a href="#" class="fv-tc-delthread">' . __('Trash Thread', 'fv_tc') . '</a>';
        $out .= '<a href="#" class="fv-tc-ban">' . __('Trash & Ban IP', 'fv_tc') . '</a>';
        $out .= '<a href="#" class="fv-tc-banthread">' . __('Trash Thread & Ban IP','fv_tc') . '</a>';
        $out .= '<a href="' . admin_url( 'tools.php?page=fv_thoughtful_comments' ) . '" class="fv-tc-already-banned" target="_blank">' . __(' IP already banned!', 'fv_tc' ) . '</a>';
        if( get_option('comment_moderation') ) {
          $out .= '<a href="#" class="fv-tc-dont-moderate">' . __('Allow user to comment without moderation', 'fv_tc') . '</a>';
          $out .= '<a href="#" class="fv-tc-moderate">' . __('Moderate future comments by this user', 'fv_tc') . '</a>';
        }
        
        return $out;
    }    

    function get_js_translations() {
        $aStrings = Array(
            'approve_error' => __('Error approving comment', 'fv_tc'),
            'comment_delete' => __('Do you really want to trash this comment?', 'fv_tc'),
            'comment_delete_has_replies' => __("Do you really want to trash this comment?\n\nReplies will be moved to the parrent comment.", 'fv_tc'),
            'delete_error' => __('Error deleting comment', 'fv_tc'),
            'comment_delete_ban_ip' => __('Do you really want to trash this comment and ban the IP?', 'fv_tc'),
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
    
    
    function get_setting( $key ) {
      $options = get_option('thoughtful_comments');
      
      if( isset($options[$key]) ) {
        if( $options[$key] === true || $options[$key] === 'true' ) return true;
        return trim($options[$key]);
      }
      
      if( $key == 'daily_comments_limit' ) return 4;
      
      return false;
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

    function get_t_unspam($comment) {
        return '<a href="#" onclick="fv_tc_approve('.$comment->comment_ID.'); return false">' . __('Unspam', 'fv_tc') . '</a>';
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
     * Generate the anchor for delete and ban IP function
     *
     * @param object $comment Comment object
     *
     * @return string HTML of the anchor
     */
    function get_t_delete_ban($comment) {
        return '<a href="#" class="fv-tc-ban" onclick="fv_tc_delete_ban('.$comment->comment_ID.',\''.$comment->comment_author_IP.'\'); return false">' . __('Trash & Ban IP', 'fv_tc') . '</a>';
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
     * Generate the anchor for delete thread and ban IP function
     *
     * @param object $comment Comment object
     *
     * @return string HTML of the anchor
     */
    function get_t_delete_thread_ban($comment) {
        return '<a href="#" class="fv-tc-banthread" onclick="fv_tc_delete_thread_ban('.$comment->comment_ID.',\''.$comment->comment_author_IP.'\'); return false">' . __('Trash Thread & Ban IP','fv_tc') . '</a>';
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


    function get_wp_count_comments($post_id) {
      $aCommentInfo = wp_count_comments($post_id);
      if( current_user_can('moderate_comments') ) {
        return $aCommentInfo->approved + $aCommentInfo->moderated;
      }
      return $aCommentInfo->approved;
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
        if(get_user_meta($user_ID,'fv_tc_moderated'))
            return  true;
        return  $approved;
    }


    function fv_tc_admin_description(){
      _e('Thoughtful Comments supercharges comment moderation by moving it into the front end (i.e. in context). It also allows banning by IP, email address or domain.', 'fv_tc');
    }

    function fv_tc_admin_comment_moderation(){
      $options = get_option('thoughtful_comments');
      ?>
      <table class="optiontable form-table">
        <?php
        $this->admin_show_setting(
                    'comment_whitelist_link',
                    'comment_whitelist_link',
                    __('Before a comment appears', 'fv_tc'),
                    __('Comment author must have a previously approved comment if the comment contains a link', 'fv_tc') );
        
          $this->admin_show_setting(
                    'comment_autoapprove_count',
                    'comment_autoapprove_count',
                    __('Comments before auto-approval', 'fv_tc'),
                    __( sprintf( 'Number of approved comments before auto-approval<br /><small>Depends on the <a href=\'%s\' target=\'_blank\'>Comment author must have a previously approved comment</a> Discussion setting</small>',site_url('wp-admin/options-discussion.php#moderation_notify') ), 'fv_tc'),
                    get_option('comment_whitelist') ? 'number' : 'number-disabled',
                    get_option('comment_whitelist') ? '' : 'disabled');
        
        $this->admin_show_setting(
                    'daily_comments_limit',
                    'daily_comments_limit',
                    __('Daily Comment Limit', 'fv_tc'),
                    __('Posting more than %input% comments to a post in a day will enable moderation for further comments by that user email or logged in user ID on that post', 'fv_tc'),
                    'number-checkbox' );
        
        $this->admin_show_setting(
                    'comments_reporting',
                    'comments_reporting',
                    __('Enable Comment Reporting', 'fv_tc'),
                    __('Enable reporting of abusive comments.', 'fv_tc') );
        
        $this->admin_show_setting(
                    'frontend_spam',
                    'frontend_spam',
                    __('Show spam comments in front-end', 'fv_tc'),
                    __('Reveal spam comments in front-end comment list for moderators', 'fv_tc') );
                
        ?>     
      </table>
      <p>
          <input type="submit" name="fv_thoughtful_comments_submit" class="button-primary" value="<?php _e('Save Changes', 'fv_tc') ?>" />
      </p>
      <?php
    }

    function fv_tc_admin_comment_tweaks(){
      $options = get_option('thoughtful_comments');

      ?>
      <table class="optiontable form-table">
          <tr valign="top">
              <th scope="row"><?php _e('Automatic link shortening', 'fv_tc'); ?>:
              </th>
              <td><select type="select" id="shorten_urls" name="shorten_urls">
                <option value="0" <?php if($options['shorten_urls'] === true) echo "selected"; ?> >link to domain.com</option>
                <option value="50" <?php if($options['shorten_urls'] === 50) echo "selected"; ?> >Shorten to 50 characters</option>
                <option value="100" <?php if($options['shorten_urls'] === false) echo "selected"; ?> >Shorten to 100 characters</option>
              </select><br /><label for="shorten_urls"><span><?php _e('Shortens the plain URL link text in comments to "link to: domain.com" or strip URL after N characters and add &hellip; at the end. Hides long ugly URLs', 'fv_tc'); ?></span></label><br />
              </td>
          </tr>
          
          <?php
          $this->admin_show_setting(
                    'user_nicename_edit',
                    'user_nicename_edit',
                    __('Allow User Nicename Change', 'fv_tc'),
                    __('Allow site administrators to change user nicename (author URL) on the "Edit user" screen.', 'fv_tc') );
          
          $this->admin_show_setting(
                    'reply_link',
                    'reply_link',
                    __('Reply Link', 'fv_tc'),
                    __('Disable HTML replies. <br /><small>Lightens your server load. Reply function still works, but through JavaScript.</small>', 'fv_tc') );
          
          $bCommentReg = get_option( 'comment_registration' );
          $this->admin_show_setting(
                    'tc_replyKW',
                    'tc_replyKW',
                    __('Reply Link Keyword', 'fv_tc'),
                    __('<strong>Advanced!</strong> Only change this if your "Log in to Reply" link doesn\'t bring the commenter back to the comment they wanted to comment on after logging in.', 'fv_tc'),
                    'text' );
          ?>
      </table>
      <p>
          <input type="submit" name="fv_thoughtful_comments_submit" class="button-primary" value="<?php _e('Save Changes', 'fv_tc') ?>" />
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
                  <textarea name="blacklist_keys" rows="10" cols="50" id="blacklist_keys" class="large-text code"><?php echo esc_textarea( get_option( 'blacklist_keys' ) ); ?></textarea>
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
      add_meta_box( 'fv_tc_comment_moderation', 'Comment Moderation', array( $this,'fv_tc_admin_comment_moderation' ), 'fv_tc_settings', 'normal' );
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

          if( isset($_POST['comments_reporting']) && $_POST['comments_reporting'] )
            FV_Comments_Reporting::install();

          $options = array(
              'shorten_urls' => $shorten_urls,
              'reply_link' => ( isset($_POST['reply_link']) && $_POST['reply_link'] ) ? true : false,
              'comment_autoapprove_count' => ( isset($_POST['comment_autoapprove_count']) && intval($_POST['comment_autoapprove_count']) > 0 ) ? intval($_POST['comment_autoapprove_count']) : 1,
              'daily_comments_limit' => ( isset($_POST['daily_comments_limit']) && intval($_POST['daily_comments_limit']) > 0 ) ? intval($_POST['daily_comments_limit']) : false,
              'daily_comments_limit-checkbox' => ( isset($_POST['daily_comments_limit-checkbox']) && $_POST['daily_comments_limit-checkbox'] ) ? true : false,
              'tc_replyKW' => isset( $_POST['tc_replyKW'] ) ? $_POST['tc_replyKW'] : 'comment-',
              'user_nicename_edit' => ( isset($_POST['user_nicename_edit']) && $_POST['user_nicename_edit'] ) ? true : false,              
              'frontend_spam' => ( isset($_POST['frontend_spam']) && $_POST['frontend_spam'] ) ? true : false,
              'comment_whitelist_link' => ( isset($_POST['comment_whitelist_link']) && $_POST['comment_whitelist_link'] ) ? true : false,
              'comments_reporting' => ( isset($_POST['comments_reporting']) && $_POST['comments_reporting'] ) ? true : false,              
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

          if( update_option( 'blacklist_keys', trim( $_POST['blacklist_keys'] ) ) ) :
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
        
        if( $this->can_edit ) {
          global $post;
          wp_localize_script('fv_tc', 'fv_tc_html', $this->frontend_template());
          wp_localize_script('fv_tc', 'fv_tc_nonce', wp_create_nonce('fv_tc-'.$post->ID) );
        }
        
        // todo: somehow consider also $this->can_ban 
        
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

                $content = preg_replace( '~(\d+)~', '<span class="tc_highlight"><abbr title="' . sprintf( _n( 'This post has one unapproved comment.', 'This post has %d unapproved comments.', $count, 'fv_tc' ), $count ) . '">$1</abbr></span>', $content );
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
        
        $options = get_option('thoughtful_comments');
        $is_needed_for_guest =
          isset($options['voting_display_type']) && strcmp($options['voting_display_type'],'off') != 0 ||
          isset($this->options['comments_reporting']) && $this->options['comments_reporting'] ||
          isset($options['live_updates']) && strcmp($options['live_updates'],'off') != 0;
        
        if( $is_needed_for_guest && is_single() && $post->comment_count > 0 || is_user_logged_in() ) {
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
            $children = array_map('esc_sql',$children);
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

        $options = get_option('thoughtful_comments');

        /*  Check user permissions */
        if($user_ID && current_user_can('edit_post', $post->ID)) {
            if( isset($options['frontend_spam']) && $options['frontend_spam'] ) {
              $comments = get_comments( array('post_id' => $post->ID, 'order' => 'ASC', 'status' => 'any' ) );
            } else {
              $comments = get_comments( array('post_id' => $post->ID, 'order' => 'ASC' ) );
            }

            /*  Target array where both approved and unapproved comments are added  */
            $new_comments = array();
            foreach($comments AS $comment) {
                if($comment->comment_approved == 'trash') {
                  continue;
                }

                if($comment->comment_approved == 'spam') {
                  if( isset($options['frontend_spam']) && $options['frontend_spam'] ) {
                    $comment->comment_author = '<span id="comment-'.$comment->comment_ID.'-unapproved" class="tc_highlight_spam">'.$comment->comment_author.'</span>';
                  } else {
                    /*  Don't display the spam comments */
                    continue;
                  }
                }
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
            $replyto = __('Reply to comment via email', 'fv_tc') . ': <a href="mailto:'.rawurlencode('"'.$comment->comment_author.'"<'.$comment->comment_author_email.'>').'?subject='.rawurlencode( __('Your comment on', 'fv_tc') . ' "'.$post->post_title.'"' ).'&body='.rawurlencode( $content ).'">' . __('Email reply', 'fv_tc') . '</a>'."\r\n";
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
      
      if( !isset($href[1]) || isset($text[1]) ) {
        return $link[0];
      }
      
      if( $href[1] == $text[1] ) {
        preg_match( '!//(.+?)/!', $text[1], $domain );
        if( isset($domain[1]) && $domain[1] ) {

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
    
    function daily_comment_limit( $approved, $commentdata ) {      
      if( current_user_can( 'manage_options' ) || current_user_can( 'moderate_comments' ) ) return $approved;
      
      if( intval($this->get_setting('daily_comments_limit')) < 1 || !$this->get_setting('daily_comments_limit-checkbox') ) return $approved;

      if( isset($commentdata['user_id']) && $commentdata['user_id'] > 0 ) {
        $where = "user_id = ".intval($commentdata['user_id']);
      } else {
        $where = "comment_author_email = '".esc_sql($commentdata['comment_author_email'])."'";
      }
      
      $day_ago = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
    
      global $wpdb;
      $iCommentsPosted = $wpdb->get_var( $wpdb->prepare(
        "SELECT count(comment_ID) FROM $wpdb->comments WHERE `comment_post_ID` = %d AND `comment_date_gmt` >= %s AND $where",
        $commentdata['comment_post_ID'],
        $day_ago
      ) );

      if( $iCommentsPosted >= intval($this->get_setting('daily_comments_limit')) ) {
        return 0; //  too many comments posted on this day
      }
      
      return $approved;
    }    
    
    function comment_class( $classes, $class, $comment_ID, $comment, $post_id ) {
        if( $comment->comment_approved == 0 ) {
          $classes[] = 'tc-unapproved';
        } else if( $comment->comment_approved == 'spam' ) {
          $classes[] = 'tc-spam';
        }
        
        if( stripos(trim(get_option('blacklist_keys')),$comment->comment_author_IP) !== false ) {
          $classes[] = 'tc-banned';
        }
        
        return $classes;
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



    function fv_tc_approve() {
      if( isset($_POST['id']) ) {        
        $objComment = get_comment( $_REQUEST['id'] );
        check_ajax_referer('fv_tc-'.$objComment->comment_post_ID);
        if( !wp_set_comment_status( $_REQUEST['id'], 'approve' ) ) {
          die('db error');
        }
        die('1');
      }
    }

    function fv_tc_count() {
      $post_id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : false;
      if( $post_id ) {
        echo $this->get_wp_count_comments($post_id);
      }
      die();
    }

    function fv_tc_del() {
        global $wpdb;

        $objComment = get_comment( $_REQUEST['id'] );
        if( !$objComment ) {
          die('db error');
        }
        
        $objComment->comment_author_ip;
        if( stripos($_POST['action'],'_ban') !== false && stripos(trim(get_option('blacklist_keys')),$objComment->comment_author_IP)===FALSE) {
          $blacklist_keys = trim(stripslashes(get_option('blacklist_keys')));
          $blacklist_keys_update = $blacklist_keys."\n".$objComment->comment_author_IP;
          update_option('blacklist_keys', $blacklist_keys_update);

          wp_set_comment_status( $objComment->comment_ID, 'spam' );
          wp_set_comment_status( $objComment->comment_ID, $objComment->comment_approved );
        }

      //check_admin_referer('fv-tc-delete_' . $_GET['id']);
        if( stripos($_POST['action'],'thread') !== false ) {
          $this->fv_tc_delete_recursive($objComment->comment_ID);          
        } else {
          if( !wp_delete_comment($objComment->comment_ID) ) {
            die('db error');
          }
        }
        die('1');
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
        $comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE `comment_parent` = ".intval($id),ARRAY_A);
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
    
    
    function hack_html_close_comment_element( $comment_text ) {
      global $comment;
      if( !$this->can_edit ) {
        return $comment_text;
      }
      
      $tag = $this->hack_comment_wrapper ? $this->hack_comment_wrapper : 'div';
      
      $comment_text .= "\n".'</'.$tag.'><!-- .comment-content (fvtc) -->'."\n";
      
      return $comment_text;
    }
    
    
    function hack_check_comment_properties( $link ) {
      if( !$this->hack_comment_wrapper ) {  //  making sure it only executed once
        ob_start();
        add_filter( 'comment_text', array( $this, 'hack_check_comment_wrapper' ) );
      }
      
      return $link;
    }
    
    
    function hack_check_comment_wrapper( $comment_text ) {
      $sHTML = ob_get_clean();
      
      if( preg_match( '~<(\S+).*?>\s*?$~', $sHTML, $tag ) ) {      
        $this->hack_comment_wrapper = trim($tag[1]);
      }
      
      echo $sHTML;
      
      remove_filter( 'comment_text', array( $this, 'hack_check_comment_wrapper' ) ); //  making sure it only executed once
      return $comment_text;
    }
    
    
    function reply_button( $comment_text, $comment, $args = false ) {			
      $add_below = current_theme_supports( 'html5', 'comment-list' ) ? 'div-comment' : 'comment'; //  you might also need to check wp_list_comments() args['style'] here
      
      remove_filter( 'comment_reply_link', '__return_false', 999 ); //  enable the reply button for a bit!
      $reply_button = get_comment_reply_link( array(
					'add_below' => isset($args['add_below']) ? $args['add_below'] : $add_below,
					'depth'     => isset($args['depth']) ? $args['depth'] : 1,
					'max_depth' => $this->max_depth,
					'before'    => '<div class="reply">',
					'after'     => '</div>'
				) );
      add_filter( 'comment_reply_link', '__return_false', 999 ); //  disabling the standard reply buttons again!
      
      if( $reply_button ) $comment_text .= '<div class="fv_tc_wrapper">'.$reply_button.'</div>';
      
      return $comment_text;
    }


    function fv_tc_auto_approve_comment( $approved, $commentdata ){
      $options = get_option('thoughtful_comments');
      $comment_whitelist_link = ( isset($options['comment_whitelist_link']) ) ? $options['comment_whitelist_link'] : false;
      
      //edit: "Comment author must have a previously approved comment" or "Comment author must have a previously approved comment if the comment contains a link" has to be on to trigger this functionality
      if( !get_option('comment_whitelist') && !$comment_whitelist_link ){
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

      if( get_option('comment_whitelist') ) {
        $auto_approve_count = ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : false;
  
        //stop if auto-approve count is not set OR is less or equal 1 (comment whitelist already handle this)
        if( !$auto_approve_count || $auto_approve_count <= 1 ){
          return $approved;
        }
  
        global $wpdb;        
        $dbCount = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->prefix}comments WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = 1", $commentdata['comment_author'], $commentdata['comment_author_email'] ) );  
        if( $dbCount >= $auto_approve_count ) {
          return 1;
        } else {
          return 0;
        }
      
      } else if( $comment_whitelist_link ) {
		// if the comment has no link, just approve it
        if( stripos($commentdata['comment_content'],'http://') === false && stripos($commentdata['comment_content'],'https://') === false ) {
          return 1;
        }        
        
        global $wpdb;
        $ok_to_comment = $wpdb->get_var( $wpdb->prepare( "SELECT comment_approved FROM $wpdb->comments WHERE comment_author = %s AND comment_author_email = %s and comment_approved = '1' LIMIT 1", $commentdata['comment_author'], $commentdata['comment_author_email'] ) );
        if( 1 == $ok_to_comment ) {
          return 1;
        }
        return 0;
        
      }
      
    }


    function fv_tc_auto_approve_comment_override_notification(){
      if( !is_admin() ){
        return;
      }

      $options = get_option('thoughtful_comments');
      //do not add warning if option is not set or is set to 1, or if comment_whitelist (Comment author must have a previously approved comment) is not set
      $auto_approve_count = ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : false;
      $comment_whitelist_link = ( isset($options['comment_whitelist_link']) ) ? $options['comment_whitelist_link'] : false;
            
      if( !$comment_whitelist_link && ( !get_option('comment_whitelist') || !$auto_approve_count || $auto_approve_count <= 1 ) ){
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
      $fv_tc_link = admin_url('options-general.php?page=manage_fv_thoughtful_comments');

      $options = get_option('thoughtful_comments');
      //do not add warning if option is not set or is set to 1
      $auto_approve_count = ( isset($options['comment_autoapprove_count']) ) ? $options['comment_autoapprove_count'] : false;
      $comment_whitelist_link = ( isset($options['comment_whitelist_link']) ) ? $options['comment_whitelist_link'] : false;
      
      if( get_option('comment_whitelist') && $auto_approve_count > 0 ) {
        $discussion_settings = preg_replace( '~(<input[^>]*id="comment_whitelist"[^>]*>[^<]*)~', '$1 <br/><strong>WARNING:</strong> This setting is extended by <a href="'.$fv_tc_link.'#comment_autoapprove_count">FV Thoughtful Comments</a> plugin.', $discussion_settings );
      } else if( $comment_whitelist_link ) {
        $discussion_settings = preg_replace( '~(<label for="comment_whitelist">[\s\S]*?</label>)~', '$1<br/><label for="comment_whitelist_link"><input type="checkbox" id="comment_whitelist_link" name="comment_whitelist_link" value="1" disabled="true" checked="checked" /> Comment author must have a previously approved comment if the comment contains a link - see <a href="'.$fv_tc_link.'#comment_whitelist_link">FV Thoughtful Comments</a> plugin.</label>', $discussion_settings );
      }

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


    function fv_tc_comment_sorting() {
      if( !have_comments() ) return;

      $order = get_option('comment_order');

      if( !empty($_GET['fvtc_order']) && ( $_GET['fvtc_order'] == 'desc' || $_GET['fvtc_order'] == 'asc' ) ) {
        if( $_GET['fvtc_order'] == 'desc' ) {
          $newest = '<span>newest</span>';
          $oldest = '<a href="'.get_comments_link().'">oldest</a>';
        }

        if( $_GET['fvtc_order'] == 'asc' ) {
          $newest = '<a href="'.get_comments_link().'">newest</a>';
          $oldest = '<span>oldest</span>';
        }

      } else {
        if( $order == 'asc' ) {
          $newest = '<a href="'.add_query_arg( array('fvtc_order' => 'desc'), get_comments_link() ).'">newest</a>';
          $oldest = '<span>oldest</span>';
        }

        if( $order == 'desc' ) {
          $newest = '<span>newest</span>';
          $oldest = '<a href="'.add_query_arg( array('fvtc_order' => 'asc'), get_comments_link() ).'">oldest</a>';
        }

      }



      echo "<div class='fv_tc_comment_sorting'>$newest $oldest</div>";
    }
    
    
    function comment_order( $value ) {

      if( !empty($_GET['fvtc_order']) && ( $_GET['fvtc_order'] == 'desc' || $_GET['fvtc_order'] == 'asc' ) ) $value = $_GET['fvtc_order'];

      return $value;
    }
    
    
    function noscript_notice() {
      echo '<noscript>' . __('Reply link does not work in your browser because JavaScript is disabled.', 'fv_tc') . '<br /></noscript>';
    }
    
    
    function wptouch_disable_reply( $settings ) {
      $settings->allow_nested_comment_replies = false;      
      return $settings;
    }
    

}
$fv_tc = new fv_tc;

add_action( 'wp_ajax_fv_tc_approve', array( $fv_tc,'fv_tc_approve'));
add_action( 'wp_ajax_fv_tc_del', array( $fv_tc,'fv_tc_del'));
add_action( 'wp_ajax_fv_tc_delthread', array( $fv_tc,'fv_tc_del'));
add_action( 'wp_ajax_fv_tc_ban', array( $fv_tc,'fv_tc_del'));
add_action( 'wp_ajax_fv_tc_banthread', array( $fv_tc,'fv_tc_del'));
add_action( 'wp_ajax_fv_tc_moderated', array( $fv_tc,'fv_tc_moderated'));

add_action( 'wp_ajax_fv_tc_count', array( $fv_tc,'fv_tc_count'));
//add_action( 'wp_ajax_nopriv_fv_tc_count', array( $fv_tc,'fv_tc_count'));


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
add_action( 'wp_head', array( $fv_tc, 'frontend_start' ) );
/* Shorten plain links */
add_filter( 'comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Thesis theme fix */
add_action( 'thesis_hook_after_comment', array( $fv_tc, 'thesis_frontend_show' ), 1 );
/* Thesis theme fix */
add_filter( 'thesis_comment_text', array( $fv_tc, 'comment_links' ), 100 );

/* Approve comment if user is set out of moderation queue */
add_filter( 'pre_comment_approved', array( $fv_tc, 'moderate' ) );
/* Whitelist commenters: Auto-apporove comments from authors, which have N comments already approved. */
add_filter( 'pre_comment_approved', array( $fv_tc, 'fv_tc_auto_approve_comment' ), 10, 2 );

add_filter( 'pre_comment_approved', array( $fv_tc, 'daily_comment_limit' ), 999999, 2 );


/* Load js */
add_action( 'wp_footer', array( $fv_tc, 'scripts' ) );
add_action( 'admin_footer', array( $fv_tc, 'scripts' ) );

/* Show number of unapproved comments in frontend */
add_filter( 'comments_number', array( $fv_tc, 'show_unapproved_count' ) );
//add_filter( 'get_comments_number', array( $fp_ecm, 'show_unapproved_count' ) );

/* Styles */
add_action('wp_print_styles', array( $fv_tc, 'styles' ) );

/* Show unapproved comments bellow posts */
add_filter( 'comments_array', array( $fv_tc, 'unapproved' ) );

/* Cache users */
if( !function_exists('apc_fetch') && !function_exists('memcache_get') && !class_exists('Redis') ) add_filter( 'comments_array', array( $fv_tc, 'users_cache' ) );

/* Bring back children of deleted comments */
add_action( 'transition_comment_status', array( $fv_tc, 'transition_comment_status' ), 1000, 3 );

/* Admin's won't get the esc_html filter */
add_filter( 'comment_author', array( $fv_tc, 'comment_author_no_esc_html' ), 0 );



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

add_filter('comment_reply_link', array($fv_tc, 'comment_reply_link'), 10, 4 );

add_action('init', array($fv_tc, 'ap_action_init'));

add_filter('get_comment_link', array($fv_tc, 'get_comment_link'));
add_filter('get_comments_pagenum_link', array($fv_tc, 'get_comments_pagenum_link'));  //  todo: test!
add_filter('paginate_links', array($fv_tc, 'get_comments_pagenum_link'));

add_filter( 'pre_option_comment_order', array( $fv_tc, 'comment_order' ) );

add_action( 'comment_form_top', array( $fv_tc, 'noscript_notice' ), 10, 2 );
add_filter( 'comment_class', array( $fv_tc, 'comment_class' ), 10, 5 );

endif;  //  class_exists('fv_tc_Plugin')
