<?php

require_once('Bot.php');

/**
 * Meme Generator Bot class
 */
class MemeGeneratorBot extends Bot {
    protected function about($aJson) {
        return $this->sendMessage($this->getChatId($aJson), "This bot gives you the ability to create Memes on the fly.\nDeveloped by @Nappa85");
    }

    protected function help($aJson) {
        return $this->sendMessage($this->getChatId($aJson), "/listMemes - List avaiable Memes (you can specify the length of the list, default is 10, or a string to filter by)\n/newMeme - Generate a new Meme\n/suggest - Suggest an improvement to the developer");
    }

    protected function _getList() {
        return json_decode(file_get_contents('https://api.imgflip.com/get_memes'), true);
    }

    public function _getTemplateId($sSearch) {
        $aMemes = $this->_getList();

        //doesn't affects numeric strings
        $sSearch = preg_replace('/\W+/', '', strtolower($sSearch));

        foreach($aMemes['data']['memes'] as $aMeme) {
            if(($aMeme['id'] == $sSearch) || ($sSearch == preg_replace('/\W+/', '', strtolower($aMeme['name'])))) {
                return $aMeme['id'];
            }
        }

        return false;
    }

    /**
     * List avaiable Memes
     * @param   $json   array   the user message
     */
    protected function listMemes($aJson, $iLimit = 10) {
        $aMemes = $this->_getList();

        if(is_numeric($iLimit)) {
            $sSearch = null;
            $iLimit = (int)$iLimit;
        }
        else {
            $sSearch = preg_replace('/\W+/', '', strtolower($iLimit));
            $iLimit = 10;
        }

        $aOut = array();
        $iCount = 0;
        foreach($aMemes['data']['memes'] as $aMeme) {
            if($iCount >= $iLimit) {
                break;
            }

            if(empty($sSearch) || (strpos(preg_replace('/\W+/', '', strtolower($aMeme['name'])), $sSearch) !== false)) {
                $aOut[] = $aMeme['name'].' ('.$aMeme['id'].') '.$aMeme['url'];
                $iCount++;
            }
        }

        return $this->sendMessage($this->getChatId($aJson), empty($aOut)?'No Memes found':implode("\n", $aOut), null, false, false);
    }

    /**
     * Generate a new Meme
     * @param   $json   array   the user message
     */
    protected function newMeme($aJson) {
        $aParams = $this->aConfig['params'];

        $aArgs = func_get_args();
        $iCount = count($aArgs);
        if($iCount > 1) {
            //scan last 3 parameters
            for($i = ($iCount > 3?$iCount - 3:1); $i < $iCount; $i++) {
                if(empty($aParams['template_id']) && ($iTemplateId = $this->_getTemplateId($aArgs[$i]))) {
                    $aParams['template_id'] = $iTemplateId;
                }
                elseif(!empty($aParams['template_id']) && empty($aParams['text0'])) {
                    $aParams['text0'] = $aArgs[$i];
                }
                elseif(!empty($aParams['template_id']) && !empty($aParams['text0']) && empty($aParams['text1'])) {
                    $aParams['text1'] = $aArgs[$i];
                }
            }
        }

        if(empty($aParams['template_id'])) {
            $this->storeMessage($aJson);
            return $this->storeMessage($this->sendMessage($this->getChatId($aJson), "Insert a meme name or ID\nYou can use /listMemes to retrieve a list of avaiable Memes\nThe list comes in \"name (ID) link\" format", $this->getMessageId($aJson), true));
        }
        elseif(empty($aParams['text0'])) {
            $this->storeMessage($aJson);
            return $this->storeMessage($this->sendMessage($this->getChatId($aJson), "Insert the top text for the Image (use \"-\" for empty string)", $this->getMessageId($aJson), true));
        }
        elseif(empty($aParams['text1'])) {
            $this->storeMessage($aJson);
            return $this->storeMessage($this->sendMessage($this->getChatId($aJson), "Insert the bottom text for the Image (use \"-\" for empty string)", $this->getMessageId($aJson), true));
        }

        if($aParams['text0'] == '-') {
            $aParams['text0'] = '';
        }
        if($aParams['text1'] == '-') {
            $aParams['text1'] = '';
        }

        $rCurl = curl_init();
        curl_setopt_array($rCurl, array(
            CURLOPT_URL => 'https://api.imgflip.com/caption_image',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $aParams
        ));
        $sResponse = curl_exec($rCurl);
        curl_close($rCurl);

        $aResponse = json_decode($sResponse, true);
        $this->sendMessage($this->getChatId($aJson), $aResponse['success']?$aResponse['data']['page_url']:$aResponse['error_message']);
        return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
    }
}
