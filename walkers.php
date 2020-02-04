<?php

/**
  * Sets special actions for first and last comment
  */
class FV_TC_Walker_Comment_capture extends Walker_Comment {

  /**
   * Count the comments being displayed
   * @int
   */ 
  var $count = 0;
  
  /**
   * Store comment output start time
   * @float
   */   
  var $tStart = false;
  
	public function start_el( &$output, $comment, $depth = 0, $args = array(), $id = 0 ) {
    if( $this->count == 0 ) {
      $this->tStart = microtime(true);
      $output .= "<!--fv comments cache - START-->\n";   
    }
    
    $this->count++;
        
		$depth++;
		$GLOBALS['comment_depth'] = $depth;
		$GLOBALS['comment'] = $comment;

		if ( !empty( $args['callback'] ) ) {
			ob_start();
			call_user_func( $args['callback'], $comment, $args, $depth );
			$output .= ob_get_clean();
			return;
		}

		if ( ( 'pingback' == $comment->comment_type || 'trackback' == $comment->comment_type ) && $args['short_ping'] ) {
			ob_start();
			$this->ping( $comment, $depth, $args );
			$output .= ob_get_clean();
		} elseif ( 'html5' === $args['format'] ) {
			ob_start();
			$this->html5_comment( $comment, $depth, $args );
			$output .= ob_get_clean();
		} else {
			ob_start();
			$this->comment( $comment, $depth, $args );
			$output .= ob_get_clean();
		}
	}

	public function end_el( &$output, $comment, $depth = 0, $args = array() ) {
		if ( !empty( $args['end-callback'] ) ) {
			ob_start();
			call_user_func( $args['end-callback'], $comment, $args, $depth );
			$output .= ob_get_clean();

		} else {
      if ( 'div' == $args['style'] )
        $output .= "</div><!-- #comment-## -->\n";
      else
        $output .= "</li><!-- #comment-## -->\n";
    }
    
        
    global $wp_query;
    //$output .= "<!--fv comments cache - DEBUG ".$this->count." ".$wp_query->query_vars['comments_per_page']." remainder ".($wp_query->queried_object->comment_count % $wp_query->query_vars['comments_per_page'])."-->\n";
    if(
      $this->count == $wp_query->queried_object->comment_count ||
      $this->count == $wp_query->query_vars['comments_per_page'] ||
        ( $this->count == $wp_query->queried_object->comment_count % $wp_query->query_vars['comments_per_page'] &&
          $wp_query->query_vars['cpage'] == ceil($wp_query->queried_object->comment_count/$wp_query->query_vars['comments_per_page'])
        )
      ) {
      global $fv_tc;
      
      $output .= "<!--fv comments cache - END-->\n";
      
      echo "<!--fv comments cache - WP took ".(microtime(true)-$this->tStart)." seconds to list comments!-->\n";
      
      $aCache = $fv_tc->cache_data;
      $aCache['html'] = $output;
      $aCache['date'] = date( 'U' );
      $aCache['comments'] = $fv_tc->cache_comment_count;
        
      if( !is_user_logged_in() && !$fv_tc->cache_comment_author && !isset( $_COOKIE['fv-debug'] ) ) {        
        $res = file_put_contents( WP_CONTENT_DIR.'/'.$fv_tc->cache_filename, serialize( $aCache ) );
        if( !$res ) {
          echo "<!--fv comments error writing into $fv_tc->cache_filename -->\n";
        } else {
          echo "<!--fv comments cache - stored $fv_tc->cache_filename @ ".$aCache['date']."-->\n";
        }
      } else {
        echo "<!--fv comments do not cache into $fv_tc->cache_filename @ ".$aCache['date']." - user logged in or comment author-->\n";
      }         
    }
     
	}  
  
} //  FV_TC_Walker_Comment_capture


/**
  * Discards any output from wp_list_comments()
  */ 
class FV_TC_Walker_Comment_blank extends Walker_Comment {

	public function start_lvl( &$output, $depth = 0, $args = array() ) {}

	public function end_lvl( &$output, $depth = 0, $args = array() ) {}

	public function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {}

	public function end_el( &$output, $object, $depth = 0, $args = array() ) {}

	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {

	}

	public function walk( $elements, $max_depth, ...$args) {

		$args = array_slice(func_get_args(), 2);
		$output = '';

		if ($max_depth < -1) //invalid parameter
			return $output;

		if (empty($elements)) //nothing to walk
			return $output;

		$parent_field = $this->db_fields['parent'];

		// flat display
		if ( -1 == $max_depth ) {
			$empty_array = array();
			foreach ( $elements as $e )
				$this->display_element( $e, $empty_array, 1, 0, $args, $output );
			return $output;
		}

		/*
		 * Need to display in hierarchical order.
		 * Separate elements into two buckets: top level and children elements.
		 * Children_elements is two dimensional array, eg.
		 * Children_elements[10][] contains all sub-elements whose parent is 10.
		 */
		$top_level_elements = array();
		$children_elements  = array();
		foreach ( $elements as $e) {
			if ( 0 == $e->$parent_field )
				$top_level_elements[] = $e;
			else
				$children_elements[ $e->$parent_field ][] = $e;
		}

		/*
		 * When none of the elements is top level.
		 * Assume the first one must be root of the sub elements.
		 */
		if ( empty($top_level_elements) ) {

			$first = array_slice( $elements, 0, 1 );
			$root = $first[0];

			$top_level_elements = array();
			$children_elements  = array();
			foreach ( $elements as $e) {
				if ( $root->$parent_field == $e->$parent_field )
					$top_level_elements[] = $e;
				else
					$children_elements[ $e->$parent_field ][] = $e;
			}
		}

		foreach ( $top_level_elements as $e )
			$this->display_element( $e, $children_elements, $max_depth, 0, $args, $output );

		/*
		 * If we are displaying all levels, and remaining children_elements is not empty,
		 * then we got orphans, which should be displayed regardless.
		 */
		if ( ( $max_depth == 0 ) && count( $children_elements ) > 0 ) {
			$empty_array = array();
			foreach ( $children_elements as $orphans )
				foreach( $orphans as $op )
					$this->display_element( $op, $empty_array, 1, 0, $args, $output );
		 }

		 return $output;
	}

	public function paged_walk( $elements, $max_depth, $page_num, $per_page, ...$args  ) {

		/* sanity check */
		if ( empty($elements) || $max_depth < -1 )
			return '';

		$args = array_slice( func_get_args(), 4 );
		$output = '';

		$parent_field = $this->db_fields['parent'];

		$count = -1;
		if ( -1 == $max_depth )
			$total_top = count( $elements );
		if ( $page_num < 1 || $per_page < 0  ) {
			// No paging
			$paging = false;
			$start = 0;
			if ( -1 == $max_depth )
				$end = $total_top;
			$this->max_pages = 1;
		} else {
			$paging = true;
			$start = ( (int)$page_num - 1 ) * (int)$per_page;
			$end   = $start + $per_page;
			if ( -1 == $max_depth )
				$this->max_pages = ceil($total_top / $per_page);
		}

		// flat display
		if ( -1 == $max_depth ) {
			if ( !empty($args[0]['reverse_top_level']) ) {
				$elements = array_reverse( $elements );
				$oldstart = $start;
				$start = $total_top - $end;
				$end = $total_top - $oldstart;
			}

			$empty_array = array();
			foreach ( $elements as $e ) {
				$count++;
				if ( $count < $start )
					continue;
				if ( $count >= $end )
					break;
				$this->display_element( $e, $empty_array, 1, 0, $args, $output );
			}
			return $output;
		}

		/*
		 * Separate elements into two buckets: top level and children elements.
		 * Children_elements is two dimensional array, e.g.
		 * $children_elements[10][] contains all sub-elements whose parent is 10.
		 */
		$top_level_elements = array();
		$children_elements  = array();
		foreach ( $elements as $e) {
			if ( 0 == $e->$parent_field )
				$top_level_elements[] = $e;
			else
				$children_elements[ $e->$parent_field ][] = $e;
		}

		$total_top = count( $top_level_elements );
		if ( $paging )
			$this->max_pages = ceil($total_top / $per_page);
		else
			$end = $total_top;

		if ( !empty($args[0]['reverse_top_level']) ) {
			$top_level_elements = array_reverse( $top_level_elements );
			$oldstart = $start;
			$start = $total_top - $end;
			$end = $total_top - $oldstart;
		}
		if ( !empty($args[0]['reverse_children']) ) {
			foreach ( $children_elements as $parent => $children )
				$children_elements[$parent] = array_reverse( $children );
		}

		foreach ( $top_level_elements as $e ) {
			$count++;

			// For the last page, need to unset earlier children in order to keep track of orphans.
			if ( $end >= $total_top && $count < $start )
					$this->unset_children( $e, $children_elements );

			if ( $count < $start )
				continue;

			if ( $count >= $end )
				break;

			$this->display_element( $e, $children_elements, $max_depth, 0, $args, $output );
		}

		if ( $end >= $total_top && count( $children_elements ) > 0 ) {
			$empty_array = array();
			foreach ( $children_elements as $orphans )
				foreach( $orphans as $op )
					$this->display_element( $op, $empty_array, 1, 0, $args, $output );
		}

		return $output;
	}

	public function get_number_of_root_elements( $elements ){

		$num = 0;
		$parent_field = $this->db_fields['parent'];

		foreach ( $elements as $e) {
			if ( 0 == $e->$parent_field )
				$num++;
		}
		return $num;
	}

	// Unset all the children for a given top level element.
	public function unset_children( $e, &$children_elements ){

		if ( !$e || !$children_elements )
			return;

		$id_field = $this->db_fields['id'];
		$id = $e->$id_field;

		if ( !empty($children_elements[$id]) && is_array($children_elements[$id]) )
			foreach ( (array) $children_elements[$id] as $child )
				$this->unset_children( $child, $children_elements );

		if ( isset($children_elements[$id]) )
			unset( $children_elements[$id] );

	}

} // FV_TC_Walker_Comment_blank
