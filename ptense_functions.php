<?php


function ptense_get_all_settings(){

  $settings = array(
    'ptense_api_key_valid' => null,
    'perfect_tense_api_key' => null,
    'perfect_tense_app_key' => null,
    'ptense_comment_mark_spam' => null,
    'ptense_comment_min_score' => null,
    'ptense_post_check_score' => null,
    'ptense_save_post' => null,
    'ptense_save_post_draft_score' => null,
    'ptense_save_post_correct_score' => null,
  );

  $settings['ptense_api_key_valid'] = get_option('ptense_api_key_valid');

  if($settings['ptense_api_key_valid'] == 'yes'){
    $settings['perfect_tense_api_key']      = get_option('perfect_tense_api_key');
    $settings['perfect_tense_app_key']      = get_option('perfect_tense_app_key');
    $settings['ptense_comment_mark_spam']       = get_option('ptense_comment_mark_spam');
    $settings['ptense_comment_min_score']       = get_option('ptense_comment_min_score');
    $settings['ptense_post_check_score']        = get_option('ptense_post_check_score');
    $settings['ptense_save_post']               = get_option('ptense_save_post');
    $settings['ptense_save_post_draft_score']   = get_option('ptense_save_post_draft_score');
    $settings['ptense_save_post_correct_score'] = get_option('ptense_save_post_correct_score');
  }
  return $settings;

}


function ptense_get_pt_client($app_key)
{
  global $ptClient;

  if (!$ptClient) {
    $ptClient = new PTClient(array( "appKey"=>$app_key ) );
  }

  return $ptClient;
}

function ptense_get_usage($api_key, $app_key)
{

  $ptClient = ptense_get_pt_client($app_key);

  $usage = $ptClient->getUsage($api_key);

  if( isset( $usage['apiRemainToday'] ) )
  {
    return $usage['apiRemainToday'];
  }
  return 0;

}

function ptense_check_api_key_status($api_key, $app_key){
  $ptClient = ptense_get_pt_client($app_key);
  // Fetch usage statistics


  // print_r($usage);
  // Number of API requests this user has remaining today
  // $numReqRemaining = $usage['apiRemainToday'];
  return true;
}

function ptense_get_app_key($api_key)
{
  if(!$api_key)
    return false;
  $response = ptense_generate_app_key(
  	$api_key,
    "WordPress Plugin ".rand(1,100),
    "This is a WordPress Plugin that automatically corrects blog posts",
    "",
    home_url()
  );
   //print_r( $response );
  if(isset($response['key']))
    return $response['key'];
  return false;
}


function ptense_get_grammar_score($api_key, $app_key, $text){


    $text = str_replace("&nbsp;", " ", $text);


    $ptClient = ptense_get_pt_client($app_key);

    $result = $ptClient->submitJob($text, $api_key, null, null);
    // error_log("py 66");
    if(!$result)
    return -1;
    
    // error_log("py 88");
    // error_log($api_key);
    // error_log($text);
    // error_log($app_key);
    
    $intEditor = new PTInteractiveEditor(
    array(
      'ptClient' => $ptClient,
      	'data' => $result,
      	'apiKey' => $api_key,
      	'ignoreNoReplacement' => true // ignore pure suggestions (This sentence is a fragment, etc.)
      )
    );
    // error_log("py 97");
    if(!$intEditor)
    return -1;
    // error_log("py 100");
    $grammar_score = $intEditor->getGrammarScore();
    // error_log("py 103");
    // error_log($grammer_score);
    return $grammar_score;

}

function ptense_fix_all_grammar_mistakes($api_key,$app_key, $text = false)
{
    
  $text = str_replace("&nbsp;", " ", $text);
  
  $ptClient = ptense_get_pt_client($app_key);

  $result = $ptClient->submitJob( $text , $api_key, null, null);
  if(!$result)
    return -1;
  $intEditor = new PTInteractiveEditor(
    array(
      'ptClient' => $ptClient,
  		'data' => $result,
  		'apiKey' => $api_key,
  		'ignoreNoReplacement' => True // ignore pure suggestions (This sentence is a fragment, etc.)
  	)
  );

  if(!$intEditor)
    return -1;
  $intEditor->applyAll();
  return $currentText = $intEditor->getCurrentText();


}

// Apply selected_indexes (indices of transformations)
/* Note: now done client-side
function ptense_get_job_fixes($api_key, $app_key, $text = false, $selected_indexes = false)
{
    if(!$text)
      return;
      
    $text = str_replace("&nbsp;", " ", $text);

    $ptClient = ptense_get_pt_client($app_key);

    // Allow cached interactive editor object for single post

    $result = $ptClient->submitJob($text, $api_key, null, null);

    if(!$result)
      return -1;

    $intEditor = new PTInteractiveEditor(
      array(
        'ptClient' => $ptClient,
        'data' => $result,
        'apiKey' => $api_key,
        'ignoreNoReplacement' => true // ignore pure suggestions (This sentence is a fragment, etc.)
      )
    );

    if(!$intEditor)
      return -1;

    foreach($selected_indexes as $idx)
    {
      $nxt = $intEditor->getTransform($idx);
      $intEditor->acceptCorrection($nxt);

    }
    $currentText = $intEditor->getCurrentText();
    return $currentText. $grammarScore;
}*/


function ptense_get_available_transforms($intEditor) {
  //print_r($intEditor);

  $ptClient = ptense_get_pt_client($app_key);

  $transforms = array();
  
  $availableTransforms = $intEditor->getAvailableTransforms();
  
  for ($i = 0; $i < count($availableTransforms); $i++) {
      $nextAvailable = $availableTransforms[$i];
     // echo "Affected of next available: " . $intEditor->getAffectedText($nextAvailable) . "<br>";
     // echo $intEditor->getTransformDocumentOffset($nextAvailable) . "<br />";
      $nextAvailable['off'] = $intEditor->getTransformOffset($nextAvailable);
      $nextAvailable['getAddedText'] = $intEditor->getAddedText($nextAvailable);
      $nextAvailable['getAffectedText'] = $intEditor->getAffectedText($nextAvailable);

      $transformSent = $intEditor->getSentenceFromTransform($nextAvailable);
      $nextAvailable['currentSentText'] = $ptClient->getCurrentSentenceText($transformSent);
      $nextAvailable['documentOffset'] = $intEditor->getTransformDocumentOffset($nextAvailable);

      $transforms[] = $nextAvailable;
  } 
  return $transforms;
}

function ptense_get_job($api_key,$app_key, $text = false)
{
  if(!$text)
    return;
    
  $text = str_replace("&nbsp;", " ", $text);
  $ptClient = ptense_get_pt_client($app_key);

  $result = $ptClient->submitJob($text, $api_key, null, null);

  if(!$result)
    return -1;


  $intEditor = new PTInteractiveEditor(
    array(
      'ptClient' => $ptClient,
  		'data' => $result,
  		'apiKey' => $api_key,
  		'ignoreNoReplacement' => true // ignore pure suggestions (This sentence is a fragment, etc.)
  	)
  );

  if(!$intEditor)
    return -1;

  return $intEditor;
}

function ptense_clear_post_score($pid, $score = false) {
  delete_post_meta($pid, 'ptense_score');
}

function ptense_update_proofread_date($pid) {
  update_post_meta($pid, 'ptense_score_last_checked', current_time('mysql') );
}

function ptense_update_post_score($pid, $score){
  update_post_meta($pid, 'ptense_score', $score);
  
  ptense_update_proofread_date($pid);
}

function ptense_update_comment_score($pid, $score){
  update_comment_meta($cid, 'ptense_score', $score);
  update_comment_meta($cid, 'ptense_score_last_checked', current_time('mysql') );

}

function ptense_debug_string($str) {
  $with_new_line = $str . "\n";
  file_put_contents('php://stderr', print_r($with_new_line, TRUE));
}
