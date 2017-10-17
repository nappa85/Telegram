<?php

require_once('Bot.php');

/**
* Swearing Bot class
*/
class BlasphemyBot extends Bot {
	protected function about($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "This bot can help you when you need to swear but you're out of words.\n\nDeveloped by @Nappa85 under GPLv4\nSource code: https://github.com/nappa85/Telegram");
	}

	protected function help($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "/swear - A generic swear\n\n/swearto - Swear about your favourite subject\nYou can pass an inline argument, or call the command and insert the subject when asked.\nFor example:\n/swearto the developer of @BlasphemyBot\n\n/blackhumor - Some good old black humor\n\n/suggest - Suggest an improvement to the developer\nYou can pass an inline argument, or call the command and insert the subject when asked.\nFor example:\n/suggest I have a new blackhumor line for you!");
	}

	/**
	* Sends a generic swear
	* @param   $json   array   the user message
	*/
	protected function swear($aJson) {
		return $this->sendMessage($this->getChatId($aJson), $this->getRandomWordA().$this->getRandomWordB().$this->getRandomWordC());
	}

	/**
	* Sends a swear about a user defined subject
	* @param   $json       array   the user message
	* @param   $subject    string  the subject
	*/
	protected function swearto($aJson, $sSubject = null) {
		if(empty($sSubject)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Now insert a Subject for the swear', $this->getMessageId($aJson), true));
		}
		else {
			$this->sendMessage($this->getChatId($aJson), $this->getRandomWordA().$sSubject.$this->getRandomWordC());
			return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
		}
	}

	protected function blackhumor($aJson) {
		return $this->sendMessage($this->getChatId($aJson), $this->getRandomBlackHumor());
	}

	/**
	* Retrieves a random word from the first set
	*/
	protected function getRandomWordA() {
		return $this->getRandom('WordsA');
	}

	/**
	* Retrieves a random word from the second set
	*/
	protected function getRandomWordB() {
		return $this->getRandom('WordsB');
	}

	/**
	* Retrieves a random word from the third set
	*/
	protected function getRandomWordC() {
		return $this->getRandom('WordsC');
	}

	/**
	* Retrieves a random phrase from the black humor set
	*/
	protected function getRandomBlackHumor() {
		return $this->getRandom('BlacHumor');
	}

	/**
	* Retrieves a random word from the specified set
	* @param   $var_name   string  the name of the set
	*/
	protected function getRandom($sVarName) {
		return $this->aConfig[$sVarName][rand(0, count($this->aConfig[$sVarName]) - 1)];
	}
}
