<?php

//include_once('FileSessionHandler.php');

/**
 * Base Bot class
 */
abstract class Bot {
	/*
	 * Bot's configuration
	 * @type array
	 */
	protected $aConfig;

	/*
	 * Config flag to switch between php sessions and files storage
	 * @type bool
	 */
	protected $bUseSessions = true;

	/**
	 * Checks if the received tocken matches with the Bot secret tocken
	 * @param   $token string  the tocken received
	 */
	public function __construct($sToken) {
		$this->aConfig = parse_ini_file(__DIR__.'/'.get_called_class().'.ini.php', true);

		if($sToken !== $this->aConfig['SECRET']) {
			throw new Exception('Wrong security token');
		}
	}

	/**
	 * Parse the received json string
	 * @param   $json   string  the json received
	 */
	public function parse($sJson) {
		$sMethod = null;
		$aJson = json_decode($sJson, true);

		if($this->bUseSessions) {
			session_id(get_called_class().$this->getChatId($aJson));
			session_start();

			//actually the check for already processed messages is available only when using php sessions
			$sMessageId = $this->getMessageId($aJson);
			if($_SESSION['processed_messages'][$sMessageId] === true) {
				return;
			}
		}

		$sText = $this->getMessageText($aJson);
		if($sText[0] == '/') {
			list($sMethod, $aParts) = $this->getMethodAndArguments($sText);
		}
		elseif($this->storedMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson))) {
			list($sMethod, $aParts) = $this->recursivelyGetMethodAndArguments($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
			$aParts[] = $sText;
		}
		else {
			$sMethod = 'catchAll';
			$aParts = array($sText);
		}

		if(!empty($sMethod) && method_exists($this, $sMethod)) {
			$aRes = call_user_func_array(array(&$this, $sMethod), array_merge(array($aJson), $aParts));
		}
		else {
			$aRes = $this->logMessage($sJson);
		}

		if($this->bUseSessions) {
			$_SESSION['processed_messages'][$sMessageId] = true;
		}

		return $aRes;
	}

	/**
	 * Retrieves the message's chat id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function isResult($aJson) {
		return array_key_exists('result', $aJson);
	}

	/**
	 * Retrieves the message's chat id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getChatId($aJson) {
		return $aJson[$this->isResult($aJson)?'result':'message']['chat']['id'];
	}

	/**
	 * Retrieves the message's id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getMessageId($aJson) {
		return $aJson[$this->isResult($aJson)?'result':'message']['message_id'];
	}

	/**
	 * Retrieves the message's text
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getMessageText($aJson) {
		return $aJson[$this->isResult($aJson)?'result':'message']['text'];
	}

	/**
	 * Retrieves the original message's id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getReplyToMessageId($aJson) {
		return $aJson[$this->isResult($aJson)?'result':'message']['reply_to_message']['message_id'];
	}

	/**
	 * Logs unmatched a message
	 * @param   $message    string  the message
	 * @returns bool
	 */
	protected function logMessage($sMessage) {
		$sDir = __DIR__.'/../logs/';
		if(!file_exists($sDir)) {
			mkdir($sDir, 0777, true);
		}

		return file_put_contents($sDir.get_called_class().'.json', $sMessage."\n", FILE_APPEND);
	}

	/**
	 * Extracts the method and the arguments from the message
	 * @param   $message    string  the message
	 * @returns array
	 */
	protected function getMethodAndArguments($sMessage) {
		if(preg_match('/^\/(\S+)\s*(.*)$/', $sMessage, $aMatch)) {
			$aParts = array();

			//check if botname has been specified
			if(strpos($aMatch[1], '@') === false) {
				$sMethod = $aMatch[1];
				$sPart = $aMatch[2];
			}
			else {
				$aTemp = explode('@', $aMatch[1]);
				if($aTemp[1] == $this->aConfig['BOT_NAME']) {
					$sMethod = $aTemp[0];
					$sPart = $aMatch[2];
				}
				else {
					$sMethod = null;
					$sPart = $aMatch[0];
				}
			}

			if(!empty($sPart)) {
				$aParts[] = $sPart;
			}
			return array($sMethod, $aParts);
		}
		else {
			return array(null, array($sMessage));
		}
	}

	/**
	 * Checks if a message has been stored (for example for a Forced Reply)
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns bool
	 */
	protected function storedMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			return array_key_exists('m'.$sMessageId, $_SESSION);
		}
		else {
			return file_exists(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json');
		}
	}

	/**
	 * Stores the message structure for later check
	 * @param   $json   string  the message structure
	 */
	protected function storeMessage($aJson) {
		if($this->bUseSessions) {
			$_SESSION['m'.$this->getMessageId($aJson)] = $aJson;
			return true;
		}
		else {
			$sDir = __DIR__.'/../stored_messages/'.$this->getChatId($aJson);
			if(!file_exists($sDir)) {
				mkdir($sDir, 0777, true);
			}

			return file_put_contents($sDir.'/'.$this->getMessageId($aJson).'.json', json_encode($aJson));
		}
	}

	/**
	 * Retrieves the command and the arguments starting from the given message until the first chained message
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns array
	 */
	protected function recursivelyGetMethodAndArguments($sChatId, $sMessageId, $aParts = array()) {
		$sMethod = null;

		while($this->storedMessage($sChatId, $sMessageId)) {
			$aJson = $this->retrieveMessage($sChatId, $sMessageId);

			if(!$this->isResult($aJson)) {
				list($sMethod, $aNewParts) = $this->getMethodAndArguments($this->getMessageText($aJson));
				$aParts = array_merge($aNewParts, $aParts);
			}

			$sMessageId = $this->getReplyToMessageId($aJson);
		}

		return array($sMethod, $aParts);
	}

	/**
	 * Retrieves the message from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns array
	 */
	protected function retrieveMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			return $_SESSION['m'.$sMessageId];
		}
		else {
			return json_decode(file_get_contents(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json'), true);
		}
	}

	/**
	 * Deletes all chained messages from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns bool
	 */
	protected function recursivelyDeleteMessage($sChatId, $sMessageId) {
		while($this->storedMessage($sChatId, $sMessageId)) {
			$aJson = $this->retrieveMessage($sChatId, $sMessageId);

			if(!$this->deleteMessage($sChatId, $sMessageId)) {
				return false;
			}

			$sMessageId = $this->getReplyToMessageId($aJson);
		}

		return true;
	}

	/**
	 * Deletes the message from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns bool
	 */
	protected function deleteMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			unset($_SESSION['m'.$sMessageId]);
			return true;
		}
		else {
			return unlink(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json');
		}
	}

	/**
	 * Retrieves a file
	 * @param   $file_id        string  the file's id
	 * @returns string
	 */
	protected function getFile($sFileId) {
		$aResult = $this->callTelegram('getFile', array(
			'file_id' => $sFileId
		));

		if($aResult['ok']) {
			//download the file to a temp path
			$sTempFile = tempnam(null, 'file');
			file_put_contents($sTempFile, file_get_contents('https://api.telegram.org/file/bot'.$this->aConfig['HTTP_TOKEN'].'/'.$aResult['result']['file_path']));
			return $sTempFile;
		}
		
		return false;
	}

	/**
	 * Sends a message to a specified chat
	 * @param   $chat_id        string  the chat's id
	 * @param   $message        string  the message
	 * @param   $reply_id       string  the message being replied
	 * @param   $forced_reply   bool    tells if the user MUST reply
	 * @param   $preview        bool    tells if the message will contains a preview
	 * @param	$parse_mode		string	tells what kind of markup is used on the message
	 * @param	$keyboard		array	matrix representing the user keyboard
	 * @returns array
	 */
	protected function sendMessage($sChatId, $sMessage, $sReplyId = null, $bForceReply = false, $bPreview = true, $sParseMode = null, $aKeyboard = null) {
		$aParams = array(
			'chat_id' => $sChatId,
			'text' => $sMessage,
		);

		if(!is_null($sReplyId)) {
			$aParams['reply_to_message_id'] = $sReplyId;
		}

		if($bForceReply) {
			$aParams['reply_markup'] = '{"force_reply":true,"selective":true}';
		}

		if(!$bPreview) {
			$aParams['disable_web_page_preview'] = 'true';
		}

		if(!is_null($sParseMode)) {
			switch($sParseMode) {
				case 'Markdown':
				case 'HTML':
					$aParams['parse_mode'] = $sParseMode;
					break;
				default:
					throw new Exception('Invalid parse_mode: '.$sParseMode);
			}
		}

		if(!is_null($aKeyboard)) {
			$aParams['reply_markup'] = json_encode($aKeyboard);
		}

		return $this->callTelegram('sendMessage', $aParams);
	}

	/**
	 * Sends a photo to a specified chat
	 * @param   $chat_id        string  the chat's id
	 * @param   $photo          string  the photo
	 * @param   $caption        string  the caption
	 * @param   $reply_id       string  the message being replied
	 * @param   $forced_reply   bool    tells if the user MUST reply
	 * @param   $preview        bool    tells if the preview must be shown
	 * @returns array
	 */
	protected function sendPhoto($sChatId, $sPhoto, $sCaption = null, $sReplyId = null, $bForceReply = false, $bPreview = true) {
		//download remote files to temp file
		if(substr($sPhoto, 0, 4) == 'http') {
			$sTempFile = tempnam(sys_get_temp_dir(), 'img');
			File_put_contents($sTempFile, file_get_contents($sPhoto));

			//Telegram pretends the file with the correct extension
			if(preg_match('/(\.\w+)[^\.]*$/', $sPhoto, $aMatch)) {
				rename($sTempFile, $sTempFile.$aMatch[1]);
				$sTempFile .= $aMatch[1];
				$sPhoto = $sTempFile;
			}
		}
		else {
			$sTempFile = false;
		}

		$aParams = array(
			'chat_id' => $sChatId,
			'photo' => new CurlFile($sPhoto),
		);

		if(!is_null($sCaption)) {
			$aParams['caption'] = $sCaption;
		}

		if(!is_null($sReplyId)) {
			$aParams['reply_to_message_id'] = $sReplyId;
		}

		if($bForceReply) {
			$aParams['reply_markup'] = '{"force_reply":true,"selective":true}';
		}

		if(!$bPreview) {
			$aParams['disable_web_page_preview'] = 'true';
		}

		$aRes = $this->callTelegram('sendPhoto', $aParams);

		if($sTempFile) {
			unlink($sTempFile);
		}

		return $aRes;
	}

	/**
	 * Sends an audio message to a specified chat
	 * @param   $chat_id        string  the chat's id
	 * @param   $audio          string  the audio file
	 * @param   $duration       int     the duration of the audio
	 * @param   $performer      string  the performer of the audio file
	 * @param   $title          string  the title of the audio file
	 * @param   $reply_id       string  the message being replied
	 * @param   $forced_reply   bool    tells if the user MUST reply
	 * @returns array
	 */
	protected function sendAudio($sChatId, $sAudio, $iDuration = null, $sPerformer = null, $sTitle = null, $sReplyId = null, $bForceReply = false) {
		//download remote files to temp file
		if(substr($sAudio, 0, 4) == 'http') {
			$sTempFile = tempnam(sys_get_temp_dir(), 'audio');
			File_put_contents($sTempFile, file_get_contents($sAudio));

			//Telegram pretends the file with the correct extension
			if(preg_match('/(\.\w+)[^\.]*^/', $sAudio, $aMatch)) {
				rename($sTempFile, $sTempFile.$aMatch[1]);
				$sTempFile .= $aMatch[1];
				$sAudio = $sTempFile;
			}
		}
		else {
			$sTempFile = false;
		}

		$aParams = array(
			'chat_id' => $sChatId,
			'audio' => new CurlFile($sAudio),
		);

		if(!is_null($iDuration)) {
			$aParams['duration'] = $iDuration;
		}

		if(!is_null($sPerformer)) {
			$aParams['performer'] = $sPerformer;
		}

		if(!is_null($sTitle)) {
			$aParams['title'] = $sTitle;
		}

		if(!is_null($sReplyId)) {
			$aParams['reply_to_message_id'] = $sReplyId;
		}

		if($bForceReply) {
			$aParams['reply_markup'] = '{"force_reply":true,"selective":true}';
		}

		$aRes = $this->callTelegram('sendAudio', $aParams);

		if($sTempFile) {
			unlink($sTempFile);
		}

		return $aRes;
	}

	/**
	 * Sends a voice message to a specified chat
	 * @param   $chat_id        string  the chat's id
	 * @param   $voice          string  the voice file
	 * @param   $duration       int     the duration of the voice message
	 * @param   $reply_id       string  the message being replied
	 * @param   $forced_reply   bool    tells if the user MUST reply
	 * @returns array
	 */
	protected function sendVoice($sChatId, $sVoice, $iDuration = null, $sReplyId = null, $bForceReply = false) {
		//download remote files to temp file
		if(substr($sVoice, 0, 4) == 'http') {
			$sTempFile = tempnam(sys_get_temp_dir(), 'voice');
			File_put_contents($sTempFile, file_get_contents($sVoice));

			//Telegram pretends the file with the correct extension
			if(preg_match('/(\.\w+)[^\.]*^/', $sVoice, $aMatch)) {
				rename($sTempFile, $sTempFile.$aMatch[1]);
				$sTempFile .= $aMatch[1];
				$sVoice = $sTempFile;
			}
		}
		else {
			$sTempFile = false;
		}

		$aParams = array(
			'chat_id' => $sChatId,
			'voice' => new CurlFile($sVoice),
		);

		if(!is_null($iDuration)) {
			$aParams['duration'] = $iDuration;
		}

		if(!is_null($sReplyId)) {
			$aParams['reply_to_message_id'] = $sReplyId;
		}

		if($bForceReply) {
			$aParams['reply_markup'] = '{"force_reply":true,"selective":true}';
		}

		$aRes = $this->callTelegram('sendVoice', $aParams);

		if($sTempFile) {
			unlink($sTempFile);
		}

		return $aRes;
	}

	/**
	 * Sends a document to a specified chat
	 * @param   $chat_id        string  the chat's id
	 * @param   $document       string  the document
	 * @param   $reply_id       string  the message being replied
	 * @param   $forced_reply   bool    tells if the user MUST reply
	 * @returns array
	 */
	protected function sendDocument($sChatId, $sDocument, $sReplyId = null, $bForceReply = false) {
		//download remote files to temp file
		if(substr($sDocument, 0, 4) == 'http') {
			$sTempFile = tempnam(sys_get_temp_dir(), 'doc');
			File_put_contents($sTempFile, file_get_contents($sDocument));

			//Telegram pretends the file with the correct extension
			if(preg_match('/(\.\w+)[^\.]*$/', $sDocument, $aMatch)) {
				rename($sTempFile, $sTempFile.$aMatch[1]);
				$sTempFile .= $aMatch[1];
				$sDocument = $sTempFile;
			}
		}
		else {
			$sTempFile = false;
		}

		$aParams = array(
			'chat_id' => $sChatId,
			'document' => new CurlFile($sDocument),
		);

		if(!is_null($sReplyId)) {
			$aParams['reply_to_message_id'] = $sReplyId;
		}

		if($bForceReply) {
			$aParams['reply_markup'] = '{"force_reply":true,"selective":true}';
		}

		$aRes = $this->callTelegram('sendDocument', $aParams);

		if($sTempFile) {
			unlink($sTempFile);
		}

		return $aRes;
	}

	/**
	 * Sets an action for the specified chat
	 * @param   $chat_id    string  the chat's id
	 * @param   $action     string  the action
	 * @returns array
	 */
	protected function sendChatAction($sChatId, $sAction) {
		$aPossibleActions = array('typing' ,'upload_photo' ,'record_video' ,'upload_video' ,'record_audio' ,'upload_audio' ,'upload_document' ,'find_location');
		if(in_array($sAction, $aPossibleActions)) {
			return $this->callTelegram('sendChatAction', array(
				'chat_id' => $sChatId,
				'action' => $sAction,
			));
		}
		else {
			return false;
		}
	}

	/**
	 * Sends a command to the Telegram server
	 * @param   $method     string  the chat's id
	 * @param   $arguments  string  the message
	 * @returns array
	 */
	protected function callTelegram($sMethod, $aArguments) {
		$rCurl = curl_init();
		curl_setopt_array($rCurl, array(
			CURLOPT_URL => 'https://api.telegram.org/bot'.$this->aConfig['HTTP_TOKEN'].'/'.$sMethod,
			CURLOPT_RETURNTRANSFER => true,

			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $aArguments
		));
		$sResponse = curl_exec($rCurl);

		if($sResponse === false) {
			$aRes = array(
				'ok' => false,
				'description' => curl_error($rCurl)
			);
		}
		else {
			$aRes = json_decode($sResponse, true);
		}
		curl_close($rCurl);

		return $aRes;
	}

	/**
	 * Sends a command to the Telegram server
	 * @param   $method     string  the chat's id
	 * @param   $arguments  string  the message
	 * @returns array
	 */
	protected function getMediaList($sFilter = '*') {
		$aFiles = glob(__DIR__.'/../media/'.get_called_class().'/'.$sFilter);

		$aRes = array();
		foreach($aFiles as $sFile) {
			$aRes[] = basename($sFile);
		}

		return $aRes;
	}

	/**
	 * Sends a command to the Telegram server
	 * @param   $method     string  the chat's id
	 * @param   $arguments  string  the message
	 * @returns array
	 */
	protected function getMedia($sFilename) {
		return __DIR__.'/../media/'.get_called_class().'/'.$sFilename;
	}

	/**
	 * Sends a suggestion to the Bot developer
	 * @param   $json       Array   the message received
	 * @param   $suggestion string  the suggestion
	 * @returns array
	 */
	protected function suggest($aJson, $sSuggestion = null) {
		if(empty($sSuggestion)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Now insert the suggestion text', $this->getMessageId($aJson), true));
		}

		$this->sendMessage($this->aConfig['DEVELOPER_CHAT_ID'], 'New suggestion received from ['.$aJson['message']['from']['first_name'].' '.$aJson['message']['from']['last_name'].'](tg://user?id='.$aJson['message']['from']['id'].'): '.$sSuggestion, null, false, true, 'Markdown');
		$this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));

		return $this->sendMessage($this->getChatId($aJson), 'Thank you for your suggestion.');
	}

	/**
	 * Describes the Bot
	 * @param   $json       Array   the message received
	 * @returns array
	 */
	abstract protected function about($aJson);

	/**
	 * Shows the usage message
	 * @param   $json       Array   the message received
	 * @returns array
	 */
	abstract protected function help($aJson);
}
