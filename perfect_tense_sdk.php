<?php

$ptUrl = 'https://api.perfecttense.com';


/**
 *	Generate an App key for this integration (alternatively, use our UI here: https://app.perfecttense.com/api).
 *
 *	@param string $apiKey			The API key to register this app under (likely your own)
 *	@param string $name				The name of this app
 *	@param string $description		The description of this app (minimum 50 characters)
 *	@param string $contactEmail		Contact email address for this app (defaults to the email associated with the API key)
 *	@param string $siteUrl			Optional URL that can be used to sign up for/use this app.
 *
 *	@return string					A unique app key
 */
function ptense_generate_app_key($apiKey, $name, $description = '', $contactEmail = '', $siteUrl = '') {

	$data = array(
		'name' => $name,
		'description' => $description,
		'contactEmail' => $contactEmail,
		'siteUrl' => $siteUrl
	);

	$execute = wp_remote_post('https://api.perfecttense.com/generateAppKey', array(
      'method' => 'POST',
      'headers' => array(
        "Content-type" => "application/json",
        "Authorization" => $apiKey
      ),
          'httpversion' => '1.0',
          'sslverify' => false,
          'body' => json_encode($data)
    ));
    $response = wp_remote_retrieve_body($execute);
    $responseData = json_decode($response, TRUE);

	return $responseData;
}

class PTClient {

	public $appKey;
	public $persist;
	public $responseType;
	public $options;

	private $STATUS_CLEAN = 'clean';
	private $STATUS_ACCEPTED = 'accept';
	private $STATUS_REJECTED = 'reject';

	private $ALL_RESPONSE_TYPES = array('rulesApplied', 'grammarScore', 'corrected');


	/**
	 * Constructor for the Perfect Tense client
	 *
	 * @param object $arguments                              And array containing the below parameters
	 * @param string $arguments->appKey                      The registered app key for this integration. 'See ptense_generate_app_key' for more info.
	 * @param boolean $arguments->persist=false              Optionally persist accept/reject actions through the perfect tense api.
	 * @param object $arguments->options=new stdClass()      An optional Object of options to be passed when submitting jobs. See our API docs for more info
	 * @param object $arguments->responseType=array(all)     An optional array of response types to receive. By default, this is set to all available resopnse types. See our api documentation for more information.
	 */
	public function __construct($arguments) {
		$this->appKey = !array_key_exists('appKey', $arguments) ? "" : $arguments['appKey'];
		$this->persist = !array_key_exists('persist', $arguments) ? false : $arguments['persist'];
		$this->options = !array_key_exists('options', $arguments) ? new stdClass() : $arguments['options'];
		$this->responseType = !array_key_exists('responseType', $arguments) ? $this->ALL_RESPONSE_TYPES : $arguments['responseType'];
	}


/*********************************************************************
		Interaction With Perfect Tense API
**********************************************************************/

	/*
		Submit text to Perfect Tense, receiving specified responseTypes in result.

	 * 	@param string text				Text to be submitted
	 * 	@param string apiKey			The user's API key
	 *	@param object options			Options such as protected text. Defaults to options set during initialization
	 *	@param object responseType		Array of response types. Defaults to responseType set during initialization
	 *
	 *	@return object					The result response from Perfect Tense
	 */
	public function submitJob($text, $apiKey, $options = null, $responseType = null) {

		$jobOptions = is_null($options) ? $this->options : $options;
		$jobResponseType = is_null($responseType) ? $this->responseType : $responseType;

		$data = array(
			'text' => ptense_normalize_space($text),
			'responseType' => $jobResponseType,
			'options' => $jobOptions
		);

		$result = $this->submitToPt($data, $apiKey, '/correct');

		$this->setMetaData($result);

		return $result;
	}

	/**
	 *	Get API usage statistics from PT
	 *
	 *	@param string $apiKey		The api key of the user you are requesting usage statistics for
	 *
	 *	@return object				The user's usage statistics
	 */
	public function getUsage($apiKey) {

		$execute = wp_remote_get('https://api.perfecttense.com/usage', array(
	      'headers' => array(
	        "Content-type" => "application/json",
	        "Authorization" => $apiKey
	      ),
	          'httpversion' => '1.0',
	          'sslverify' => false
	    ));
	    $response = wp_remote_retrieve_body($execute);
		$responseData = json_decode($response, TRUE);

		return $responseData;
	}

	/**
	 *	Utility to submit a payload to the Perfect Tense API
	 *
	 *	Once configured, this integration's "App Key" will be inserted into all API requests.
	 *
	 *	See our API documentation for more information: https://www.perfecttense.com/docs/#introduction
	 *
	 *	@param object $data			Payload to be submitted (see api docs: )
	 *	@param object $apiKey		The user's apiKey to validate this request
	 *	@param string $endPoint		The API endpoint (see docs)
	 */
	private function submitToPt($data, $apiKey, $endPoint) {

		$execute = wp_remote_post('https://api.perfecttense.com' . $endPoint, array(
          'method' => 'POST',
          'headers' => array(
	        "Content-type" => "application/json",
	        "Authorization" => $apiKey,
	        "AppAuthorization" => $this->appKey
	      ),
	          'httpversion' => '1.0',
	          'sslverify' => false,
	          'body' => json_encode($data))
        );
        $response = wp_remote_retrieve_body($execute);
        $responseData = json_decode($response, TRUE);

		return $responseData;
	}

/*********************************************************************
		Interaction With Perfect Tense Result
**********************************************************************/

	/**
	 *	Set the app key for this client.
	 *
	 *	Generally, this will be done during initialization. However, if you have used generateApiKey
	 *	to generate a new
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return number			The grammar score result to this job
	 */
	public function setAppKey($appKey) {
		$this->appKey = $appKey;
	}

	/**
	 *	Get the grammar score result of this job.
	 *
	 *	If the grammar score was requested in the original request, a value from 0.0 to 100.0
	 *	will be returned. Otherwise, null will be returned.
	 *
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return number			The grammar score result to this job
	 */
	public function getGrammarScore($data) {
		return $data['grammarScore'];
	}


	/**
	 *	Returns true if the Perfect Tense job completed successfully, else false.
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return					True if successful, else false
	 */
	public function successfulJob($data) {
		return $data['status'] === 201;
	}

	/**
	 *	Set job metadata in-place for easier interaction/manipulation.
	 *
	 *	Main steps:
	 *
	 *	1.
	 *		Some transformations overlap/are derived from the result of others.
	 *		Iterate through all transformations and mark transformations that are
	 *		dependent on others as being members of the same group.
	 *
	 *			ex: "He hzve be there befor"
	 *
	 *			Transformations:
	 *				hzve -> have
	 *				have be -> has been
	 *				befor -> before
	 *			Groups:
	 *				0:
	 *					hzve -> have
	 *					have be -> has been
	 *				1:
	 *					befor -> before
	 *		2.
	 *			Set transform -> sentence/transform index and sentence -> sentenceIndex for quicker referencing
	 *		3.
	 *			Update the "active tokens" in the sentence based on the current status of the transformations
	 *			(useful if recovering a previous job that already has transformations set to accept/reject)
	 *
	 *			If not recovering a previous job, set the active tokens to the initial set.
	 *
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return					Result with metadata set
	 */
	public function setMetaData(&$data) {

		// Only valid for rulesApplied response type
		if (!array_key_exists('rulesApplied', $data)) {
			return;
		}

		// Count all transforms seen (accross all sentences) and assign index (used as unique id)
		$transformCounter = 0;

		for ($sentenceIndex = 0; $sentenceIndex < count($data['rulesApplied']); $sentenceIndex++) {

			$sentence =& $data['rulesApplied'][$sentenceIndex];

			// current working set of tokens in the sentence
			$sentence['activeTokens'] = $sentence['originalSentence'];

			// hold reference to index
			$sentence['sentenceIndex'] = $sentenceIndex;

			// track overlapping transform groups
			$sentence['groups'] = array();

			// Group id for overlapping transformations in the current sentence
			$groupIdCounter = 0;

			$numTransformsInSent = count($sentence['transformations']);

			for ($transformIndex = 0; $transformIndex < $numTransformsInSent; $transformIndex++) {

				$transform =& $sentence['transformations'][$transformIndex];

				// Set indices for future reference
				$transform['transformIndex'] = $transformCounter++;
				$transform['indexInSentence'] = $transformIndex;
				$transform['sentenceIndex'] = $sentenceIndex;

				if (!array_key_exists('status', $transform)) {
					$transform['status'] = $this->STATUS_CLEAN;
				}

				$this->updateActiveTokens($sentence, $transform);

				if (!array_key_exists('groupId', $transform)) {
					$groupQueue = array($transform['indexInSentence']);
					$groupId = $groupIdCounter++;
					$sentence['groups'][$groupId] = array();

					$nextTransInd = NULL;

					while (!is_null($nextTransInd = array_shift($groupQueue))) {

						$nextInGroup =& $sentence['transformations'][$nextTransInd];

						if (!array_key_exists('groupId', $nextInGroup)) {
							$nextInGroup['groupId'] = $groupId;

							$sentence['groups'][$groupId][] =& $nextInGroup;

							for ($i = $nextInGroup['indexInSentence'] + 1; $i < $numTransformsInSent; $i++) {
								$nextTrans =& $sentence['transformations'][$i];

								$nextTrans['indexInSentence'] = $i;

								if (!array_key_exists('groupId', $nextTrans) && $this->transformsOverlap($nextInGroup, $nextTrans, true)) {
									$groupQueue[] = $i;
								}
							}
						}
					}
				}
			}

			$this->setIsAvailable($sentence['transformations'], $sentence);
		}

		$data['hasMeta'] = True;
	}

	/**
	 *	Get the current text of the job, considering transformations that have been accepted or rejected.
	 *
	 *	@param object $data		Result returned from submitJob
	 *
	 *	@return string			current text of the job
	 */
	public function getCurrentText($data) {
		$currentSentences = array_map(function ($sentence) {
			return $this->getCurrentSentenceText($sentence);
		}, $data['rulesApplied']);

		return join("", $currentSentences);
	}

	/**
	 *	Get the original text of an entire document/job, prior to any corrections.
	 *
	 *	@param object $data		Result returned from submitJob
	 *
	 *	@return string			original text of the job
	 */
	public function getOriginalText($data) {
		$originalSentences = array_map(function ($sentence) {
			return $this->getOriginalSentenceText($sentence);
		}, $data['rulesApplied']);

		return join("", $originalSentences);
	}

	/**
	 *  Get the text of the sentence in its current state, considering accepted/rejected corrections.
	 *
	 *
	 * 	@param object $sentence	Sentence object
	 *
	 *	@return string			The current text of the sentence
	 */
	public function getCurrentSentenceText($sentence) {
		return $this->tokensToString($sentence['activeTokens']);
	}

	/**
	 *  Get the original text of a sentence, prior to any corrections.
	 *
	 *
	 * 	@param object $sentence	Sentence object
	 *
	 *	@return string			The original text of the sentence
	 */
	public function getOriginalSentenceText($sentence) {
		return $this->tokensToString($sentence['originalSentence']);
	}



	/**
	 *  Returns the number of sentences in the job.
	 *
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return {number}			The number of sentences in the job
	 */
	public function getNumSentences($data) {
		return count($data['rulesApplied']);
	}


	/**
	 *  Returns the number of transformations in a sentence.
	 *
	 *
	 * 	@param object sentence		A sentence object
	 *
	 *	@return number				The number of transformations in the sentence
	 */
	public function getNumTransformations($sentence) {
		return count($sentence['transformations']);
	}

	/**
	 *  Returns the transformation at the specified index in the sentence.
	 *
	 *
	 * 	@param object sentence			A sentence object
	 *	@param number transformIndex 	The transformation index
	 *
	 *	@return number					The number of transformations in the sentence
	 */
	public function getTransformationAtIndex($sentence, $transformIndex) {
		return $sentence['transformations'][$transformIndex];
	}

	/**
	 *  Gets the sentence at the specified index.
	 *
	 *
	 * 	@param object $data		Result returned from submitJob
	 *
	 *	@return object				The sentence object at the specified index
	 */
	public function getSentence($data, $sentenceIndex) {
		return $data['rulesApplied'][$sentenceIndex];
	}


	/**
	 *  Get all transformations that overlap with the parameter transform.
	 *
	 *	Ex: "He hzve be there before"
	 *	t1: "hzve" -> "have"
	 *	t2: "have be" -> "has been"
	 *
	 *	getOverlappingGroup(sentence, t1) will return [t1, t2]
	 *
	 *
	 * 	@param object $sentence		A sentence from the submitJob response (data.rulesApplied[index])
	 *	@param object $transform 	A transformation inside that sentence (sentence.transformations[index])
	 *
	 *	@return object 				An array of transformations that overlap with the parameter transformation
	 */
	public function getOverlappingGroup($sentence, $transform) {
		return $sentence['groups'][$transform['groupId']];
	}

	/**
	 *  Returns true if the parameter transformations affect the exact same tokens in the sentence.
	 *
	 *
	 * 	@param object $transform1		The first transformation
	 * 	@param object $transform2		The second transformation
	 *
	 *	@return boolean				True if the transformations effect the exact same tokens, else false
	 */
	public function affectsSameTokens($transform1, $transform2) {

		// Verify that transformations are on the same sentence and have same number of tokens affected
		if ($transform1['sentenceIndex'] === $transform2['sentenceIndex'] &&
			count($transform1['tokensAffected']) === count($transform2['tokensAffected'])) {

			$tAff2 = $transform2['tokensAffected'];

			// Verify that transformations affect the same token ids
			foreach ($transform1['tokensAffected'] as $index=>$token) {
				if ($tAff2[$index]['id'] !== $token['id']) {
					return False;
				}
			}

			return True;
		}

		return False;
	}


	/**
	 *  Get the sentence index of the parameter transformation.
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return number				The index of the sentence in the job (0-based)
	 */
	public function getSentenceIndex($transform) {
		return $transform['sentenceIndex'];
	}

	/**
	 *  Get the index of the parameter transformation in the current sentence.
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return number				The index of the transformation in the sentence (0-based)
	 */
	public function getTransformIndexInSentence($transform) {
		return $transform['indexInSentence'];
	}

	/**
	 *  Get the index of the parameter transformation in the job.
	 *
	 *	Note that this is a 0-based index relative to ALL transformations in the job,
	 *	not just those in the current sentence.
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return number				The index of the transformation in the job (0-based)
	 */
	public function getTransformIndex($transform) {
		return $transform['transformIndex'];
	}

	/**
	 *  Get the "tokens added" field as text (from an array of tokens).
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return string				The tokens added as a string
	 */
	public function getAddedText($transform) {
		return $this->tokensToString($transform['tokensAdded']);
	}

	/**
	 *  Get the "tokens affected" field as text (from an array of tokens).
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return string				The tokens affected as a string
	 */
	public function getAffectedText($transform) {
		return $this->tokensToString($transform['tokensAffected']);
	}

	/**
	 *  Returns true if the transformation is "clean", i.e. has not been accepted or rejected by the user.
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return boolean				True if the transform is clean, else false
	 */
	public function isClean($transform) {
		return $transform['status'] === $this->STATUS_CLEAN;
	}

	/**
	 *	Returns true if the transformation has been accepted by the user
	 *
	 *
	 *	@param object $transform	The transformation in question
	 *
	 *	@return boolean				True if the transform has been accepted, else false
	 */
	public function isAccepted($transform) {
		return $transform['status'] === $this->STATUS_ACCEPTED;
	}

	/**
	 *	Returns true if the transformation has been rejected by the user
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return boolean				True if the transform has been rejected, else false
	 */
	public function isRejected($transform) {
		return $transform['status'] === $this->STATUS_REJECTED;
	}

	/**
	 *	Returns true if the transformation is a suggestion
	 *
	 *
	 * 	@param object $transform	The transformation in question
	 *
	 *	@return boolean				True if the transform has been rejected, else false
	 */
	public function isSuggestion($transform) {
		return $transform['isSuggestion'] === true; // return true/false, not null
	}

	/**
	 *	Returns true if the transformation can be made, given the current state of the sentence.
	 *
	 *	This is checked by verifying that all of the "tokensAffected" in the transformation are present
	 *	in the active working set of tokens in the sentence.
	 *
	 *	@param object $transform		The transformation in question
	 *
	 *	@return boolean					True if the transform can be made, else false
	 */
	public function canMakeTransform($sentence, $transform) {
		return $this->tokensArePresent($transform['tokensAffected'], $sentence['activeTokens']);
	}


	/**
	 *	Returns true if the transformation can be undone, given the current state of the sentence.
	 *
	 *	This is checked by verifying that all of the "tokensAdded" in the transformation are present
	 *	in the active working set of tokens in the sentence (if there are any).
	 *
	 *	@param object $transform		The transformation in question
	 *
	 *	@return boolean				True if the transform can be undone, else false
	 */
	public function canUndoTransform($sentence, $transform) {
		return !$transform['hasReplacement'] ||
			($this->isAccepted($transform) && $this->tokensArePresent($transform['tokensAdded'], $sentence['activeTokens'])) ||
			($this->isRejected($transform) && $this->tokensArePresent($transform['tokensAffected'], $sentence['activeTokens']));
	}

	/**
	 *	Get the character offset of the sentence, given the current state of the job.
	 *
	 * 	@param object $data		Result returned from submitJob
	 *	@param object $sentence	The sentence in question
	 *
	 *	@return number			The character offset of the sentence
	 */
	public function getSentenceOffset($data, $sentence) {
		$textBeforeSent = "";
		$sentIndex = $sentence['sentenceIndex'];

		for ($sentenceIndex = 0; $sentenceIndex < count($data['rulesApplied']); $sentenceIndex++) {
			if ($sentenceIndex == $sentIndex) {
				return strlen($textBeforeSent);
			} else {
				$textBeforeSent = $textBeforeSent . $this->getCurrentSentenceText($data['rulesApplied'][$sentenceIndex]);
			}
		}

		return -1;
	}

	/**
	 *	Get the character offset of the transformation relative to the sentence start
	 *	given the current state of the job.
	 *
	 * 	@param object $data		Result returned from submitJob
	 *	@param object $sentence	The transformation in question
	 *
	 *	@return number			The character offset of the transformation (relative to sentence start), or -1 if it is not present
	 */
	public function getTransformOffset($data, $transform) {

		$sentence = $data['rulesApplied'][$transform['sentenceIndex']];

		if ($this->canMakeTransform($sentence, $transform)) {

			$activeTokens = $sentence['activeTokens'];

			/*
				Since the transformation is availble, we know that the tokensAffected are present as a sublist of active tokens.
				We can just iterate through the active tokens and return the offset of the first affected token.
			*/

			$textBeforeTok = "";
			$firstAffectedId = $transform['tokensAffected'][0]['id'];

			for ($tokenIndex = 0; $tokenIndex < count($activeTokens); $tokenIndex++) {

				$nextTok = $activeTokens[$tokenIndex];

				if ($nextTok['id'] === $firstAffectedId) {
					return strlen($textBeforeTok);
				} else {
					$textBeforeTok = $textBeforeTok . $this->tokenToString($nextTok);
				}
			}
		}

		return -1;
	}

	/**
	 *	Join tokens into a single string.
	 *
	 *	This will map each token to [token.value] + [token.after] and join together.
	 *
	 *	@param object $tokens		The tokens to turn into a string
	 *
	 *	@return string				The tokens joined as a single string
	 */
	public function tokensToString($tokens) {
		return join("", array_map(function ($token) {
			return $this->tokenToString($token);
		}, array_values($tokens)));
	}

	/**
	 *	Map a token to a string (token.value + token.after)
	 *
	 *	@param object $tokens		The token to turn into a string
	 *
	 *	@return string				The token as a string
	 */
	public function tokenToString($token) {
		return $token['value'] . $token['after'];
	}

	/**
	 *	Get all available transformations in the sentence.
	 *
	 *	It is assumed that the "isAvailable" field in each transformation is kept up-to-date
	 *	(generally handled for you when using this API).
	 *
	 *	@param object $sentence		The sentence in question
	 */
	public function getAvailableTransforms($sentence) {
		return array_filter($sentence['transformations'], function ($transform) {
			return $transform['isAvailable'];
		});
	}

	/**
	 *  Accepts the transformation and modifies the state of the job to reflect the change
	 *
	 *
	 * 	@param object $data			Result returned from submitJob
	 *	@param object $transform 	The transformation to be accepted
	 *	@param object [$apiKey]		Optional user API Key to track transformation status (found at https://app.perfecttense.com/home)
	 *
	 *	@return boolean				True if successfully accepted, else false
	 */
	public function acceptCorrection(&$data, &$transform, $apiKey) {
		$sentence =& $data['rulesApplied'][$transform['sentenceIndex']];

		if ($transform['isAvailable']) {

			$prevText = $this->getCurrentSentenceText($sentence);
			$offset = $this->getTransformOffset($data, $transform);

			$this->makeTransform($sentence, $transform);

			$transform['status'] = $this->STATUS_ACCEPTED;

			if ($this->canPersist()) {
				$this->saveTransformStatus($data, $transform, $apiKey, $prevText, $offset);
			}

			return True;
		}

		return False;
	}

	/**
	 *  Rejects the transformation and modifies the state of the job to reflect the change
	 *
	 *
	 * 	@param object $data			Result returned from submitJob
	 *	@param object $transform 	The transformation to be rejected
	 *	@param string [$apiKey]		Optional user API Key to track transformation status (found at https://app.perfecttense.com/home)
	 *
	 *	@return boolean				True if successfully rejected, else false
	 */
	public function rejectCorrection($data, &$transform, $apiKey) {

		$sentence =& $data['rulesApplied'][$transform['sentenceIndex']];

		if ($transform['isAvailable']) {

			$prevText = $this->getCurrentSentenceText($sentence);
			$offset = $this->getTransformOffset($data, $transform);

			$transform['status'] = $this->STATUS_REJECTED;
			$transform['isAvailable'] = False;

			if ($this->canPersist()) {
				$this->saveTransformStatus($data, $transform, $apiKey, $prevText, $offset);
			}

			return True;
		}

		return False;

	}

	/**
	 *  Resets the transformation to "clean" and modifies the state of the job to reflect the change
	 *
	 *
	 * 	@param object $data			Result returned from submitJob
	 *	@param object $transform 	The transformation to be reset
	 *	@param string [$apiKey]	Optional user API Key to track transformation status (found at https://app.perfecttense.com/home)
	 *
	 *	@return boolean				True if successfully reset, else false
	 */
	public function resetCorrection(&$data, &$transform, $apiKey) {

		$sentence =& $data['rulesApplied'][$transform['sentenceIndex']];

		if ($this->canUndoTransform($sentence, $transform)) {
			$this->undoTransform($sentence, $transform);

			$transform['status'] = $this->STATUS_CLEAN;

			if ($this->canPersist()) {

				$text = $this->getCurrentSentenceText($sentence);
				$offset = $this->getTransformOffset($data, $transform);

				$this->saveTransformStatus($data, $transform, $apiKey, $text, $offset);
			}

			return True;
		}

		return False;
	}

	/*********************************************************************
				Private Helper Functions/Utilities
	**********************************************************************/

	/**
	 *  Returns true if the tokens are a valid subsequence of the active tokens in the sentence.
	 *
	 *
	 * 	@param object $tokens		An array of token objects
	 * 	@param object $allTokens		An array of tokens to look for "tokens" in
	 *
	 *	@return boolean				True if the tokens are a valid subsequence, else false
	 */
	private function tokensArePresent($tokens, $allTokens) {

		$lastIndex = -1;

		foreach ($tokens as $token) {

			$indexOfId = $this->findTokenIndex($allTokens, $token['id']);

			if ($indexOfId === -1 || ($lastIndex !== -1 && $indexOfId !== ($lastIndex + 1))) {
				return False;
			}

			$lastIndex = $indexOfId;
		}

		return True;
	}

	/**
	 *	The the "isAvailable" status of the parameter transformations.
	 *
	 *	A transformation is defined as "available" if its tokensAffected field is a valid
	 *	subsequence of the active tokens in the sentence (after any accept/reject actions).
	 *
	 *	@param object transforms		The transformation to be updated
	 *	@param object sentence			The sentence that the transformations belong to
	 */
	private function setIsAvailable(&$transforms, $sentence) {

		for ($index = 0; $index < count($transforms); $index++) {
			$transform =& $transforms[$index];
			if ($this->canMakeTransform($sentence, $transform)) {
				$transform['isAvailable'] = True;
			} else {
				$transform['isAvailable'] = False;
			}
		}
	}


	/**
	 *  Returns true if the parameter transformations overlap in any way, considering both tokensAffected and tokensAdded.
	 *
	 *	If inOrder is true, it is assumed that t1 came prior to t2 in the correction pipeline, and we are guaranteed
	 *	that the tokensAffected of t1 do not overlap with the tokensAdded of t2 (t1 is not dependent on t2)
	 *
	 *
	 * 	@param object $t1				The first transformation
	 * 	@param object $t2				The second transformation
	 *	@param boolean $inOrder			True if the transformations are in the order they were created, else false
	 *
	 *	@return boolean					True if the transformations overlap, else false
	 */
	private function transformsOverlap($t1, $t2, $inOrder) {
		return $this->tokenArraysOverlap($t1['tokensAffected'], $t2['tokensAffected']) ||
			$this->tokenArraysOverlap($t1['tokensAdded'], $t2['tokensAffected']) ||
			(!$inOrder && $this->tokenArraysOverlap($t1['tokensAffected'], $t2['tokensAdded']));
	}

	/**
	 *  Returns true if the elements of the parameter token arrays overlap.
	 *
	 *
	 * 	@param object $a1		The first array
	 * 	@param object $a2		The second array
	 *
	 *	@return boolean			True if the array elements overlap, else false
	 */
	private function tokenArraysOverlap($a1, $a2) {
		foreach($a1 as $token) {
			if ($this->findTokenIndex($a2, $token['id']) !== -1) {
				return True;
			}
		}

		return False;
	}

	/**
	 *  Returns true if the ids of the parameter tokens match.
	 *
	 *
	 * 	@param object $t1		The first token
	 * 	@param object $t2		The second token
	 *
	 *	@return boolean			True if the token ids are the same, else false
	 */
	private function compareTokens($t1, $t2) {
		return $t1['id'] === $t2['id'];
	}


	/**
	 *  Returns the index of the token matching the parameter 'id' in the array 'tokens', or -1 if not found
	 *
	 *
	 * 	@param object $tokens	An array of tokens
	 * 	@param number $id		The id of the token to look for
	 *
	 *	@return number			The index of the token matching 'id', or -1 if not found
	 */
	private function findTokenIndex($tokens, $id) {
		foreach ($tokens as $tokIndex=>$token) {
			if ($id == $token['id']) {
				return $tokIndex;
			}
		}

		return -1;
	}

	/**
	 *	Update the "isAvailable" status of every transformation in the same "group"
	 *	as the parameter transform in the sentence.
	 *
	 *	When a transformation is accepted, rejected, or undone, we only want to refresh
	 *	the status of transformations that potentially overlap/are affected. In "setMetaData",
	 *	we grouped all transformations that overlapped together, so we can just use that cache here.
	 *
	 *
	 *	@param object $sentence		The sentence that the transformation is in
	 *	@param object $transform	The transformation whose overlapping group will be refreshed
	 */
	private function updateTokenGroup(&$sentence, $transform) {
		$tokenGroup =& $sentence['groups'][$transform['groupId']];
		$this->setIsAvailable($tokenGroup, $sentence);
	}

	/**
	 *	Utility used during setMetaData to update the active tokens if the recovered transformation was accepted.
	 *
	 *	Updates the active working set of tokens for the sentence.
	 *
	 *	@param object $sentence		The sentence that the transformation is in
	 *	@param object $transform	The transformation to accept
	 */
	private function updateActiveTokens(&$sentence, $transform) {
		if ($transform['hasReplacement'] && $this->isAccepted($transform)) {
			$sentence['activeTokens'] = $this->replaceTokens($sentence['activeTokens'], $transform['tokensAffected'], $transform['tokensAdded']);
		}
	}


	/**
	 *	Utility to replace the "affected" tokens with the "added" tokens in the parameter "tokens" array.
	 *
	 *	When a transformation is "accepted", we replace the "tokensAffected" with the "tokensAdded".
	 *
	 *	When a transformation is "undone", we do the opposite.
	 *
	 *	If "affepted" is not a valid subsequence of the parameter "tokens", then no replacement can be made
	 *	and the original tokens are returned.
	 *
	 *
	 *	@param object $tokens		An array of tokens to make the replacement in
	 *	@param object $affected		A subsequence of "tokens" to be replaced
	 *	@param object $added		An array of tokens that will replace "affected"
	 */
	private function replaceTokens($tokens, $affected, $added) {

		if (!$this->tokensArePresent($affected, $tokens)) {
			return $tokens;
		}

		$firstTokenId = $affected[0]['id'];
		$lastTokenId = $affected[count($affected) - 1]['id'];

		$startInd = $this->findTokenIndex($tokens, $firstTokenId);
		$endInd = $this->findTokenIndex($tokens, $lastTokenId);

		// Sanity check. They should be there if tokensArePresent passed
		if ($startInd !== -1 && $endInd !== -1 && $endInd >= $startInd) {
			$before = array_slice($tokens, 0, $startInd);
			$after = array_slice($tokens, $endInd + 1);
			$final_tokens = array_merge_recursive($before, $added, $after);

			return $final_tokens;
		}

		return $tokens;
	}


	/**
	 *	Check if the client has been configured to persist transformation status updates.
	 *
	 *
	 *	@return {boolean}		True if can persist, else false
	 */
	private function canPersist() {
		return $this->persist;
	}

	/**
	 *	Save the transformation's status (clean, accepted, rejected).
	 *
	 *	This is optional and can be turned off when calling "initialize".
	 *
	 *	Please consider leaving this enabled, as it helps Perfect Tense learn!
	 *
	 *
	 *	@param object $ptData			Result returned from submitJob
	 *	@param object $transform		The transformation to save
	 *	@param string $apiKey			The apiKey associated with this job
	 *	@param string $sentenceText		The sentence's current text (just prior to making transformation)
	 *	@param number $offset			Offset of the transform's tokensAffected in the sentenceText
	 */
	private function saveTransformStatus($ptData, $transform, $apiKey, $sentenceText, $offset) {

		$data = array(
			'jobId' => $ptData['id'],
			'responseType' => 'rulesApplied',
			'sentenceIndex' => $transform['sentenceIndex'],
			'transformIndex' => $transform['indexInSentence'],
			'sentence' => $sentenceText,
			'offset' => $offset,
			'status' => $transform['status']
		);

		$this->submitToPt($data, $apiKey, "/updateStatus");
	}

	/**
	 *	Utility to "make" a transformation (accept it).
	 *
	 *	This involves swapping the tokensAdded in for the tokensAffected (if the transform has a replacement),
	 *	and refreshing the "isAvailable" status of all tokens in the same overlapping group.
	 *
	 *	Note that "canMakeTransform" should generally be called before this.
	 *
	 *
	 *	@param object $sentence		The sentence that the transformation is in
	 *	@param object $transform	The transformation to accept
	 */
	private function makeTransform(&$sentence, &$transform) {

		if ($transform['hasReplacement']) {
			$sentence['activeTokens'] = $this->replaceTokens($sentence['activeTokens'],
				$transform['tokensAffected'], $transform['tokensAdded']);

			$this->updateTokenGroup($sentence, $transform);
		}

		$transform['isAvailable'] = False;
	}

	/**
	 *	Utility to "undo" a transformation.
	 *
	 *	This involves swapping the tokensAffected in for the tokensAdded (if the transform has a replacement),
	 *	and refreshing the "isAvailable" status of all tokens in the same overlapping group.
	 *
	 *	Note that "canUndoTransform" should generally be called before this.
	 *
	 *
	 *	@param object $sentence		The sentence that the transformation is in
	 *	@param object $transform	The transformation to undo
	 */
	private function undoTransform(&$sentence, &$transform) {

		if ($transform['hasReplacement'] && $this->isAccepted($transform)) {
			$sentence['activeTokens'] = $this->replaceTokens($sentence['activeTokens'], $transform['tokensAdded'], $transform['tokensAffected']);

			$this->updateTokenGroup($sentence, $transform);
		}

		$transform['isAvailable'] = True;
	}
}


/**
 * This class is used to interact with the result returned from PTClient->submitJob.
 *
 * The most basic usage is as follows:
 *
 * // submit a job to perfect tense
 * $apiKey = [user's api key - they are assigned one when they create an account at https://app.perfecttense.com/];
 * $result = ptClient->submitJob("Some text", $apiKey);
 *
 * // wrap the result in an interactive editor object
 * $intEditor = new PTInteractiveEditor(array('ptClient'=>$ptClient, 'data'=>$result, 'apiKey'=>$apiKey));
 *
 * // interact with the result as needed
 * $originalText = $intEditor->getCurrentText(); // the original text that was submitted (since no corrections have been made yet)
 * $grammarScore = $intEditor->getGrammarScore(); // get the assigned grammar score for the original text (value from 0-100, 0 being the worst)
 * $intEditor->applyAll(); // apply all corrections that were found
 * $correctedText = $intEditor->getCurrentText(); // the final correct text, now that all corrections have been applied
 *
 */
class PTInteractiveEditor {

	private $ptClient;
	private $data;
	private $apiKey;
	private $ignoreNoReplacement;
	private $flattenedTransformations;
	private $transformStack;
	private $transStackSize;
	private $allAvailableTransforms;


	/**
	 * Constructor for the interactive editor.
	 *
	 * @param object $arguments                                 And array containing the below parameters
	 * @param object $arguments->ptClient                       An instance of the PTClient object (generally, only 1 is ever created)
	 * @param object $arguments->data                           The result object returned from PTClient->submitJob
	 * @param string $arguments->apiKey                         The user's API key (they must be prompted in some way to enter this.
     *                                                              It can be found here: https://app.perfecttense.com/home)
     * @param boolean $arguments->ignoreNoReplacement=false     Optionally ignore transformations that do not offer replacement text
	 */
	public function __construct($arguments) {

		$this->ptClient = $arguments['ptClient'];
		$this->data = $arguments['data'];
		$this->apiKey = $arguments['apiKey'];
		$this->ignoreNoReplacement = !array_key_exists('ignoreNoReplacement', $arguments) ? False : $arguments['ignoreNoReplacement'];

		// Jobs must be submitted with the 'rulesApplied' response type to use editor. This is on by default.
		if (!array_key_exists('rulesApplied', $this->data)) {
			die("Must include rulesApplied response type to use interactive editor.");
		}

		// All functions assume that this metadata has been set when interacting with corrections
		if (!array_key_exists('hasMeta', $this->data) || !$this->data['hasMeta']) {
			$this->ptClient->setMetaData($this->data);
		}

		// Transformations from each sentence flattened into one array for easier indexing
		$this->flattenedTransformations = array();


		for ($sentIndex = 0; $sentIndex < count($this->data['rulesApplied']); $sentIndex++) {
			$sentence =& $this->data['rulesApplied'][$sentIndex];

			for ($transIndex = 0; $transIndex < count($sentence['transformations']); $transIndex++) {
				$this->flattenedTransformations[] =& $sentence['transformations'][$transIndex];
			}
		}

		// Stack tracking accepted/rejected transformations
		$this->transformStack = array_filter($this->flattenedTransformations, function ($transform) {
			return !$this->ptClient->isClean($transform);
		});

		$this->transStackSize = count($this->transformStack);

		// Cache of available transformations in current state
		$this->allAvailableTransforms = null;

		$this->updateAvailableCache();
	}

	/**
	 * Execute (accept) all transformations found for this input.
	 *
	 * @param boolean $skipSuggestions=false      Optionally skip any transformations that are just suggestions
	 */
	public function applyAll($skipSuggestions = false) {
		while ($this->hasNextTransform($skipSuggestions)) {
			$this->acceptCorrection($this->getNextTransform($skipSuggestions));
		}
	}

	/**
	 * Undo all actions taken (accepting/rejecting transformations)
	 */
	public function undoAll() {
		while ($this->canUndoLastTransform()) {
			$this->undoLastTransform();
		}
	}

	/**
	 * Get the assigned grammar score for this input text.
	 *
	 * This is a value from 0 to 100, 0 being the lowest possible score.
	 *
	 * @return number        The assigned grammar score
	 */
	public function getGrammarScore() {
		return $this->ptClient->getGrammarScore($this->data);
	}

	/**
	 * Get usage statistics for the current user
	 *
	 * @return object   Returns an object containing the user's usage statistics. See our API documentation for more: https://www.perfecttense.com/docs/
	 */
	public function getUsage() {
		return $this->ptClient->getUsage($this->apiKey);
	}

	/**
	 * Accessor for the result that this interactive editor is wrapping
	 *
	 * @return object   The result from PTClient->submitJob
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Get the transformation at the specified 0-based index.
	 *
	 * This index is relative to the "flattened" list of all transformations found accross all sentences.
	 *
	 * @return object   The transformation object at the specified index, or null if the index is invalid
	 */
	public function getTransform($flattenedIndex) {
		return $this->flattenedTransformations[$flattenedIndex];
	}

	/**
	 * Get the sentence at the specified 0-based index.
	 *
	 * Sentences are indexed based on their order in the original text.
	 *
	 * ex. "This is sentence 1. This is sentence 2."
	 * intEditor->getSentence(1) // returns sentence object for "This is sentence 2."
	 *
	 * @return object   The sentence object at the specified index, or null if the index is invalid
	 */
	public function getSentence($sentenceIndex) {
		return $this->ptClient->getSentence($this->data, $sentenceIndex);
	}

	/**
	 * Get the sentence object that contains the parameter transformation.
	 *
	 * @return object   The sentence object containing the parameter transformation
	 */
	public function getSentenceFromTransform($transform) {
		return $this->getSentence($transform['sentenceIndex']);
	}

	/**
	 * Get a list of all currently available transformations.
	 *
	 * @return object   A list of all currently available transformations.
	 */
	public function getAvailableTransforms() {
		return $this->allAvailableTransforms;
	}

	/**
	 * Private utility to get the next available transformation that is not a suggestion.
	 *
	 * To accomplish the same thing publicly, use getNextTransform($ignoreSuggestions=true)
	 *
	 * @return object   The next available transformation that is not a suggestion (or null if none)
	 */
	private function getNextNonSuggestion() {
		for ($i = 0; $i < count($this->allAvailableTransforms); $i++) {
			if (!$this->ptClient->isSuggestion($this->allAvailableTransforms[$i])) {
				return $this->allAvailableTransforms[$i];
			}
		}
	}

	/**
	 * Returns true if there exists an available transformation.
	 *
	 * This is a utility for iterating through available transformations:
	 *
	 * while (intEditor->hasNextTransform()) {
	 *    $nextTransform = intEditor->getNextTransform();
	 * }
	 *
	 * @param boolean $ignoreSuggestions=false    Optionally ignore transformations that are suggestions
	 * @return boolean                            True if there is an available transform, else false
	 */
	public function hasNextTransform($ignoreSuggestions = false) {
		if ($ignoreSuggestions) {
			return $this->getNextNonSuggestion() != null;
		} else {
			return count($this->allAvailableTransforms) > 0;
		}
	}

	/**
	 * Get the next available transformation
	 *
	 * This is a utility for iterating through available transformations:
	 *
	 * while (intEditor->hasNextTransform()) {
	 *    $nextTransform = intEditor->getNextTransform();
	 * }
	 *
	 * @param boolean $ignoreSuggestions=false    Optionally ignore transformations that are suggestions
	 * @return object                             A transformation object if available, else null
	 */
	public function getNextTransform($ignoreSuggestions = false) {
		if ($ignoreSuggestions) {
			return $this->getNextNonSuggestion();
		} else {
			return $this->allAvailableTransforms[0];
		}
	}

	/**
	 * Get a list of all transformations that affect the EXACT SAME text as the parameter transformation.
	 *
	 * Ex. "He hould do it"
	 *
	 * Two transformations will be created:
	 *   "hould" -> "could"
	 *   "hould" -> "would"
	 *
	 * $overlapping = $intEditor-getOverlappingTransforms(["hould" -> "could"])
	 *      This returns array("hould" -> "could", "hould" -> "would")
	 *
	 * @param object $transform         The transform that you are looking for overlaps of
	 * @return object                   An array of overlapping transformations (minimum an array of 1 element - the parameter transform itself)
	 */
	public function getOverlappingTransforms($transform) {
		$sentence = $this->getSentenceFromTransform($transform);
		$overlappingTransforms = $this->ptClient->getOverlappingGroup($sentence, $transform);

		return array_filter($overlappingTransforms, function ($t) {
			return $this->ptClient->affectsSameTokens($t, $transform);
		});
	}

	/**
	 * Returns the "current" text, considering transformations that have been accepted or rejected.
	 *
	 * When the interactive editor is first created, this will return the original text submitted to
	 * Perfect Tense since no actions have been taken. Once transformations are accepted, the return
	 * from this function will change accordingly.
	 *
	 * @return string        The current text, considering applied transformations
	 */
	public function getCurrentText() {
		return $this->ptClient->getCurrentText($this->data);
	}

	/**
	 * Accept the parameter transformation
	 *
	 * Mainly, this will replace the "affected" string with the "added" string, as well as update state
	 * information to track how this might affect the availability of other transformations.
	 *
	 *
	 * @param boolean $transform       The transformation to accept
	 * @return boolean                 True if the transformation was successfully accepted, else false (it may not be available)
	 */
	public function acceptCorrection($transform) {
		if ($this->ptClient->acceptCorrection($this->data, $transform, $this->apiKey)) {
			$this->updateTransformRefs($transform);
			$this->updateAvailableCache();
			$this->transformStack[] = $transform;
			$this->transStackSize++;

			return True;
		}

		return False;
	}

	/**
	 * Reject the parameter transformation, and update state information accordingly
	 *
	 * @param boolean $transform       The transformation to reject
	 * @return boolean                 True if the transformation was successfully rejected, else false (it may not be available)
	 */
	public function rejectCorrection($transform) {

		if ($this->ptClient->rejectCorrection($this->data, $transform, $this->apiKey)) {
			$this->updateTransformRefs($transform);
			$this->updateAvailableCache();
			$this->transformStack[] = $transform;
			$this->transStackSize++;

			return True;
		}

		return False;
	}

	/**
	 * Undo the last accept or reject action (pop off of the stack), and update state information
	 *
	 * @return boolean       True if the last action was successfully undone, else false
	 */
	public function undoLastTransform() {

		if ($this->transStackSize > 0) {
			$lastTransform = $this->getLastTransform();

			if ($this->ptClient->resetCorrection($this->data, $lastTransform, $this->apiKey)) {
				$this->updateTransformRefs($lastTransform);
				$this->updateAvailableCache();
				array_pop($this->transformStack);
				$this->transStackSize--;
				return True;
			}
		}

		return False;
	}

	/**
	 * Check to see if the parameter transformation can be made (is present in the sentence).
	 *
	 * A transform "can be made" if its 'affected' tokens are present in the 'activeTokens' of the sentence.
	 *
	 * If you use the getNextTransform function, you do not need to call this. Use this function
	 * if you are operating on transformations in a random order and are unsure if the transform
	 * is available.
	 *
	 * @param boolean $transform       The transformation in question
	 * @return boolean                 True if the parameter transformation can be made, else false
	 */
	public function canMakeTransform($transform) {
		$sentence = $this->getSentenceFromTransform($transform);
		return $this->ptClient->canMakeTransform($sentence, $transform);
	}

	/**
	 * Returns true if the last accept/reject action can be undone.
	 *
	 * @return boolean       True if the last action can be undone, else false
	 */
	public function canUndoLastTransform() {

		if ($this->transStackSize > 0) {
			$lastTransform = $this->getLastTransform();
			$sentence = $this->data['rulesApplied'][$lastTransform['sentenceIndex']];

			return $this->ptClient->canUndoTransform($sentence, $lastTransform);
		}

		return False;
	}

	/**
	 * Get the last transformation that was interacted with (accepted or rejected)
	 *
	 * @return object    The last transformation interacted with, or null if none
	 */
	public function getLastTransform() {
		return $this->transformStack[$this->transStackSize - 1];
	}

	/**
	 * Get the character offset of the parameter transformation relative to the sentence start
	 * in the sentence's current state (considering any modifications through accepting/rejecting
	 * transformations)
	 *
	 * @return number     The character offset of the transform in its sentence
	 */
	public function getTransformOffset($transform) {
		return $this->ptClient->getTransformOffset($this->data, $transform);
	}

	/**
	 * Get the character offset of the parameter sentence relative to the overall text start
	 * in the text's current state (considering any modifications through accepting/rejecting
	 * transformations)
	 *
	 * @return number     The character offset of the sentence
	 */
	public function getSentenceOffset($sentence) {
		return $this->ptClient->getSentenceOffset($this->data, $sentence);
	}

	/**
	 * Get the character offset of the parameter transformation relative to the overall text start
	 * in the text's current state (considering any modifications through accepting/rejecting
	 * transformations)
	 *
	 * This is effectively the same as calling getSentenceOffset + getTransformOffset
	 *
	 * @return number     The character offset of the transform in the text
	 */
	public function getTransformDocumentOffset($transform) {
		$sentence = $this->getSentenceFromTransform($transform);
		return $this->getSentenceOffset($sentence) + $this->getTransformOffset($transform);
	}

	/**
	 * Get the tokens affected by this transform as a string
	 *
	 * @return string     The tokens affected as a string
	 */
	public function getAffectedText($transform) {
		return $this->ptClient->getAffectedText($transform);
	}

	/**
	 * Get the tokens added by this transform as a string
	 *
	 * @return string     The tokens added as a string
	 */
	public function getAddedText($transform) {
		return $this->ptClient->getAddedText($transform);
	}

	/**
	 * Get the original text of this job
	 *
	 * @return string     The original text of the job
	 */
	public function getOriginalText() {
		return $this->ptClient->getOriginalText($this->data);
	}

	/**
	 * Get all clean transformations remaining.
	 *
	 * A transform is "clean" if it has not been accepted or rejected yet.
	 *
	 * Note that not all "clean" transformations are necessarily available. To check
	 * if they are available, call canMakeTransform.
	 *
	 * @return object     An array of all clean transformations.
	 */
	public function getAllClean() {
		return array_filter($this->flattenedTransformations, function ($transform) {
			return $this->ptClient->isClean($transform);
		});
	}

	/**
	 * Get the number of sentences in the job
	 *
	 * @return number     The number of sentences in the job
	 */
	public function getNumSentences() {
		return $this->ptClient->getNumSentences($this->data);
	}

	/**
	 * Get the number of transformations in the job
	 *
	 * @return number     The number of transformations in the job
	 */
	public function getNumTransformations() {
		return count($this->flattenedTransformations);
	}

	// Updates cache of available transformations (optionally skipping suggestions without replacements)
	private function updateAvailableCache() {
		$this->allAvailableTransforms = array_values(array_filter($this->flattenedTransformations, function ($transform) {
			return $transform['isAvailable'] && (!$this->ignoreNoReplacement || $transform['hasReplacement']);
		}));
	}

	// PHP mutability... update all references to transform
	private function updateTransformRefs(&$transform) {
		$sentence =& $this->data['rulesApplied'][$transform['sentenceIndex']];
		$sentence['transformations'][$transform['indexInSentence']] = $transform;
		$this->flattenedTransformations[$transform['transformIndex']] = $transform;
	}

}

// PHP non-breaking spaces throw off character offsets, etc.
function ptense_normalize_space($str) {
	return str_replace("\xc2\xa0", " ", $str);
}
?>