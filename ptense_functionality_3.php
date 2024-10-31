<?php

global $ptense_settings;

 //print_r($ptense_settings);

if( $ptense_settings['ptense_api_key_valid'] =='yes' && $ptense_settings['ptense_comment_mark_spam']){
  add_filter( 'pre_comment_approved', 'ptense_check_comment', 10, 2 );
}




if( $ptense_settings['ptense_api_key_valid'] =='yes' && $ptense_settings['ptense_post_check_score'] && ( $ptense_settings['ptense_save_post'] == "draft" || $ptense_settings['ptense_save_post'] == "correct"  ) ) {
 // add_filter("save_post", "ptense_check_post", 10, 3 );
  add_action(  'publish_post',  'ptense_check_post_inner', 10, 2 );

}


function ptense_check_comment( $approved, $commentdata ) {
    
  if( ! $commentdata['comment_content'] )
    return $approved;

  if( "spam" === $approved  || "trash" === $approved ){
    return $approved;
  }

  global $ptense_settings;

  if($k =  ptense_get_usage($ptense_settings['perfect_tense_api_key'],  $ptense_settings['perfect_tense_app_key']) <= 0 )
  {
  
    return $approved;
  }

  $grammar_score = ptense_get_grammar_score($ptense_settings['perfect_tense_api_key'], $ptense_settings['perfect_tense_app_key'], $commentdata['comment_content']);

  if($grammar_score <  0)
  {
    // api didnt run, some error occoured.
    return $approved;
  }

  if( floatval($grammar_score) <= floatval($ptense_settings['ptense_comment_min_score']) )
  {
    // meets the spam criteria
    $approved = 'spam';
    ptense_update_comment_score($commentdata['ID'], $grammar_score);
  }

  return $approved;

}

//add_action( 'wp_ajax_ptense_apply_fixes', 'ptense_apply_fixes_render_ajax' );

add_action( 'wp_ajax_ptense_force_publish', 'ptense_force_publish_ajax');

function ptense_force_publish_ajax() {

  $post_id = $_POST['post_id'];
  //ptense_debug_string("In PHP force publish");
  //ptense_debug_string("Current Post id: " . $post_id);

  // temporarily unregister so we don't trigger
  remove_action( 'publish_post', 'ptense_check_post_inner' );
  wp_publish_post($post_id);
  wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
  // do_action("post_action_publish", $post_id);
  add_action( 'publish_post', 'ptense_check_post_inner' );
}

add_action('post_updated_messages', 'ptense_update_messages_hook');

/*
  Executed when a post is published
*/
function ptense_check_post_inner( $post_id, $post) {
    //print_r($post);
    //wp_die('s');
  //ptense_debug_string("In ptense_check_post_inner");
  if($post->post_status == 'auto-draft') return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if ( "post" != $post->post_type ) return;
  if ( wp_is_post_revision( $post_id ) ) return;
   $is_new = ($post->post_date === $post->post_modified);
  if(!$is_new && $post->post_status !='publish')
  {
      
      print_r($post);
      wp_die();
    // Is old, and not a new post, means someone tried to edit the post.
    // lets save score only
    global $ptense_settings;
    $pid          = $post->ID;
    $post_content = $post->post_content;
    return;
    if( ptense_get_usage($ptense_settings['perfect_tense_api_key'],  $ptense_settings['perfect_tense_app_key']) <= 0 )
      return;
    $grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $post_content
    );
    if($grammar_score <  0)
      return;
    ptense_update_post_score($pid, $grammar_score);
  }
  
 


  global $ptense_settings;


  $pid          = $post->ID;
  $post_content = $post->post_content;

  // If it's set to save posts to draft when below score
  if( $ptense_settings['ptense_save_post'] =='draft' && $ptense_settings['ptense_save_post_draft_score'] )
  {

    if( ptense_get_usage($ptense_settings['perfect_tense_api_key'],  $ptense_settings['perfect_tense_app_key']) <= 0 )
      return;


    $grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $post_content
    );

    if($grammar_score <  0)
      return;

    // Update the score on the All Posts page since we have it
    ptense_update_post_score($pid, $grammar_score);

    if( floatval($grammar_score) <= floatval($ptense_settings['ptense_save_post_draft_score']) )
    {
      // score is less, lets update it as draft.
      remove_action( 'publish_post', 'ptense_check_post_inner' );
      wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
     	add_action( 'publish_post', 'ptense_check_post_inner' );

      // Update post meta marking that it was blocked. This will signal blocked message with force publish button
      update_post_meta($pid, 'ptense_post_status', 'blocked');
      update_post_meta($pid, 'ptense_post_score', $grammar_score);

    }
    // done. what else ?

  }

  // If it's set to correct posts before publish
  if( $ptense_settings['ptense_save_post'] == 'correct' /* && $ptense_settings['ptense_save_post_correct_score']*/)
  {

    if( ptense_get_usage($ptense_settings['perfect_tense_api_key'],  $ptense_settings['perfect_tense_app_key']) <= 0 )
      return;

    /*$grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $post_content
    );
    if($grammar_score <  0)
      return;

    ptense_update_post_score($pid, $grammar_score);

    if( floatval($grammar_score) <= floatval($ptense_settings['ptense_save_post_correct_score']) )
    {
      // score is less, lets correct stuff.
  */

    // NOTE: Now will always correct. No minimum score option.
    $new_post_content = ptense_fix_all_grammar_mistakes(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $post_content
    );

    remove_action( 'publish_post', 'ptense_check_post_inner' );
    wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_post_content ) );
   	add_action( 'publish_post', 'ptense_check_post_innner' );

    // notify user that we changed something
    if ($new_post_content != $post_content) {
      update_post_meta($pid, 'ptense_post_status', 'corrected');
    }

    $grammar_score = ptense_get_grammar_score(
      $ptense_settings['perfect_tense_api_key'],
      $ptense_settings['perfect_tense_app_key'],
      $new_post_content
    );
    if($grammar_score <  0)
      return;

    ptense_update_post_score($pid, $grammar_score);
    //}
  }
}

/*
  Customize message to user for cases where we blocked or corrected a post before publishing.
*/
function ptense_update_messages_hook($messages) {

  global $post;
  global $ptense_settings;

  $pid = $post->ID;

  $ptense_post_status = get_post_meta($pid, 'ptense_post_status', true);

  switch($ptense_post_status) {

    case 'blocked': // we blocked a publish and saved as draft

      $grammar_score = get_post_meta($pid, 'ptense_post_score', true);
      $grammar_threshold = $ptense_settings['ptense_save_post_draft_score'];

      $messages['post'][6] = "Your post was saved as a draft because its grammar score of <b>".$grammar_score."</b> is below the threshold <b>".$grammar_threshold."</b> <button id='ptense_force_publish' class='button' style='margin-left: 5px'>Publish Anyway</button>";

      break;
    case 'corrected': // we corrected before publishing

      $permalink = get_permalink($post->ID);

      if (!$permalink) {
        //ptense_debug_string("Didn't find permalink for post id " . $post->ID);
        $permalink = '';
      }

      $view_post_link_html = sprintf(
        ' <a href="%1$s">%2$s</a>',
        esc_url( $permalink ),
        __( 'View post' )
      );

      $messages['post'][6] = __( 'Post was corrected by Perfect Tense and published.' ) . $view_post_link_html;

      break;

    case 'clean': // normal message after publish

      $permalink = get_permalink($post->ID);

      if (!$permalink) {
        //ptense_debug_string("Didn't find permalink for post id " . $post->ID);
        $permalink = '';
      }

      $view_post_link_html = sprintf(
        ' <a href="%1$s">%2$s</a>',
        esc_url( $permalink ),
        __( 'View post' )
      );

      $messages['post'][6] = __( 'Post published.' ) . $view_post_link_html;

      break;
  }

  update_post_meta($pid, 'ptense_post_status', 'clean');  

  return $messages;
}