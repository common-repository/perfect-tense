<?php

add_filter( 'bulk_actions-edit-post', 'ptense_register_bulk_action' );
add_filter( 'manage_post_posts_columns', 'ptense_post_column_register' );
// add_filter( 'manage_page_posts_columns', 'ptense_post_column_register' );
add_action( 'manage_post_posts_custom_column' , 'ptense_post_column_render', 10, 2 );
// add_action( 'manage_page_posts_custom_column' , 'ptense_post_column_render', 10, 2 );

add_action( 'admin_notices', 'ptense_bulk_complete_job' );

function ptense_register_bulk_action($bulk_actions) {
  $bulk_actions['correct_grammar'] = __( 'Correct Grammar', 'perfect_tense');
  $bulk_actions['check_grammar_score'] = __( 'Check Grammar Score', 'perfect_tense');
  return $bulk_actions;
}

/*
  Add new columns
*/
function ptense_post_column_register($columns) {
  $columns['grammar_score'] = __( 'Grammar Score', 'perfect_tense' );
  $columns['proof_read_date'] = __( 'Proofread Date', 'perfect_tense' );
  return $columns;
}

/*
  Make new columns sortable
*/
add_filter('manage_edit-post_sortable_columns', 'ptense_post_sortable_columns');

function ptense_post_sortable_columns($columns) {
  $columns['grammar_score'] = 'grammar_score';
  $columns['proof_read_date'] = 'proof_read_date';
  return $columns;
}

// Add the data to the custom columns for the book post type:
function ptense_post_column_render( $column, $post_id ) {
  switch ( $column ) {
    case 'proof_read_date' :
      echo date_i18n( get_option( 'date_format' ) . " " .get_option('time_format'), strtotime(get_post_meta( $post_id , 'ptense_score_last_checked' , true )), false);
      break;

    case 'grammar_score' :
      echo get_post_meta( $post_id , 'ptense_score' , true );
      break;

  }
}


function ptense_bulk_complete_job() {
  if ( ! empty( $_REQUEST['correct_grammar'] ) ) {
    $emailed_count = intval( $_REQUEST['correct_grammar'] );
    printf( '<div id="message" class="updated fade">' .
      _n( '%s post added for "Grammar correction" queue',
        '%s posts added for "Grammar correction" queue',
        $emailed_count,
        'perfect_tense'
      ) . '</div>', $emailed_count );
  }

  if ( ! empty( $_REQUEST['check_grammar_score'] ) ) {
    $emailed_count = intval( $_REQUEST['check_grammar_score'] );
    printf( '<div id="message" class="updated fade">' .
      _n( '%s post added for "check score" queue',
        '%s posts added for "check score" queue',
        $emailed_count,
        'perfect_tense'
      ) . '</div>', $emailed_count );
  }
}

add_action( 'wp_ajax_ptense_delete_job', 'ptense_delete_job_ajax');

function ptense_delete_job_ajax() {

  $job_id = $_POST['job_id'];
  
  //ptense_debug_string("PHP deleting job ".$job_id);
  PTENSE_JOB::delete_job($job_id);
}

class PTENSE_JOB {

  public function init($type, $pending_ids, $done_ids, $errored_ids, $id = false){
    if(!$id)
      $this->id = uniqid();//rand(1,1000);
    else {
      $this->id = $id;
    }
    $this->type = $type;
    $this->pending_ids = $pending_ids;
    $this->done_ids = $done_ids;
    $this->errored_ids = $errored_ids;
  }

  public function doItem($pid, $success = true){
      // error_log( count($this->pending_ids) );
    if (($key = array_search($pid, $this->pending_ids)) !== false) {
      array_splice( $this->pending_ids , $key, 1);

      if (!$success) {
        $this->errored_ids[] = $pid;
      } else {
        $this->done_ids[] = $pid;
      }
      
    }
     // error_log( count($this->pending_ids) );

  }

  public function getJson(){
    return array(
      'id'          => $this->id,
      'type'        => $this->type,
      'pending_ids' => $this->pending_ids,
      'done_ids'    => $this->done_ids,
      'errored_ids' => $this->errored_ids
    );
  }

  public static function build_job($ar){
    $tmp = new PTENSE_JOB();
    $tmp->init($ar['type'], $ar['pending_ids'], $ar['done_ids'], $ar['errored_ids'], $ar['id']);
    return $tmp;
  }

  /*
    Delete job id
  */
  public static function delete_job($job_id) {
    $job_id = 'ptense_job_'.$job_id;

    $job_list = get_option('ptense_jobs');
    $job_key = array_search($job_id, $job_list);

    //ptense_debug_string("Current job list:");
    //ptense_debug_string(print_r($job_list, true));

    // Delete job from job list and remove its own entry
    if ($job_key) {
      //ptense_debug_string("Deleting job id in PHP: ".$job_id);
      
      delete_option($job_id);
      array_splice($job_list , $job_key, 1);
      update_option('ptense_jobs', $job_list);

    } else {
      //ptense_debug_string("Couldn't find job id ".$job_id);
    }
  }

  public function save_job(){
    // error_log('saving...');
    update_option('ptense_job_'.$this->id, $this->getJson() );
  }

  public function get_pending_count(){
    return count($this->pending_ids);
  }
  public function get_type(){
    return $this->type;
  }
  public function get_next_id(){
    return count($this->pending_ids) ? $this->pending_ids[0] : false;
  }

  public static function add_to_queue($job){
    // error_log('add to queue');
    $job_list = get_option('ptense_jobs');
    $job_list[] = 'ptense_job_'.$job->id;
    add_option('ptense_job_'.$job->id, $job->getJson() );
    update_option('ptense_jobs', $job_list);
  }

}





add_filter( 'handle_bulk_actions-edit-post', 'ptense_handle_bulk_action', 10, 3 );

function ptense_handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
  if ( $doaction !== 'correct_grammar'  &&  $doaction !== 'check_grammar_score'  ) {
    return $redirect_to;
  }

  global $ptense_settings;

  $job = new PTENSE_JOB();
  $job->init($doaction, $post_ids, array(), array());
  PTENSE_JOB::add_to_queue($job);
  ptense_do_this_daily();
  $redirect_to = remove_query_arg( 'correct_grammar' );
  $redirect_to = remove_query_arg( 'check_grammar_score' );
  $redirect_to = add_query_arg   ( $doaction, count( $post_ids ), $redirect_to );
  return $redirect_to;


}

function process_id($type, $pid)
{
  // error_log( "PROCESSING _ID " );
  global $ptense_settings;
  // error_log($type);
  if($type == 'check_grammar_score')
  {
    $content_post = get_post($pid);
    if(!$content_post)
      return false;
      // error_log(159);
    $content = $content_post->post_content;
    $c = str_replace("&nbsp;" , "", $content);
    if(trim($c) == '') 
    {
        ptense_update_post_score($pid, 100.0);
        return true; // successful, just no work to do
    }

    $grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $content
    );
    // error_log(165);
    if($grammar_score <  0)
      return false;
    // error_log(169);
    ptense_update_post_score($pid, $grammar_score);
    // error_log(171);

  }else if($type == 'correct_grammar')
  {
    $content_post = get_post($pid);
    if(!$content_post)
      return false;
    // error_log(178);
    $content = $content_post->post_content;
    $c = str_replace("&nbsp;" , "", $content);
    if(trim($c) == '') 
    {
        return true; // succesful, just no work to do
    }
    $new_text = ptense_fix_all_grammar_mistakes(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $content
    );
    // error_log(185);
    if(!$new_text)
      return false;
    $my_post = array(
      'ID'           => $pid,
      'post_content' => $new_text,
    );

    /*ptense_debug_string("Updating post with pid " . $pid);
    ptense_debug_string("Original text:");
    ptense_debug_string($content);
    ptense_debug_string("New text:");
    ptense_debug_string($new_text);*/

    //ptense_debug_string("Disabling action before updating grammar score for bulk correct");

    remove_action( 'publish_post', 'ptense_check_post_inner' );
    wp_update_post( $my_post );
    add_action( 'publish_post', 'ptense_check_post_innner' );

    // clear any out-dated score and update proofread date
    ptense_clear_post_score($pid);
    ptense_update_proofread_date($pid);

    //ptense_debug_string("Successfully posted. Now grammar score.");
    /*
    $grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $new_text
    );
    if($grammar_score <  0)
      return false;
    ptense_update_post_score($pid, $grammar_score);
    */

    //ptense_debug_string("Finished everything!");
  }

  return true;
}
