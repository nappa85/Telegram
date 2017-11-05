<?php

class Session {

	/*
	 * Config flag to switch between php sessions and files storage
	 * @type bool
	 */
	protected $bUseSessions = true;

	/*
	 * Starts the session
	 * @param	$class		string	caller classname
	 * @param	$chat_id	string	chat id
	 */
	public function __construct($sClass, $sChaId) {
		if($this->bUseSessions) {
			session_id($sClass.$sChaId);
			session_start();
		}
	}

	/*
	 * Ensures the Session is started once
	 * @param	$class		str9jg	caller classname
	 * @param	$chat_id	string	chat id
	 */
	public static function &getSingleton($sClass = null, $sChaId = null) {
		static $oInstance = null;

		if(is_null($oInstance)) {
			$oInstance = new Session($sClass, $sChaId);
		}

		return $oInstance;
	}

	/*
	 * Checks if the message has already been processed
	 * @param	$message_id	string	message id
	 */
	 function isAlreadyProcessed($sMessageId) {
		if($this->bUseSessions) {
			return array_key_exists($sMessageId, $_SESSION['processed_messages']) && ($_SESSION['processed_messages'][$sMessageId] === true);
		}
		else {
			return file_exists(__DIR__.'/../processed_messages/'.$sMessageId);
		}
	}

	/*
	 * Checks if the message has already been processed
	 * @param	$message_id	string	message id
	 */
	public function setAsProcessed($sMessageId) {
		if($this->bUseSessions) {
			$_SESSION['processed_messages'][$sMessageId] = true;
		}
		else {
			$sDir = __DIR__.'/../processed_messages/';
			if(!file_exists($sDir)) {
				mkdir($sDir, 0777, true);
			}

			touch($sDir.$sMessageId);
		}
	}

	/**
	 * Checks if a key-value pair has been stored
	 * @param   $chat_id	string  the chat's id
	 * @param   $key		string  key
	 * @returns bool
	 */
	public function storedValue($sChatId, $sKey) {
		if($this->bUseSessions) {
			return array_key_exists('v'.$sKey, $_SESSION);
		}
		else {
			return file_exists(__DIR__.'/../stored_values/'.$sChatId.'/'.$sKey.'.json');
		}
	}

	/**
	 * Stores a generic key-value pair
	 * @param   $chat_id    string  the chat's id
	 * @param   $key		string  key
	 * @param   $value		string  value
	 */
	public function storeValue($sChatId, $sKey, $sValue) {
		if($this->bUseSessions) {
			$_SESSION['v'.$sKey] = $sValue;
		}
		else {
			$sDir = __DIR__.'/../stored_values/'.$sChatId;
			if(!file_exists($sDir)) {
				mkdir($sDir, 0777, true);
			}

			file_put_contents($sDir.'/'.$sKey.'.json', json_encode($sValue));
		}
	}

	/**
	 * Retrieve a generic key-value pair
	 * @param   $chat_id    string  the chat's id
	 * @param   $key		string  key
	 * @returns	string
	 */
	public function retrieveValue($sChatId, $sKey) {
		if($this->bUseSessions) {
			return $_SESSION['v'.$sKey];
		}
		else {
			return json_decode(file_get_contents(__DIR__.'/../stored_values/'.$sChatId.'/'.$sKey.'.json'), true);
		}
	}

	/**
	 * Deletes a generic key-value pair
	 * @param   $chat_id    string  the chat's id
	 * @param   $key		string  key
	 */
	public function deleteValue($sChatId, $sKey) {
		if($this->bUseSessions) {
			unset($_SESSION['v'.$sKey]);
		}
		else {
			unlink(__DIR__.'/../stored_values/'.$sChatId.'/'.$sKey.'.json');
		}
	}

	/**
	 * Checks if a message has been stored
	 * @param   $chat_id	string  the chat's id
	 * @param   $message_id	string  the message's id
	 * @returns bool
	 */
	public function storedMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			return array_key_exists('m'.$sMessageId, $_SESSION);
		}
		else {
			return file_exists(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json');
		}
	}

	/**
	 * Stores the message structure for later check
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @param   $json   array  the message structure
	 */
	public function storeMessage($sChatId, $sMessageId, $aJson) {
		if($this->bUseSessions) {
			$_SESSION['m'.$sMessageId] = $aJson;
			return true;
		}
		else {
			$sDir = __DIR__.'/../stored_messages/'.$sChatId;
			if(!file_exists($sDir)) {
				mkdir($sDir, 0777, true);
			}

			return file_put_contents($sDir.'/'.$sMessageId.'.json', json_encode($aJson));
		}
	}

	/**
	 * Retrieves the message from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns array
	 */
	public function retrieveStoredMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			return $_SESSION['m'.$sMessageId];
		}
		else {
			return json_decode(file_get_contents(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json'), true);
		}
	}

	/**
	 * Deletes the message from the storage
	 * @param   $chat_id    string  the chat's id
	 * @param   $message_id string  the message's id
	 * @returns bool
	 */
	public function deleteStoredMessage($sChatId, $sMessageId) {
		if($this->bUseSessions) {
			unset($_SESSION['m'.$sMessageId]);
			return true;
		}
		else {
			return unlink(__DIR__.'/../stored_messages/'.$sChatId.'/'.$sMessageId.'.json');
		}
	}
}
