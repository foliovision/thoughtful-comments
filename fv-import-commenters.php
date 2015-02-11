<?php
/*
Plugin Name: FV Import Commenters
Plugin URI: http://foliovision.com/
Description: Approved Comments will trigger linking of new comments to their user profile or addition of the commenter as user. The user also gets added into the Newsletter Pro plugin as Subscriber.
Author: Foliovision
Version: 0.2
Author URI: http://www.foliovision.com/
*/

class FV_Import_Commenters {

   function __construct() {
      add_action( 'transition_comment_status', array( $this, 'fv_transition_comment_status' ), 10, 3 );

      add_action( 'edit_user_profile_update', array( $this, 'userupdate' ) );
      add_action( 'personal_options_update', array( $this, 'userupdate' ) );
//       add_action( 'profile_update', array( $this, 'userupdate' ) );

      add_action( 'delete_user', array( $this, 'delWPUser' ) );

   }

   public function fv_transition_comment_status( $new_status, $old_status, $comment ) {

      if ( $new_status == $old_status )
         return;

      # we don't need to record a history item for deleted comments
      if ( $new_status == 'delete' )
         return;

      if ( !is_admin() )
         return;

      if ( !current_user_can( 'edit_post', $comment->comment_post_ID ) && !current_user_can( 'moderate_comments' ) )
         return;

      if ( defined('WP_IMPORTING') && WP_IMPORTING == true )
         return;

      $strMail = trim( $comment->comment_author_email );
      if( !isset( $strMail ) || empty( $strMail ) )
         return;

      global $current_user;
      $reporter = '';
      if ( is_object( $current_user ) )
         $reporter = $current_user->user_login;

      // Assumption alert:
      // We want to submit comments to Akismet only when a moderator explicitly spams or approves it - not if the status
      // is changed automatically by another plugin.  Unfortunately WordPress doesn't provide an unambiguous way to
      // determine why the transition_comment_status action was triggered.  And there are several different ways by which
      // to spam and unspam comments: bulk actions, ajax, links in moderation emails, the dashboard, and perhaps others.
      // We'll assume that this is an explicit user action if POST or GET has an 'action' key.
      if ( isset($_POST['action']) || isset($_GET['action']) ) {
         if ( $new_status == 'approved'/* || $new_status == 'unapproved'*/ ) {
            //if user exists, link the comment
            //else create user

            $iUserID = email_exists( $comment->comment_author_email );
            if( false !== $iUserID ) {
               // user exists
               $this->fv_fix_unlinked_comment( $comment, $iUserID );
            } else {
               // user doesn't exist yet
               $this->fv_add_commenter( $comment );
            }
         }
      }
   }

   private function fv_fix_unlinked_comment( $objComment, $iUserID ) {
      global $wpdb;

      $res = add_comment_meta( $objComment->comment_ID, 'fv_user_imported', 'automatically linked comment to user ' . $iUserID , true );

      $aComment = array(
         'user_id' => $iUserID
      );
      $aWhere = array(
         'comment_author_email' => $objComment->comment_author_email,
         'user_id' => 0
      );
      $wpdb->update(
         $wpdb->comments,
         $aComment,
         $aWhere
      );
   }

   private function newsletter_save($subscriber) {
      global $wpdb, $newsletter;

      $email = $newsletter->normalize_email($email);
      $name = $newsletter->normalize_name($name);
      if (isset($subscriber['id'])) {
         $wpdb->update($wpdb->prefix . 'newsletter', $subscriber, array('id' => $subscriber['id']));
      } else {
         $subscriber['status'] = 'C';
         $subscriber['token'] = md5(rand());

         $wpdb->insert($wpdb->prefix . 'newsletter', $subscriber);
         $subscriber['id'] = $wpdb->insert_id;
      }
      return $subscriber;
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
         'user_login' => $g_login,
         'user_pass' => $strGeneratedPW,
         'user_email' => $objComment->comment_author_email,
         'display_name' => $objComment->comment_author,
         'first_name' => $sFirstName,
         'last_name' => $sLastName,
         'role' => 'subscriber'
      );
   //    var_dump( $aUserData );
      $user_id = wp_insert_user( $aUserData );
      add_user_meta( $user_id, 'fv_user_imported', 'automatically imported commenter', true );
      add_user_meta( $user_id, 'fv_user_imported_p', 'fv'.$strGeneratedPW.'poiu', true );

      $aComment = array(
         'user_id' => $user_id
      );
      $aWhere = array(
         'comment_author_email' => $objComment->comment_author_email
      );
      $wpdb->update(
         $wpdb->comments,
         $aComment,
         $aWhere
      );
      
      
      global $newsletter;
      if( isset( $newsletter ) /*false && '188.167.60.96' == $_SERVER['REMOTE_ADDR']*/ ) { // i.e. if the newsletter plugin is active

         $lines = array();
         $lines[0] = "{$objComment->comment_author_email},$sFirstName,$sLastName";
         $options['separator'] = ',';
         $options['mode'] = 'update';
//          $options['list_1'] = 1; // Subscribers - feedburner
         $options['list_2'] = 1; // Members - commenters
//          $options['list_3'] = 1; // All Supporters
//          $options['list_4'] = 1; // SupportersAndSubscribers
//          $options['list_5'] = 1; // OnlySupporters
         $options['list_6'] = 1; // NewSubscribers
//          $options['list_7'] = 1; // NewOnlySupporters
//          $options['list_8'] = 1; // TEST
//          $options['list_9'] = 1; // -
         $options['profile_1'] = $g_login;         // username
         $options['profile_2'] = $strGeneratedPW;  // pwd


         $subscriber = array();
         $error = array();
         for ($i=1; $i<=NEWSLETTER_LIST_MAX; $i++)
         {
            $list = 'list_' . $i;
            if (isset($options[$list])) $subscriber[$list] = 1;
            else {
                  if ($options['mode'] == 'overwrite') $subscriber[$list] = 0;
            }
         }

         foreach ($lines as $line) {
         // Parse the CSV line
            $line = trim($line);
            if ($line == '') continue;
            if ($line[0] == '#') continue;
            $separator = $options['separator'];
            if ($separator == 'tab') $separator = "\t";
            $data = explode($separator, $line);


            // Builds a subscriber data structure
            $subscriber['email'] = $newsletter->normalize_email($data[0]);
            if (!$newsletter->is_email($subscriber['email']))
            {
                  $error[] = '[INVALID EMAIL] ' . $line;
//                   continue;
            }
            $subscriber['name'] = $newsletter->normalize_name($data[1]);
            $subscriber['surname'] = $newsletter->normalize_name($data[2]);

            $subscriber['profile_1'] = $options['profile_1'];
            $subscriber['profile_2'] = $options['profile_2'];

//             for ($i=1; $i<=NEWSLETTER_PROFILE_MAX; $i++) {
//                   if (isset($data[$i+2])) $subscriber['profile_' . $i] = $data[$i+2];
//             }

            // May by here for a previous saving
            if( isset($subscriber['id']) ) {
                  unset($subscriber['id']);
            }

            // Try to load the user by email
            $user = $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix .
                                 "newsletter where email=%s", $subscriber['email']), ARRAY_A);

            // If the user is new, we simply add it
            if (empty($user)) {
                  $this->newsletter_save($subscriber);
                  continue;
            }

            if ($options['mode'] == 'skip') {
                  $error[] = '[DUPLICATE] ' . $line;
                  continue;
            }

            if ($options['mode'] == 'overwrite') {
                  $subscriber['id'] = $user['id'];
                  $this->newsletter_save($subscriber);
                  continue;
            }

            if ($options['mode'] == 'update') {
                  $this->newsletter_save(array_merge($user, $subscriber));
            }
         }
         add_user_meta( $user_id, 'fv_user_imported_to_newsletterpro', $lines[0], true );
         if( !empty( $error ) )
            add_user_meta( $user_id, 'fv_user_imported_to_newsletterpro_err', $error, true );

     }
      ///TODO send email to the commenter about their new account
   }

   public function userupdate( $iUserID ) {
      $objUser = get_userdata( $iUserID );
      global $wpdb;

      $strEmail = $objUser->user_email;
      $user = $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix .
                                 "newsletter where email=%s", $strEmail), ARRAY_A);
      if( !isset( $user ) || empty( $user ) ) {
         return false;
      }

      $strFirstName = $user['name'];
      $strLastName  = $user['surname'];
      if( isset( $_POST['email'] ) ) {
         $strEmail = trim( $_POST['email'] );
      }
      if( isset( $_POST['first_name'] ) ) {
         $strFirstName = trim( $_POST['first_name'] );
      }
      if( isset( $_POST['last_name'] ) ) {
         $strLastName = trim( $_POST['last_name'] );
      }

      $strQuery = "UPDATE {$wpdb->prefix}newsletter SET name = '{$strFirstName}', surname = '{$strLastName}', email = '{$strEmail}' WHERE id = '" . $user["id"] . "'";
      $wpdb->query( $strQuery );
   }

   public function delWPUser( $iUserID ) {
      global $wpdb;
      $objUser = get_userdata( $iUserID );
      if( $objUser !== false ) {
         $strEmail = $objUser->user_email;

         $user = $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix .
                                    "newsletter where email=%s", $strEmail), ARRAY_A);
         if( !isset( $user ) || empty( $user ) ) {
            return false;
         }

         $strQuery = "DELETE FROM {$wpdb->prefix}newsletter WHERE id = '" . $user["id"] . "'";
         $wpdb->query( $strQuery );
         return true;

      }
      return false;
   }

}

$fv_import_commenters = new FV_Import_Commenters();
