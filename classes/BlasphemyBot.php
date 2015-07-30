<?php

require_once('Bot.php');

/**
 * Swearing Bot class
 */
class BlasphemyBot extends Bot {
    protected function about($aJson) {
        return $this->sendMessage($this->getChatId($aJson), "This bot can help you when you need to swear but you're out of words.\nDeveloped by @Nappa85");
    }

    protected function help($aJson) {
        return $this->sendMessage($this->getChatId($aJson), "/swear - A generic swear\n/swearto - Swear about your favourite subject\n/blackhumor - Some good old black humor\n/suggest - Suggest an improvement to the developer");
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
    protected function swearto($aJson, $sSubject) {
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
