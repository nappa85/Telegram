<?php

require_once(__DIR__.'/Session.php');

/**
 * Base Bot class
 */
abstract class Bot {
	/**
	 * Telegram message max lenght
	 * @type int
	 */
	protected const MAX_LENGTH = 4096;

	/*
	 * Bot's configuration
	 * @type array
	 */
	protected $aConfig;

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

		$oSession = Session::getSingleton(get_called_class(), $this->getChatId($aJson));

		$sMessageId = $this->getMessageId($aJson);
		if($oSession->isAlreadyProcessed($sMessageId)) {
			return;
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

		$oSession->setAsProcessed($sMessageId);

		return $aRes;
	}

	/**
	 * Retrieves the message's entity type
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getEntityType($aJson) {
		switch(true) {
			case array_key_exists('result', $aJson):
				return 'result';
			case array_key_exists('message', $aJson):
				return 'message';
			case array_key_exists('callback_query', $aJson):
				return 'callback_query';
			default:
				throw new Exception('Unknown entity type '.json_encode($aJson));
		}
	}

	/**
	 * Retrieves the message's entity
	 * @param   $message    array   the message
	 * @returns array
	 */
	protected function &getEntity($aJson) {
		switch(true) {
			case array_key_exists('result', $aJson):
				return $aJson['result'];
			case array_key_exists('message', $aJson):
				return $aJson['message'];
			case array_key_exists('callback_query', $aJson):
				return $aJson['callback_query']['message'];
			default:
				throw new Exception('Unknown entity type '.json_encode($aJson));
		}
	}

	/**
	 * Retrieves the message's chat id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getChatId($aJson) {
		return $this->getEntity($aJson)['chat']['id'];
	}

	/**
	 * Retrieves the message's id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getMessageId($aJson) {
		return $this->getEntity($aJson)['message_id'];
	}

	/**
	 * Retrieves the message's text
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getMessageText($aJson) {
		return $this->getEntity($aJson)['text'];
	}

	/**
	 * Retrieves the original message's id
	 * @param   $message    array   the message
	 * @returns string
	 */
	protected function getReplyToMessageId($aJson) {
		return $this->getEntity($aJson)['reply_to_message']['message_id'];
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
		$oSession = Session::getSingleton();
		return $oSession->storedMessage($sChatId, $sMessageId);
	}

	/**
	 * Stores the message structure for later check
	 * @param   $json   string  the message structure
	 */
	protected function storeMessage($aJson) {
		$oSession = Session::getSingleton();
		return $oSession->storeMessage($this->getChatId($aJson), $this->getMessageId($aJson), $aJson);
	}

	/**
	 * Retrieves the command and the arguments starting from the given message until the first chained message
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns array
	 */
	protected function recursivelyGetMethodAndArguments($sChatId, $sMessageId, $aParts = array()) {
		$oSession = Session::getSingleton();
		$sMethod = null;

		while($oSession->storedMessage($sChatId, $sMessageId)) {
			$aJson = $oSession->retrieveStoredMessage($sChatId, $sMessageId);

			if($this->getEntityType($aJson) != 'result') {
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
	protected function retrieveStoredMessage($sChatId, $sMessageId) {
		$oSession = Session::getSingleton();
		return $oSession->retrieveStoredMessage($sChatId, $sMessageId);
	}

	/**
	 * Deletes all chained messages from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns bool
	 */
	protected function recursivelyDeleteStoredMessage($sChatId, $sMessageId) {
		$oSession = Session::getSingleton();

		while($oSession->storedMessage($sChatId, $sMessageId)) {
			$aJson = $oSession->retrieveStoredMessage($sChatId, $sMessageId);

			if(!$oSession->deleteStoredMessage($sChatId, $sMessageId)) {
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
	protected function deleteStoredMessage($sChatId, $sMessageId) {
		$oSession = Session::getSingleton();
		return $oSession->deleteStoredMessage($sChatId, $sMessageId);
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

		//the message is too long, we need to split it
		if(strlen($sMessage) > self::MAX_LENGTH) {
			$aMessage = explode("\n", $sMessage);
			$aMessages = array();
			$iIndex = 0;
			foreach($aMessage as $sText) {
				if(strlen($sText) > self::MAX_LENGTH) {
					throw new Exception('Message too long');
				}
				if(strlen($aMessages[$iIndex].(empty($aMessages[$iIndex])?'':"\n").$sText) > self::MAX_LENGTH) {
					$iIndex++;
				}
				$aMessages[$iIndex] .= (empty($aMessages[$iIndex])?'':"\n").$sText;
			}

			$aResults = array();
			foreach($aMessages as $sMessage) {
				$aParams['text'] = $sMessage;
				$aResults[] = $this->callTelegram('sendMessage', $aParams);
			}
			return $aResults;
		}
		else {
			return $this->callTelegram('sendMessage', $aParams);
		}
	}

	protected function deleteMessage($sChatId, $sMessageId) {
		return $this->callTelegram('deleteMessage', array(
			'chat_id' => $sChatId,
			'message_id' => $sMessageId
		));
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

		$aEntity = $this->getEntity($aJson);
		$this->sendMessage($this->aConfig['DEVELOPER_CHAT_ID'], 'New suggestion received from ['.$aEntity['from']['first_name'].' '.$aEntity['from']['last_name'].'](tg://user?id='.$aEntity['from']['id'].'): '.$sSuggestion, null, false, true, 'Markdown');
		$this->recursivelyDeleteStoredMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));

		return $this->sendMessage($this->getChatId($aJson), 'Thank you for your suggestion.');
	}

	/**
	 * Perform optionals scheduled operations
	 */
	protected function cron() {
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
