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
            return call_user_func_array(array(&$this, $sMethod), array_merge(array($aJson), $aParts));
        }
        else {
            return $this->logMessage($sJson);
        }
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
        if(preg_match('/^\/([^\s\@]+)\S*\s*(.*)$/', $sMessage, $aMatch)) {
            $aParts = array();
            if(!empty($aMatch[2])) {
                $aParts[] = $aMatch[2];
            }
            return array($aMatch[1], $aParts);
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
     * Sends a message to a specified chat
     * @param   $chat_id        string  the chat's id
     * @param   $message        string  the message
     * @param   $reply_id       string  the message being replied
     * @param   $forced_reply   bool    tells if the user MUST reply
     * @returns string
     */
    protected function sendMessage($sChatId, $sMessage, $sReplyId = null, $bForceReply = false, $bPreview = true) {
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

        return $this->callTelegram('sendMessage', $aParams);
    }

    /**
     * Sends a command to the Telegram server
     * @param   $method     string  the chat's id
     * @param   $arguments  string  the message
     * @returns string
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
        curl_close($rCurl);

        return json_decode($sResponse, true);
    }

    protected function suggest($aJson, $sSuggestion) {
        if(empty($sSuggestion)) {
            $this->storeMessage($aJson);
            return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Now insert the suggestion text', $this->getMessageId($aJson), true));
        }

        $this->sendMessage($this->aConfig['DEVELOPER_CHAT_ID'], 'New suggestion received from '.(empty($aJson['message']['from']['username'])?$aJson['message']['from']['first_name'].' '.$aJson['message']['from']['last_name'].' ('.$aJson['message']['from']['id'].')':'@'.$aJson['message']['from']['username']).': '.$sSuggestion);
        $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));

        return $this->sendMessage($this->getChatId($aJson), 'Thank you for your suggestion.');
    }

    abstract protected function about($aJson);

    abstract protected function help($aJson);
}
