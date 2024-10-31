<?php

// Make current post id available in javascript as currentPostId
add_action('admin_head','ptense_add_styles_admin');
function ptense_add_styles_admin() {

    global $current_screen;
    $type = $current_screen->post_type;

    if (is_admin() && $type == 'post' || $type == 'page') {
        ?>
        <script type="text/javascript">
        var currentPostId = '<?php global $post; echo $post->ID; ?>';
        </script>
        <?php
    }
}

// "https://wordai.com/try-this.png"
add_action( 'media_buttons', function($editor_id){
    echo '<a href="#" class="button perfect_tense_proof_read ptense_logo">Proofread with </a>';

    ?>


    <style>

        .ptense_logo::after {
            background-image:url(<?php echo plugin_dir_url( __FILE__ ) . 'images/PTENSE_logo_no_bg.png'; ?>);
            content: "";
            float: right;
            width: 65px;
            height: 26px;
            background-size: 63px;
            display: inline-block;
            vertical-align: inherit;
            background-color: transparent;
            background-position: center center;
            background-repeat: no-repeat;
        }

        .ptense_item {
          display: inline-block;
          width: 100%;
          margin-bottom: 10px;
          min-width: 400px;
        }

        .ptense_item div {
          float: left;
          width: 95%;
        }
        .ptense_items {
            max-height: 200px;
            overflow-y: auto;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .ptense_force_publish_button {

        }

        .ptense_bg {
          color: red;
          min-width: 100px;
          display: inline-block;
          text-align: right;
        }
        .ptense_chk {
          margin-left: 5px;
          margin-right: 5px;
          float: right;
        }
        .ptense_sent {

        }
        .ptense_grm_cnt{
          width: 100%;
          margin-bottom: 20px;
          text-align: center;
        }
        .ptense_grm_sc {
            width: 100%;
            margin-top: 20px;
            text-align: center;
        }
        .ui-dialog {
            z-index: 100001 !important;
        }
        .ui-widget-overlay.ui-front {
            z-index: 100000 !important;
        }

        .ptense_high{
          font-weight: bold;
          color:blue;
        }
    </style>
    <!-- The modal / dialog box, hidden somewhere near the footer -->
    <div id="ptense_dialog" class="hidden" style="max-width:800px">

    </div>

    <!-- This script should be enqueued properly in the footer -->
    <script>
      // initalise the dialog
      String.prototype.replaceAt=function(index, replacement) {
          return this.substr(0, index) + replacement+ this.substr(index + replacement.length);
      }
      String.prototype.stripSlashes = function(){
          return this.replace(/\\(.)/mg, "$1");
      }

      String.prototype.newlineToBr = function() {
        return this.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ "<br>" +'$2');
      }

      function get_tinyMce_content(){
        if(tinyMCE.editors.length){
          return tinyMCE.editors[0].save()
        }
        else{
          return jQuery('#'+wpActiveEditor).val();
        }
      }

      function set_tinyMce_content(content){
        if(tinyMCE.editors.length){
          tinyMCE.editors[0].setContent(content);
        }
        else{
            jQuery('#'+wpActiveEditor).val(content);
        }
      }

      function ptense_format_report_url(error) {
        var supportUrlWithParams = "https://www.perfecttense.com/contact";
        supportUrlWithParams += `?subject=${encodeURIComponent("WordPress Error")}`;

        var message

        if (error.stack) {
          message = `Stack trace:\n${error.stack.slice(0, 200)}...`;
        } else {
          message = error.toString().slice(0, 100);
        }

        supportUrlWithParams += `&message=${encodeURIComponent(message)}`;

        return supportUrlWithParams;
      }

      function ptense_handle_error(e) {
        console.log(e)
        jQuery('#ptense_dialog').html(`Oops, it looks like there was an error and we can\'t process your text.<br><br><a href=${ptense_format_report_url(e)} target='_blank'>Contact Support</a>`);
        jQuery('#ptense_dialog').dialog('open');
      }


      var lastJobText = null;
      var lastBugList = null;

      /*
        Display errors found in the current post (triggers from Proofread with Perfect Tense button)
      */
      function doBugList(bug_list, cnt, score)
      {

        var htm = '';
        var original_word;
        var new_word;
        var new_sentence;
        var current_sentence;
        var check_box_index;
        var before_text;
        var off;
        var after_text;

        // short in sentence + offset order
        var sorted_bug_list = bug_list.sort(function(a, b) {
          if (a.sentenceIndex == b.sentenceIndex) {
            return a.off - b.off
          } else {
            return a.sentenceIndex - b.sentenceIndex
          }
        })

        htm+= '<div class="ptense_grm_cnt">Perfect Tense found <strong>'+cnt+'</strong> mistakes in your post.</strong></div>';

        htm +='<div class="ptense_items">';

        for (var i = 0 ; i < sorted_bug_list.length ; i++)
        {

          original_word = sorted_bug_list[i].original_word;
          new_word = sorted_bug_list[i].new_word;
          check_box_index = sorted_bug_list[i].index;
          off = sorted_bug_list[i].off;


          // Offsets are relative to the current state of the sentence!
          current_sentence = sorted_bug_list[i].current_sent;
          before_text = current_sentence.substring(0, off);
          after_text = current_sentence.substring(off + original_word.length);

          // Offer the added text as a replacement, highlighted
          new_sentence = (before_text.stripSlashes()) + "<span class=\"ptense_high\">"+  (new_word.stripSlashes())+"</span>"+ (after_text.stripSlashes());

          htm += '<div class="ptense_item"><div><span class="ptense_bg">'+original_word+'</span> => <span class="ptense_sent">'+new_sentence+'</span></div><input type="checkbox" class="ptense_chk" data-id="'+check_box_index+'" /></div>';
        }

        htm +='</div>';

        htm += '<div class="ptense_grm_sc">Your grammar score is <strong>'+score+'</strong></div>';

        htm += '<button class="ptense_canl button">Close</button>';
        
        htm += '<button class="ptense_aprv button button-primary" style=" float: right; ">Apply</button>';
        htm += '<button id="ptense_select_all" onclick="ptSelectAll()" class="button" style=" float: right; margin-right: 5px">Select All</button>';

        jQuery('#ptense_dialog').html(htm);
        jQuery('#ptense_dialog').dialog('open');

      }

      function ptSelectAll() {
        
        const selectAll = jQuery('#ptense_select_all');
        var newText;
        var newCheckStatus;


        if (selectAll.text() == "Select All") {
          newText = "Deselect All";
          newCheckStatus = true;
        } else {
          newText = "Select All";
          newCheckStatus = false;
        }

        jQuery('.ptense_chk').attr('checked', newCheckStatus);

        selectAll.text(newText);
      }

      function applySelectedTransforms(text, proposedTransforms, selectedIndices) {
        var offsetAdjustment = 0;

        //console.log("Applying selected indices:");
        //console.log(selectedIndices);

        // MUST iterate in order so that offset is increasing with each change
        selectedIndices.forEach(function(transformIndex) {

          const transform = proposedTransforms[transformIndex];

          /*console.log("Current text:")
          console.log(text)
          console.log("Applying next selected transform:");
          console.log(transform);*/

          const adjustedOffset = transform.docOffset + offsetAdjustment;
          const before = text.substring(0, adjustedOffset);
          const after = text.substring(adjustedOffset + transform.original_word.length);
          text = before + transform.new_word + after;

          offsetAdjustment += transform.new_word.length - transform.original_word.length;
        })

        //console.log("Result: ");
        //console.log(text); 
        return text;
      }

      // bind a button or a link to open the dialog
      jQuery(document).ready(function(){

        var originalPublishText = ""

        jQuery('body').on('click', '.ptense_canl', function(e){ // on "Close" of the dialog
          e.preventDefault();
          jQuery('#ptense_dialog').dialog('close');
        })

        // Value is still publish aka it didn't go through (block for low grammar score)
        /*if (jQuery('#submitdiv input.button-primary[type="submit"]').val() == "Publish")
        {
            if(jQuery('.updated.notice.notice-success').text() == "Post published. View postDismiss this notice." || jQuery('.updated.notice.notice-success').text() == "Post published. View post")
            {
                originalPublishHtml =  jQuery('.updated.notice.notice-success').html();
                jQuery('.updated.notice.notice-success p').html("Post saved as draft because grammar score was too low. <button id='ptense_force_publish'>Publish Anyway</button>");   
            }
        }*/
        
        // Force publish even if grammar score is too low
        jQuery('#ptense_force_publish').click(function(e) {
          e.preventDefault();

          jQuery.post(ajaxurl, {action: 'ptense_force_publish', post_id: currentPostId}, function(response) {
            //console.log("In force publish callback");
            location.reload();
          })
        });

        jQuery('body').on('click', '.ptense_aprv', function(e) { // on "Apply" of the dialog
          e.preventDefault();

          // All corrections to be applied
          /*var a = jQuery('.ptense_chk:checked');
          var a_len = a.length;
          if(a_len < 1) return;
          // console.log(a.length);
          var selected_indexes = [];
          for(var i = 0 ; i < a_len; i++)
          {
            selected_indexes.push( jQuery(a[i]).attr('data-id'));
          }*/
          const allCheckBoxes = jQuery('.ptense_chk');
          const numCheckBoxes = allCheckBoxes.length;
          const selectedIndices = [];
          var checkBoxIndex = 0;

          for (checkBoxIndex = 0; checkBoxIndex < numCheckBoxes; checkBoxIndex++) {
            if (jQuery(allCheckBoxes[checkBoxIndex]).is(":checked")) {
              selectedIndices.push(checkBoxIndex);
            }
          }

          //selected_indexes = selected_indexes.join(',');
          jQuery('#ptense_dialog').html('<div class="ptense_grm_cnt">Please wait. . . </div>');


          const newText = applySelectedTransforms(lastJobText, lastBugList, selectedIndices);
          set_tinyMce_content(newText.newlineToBr().stripSlashes());
          jQuery('#ptense_dialog').dialog('close');
            /*jQuery.post(ajaxurl, {action: 'ptense_apply_fixes', data: get_tinyMce_content(), selected_indexes: selected_indexes}, function(fixes_response) {
              
              console.log("Corrected post:")
              console.log(fixes_response)
              set_tinyMce_content(fixes_response.newlineToBr().stripSlashes())

              //jQuery(<?php echo $editor_id;?>).val(fixes_response);
              jQuery('#ptense_dialog').dialog('close');

            });*/

        })
        jQuery('#ptense_dialog').dialog({
          title: 'Perfect Tense',dialogClass: 'wp-dialog',autoOpen: false,draggable: false,width: '600px',modal: true,resizable: false,closeOnEscape: true,position: {  my: "center",  at: "center",  of: window},
          open: function () {
            jQuery('.ui-widget-overlay').bind('click', function(){
              jQuery('#ptense_dialog').dialog('close');
            })
          },
          create: function () {
            jQuery('.ui-dialog-titlebar-close').addClass('ui-button');
          },
        });
        jQuery('.perfect_tense_proof_read').click(function(e) {
          e.preventDefault();


          try {

              jQuery.post(ajaxurl, {action: 'ptense_get_usage'}, function(response) {
                if(response == 'Api key is not defined')
                {
                    jQuery('#ptense_dialog').html('Oops, looks like you have not set a valid Perfect Tense API key yet.');
                jQuery('#ptense_dialog').dialog('open');
                    return;
                }
                if( response > 0)
                {

                  try {
                    jQuery.post(ajaxurl, {action: 'ptense_get_job', data: get_tinyMce_content() }, function(response2) {
                      
                      try {

                        if(response2 == '') {
                          //console.log("Empty response!")
                          throw "Unable to correct article. Please contact support.";
                          return;
                        }

                        response2 = JSON.parse(response2);

                        var job_details = response2.job_detail;
                        var score = response2.score;
                        var cnt = job_details.length;
                        var bug_list = [];

                        for(var i = 0 ; i < job_details.length; i++)
                        {
                            if(job_details[i].isSuggestion) {
                              continue;
                            }
                                
                            bug_list.push({
                                sentenceIndex : job_details[i].sentenceIndex,
                                index         : job_details[i].transformIndex,
                                result        : job_details[i].sentAfterTransform,
                                off           : job_details[i].off,
                                original_word : job_details[i].getAffectedText,
                                new_word      : job_details[i].getAddedText,
                                current_sent  : job_details[i].currentSentText,
                                docOffset     : job_details[i].documentOffset
                            })
                        }
                        cnt = bug_list.length;

                        //console.log("Doing bug list!");
                        doBugList(bug_list, cnt, score);

                        // cache to re-use on apply
                        lastJobText = response2.job_text;
                        lastBugList = bug_list;
                      } catch (e) {
                        ptense_handle_error(e)
                      }
                      
                    });
                  } catch (e) {
                    ptense_handle_error(e)
                  }
                }else{
                  jQuery('#ptense_dialog').html('Oops, it looks like you have no more usage left for today. If you would like to upgrade your usage, contact us.');
                  jQuery('#ptense_dialog').dialog('open');
                }
            });
          } catch (e) {
            ptense_handle_error(e)
          }
        });
      });
    </script>
    <?php
} );









add_action( 'wp_ajax_ptense_get_usage', 'ptense_get_usage_render_ajax' );

add_action( 'wp_ajax_ptense_get_job', 'ptense_get_job_render_ajax' );


function ptense_get_usage_render_ajax(){

  global $ptense_settings;
  if( $ptense_settings['perfect_tense_api_key'] == '' || $ptense_settings['perfect_tense_app_key'] == '')
  {
      echo 'Api key is not defined';
      wp_die();
  }

  echo ptense_get_usage( $ptense_settings['perfect_tense_api_key'], $ptense_settings['perfect_tense_app_key'] );

  wp_die();
}



/*
function ptense_apply_fixes_render_ajax(){

    global $ptense_settings;

    if(!isset($_POST['data']) || !isset($_POST['selected_indexes']))
      wp_die();

    if($_POST['data'] == '' || $_POST['selected_indexes'] == '')
      wp_die();

    $selected_indexes = explode(",", $_POST['selected_indexes']);

    $get_final_output = ptense_get_job_fixes($ptense_settings['perfect_tense_api_key'], $ptense_settings['perfect_tense_app_key'] , $_POST['data'], 
      $selected_indexes, $_POST['intEditor']);

    echo $get_final_output;
    wp_die();


}*/



function ptense_get_job_render_ajax(){

  global $ptense_settings;

  if(!isset($_POST['data']))
    wp_die();

  if($_POST['data'] == '')
    wp_die();

  $score = ptense_get_grammar_score($ptense_settings['perfect_tense_api_key'], $ptense_settings['perfect_tense_app_key'] , wp_filter_post_kses($_POST['data']));
  $intEditor = ptense_get_job($ptense_settings['perfect_tense_api_key'], $ptense_settings['perfect_tense_app_key'] , wp_filter_post_kses($_POST['data']));
  $job_detail = ptense_get_available_transforms($intEditor);

  // wp_die();
  echo json_encode(
    array(
      'score'      => $score,
      'job_detail' => $job_detail,
      'job_text' => $intEditor->getCurrentText()
  ));
  wp_die();
}
