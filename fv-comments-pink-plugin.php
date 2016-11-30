<?php



class FV_Comments_Pink {

  function __construct() {
    $options = get_option('thoughtful_comments');
    if( !isset($options['live_updates']) || $options['live_updates'] == 'off' ) {
      return;
    }

    if( !isset($options['live_updates_manual_insert'] ) || !$options['live_updates_manual_insert'] )
      add_action( 'comment_form_before', array( $this, 'fv_add_fv_tc_controls' ) );

    /*logged in users only*/
    //add_action('wp_ajax_fv_cp_store_comments', array($fvcp, 'fv_cp_store_comments_callback'));

    /*not logged in users only*/
    //add_action('wp_ajax_nopriv_fv_cp_store_comments', array($fvcp, 'fv_cp_store_cookie_callback'));

    //  much faster than adding via JS!
    add_action('fv_comments_pink_show', array( $this, 'comment_text_show' ));


    //add_action( 'fv_tc_controls', array( $fvcp, 'toggle' ) );

    add_action( 'wp_footer', array( $this, 'scripts' ) );
  }

  function fv_add_fv_tc_controls() {
    do_action('fv_tc_controls');
    do_action('fv_tc_show_new_comments');
  }


  /*max records in wp_usermeta table*/
  private $max_records = 100;

  function comment_text_show( $comment_text = null ) {
    if( !is_user_logged_in() ) return;

    if( !is_admin() ) {
      //$comment_text = "<a href='#' class='fv-cp-comment-show'>Show</a><a href='#' class='fv-cp-comment-hide'>Hide</a>\n\n".$comment_text;
      $comment_text = "<a href='#' class='fv-cp-comment-show'>Show</a>\n\n".$comment_text;
    }
    echo $comment_text;
  }

  function fv_cp_store_comments_callback() {
    global $wpdb;
    $post_id = get_the_ID();

    /*timestamp NOW*/
    $date = date('Y-m-j H:i:s');

    /*logged in user*/
    $user_id = get_current_user_id();

    /*get last time on the post*/
    $last_time = get_user_meta($user_id, 'fv_comments_pink_last_' . $post_id, true);

    /*array of comments' IDs*/
    $comments_to_show = array();
    $comments_to_show_user = array();

    /*all approved comments will be shown*/
    $approved_comments = $wpdb->get_results("SELECT comment_ID, user_id FROM {$wpdb->comments} WHERE comment_post_ID = {$post_id} AND comment_date_gmt > '{$last_time}' AND comment_approved = '1' ORDER BY comment_date ASC");
    if (isset($approved_comments)) {
      foreach ($approved_comments as $comment) {
        if( $comment->user_id == $user_id ) {
          $comments_to_show_user[] = $comment->comment_ID;
        } else {
          $comments_to_show[] = $comment->comment_ID;
        }
      }
    }

    if( count($comments_to_show) > 0 && count($comments_to_show_user) > 0 ) { //  this stops comment from hiding if there is only new comment by the user himself
      $comments_to_show = array_merge( $comments_to_show, $comments_to_show_user );
    }

    //  unapproved comments
    $unapproved_comments = $wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = {$post_id} AND comment_approved = '0' ORDER BY comment_date ASC");
    //$comments_to_show = $comments_to_show + $unapproved_comments;

    // old and misterious part of code, I guess it's there to now show unapproved comments again and again
    $unaproved = get_user_meta($user_id, 'fv_comments_pink_unapproved', true);
    if ($unaproved == '') {
      if (isset($unapproved_comments)) {
        $comments_to_approve = '';
        foreach ($unapproved_comments as $comment) {
          $comments_to_approve .= $comment->comment_ID . '#';
        }
        update_user_meta($user_id, 'fv_comments_pink_unapproved', $comments_to_approve);
      }
    }
    else {
      // id#id#id#
      $unaproved = explode('#', get_user_meta($user_id, 'fv_comments_pink_unapproved', true));
      unset($unaproved[count($unaproved) - 1]);

      $comments_to_approve = '';
      foreach ($unaproved as $index => $id) { //  todo: single SQL with PHP parsing
        if( intval($id) < 1 ) continue;

        $comment = $wpdb->get_row("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID = {$id} AND comment_post_ID = {$post_id} AND (comment_approved = '1' OR comment_approved = 'trash' OR comment_approved = 'spam') LIMIT 1");
        if (isset($comment->comment_ID)) {
          $comments_to_show[] = $id;
          unset($unaproved[$index]);
        }

        $comments_to_approve .= $id . '#';
      }

      if( $comments_to_approve ) {
        //  add new unaproved comments
        foreach ($unapproved_comments as $comment) {
          if (strpos($comments_to_approve, $comment->comment_ID) === false) {
            $comments_to_approve .= $comment->comment_ID . '#';
          }
        }

        update_user_meta($user_id, 'fv_comments_pink_unapproved', $comments_to_approve);
      }
    }

    /*if all comments are new*/
    $all_comments = $wpdb->get_var("SELECT count(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = {$post_id} AND comment_approved = '1'");
    if (count($comments_to_show) == $all_comments) {
      $comments_to_show = array(-1);
    }

    /*update last time on the post*/
    $meta_records = $wpdb->get_results("SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE user_id = {$user_id} AND meta_key LIKE 'fv_comments_pink_last_%' ORDER BY umeta_id ASC"); //  todo: what is this??
    $found = false;
    foreach($meta_records as $meta) {
      if ($meta->meta_key == 'fv_comments_pink_last_' . $post_id) {
        $found = true;
        break;
      }
    }
    if (count($meta_records) == $this->max_records && !$found) {
      delete_metadata('user', $meta_records[0]->user_id, $meta_records[0]->meta_key);
    }
    update_user_meta($user_id, 'fv_comments_pink_last_' . $post_id, $date);

    return $comments_to_show;
  }

  function scripts() {
    if( !is_user_logged_in() ) return;
    ?>
    <script>var fv_tc_new_comments = <?php echo json_encode($this->fv_cp_store_comments_callback()); ?>;</script>
    <?php
  }

  function toggle() {
    if( !is_user_logged_in() || !have_comments() ) return;
    ?>
    <a style="display: none; " id="fv-comments-pink-toggle" href="#">Show only new comments</a>
    <?php
  }
}

$fvcp = new FV_Comments_Pink;