<?php

class FV_Comments_Reporting {
  
  var $options;
  
  function __construct() {
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    add_filter( 'comment_reply_link', array( $this, 'add_frontend' ), 11, 4 );
    add_filter( 'nonce_life', array( $this, 'nonce_life' ) );
    
    add_action( 'deleted_comment', array( $this, 'delete_report' ) );
    add_action( 'wp_set_comment_status', array( $this, 'update_report_status' ), 10, 2 );    
    
    add_action( 'wp_ajax_fv_tc_report_close', array( $this,'fv_tc_report_close') );    
    add_action( 'wp_ajax_fv_tc_report', array( $this,'fv_tc_report') );
    add_action( 'wp_ajax_nopriv_fv_tc_report', array( $this,'fv_tc_report') );
    
    add_filter( 'comment_class', array( $this, 'comment_class' ), 10, 4 );
    
    add_filter( 'comments_array', array( $this, 'cache' ) );
        
    add_filter( 'fv_tc_report', array( $this, 'show_frontend' ), 11, 4 );
        
    $this->options =  get_option('thoughtful_comments');
  }
  
  
  function add_frontend( $html, $args, $comment, $post ) {
    
    $bCommentReg = get_option( 'comment_registration' );
    
    if( $this->options['comments_reporting'] && ( !$bCommentReg || is_user_logged_in() ) ) {
      
      global $fv_tc;
      $fv_tc->loadScripts = true;
      
      // TODO show different interface for admin
      // TODO display for guest
      $button = "<a rel='nofollow' class='comment-report-link' href='#comment_report_{$comment->comment_ID}' onclick='fv_tc_report_display( {$comment->comment_ID} ); return false;'>Report</a>";
      if( $html ) {
        $html = str_replace( "</a>", "</a> ".$button, $html );
      } else {
        $html  = $button;
      }
    
    
      $report_box  = "<div class='comment_report_wrap' id='comment_report_{$comment->comment_ID}' style='display:none'>\n";
      $report_box .= "<p>What is it about this comment which you think readers of ".get_bloginfo()." would find offensive? Thanks for your report!</p>\n";
      $report_box .= "<input type='hidden' id='report_nonce_{$comment->comment_ID}' name='report_nonce_{$comment->comment_ID}' value='".wp_create_nonce('report_comment_'.$comment->comment_ID)."'/>\n";
      $report_box .= "<label for='report_reason_{$comment->comment_ID}'>Reason:</label>\n";
      $report_box .= "<p><input type='text' id='report_reason_{$comment->comment_ID}' name='report_reason_{$comment->comment_ID}' /></p>\n";
      $report_box .= "<button id='report_button_{$comment->comment_ID}' class='report_button' onclick='fv_tc_report_comment( {$comment->comment_ID} )'>Submit report</button>\n";
      $report_box .= "<p id='report_response_{$comment->comment_ID}'></p>\n";
      $report_box .= "</div>\n";
      
      $html .= $report_box;
    }
    
    return $html;
  }
  
  
  function admin_menu() {
    if( isset($this->options['comments_reporting']) && $this->options['comments_reporting'] ) {
      $count = count( $this->get_reports( false, 'open' ) );
      $count_label = $count > 0 ? " <span class='awaiting-mod count-60504'><span class='pending-count'>".$count."</span></span>" : false;
      add_menu_page( "Comment Reports", "Reports".$count_label, 'moderate_comments', 'comment_reports', array($this, 'comment_reports_panel'), 'dashicons-admin-comments' );
    }    
  }
  
  
  function cache( $comments ) {
    global $wpdb;
    
    if( $comments !== NULL && count( $comments ) > 0 ) {

      $all_IDs = array();
      foreach( $comments AS $comment ) {
        $all_IDs[] = $comment->comment_ID;
      }
      
      if( count($all_IDs) > 0 ) {
        $this->aReports = $this->get_reports($all_IDs, 'open');
        
        if( count($this->aReports) > 0 ) {
          $aNew = array();
          foreach( $this->aReports AS $objReport ) {
            if( !isset($aNew[$objReport->comment_id]) ) $aNew[$objReport->comment_id] = array();
            $aNew[$objReport->comment_id][] = $objReport;
          }
          $this->aReports = $aNew;
        }
        
      } else {
        $this->aReports = false;
      }
      
    }
    
    return $comments;
  }
  
  
  function comment_class( $aClasses, $sClasses, $comment_id, $objComment ) {
    if( isset($this->aReports[$objComment->comment_ID]) && current_user_can('moderate_comments') ) {
      $aClasses[] = 'comment-has-report';
    }
    
    return $aClasses;
  }
  
  
  function comment_reports_panel() {
    global $wpdb;

    $comment_ids = array();
    $comments = array();

    // TODO: add filters
    $reports = $this->get_reports();

    foreach ( $reports as $rep ) {
      if( ! in_array( $rep->comment_id, $comment_ids) ) {
        $comment_ids[] = $rep->comment_id;
      }
    }

    $comments_data = get_comments( array(
      'comment__in' => $comment_ids,
    ));

    // Sort comments:
    foreach( $comments_data as $comment ) {
      $comments[ $comment->comment_ID ] = $comment;
    }
    unset( $comments_data );
    
    ?>
      <div class="wrap">
        <div style="position: absolute; right: 20px; margin-top: 5px">
            <a href="http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments" target="_blank" title="Documentation"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
        </div>
        <div>
            <div id="icon-options-general" class="icon32"><br /></div>
            <h2>FV Thoughtful Comments Reports</h2>
        </div>
        <div id="poststuff" class="ui-sortable">
        
        <table class="wp-list-table widefat">
          <thead>
            <tr>
              <th>Date</th>
              <th>Comment</th>
              <th>Reporter</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tr>
          <tbody id="the-list">
            <?php
            foreach( $reports as $report ) {
              
              $comment_id = $report->comment_id;
              
              if( $report->status != 'deleted' ) {
                $comment = $comments[ $report->comment_id ];
                
                $link = get_comment_link( $comment );
                            
                $text = $comment->comment_content;
                if( strlen( $text ) > 127 ){
                  $text = substr( $comment->comment_content, 0, 127 ) . "...";
                }
                
                $comment = $text." (<a href='$link' target='_blank'>link</a>)";
              } else {
                $comment = "[$comment_id] <i>deleted</i>";
              }

              // Reporter data
              if( $report->rep_uid ) {
                $user_data = get_userdata( $report->rep_uid );
                $user_link = get_edit_user_link( $report->rep_uid );
                $reporter = "<a href='$user_link'>" . $user_data->user_login . '</a>';
              }
              else{
                $reporter = "guest";
              }
              $reporter .= "<br/>{$report->rep_ip}";

              // Reason
              // TODO javascript hiding long text
              $reason = $report->reason;

              // Close link
              $action_link = '';
              if( $report->status == 'open' ) {
                $action_link .= "<a href='#' onclick='fv_tc_report_admin( $report->id, \"close\" ); return false'>Close</a>";
                $action_link .= "<br /><a href='#' onclick='fv_tc_report_admin( $report->id, \"trash\", $report->comment_id ); return false'>Trash</a>";
              }

              echo "<tr class='{$report->status}' id='report_row_{$report->id}'>\n";
              echo "\t<td>{$report->rep_date}</td>\n";
              echo "\t<td>$comment</td>\n";
              echo "\t<td>$reporter</td>\n";
              echo "\t<td>$reason</td>\n";
              echo "\t<td class='report_status'>{$report->status}</td>\n";
              echo "\t<td class='report_action'>$action_link</td>\n";
              echo "</tr>\n";
            }
            ?>
            
          </tbody>
        </table>

        </div>
    </div>

    <?php /*
    <style>

    </style>
    */ ?>

    <?php
  }
  
  
  function delete_report ( $comment_id ) {
    global $wpdb;

    $current_user = wp_get_current_user();

    $wpdb->update(
       $wpdb->prefix.'commentreports_fvtc',
      array(
        'status'      => 'deleted',
        'mod_uid'     => $current_user->ID,
        'mod_date'    => date("Y-m-d H:i:s")
      ),
      array(
        'comment_id'  => $comment_id
      )
    );
  }
  
  
  function fv_tc_report() {
    global $wpdb;
    $comment_id = intval($_REQUEST['id']);
    $reason = strip_tags( stripslashes($_REQUEST['reason']) );

    check_ajax_referer( 'report_comment_'.$comment_id );

    $options = get_option( 'thoughtful_comments' );
    $bCommentReg = get_option( 'comment_registration' );

    if( ( $bCommentReg && !is_user_logged_in() ) || !$options['comments_reporting'] ) {
      die( "-2" );
    }

    $reported_before = $wpdb->get_var(
      "SELECT count(*) FROM {$wpdb->prefix}commentreports_fvtc
      WHERE comment_id = ".$comment_id."
      AND rep_ip = '".$_SERVER['REMOTE_ADDR']."'
      AND status = 'open'" // TODO: consider checking of status
    );

    if( intval( $reported_before ) ) {
      die( "-3" );
    }

    $current_user = wp_get_current_user();

    $inserted = $wpdb->insert(
       $wpdb->prefix.'commentreports_fvtc',
      array(
        'comment_id'  => $comment_id,
        'rep_uid'     => $current_user->ID,
        'rep_ip'      => $_SERVER['REMOTE_ADDR'],
        'rep_date'    => date("Y-m-d H:i:s"),
        'reason'      => $reason,
        'status'      => 'open'
      )
    );

    $objComment = get_comment($comment_id);
    $message = "Following comment by ".$objComment->comment_author." (".$objComment->comment_author_email.") has been reported:\n\n".$objComment->comment_content."\n\nReason: ".$reason."\nLink: ".get_comment_link($comment_id);            
    wp_mail( get_option('admin_email'), 'Comment Report', $message );

    echo $inserted;
    die();
  }

  
  function fv_tc_report_close() {
    global $wpdb;

    $current_user = wp_get_current_user();

    $wpdb->update(
       $wpdb->prefix.'commentreports_fvtc',
      array(
        'status'      => 'closed',
        'mod_uid'     => $current_user->ID,
        'mod_date'    => date("Y-m-d H:i:s")
      ),
      array(
        'id'          => $_REQUEST['id']
      )
    );

    die();
  }  

  
  /**
   * Get reports for specified comment id
   * @param  int    $comment_id Commment ID. False for all comments. (default = false)
   * @param  string $status     Status of report. False for all reports. (default = false)
   * @return array             Array of repots Objects
   */
  function get_reports ( $comment_id = false, $status = false ) {
    global $wpdb;

    $query = "SELECT * FROM {$wpdb->prefix}commentreports_fvtc WHERE 1=1 ";

    if( is_array($comment_id) ) {
      $query .= " AND comment_id IN (".implode(',',$comment_id).")";
    } else if( $comment_id ) {
      $query .= " AND comment_id = ".intval($comment_id);
    }

    if( $status ) {
      $query .= " AND status = '{$status}'";
    }
    
    $query .= " ORDER BY rep_date DESC LIMIT 1000";

    $reports = $wpdb->get_results( $query );
    return $reports;
  }
  
  

  /**
   * Generate the anchor for close report
   *
   * @param int $comment Comment object
   *
   * @return string HTML of the anchor
   */
  function get_t_reports($comment) {
    $out = '';

    if( isset($this->options['comments_reporting']) && $this->options['comments_reporting'] ) {

      $aReports = isset($this->aReports[$comment->comment_ID]) ? $this->aReports[$comment->comment_ID] : false;
      if( $aReports ){
        $out .= '<div class="fv_tc_reports">Reports:';
        $out .= '<ul>';
        foreach( $aReports as $objReport ) {
          $out .= "<li id='report_row_{$objReport->id}'>{$objReport->reason} <a href='#' class='fc-tc-closereport' onclick='fv_tc_report_front_close({$objReport->id}); return false'>" . __('Close','fv_tc') . "</a></li>";
        }
        $out .= '</ul>';
        $out .= '</div>';
      }
    }

    return $out;
  }  
  
	
  static function install() {
    global $wpdb;
    $wpdb->query(
      "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}commentreports_fvtc (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `comment_id` BIGINT(20) UNSIGNED NOT NULL,
        `rep_uid` BIGINT(20) UNSIGNED NOT NULL,
        `rep_ip` VARCHAR(45) NULL,
        `rep_date` DATETIME NULL,
        `reason` TEXT NULL,
        `status` ENUM('open','closed','deleted') NULL,
        `mod_uid` BIGINT(20) UNSIGNED NULL,
        `mod_date` DATETIME NULL,
        `notes` TEXT NULL
      );"
    );
  }


  function nonce_life( $default ) {
    
    if( !isset($this->options['comments_reporting']) || !$this->options['comments_reporting'] ){
      return $default;
    }
    
    return 2*24*3600; //  2 days
  }
  
  
  function show_frontend() {
    
    global $comment;
    echo $this->add_frontend( false, false, $comment );
  }
  
  
  function update_report_status( $comment_id, $status ) {
    global $wpdb;

    $current_user = wp_get_current_user();

    if( $status != 'spam' && $status != 'trash' ) {
      return;
    }

    $wpdb->update(
       $wpdb->prefix.'commentreports_fvtc',
      array(
        'status'      => 'deleted',
        'mod_uid'     => $current_user->ID,
        'mod_date'    => date("Y-m-d H:i:s")
      ),
      array(
        'comment_id'  => $comment_id
      )
    );
  }  
  
  
}

$fvcrep = new FV_Comments_Reporting;
