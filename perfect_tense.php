<?php
/*
Plugin Name: Perfect Tense Plugin
Version: 1.0.1
Author: Perfect Tense
Author URI: https://www.perfecttense.com
Copyright: Perfect Tense
Description: Perfect Tense is an AI-powered, spelling and grammar corrector. Perfect Tense will automatically detect and fix mistakes, proofread entire blog posts, and even block low-quality posts and comments.
Text Domain: perfect_tense
*/

// error_log(wp_cron()) ;

// echo( wp_next_scheduled('ptense_daily_event') );
add_action( 'admin_menu',          'ptense_register_admin_page' );
add_filter( 'plugin_action_links', 'ptense_plugin_setting_link', 10, 4 );
add_filter( 'plugin_row_meta',     'ptense_plugin_setting_link', 10, 4 );
add_action( 'admin_notices',       'ptense_admin_notice'               );
add_action( 'admin_enqueue_scripts', 'ptense_admin_scripts' );

require_once('perfect_tense_sdk.php');
require_once('ptense_functions.php');
require_once('background_processing/wp-background-processing.php');
add_action( 'init',  'ptense_setup_schedule'  );


add_action( 'ptense_daily_event', 'ptense_do_this_daily' );
add_action( 'plugins_loaded', 'ptense_handle_handler' );


function ptense_handle_handler(){
    $ptense_job_req = new PTENSE_JOB_REQUEST();
}

//
// function ptense_process_handler(){
//  if ( ! isset( $_GET['process'] ) || ! isset( $_GET['_wpnonce'] ) ) {
//    return;
//  }
//  if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'process') ) {
//    return;
//  }
//
//  if ( 'all' === $_GET['process'] ) {
//    $this->handle_all();
//  }
// }


class PTENSE_JOB_REQUEST extends WP_Async_Request {

  /**
   * @var string
   */
  protected $action = 'ptense_job_perform';

  /**
   * Handle
   *
   * Override this method to perform any actions required
   * during the async request.
   */
  protected function handle() {
    // Actions to perform
      // error_log( 'STATING HANDLE - VASU' );

    // error_reporting(1);
    global $ptense_settings;
    $usage = ptense_get_usage($ptense_settings['perfect_tense_api_key'],  $ptense_settings['perfect_tense_app_key']);
    if($usage  <= 0 )
      return;
      // error_log( 'USAGE IS - VASU '.$usage );
    $job_list = get_option('ptense_jobs');
    if(count($job_list) < 1)
      return;
     // error_log( 'JOBS COUNT  IS - VASU '.count($job_list) );
    $i = 0;
    $current_job = PTENSE_JOB::build_job( get_option($job_list[$i]) );
// error_log("JOB START WHILE");
    while($usage || $i < count($job_list))
    {
       // error_log('into job while');
      if( $current_job->get_pending_count() < 1 )
      {
           // error_log( 'this job ended, fetching next job');
        $i++;
        if(!isset($job_list[$i]))
          break;
        $current_job = PTENSE_JOB::build_job( get_option($job_list[$i]) );
      }
       // error_log('lets do it');
      // we have current_job lets do it
      $type = $current_job->get_type();

      $pid = $current_job->get_next_id();
    if(!$pid)
      continue;
        // error_log('---------');
        // error_log('got pid' .$pid);
        // error_log('type is ' .$type);

      $success =  process_id( $type, $pid);
      
      
      // error_log("status is ".$status);
      
      $current_job->doItem($pid, $success);
      $current_job->save_job();
       // error_log( 'job updated');
      $usage--;
    }
       // error_log( 'OUT OF LOOP');
  }

}

// register_activation_hook(__FILE__, 'ptense_setup_schedule');
register_deactivation_hook(__FILE__, 'ptense_deactivate_schedule');

function ptense_deactivate_schedule() {
  wp_clear_scheduled_hook('ptense_daily_event');
}
function ptense_setup_schedule() {
    if ( ! wp_next_scheduled( 'ptense_daily_event' ) ) {
        wp_schedule_event( time(), 'daily', 'ptense_daily_event');
    }
}
function ptense_do_this_daily() {
  // error_log( 'DISPATCHIN VASU');
  $req =  new PTENSE_JOB_REQUEST();
  $a = $req->dispatch();
  // error_log( print_r($a, true) );
  // error_log( 'DISPATCH END VASU');
}


global $ptense_settings;
if(!$ptense_settings){
  $ptense_settings = ptense_get_all_settings();
}

// print_r($ptense_settings);
require_once('ptense_functionality_1.php');
require_once('ptense_functionality_2.php');
require_once('ptense_functionality_3.php');

if(!function_exists('ptense_register_admin_page'))
{
  function ptense_register_admin_page(){
    $bulb_image_path = plugin_dir_url( __FILE__ ) . 'images/PTENSE_bulb_small_no_bg.png';
    add_menu_page('Perfect Tense', 'Perfect Tense', 'manage_options', 'perfect_tense_settings', '', $bulb_image_path);
    add_submenu_page( 'perfect_tense_settings', 'Perfect Tense Settings', 'Settings','manage_options','perfect_tense_settings', 'perfect_tense_admin_render');
    add_submenu_page( 'perfect_tense_settings', 'Perfect Tense Jobs', 'Jobs', 'manage_options', 'perfect_tense_jobs', 'perfect_tense_jobs_render');

  }

}


if(!function_exists('ptense_admin_notice'))
{
  function ptense_admin_notice() {

    if( get_transient( 'ptense_hide_notice' ) )
      return;

    if(isset($_GET['page']) && $_GET['page'] == 'perfect_tense_settings')
      return;

    $ptense_api_key       = get_option('perfect_tense_api_key');
    $ptense_api_key_valid = get_option('ptense_api_key_valid');

    if(!$ptense_api_key ){

      ?>
      <div class="notice notice-error is-dismissible">
        <p><?php _e( 'Perfect Tense : You have not yet provided the perfect tense api key. Go to Perect-Tense Settings page to update it.', 'perfect_tense' ); ?></p>
      </div>
      <?php

    }elseif($ptense_api_key_valid == 'no')
    {
      ?>

      <div class="notice notice-error is-dismissible">
        <p><?php _e( 'Perfect Tense : The Api Key you provided is not Valid. Go to Perect-Tense Settings page to update it.', 'perfect_tense' ); ?></p>
      </div>

      <?php
    }
  }
}

if(!function_exists('ptense_showSt'))
{
  function ptense_showSt($pending, $done)
  {
    $total = $pending+$done;

    $done = ($done/$total)*100;
    if($done == 0)
    $done = 2;
    return '<div class="prg">
    <div class="prg_st" style="width:'.$done.'%"></div>
    </div>';
  }
}

if(!function_exists('perfect_tense_jobs_render'))
{
  function perfect_tense_jobs_render(){
    if(!current_user_can('manage_options')){ wp_die('You do not have sufficient permissions to access this page.');}
    echo '<div class="wrap"><h2>Perfect Tense Jobs</h2><br />';

    $job_list = get_option('ptense_jobs');

    if (is_null($job_list) || !$job_list) {
      $job_list = array();
    }
    
    $job_list = array_reverse($job_list);
    // echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';
    echo '<table class="wp-list-table widefat fixed striped posts"><thead>
      <th  class="manage-column column-author">Manage</th>
      <th  class="manage-column column-author">Job ID</th>
      <th  class="manage-column column-author">Job Type</th>
      <th  class="manage-column column-author">Pending Posts</th>
      <th  class="manage-column column-author">Completed Posts</th>
      <th  class="manage-column column-author">Errored Posts</th>
      <th  class="manage-column column-author">Status</th>
    </thead><tbody>';
    foreach($job_list as $job)
    {
      $job = get_option($job);

      $manage_html = "";

      ?>
      <script>
      function deletePTJob(jobId) {

        //console.log("in deletePTJob! " + jobId);
        jQuery.post(ajaxurl, {action: 'ptense_delete_job', job_id: jobId}, function(response) {
          //console.log(`Job ${jobId} deleted!`);
          location.reload();
        })
      }
      </script>
      <?php

      // show nothing until job is done
      if (count($job['pending_ids']) <= 0) {
        $manage_html = '<button class="button" onclick="deletePTJob(\''.$job['id'].'\')">Delete</button>';
      }

      // print_r($job);
      echo '<tr>
        <th>'.$manage_html.'</th>
        <th>'.$job['id'].'</th>
        <th>'.ucwords(str_replace("_", " ", $job['type'])).'</th>
        <th>'.count($job['pending_ids']).'</th>
        <th>'.count($job['done_ids']).'</th>
        <th>'.count($job['errored_ids']).'</th>
        <th>'.((count($job['pending_ids']) < 1)?'Complete':ptense_showSt(count($job['pending_ids']), count($job['done_ids']) )).'</th>
      </tr>';
    }
    echo '</tbody></table>';
    echo '</div><style>.prg {width: 100%;background: lightgrey;border-radius: 3px;height: 5px;}
    .prg_st {background: blue;height: 5px;}';
  }
}

if(!function_exists('perfect_tense_admin_render'))
{
  function perfect_tense_admin_render(){
    if(!current_user_can('manage_options')){ wp_die('You do not have sufficient permissions to access this page.');}
    echo '<div class="wrap">';
    $msg_shown = false;

    $perfect_tense_api_key = get_option('perfect_tense_api_key');

    if(isset($_POST['action']) && $_POST['action'] == 'update')
    {
      //Security check
      $retrieved_nonce = $_REQUEST['_wpnonce'];
      if (!wp_verify_nonce($retrieved_nonce, 'ptense_settings_change' ) ) die( 'Failed security check' );

      $new_api_key = $_POST['perfect_tense_api_key'];
      if( $new_api_key != $perfect_tense_api_key)
      {
          // check if api key is valid
          $app_key = ptense_get_app_key($new_api_key);
          if($app_key){
              $key_is_valid = true;
              $perfect_tense_app_key = $app_key;
              update_option('perfect_tense_app_key', $app_key);
              update_option('perfect_tense_api_key', $new_api_key);
              update_option('ptense_api_key_valid', 'yes');
              set_transient( 'ptense_hide_notice', true, 5 );
               
              
              
          }
          elseif($perfect_tense_api_key != ''){
              $new_api_key = $perfect_tense_api_key;
              // dont change api key, or app key, keep it same.
              echo '<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
              <p><strong>Error: The new API Key you provided is not valid.</strong></p></div>';
              $msg_shown = true;
          }
          else{
            echo '<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
              <p><strong>Error: The API Key you provided is not valid. You can find your API key in your <a href="https://app.perfecttense.com/home" target="_blank">dashboard</a>.</strong></p></div>';
              $msg_shown = true;
          }
          
          
      }
      else{
          // all same
        $prev_app_key = get_option('perfect_tense_app_key');

        if ($prev_app_key && $prev_app_key != "") {
          update_option('ptense_api_key_valid', 'yes'); // make sure this is set
        }
      }
      
      $perfect_tense_api_key = get_option('perfect_tense_api_key');
      
      if($_POST['ptense_save_post'] == 'none')
      {
        $_POST['ptense_save_post'] = false;
      }
      else
      {
        $_POST['ptense_post_check_score'] = "1";
      }

      // update other settings.
      $keys =  array(
        'ptense_comment_mark_spam',
        'ptense_comment_min_score',
        'ptense_post_check_score',
        'ptense_save_post',
        'ptense_save_post_draft_score',
        // 'ptense_save_post_correct',
        //'ptense_save_post_correct_score'
      );
      foreach($keys as $k)
      {
        if(isset($_POST[$k]))
          update_option($k, $_POST[$k]);
        else
          update_option($k, 0);
      }
      if(!$msg_shown)
      echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
              <p><strong>Settings saved.</strong></p></div>';
    }

    $perfect_tense_app_key      = get_option('perfect_tense_app_key');
    $ptense_comment_mark_spam       = get_option('ptense_comment_mark_spam');
    $ptense_comment_min_score       = get_option('ptense_comment_min_score');
    $ptense_post_check_score        = get_option('ptense_post_check_score');
    $ptense_save_post         = get_option('ptense_save_post');
    $ptense_save_post_draft_score   = get_option('ptense_save_post_draft_score');
    // $ptense_save_post_correct       = get_option('ptense_save_post_correct');
    //$ptense_save_post_correct_score = get_option('ptense_save_post_correct_score');

    if( $ptense_comment_mark_spam  ) $ptense_comment_mark_spam = ' checked="checked" ';
    if( $ptense_post_check_score   ) $ptense_post_check_score  = ' checked="checked" ';
    $ptense_save_post_draft = '';
    $ptense_save_post_correct = '';

    if($ptense_save_post == 'draft')
    {
      $ptense_save_post_draft   = ' checked="checked" ';
      $ptense_save_post_correct = '';
      $ptense_save_post_none = '';
    }elseif( $ptense_save_post == 'correct' ){
      $ptense_save_post_correct = ' checked="checked" ';
      $ptense_save_post_draft = '';
      $ptense_save_post_none = '';
    }elseif( $ptense_save_post == ''){
      $ptense_save_post_correct = '';
      $ptense_save_post_draft = '';
      $ptense_save_post_none = ' checked="checked" ';
    }

    ?>
      <h2><img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/PTENSE_logo_tall_no_bg.png'; ?>" style="width:150px;height:75px;"></h2>
      <?php
            if(!$perfect_tense_api_key)
            {
              ?>
              <p>Welcome to the Perfect Tense WordPress Plugin! This plugin will automatically detect and fix mistakes, proofread entire blog posts, and even block low-quality posts and comments.</p>
              <p>To get started, <a href="https://www.perfecttense.com/?iref=WordPress" target="_blank"><b>click here to create your free Perfect Tense trial!</b></a></p>
              <?php
            }
            ?>
      <form method="post" action="admin.php?page=perfect_tense_settings"    >
         <input type="hidden" name="action" value="update">

        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row">
                <label for="perfect_tense_api_key">Perfect Tense API Key</label>
              </th>
              <td>
                <input name="perfect_tense_api_key" required type="text" id="perfect_tense_api_key" value="<?php echo $perfect_tense_api_key; ?>" class="regular-text">
                <p class="description">Find your API Key on your Perfect Tense <a href="https://app.perfecttense.com/home" target="_blank">dashboard</a>.</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="perfect_tense_app_key">Perfect Tense App Key</label>
              </th>
              <td>
                <input name="perfect_tense_app_key" readonly type="text" id="perfect_tense_app_key" value="<?php echo $perfect_tense_app_key; ?>" class="regular-text">
                <p class="description" id="tagline-description">This is automatically updated when you enter a new API key. Manage your App Keys <a href="https://app.perfecttense.com/api" target="_blank">here</a>.</p>
              </td>
            </tr>
            <?php
            if($perfect_tense_api_key != '')
            {
              ?>
            <tr>
              <th scope="row" colspan="2">
                <hr />
              </th>
            </tr>
            <tr>
              <th scope="row">Comments</th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text"><span>Comments</span></legend>
                  <label for="ptense_comment_mark_spam">
                    <input name="ptense_comment_mark_spam" type="checkbox" id="ptense_comment_mark_spam" value="1" <?php echo $ptense_comment_mark_spam;?> >
                    Automatically mark new comments as spam with a grammar score of <input name="ptense_comment_min_score" type="number" min="0" max="100" id="ptense_comment_min_score" value="<?php echo $ptense_comment_min_score; ?>" class="small-text"> or lower.
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr>
              <th scope="row" colspan="2">
                <hr />
              </th>
            </tr>
            <tr>
              <th scope="row">When a new post is published</th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text"><span>Check Posts</span></legend>
                  <label for="ptense_save_post_none">
                    <input type="radio" name="ptense_save_post"  id="ptense_save_post_none" value="none" <?php echo $ptense_save_post_none; ?> >
                    Do nothing
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr>
              <th scope="row">
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text"><span>Save post as draft</span></legend>
                  <label for="ptense_save_post_draft">
                    <input type="radio" name="ptense_save_post"  id="ptense_save_post_draft" value="draft" <?php echo $ptense_save_post_draft; ?> >
                    Move new posts to draft and require admin approval if they have a grammar score of <input name="ptense_save_post_draft_score" type="number" min="0" max="100" id="ptense_save_post_draft_score" value="<?php echo $ptense_save_post_draft_score; ?>" class="small-text"> or lower
                  </label>
                </fieldset>
              </td>
            </tr>
              <th scope="row">
              </th>
              <td>
                <fieldset>
                  <legend class="screen-reader-text"><span>Correct the post.</span></legend>
                  <label for="ptense_save_post_correct">
                    <input name="ptense_save_post" type="radio" id="ptense_save_post_correct" value="correct" <?php echo $ptense_save_post_correct; ?> >
                    Proofread and correct new posts before publishing.
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr>
              <th scope="row">
              </th>
              <td>
                <p class="description">NOTE: This feature will only trigger when a post is set to be published.</p>

              </td>
            </tr>
            <?php
          }
          ?>
          </tbody>
        </table>
        <?php wp_nonce_field('ptense_settings_change'); ?>
        <p class="submit">
          <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
      </form>
    </div>
    <?php
  }
}

if(!function_exists('ptense_plugin_setting_link')){
function ptense_plugin_setting_link( $links, $plugin ) {
  if($plugin == plugin_basename(__FILE__))
    {
      $links['settings'] = '<a href="'.admin_url( 'admin.php?page=perfect_tense_settings' ).'">Settings</a>';
    }
    return $links;
  }
}




function ptense_admin_scripts(){
  wp_enqueue_script( 'jquery-ui-dialog' );
  wp_enqueue_style( 'wp-jquery-ui-dialog' );
}
