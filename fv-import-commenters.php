<?php

class FVTC_Import_Commenters {

  var $options;
  var $result;
  var $batch = false;

  function __construct() {
    $this->options = get_option('thoughtful_comments_import_commenters');
    
    if( isset($this->options['commenter_importing']) && $this->options['commenter_importing'] ){
      //comments with moderation
      add_action( 'transition_comment_status', array( $this, 'fv_transition_comment_status' ), 10, 3 );
      //comments without moderation
      add_action( 'comment_post', array( $this, 'fv_comment_post'), 10, 2 );
      
      $this->result = array( 'linked' => 0, 'added' => 0);
    }
  }

  public function fv_comment_post( $comment_ID, $comment_approved ){
    $comment = get_comment($comment_ID);
    
    if( $comment_approved === 1 ){
      $this->fv_transition_comment_status('approved', false, $comment);
    }
  }
  
  public function fv_transition_comment_status( $new_status, $old_status, $comment ) {
    if ( $new_status != 'approved')
      return;
    
    //only for comment from non-logged in users
    if( $comment->user_id != 0 ){
      return;
    }
    
    if ( defined('WP_IMPORTING') && WP_IMPORTING == true )
      return;
    
    $strMail = trim( $comment->comment_author_email );
    if( !isset( $strMail ) || empty( $strMail ) )
      return;

    //if user exists, link the comment
    //else create user
    $iUserID = email_exists( $comment->comment_author_email );
    if( false !== $iUserID ) {
      // user exists
      $this->fv_fix_unlinked_comment( $comment, $iUserID );
      $this->result['linked']++;
    } else {
      // user doesn't exist yet
      $this->fv_add_commenter( $comment );
      $this->result['added']++;
    }
  }
  
  /* @void
   * calling via wp-ajax
   * get annonymous comments
   * echo json encoded counts of added or linked commenters and result
   */
  function fv_refresh_comments_import_callback(){
    $this->batch = true;
    $number = 20;
    
    global $wpdb;
    $aComments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}comments WHERE comment_approved = 1 AND user_id = 0 AND comment_author_email LIKE '%@%' LIMIT $number");
    
    foreach( $aComments as $comment ){
      $this->fv_transition_comment_status('approved', false, $comment);
    }
    
    $total = count($aComments);
    $last_status = ( $total < $number ) ? true : false;
      
    $aInfo = array( 'total' => $total,
                    'added' => $this->result['added'],
                    'linked' => $this->result['linked'],
                    'last'  => $last_status
                   );
    
    echo json_encode($aInfo);
    
    wp_die();
  }
  /* @int
   * return count of anonymous comments
   * */
  function fv_get_anonymous_comment_count(){
    global $wpdb;
    $count = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}comments WHERE comment_approved = 1 AND user_id = 0 AND comment_author_email LIKE '%@%'");
    
    return $count;
  }

  private function fv_fix_unlinked_comment( $objComment, $iUserID ) {
    global $wpdb;

    $res = add_comment_meta( $objComment->comment_ID, 'fv_user_imported', 'automatically linked comment to user ' . $iUserID , true );

    $aComment = array(
      'user_id' => $iUserID
    );
    $aWhere = array(
      'comment_ID' => $objComment->comment_ID,
      'comment_author_email' => $objComment->comment_author_email
    );
    $wpdb->update(
      $wpdb->comments,
      $aComment,
      $aWhere
    );
  }

  private function fv_add_commenter( $objComment ) {
    global $wpdb;

    $strQueryUserLogins = "
      select user_login
      from {$wpdb->users}
    ";

    $aUserLogins = $wpdb->get_col( $strQueryUserLogins );

    $aUserName = explode(' ', $objComment->comment_author, 2);
    $sFirstName = $aUserName[0];
    if( isset( $aUserName[1] ) )
      $sLastName = $aUserName[1];
    else
      $sLastName = '';

    if( !empty($sLastName) )
      $sDisName = $sFirstName . ' ' . $sLastName[0] . '.';
    else
      $sDisName = $sFirstName;
    $g_login = sanitize_user( strtolower( $aUserName[0] ), true );
    $g_login = preg_replace("/[^A-Za-z0-9 ]/", '', $g_login);
    unset( $aUserName[0] );
    $user_append = '';
    $user_append = sanitize_user( strtolower( implode( '', $aUserName ) ) );
    $user_append = preg_replace("/[^A-Za-z0-9 ]/", '', $user_append);

    if( !empty( $user_append ) )
      $g_login .= $user_append[0];

    if( in_array( $g_login, $aUserLogins ) !== FALSE ) {
      $i=1;
      while( in_array( $g_login . $i, $aUserLogins ) !==FALSE )
        $i++;
      $g_login .= $i;
    }

    $strGeneratedPW = wp_generate_password( 12, false );
    $aUserData = array(
      'user_login' => $objComment->comment_author_email,
      'user_nicename' => $g_login,
      'user_pass' => $strGeneratedPW,
      'user_email' => $objComment->comment_author_email,
      'display_name' => $objComment->comment_author,
      'first_name' => $sFirstName,
      'last_name' => $sLastName,
      'role' => 'subscriber'
    );

    $user_id = wp_insert_user( $aUserData );
    add_user_meta( $user_id, 'fv_user_imported', 'automatically imported commenter', true );
    add_user_meta( $user_id, 'fv_user_imported_p', 'fv'.$strGeneratedPW.'poiu', true );

    $aComment = array(
      'user_id' => $user_id
    );
    $aWhere = array(
      'comment_ID' => $objComment->comment_ID,
      'comment_author_email' => $objComment->comment_author_email
    );
    $wpdb->update(
      $wpdb->comments,
      $aComment,
      $aWhere
    );

    $res = add_comment_meta( $objComment->comment_ID, 'fv_user_imported', 'automatically linked comment to user ' . $user_id , true );
    
    $send_welcome_email = ( isset($this->options['commenter_importing_welcome_email']) && $this->options['commenter_importing_welcome_email'] ) ? true : false;    
    //checkin only for ajax batch importing: "Crawl all existing comments"
    if( $send_welcome_email && $this->batch ){
      $aArgs = array( 'status' => 'approve', 'author_email' => $objComment->comment_author_email, 'count' => true );
      $iCommentsCount = get_comments( $aArgs );
      //less comment than minimal count, don't send welcome email
      if( isset($this->options['commenter_importing_welcome_email_count']) && intval($this->options['commenter_importing_welcome_email_count']) > $iCommentsCount ){
        $send_welcome_email = false;
      }
    }

    if( $send_welcome_email ){
      $this->fv_send_mail_invite( $objComment->comment_author_email, $strGeneratedPW, $objComment->comment_author_email, $sFirstName, $sLastName );
    }
     
  }
  
  private function fv_send_mail_invite( $sLogin, $sPassword , $sEmail, $sFirstName, $sLastName ){
    $subject = $this->options['commenter_importing_welcome_email_subject'];
    $subject = str_replace( '%sitename%', get_bloginfo('name'), $subject );
    $subject = str_replace( '%firstname%', $sFirstName, $subject );
    
    $content = $this->options['commenter_importing_welcome_email_content'];
    $content = str_replace( '%login%', $sLogin, $content );
    $content = str_replace( '%password%', $sPassword, $content );
    $content = str_replace( '%firstname%', $sFirstName, $content );
    $content = str_replace( '%lastname%', $sLastName, $content );
    $content = str_replace( '%sitename%', get_bloginfo('name'), $content );
    $content = str_replace( '%login_page%', site_url('wp-login.php'), $content );
    
    //TESTING!!
    //file_put_contents( ABSPATH.'/fv-tc-mails.txt', date('r'). "\n" . $sEmail . "\n". $subject ."\n". $content . "\n" . "------------------\n", FILE_APPEND);
    wp_mail( $sEmail, $subject, $content );

  }

}

$fvtc_import_commenters = new FVTC_Import_Commenters();