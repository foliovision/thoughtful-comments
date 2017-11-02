<?php

add_filter( 'pre_comment_approved', 'fv_tc_pre_comment_approved', -999999, 2 );

function fv_tc_pre_comment_approved( $approved, $commentdata ) {
    
  if ( ! empty( $commentdata['user_id'] ) ) {
		$user = get_userdata( $commentdata['user_id'] );
    global $wpdb;
		$post_author = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_author FROM $wpdb->posts WHERE ID = %d LIMIT 1",
			$commentdata['comment_post_ID']
		) );
	}
    
  if ( isset( $user ) && ( $commentdata['user_id'] == $post_author || $user->has_cap( 'moderate_comments' ) ) ) {
		// The author and the admins get respect.
		$approved = 1;
	} else {
    if ( fv_tc_check_comment(
      $commentdata['comment_author'],
      $commentdata['comment_author_email'],
      $commentdata['comment_author_url'],
      $commentdata['comment_content'],
      $commentdata['comment_author_IP'],
      $commentdata['comment_agent'],
      $commentdata['comment_type']
    ) ) {
      $approved = 1;
    } else {
      $approved = 0;
    }
  
    if ( fv_tc_wp_blacklist_check(
      $commentdata['comment_author'],
      $commentdata['comment_author_email'],
      $commentdata['comment_author_url'],
      $commentdata['comment_content'],
      $commentdata['comment_author_IP'],
      $commentdata['comment_agent']
    ) ) {
      $approved = 'spam';
    }
  }
  
  return $approved;
}


function fv_tc_check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type) {
  global $wpdb;

  // If manual moderation is enabled, skip all checks and return false.
  if ( 1 == get_option('comment_moderation') )
    return false;

  /** This filter is documented in wp-includes/comment-template.php */
  $comment = apply_filters( 'comment_text', $comment );

  // Check for the number of external links if a max allowed number is set.
  if ( $max_links = get_option( 'comment_max_links' ) ) {
    $num_links = preg_match_all( '/<a [^>]*href/i', $comment, $out );

    /**
     * Filter the maximum number of links allowed in a comment.
     *
     * @since 3.0.0
     *
     * @param int    $num_links The number of links allowed.
     * @param string $url       Comment author's URL. Included in allowed links total.
     */
    $num_links = apply_filters( 'comment_max_links_url', $num_links, $url );

    /*
     * If the number of links in the comment exceeds the allowed amount,
     * fail the check by returning false.
     */
    if ( $num_links >= $max_links )
      return false;
  }

  $mod_keys = trim(get_option('moderation_keys'));

  // If moderation 'keys' (keywords) are set, process them.
  if ( !empty($mod_keys) ) {
    $words = explode("\n", $mod_keys );

    foreach ( (array) $words as $word) {
      $word = trim($word);

      // Skip empty lines.
      if ( empty($word) )
        continue;

      /*
       * Do some escaping magic so that '#' (number of) characters in the spam
       * words don't break things:
       */
      $word = preg_quote($word, '#');

      /*
       * Check the comment fields for moderation keywords. If any are found,
       * fail the check for the given field by returning false.
       */
      $pattern = "#\b$word#i";
      if ( preg_match($pattern, $author) ) return false;
      if ( preg_match($pattern, $email) ) return false;
      if ( preg_match($pattern, $url) ) return false;
      if ( preg_match($pattern, $comment) ) return false;
      if ( preg_match($pattern, $user_ip) ) return false;
      if ( preg_match($pattern, $user_agent) ) return false;
    }
  }

  /*
   * Check if the option to approve comments by previously-approved authors is enabled.
   *
   * If it is enabled, check whether the comment author has a previously-approved comment,
   * as well as whether there are any moderation keywords (if set) present in the author
   * email address. If both checks pass, return true. Otherwise, return false.
   */
  if ( 1 == get_option('comment_whitelist')) {
    if ( 'trackback' != $comment_type && 'pingback' != $comment_type && $author != '' && $email != '' ) {
      // expected_slashed ($author, $email)
      $ok_to_comment = $wpdb->get_var("SELECT comment_approved FROM $wpdb->comments WHERE comment_author = '$author' AND comment_author_email = '$email' and comment_approved = '1' LIMIT 1");
      if ( ( 1 == $ok_to_comment ) &&
        ( empty($mod_keys) || false === strpos( $email, $mod_keys) ) )
          return true;
      else
        return false;
    } else {
      return false;
    }
  }
  return true;
}


function fv_tc_wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent) {
  /**
   * Fires before the comment is tested for blacklisted characters or words.
   *
   * @since 1.5.0
   *
   * @param string $author     Comment author.
   * @param string $email      Comment author's email.
   * @param string $url        Comment author's URL.
   * @param string $comment    Comment content.
   * @param string $user_ip    Comment author's IP address.
   * @param string $user_agent Comment author's browser user agent.
   */
  do_action( 'wp_blacklist_check', $author, $email, $url, $comment, $user_ip, $user_agent );

  $mod_keys = trim( get_option('blacklist_keys') );
  if ( '' == $mod_keys )
    return false; // If moderation keys are empty
  $words = explode("\n", $mod_keys );

  foreach ( (array) $words as $word ) {
    $word = trim($word);

    // Skip empty lines
    if ( empty($word) ) { continue; }

    // Do some escaping magic so that '#' chars in the
    // spam words don't break things:
    $word = preg_quote($word, '#');

    $pattern = "#\b$word#i";
    if (
         preg_match($pattern, $author)
      || preg_match($pattern, $email)
      || preg_match($pattern, $url)
      || preg_match($pattern, $comment)
      || preg_match($pattern, $user_ip)
      || preg_match($pattern, $user_agent)
     )
      return true;
  }
  return false;
}


add_action( 'admin_footer-options-discussion.php', 'fv_tc_admin_footer' );

function fv_tc_admin_footer( ) {
  ?>
  <script>
  (function( $ ) {
    var message = '<p><strong>FV Thoughtful Comments</strong>: This is changed so that "press" matches "pressing" but not "WordPress".</p>';
    $('[name=moderation_keys]').parents('p').before(message);
    $('[name=blacklist_keys]').parents('p').before(message);
  })(jQuery);
  </script>
  <?php
}