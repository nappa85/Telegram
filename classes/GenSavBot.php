<?php

require_once('HTMLtoImageBot.php');

/**
 * GenSav Bot class
 */
class GenSavBot extends HTMLtoImageBot {
    protected $aKeywords = array(
        'zingar',
        'negr',
        'rom',
        'rusp',
        'immigrat',
        'clandestin',
        'arab',
        'cines',
        'rumen',
        'slav',
        'razzist',
        'barcon',
        'drog',
        'stupr',
        'ladr',
        'padani',
    );
    protected $sUrl = 'http://gensav.altervista.org/';

    protected function about($aJson) {
        return $this->sendMessage($this->getChatId($aJson), "Questo bot è basato sull'ottimo lavoro dei ragazzi di http://gensav.altervista.org/\nTutti i crediti vanno a loro.\n\nSviluppato da @Nappa85 su licenza GPLv4\nCodice sorgente: https://github.com/nappa85/Telegram");
    }

    protected function help($aJson) {
        $this->sendMessage($this->getChatId($aJson), "@GenSavBot non accetta comandi. @GenSavBot vi ascolta, e vi giudica.\n\n/about - per avere informazioni sul bot\n/help - stampa questo messaggio\n/suggest - permette di inviare suggerimenti allo sviluppatore");
    }

    protected function start($aJson) {
        $this->help($aJson);
    }

    protected function catchAll($aJson, $sText) {
        if(preg_match('/('.implode('|', $this->aKeywords).')/i', $sText)) {
            $sChatId = $this->getChatId($aJson);

            $this->sendChatAction($sChatId, 'upload_photo');

            $sHTML = file_get_contents($this->sUrl);

            //clean html from undesired parts
            $aParts = explode('</div>', $sHTML);
            $aParts[0] = substr($aParts[0], 0, strpos($aParts[0], '<div')).'<div>';
            unset($aParts[1]);
            $iCount = count($aParts);
            $aParts[$iCount] = '</div></body></html>';
            for($i = $iCount - 1; $i > $iCount - 8; $i--) {
                unset($aParts[$i]);
            }

            $sHTML = implode('</div>', $aParts);

            //replace relative links with absolute equivalent
            $sHTML = preg_replace_callback('/[^\S]+(src|href)\=\"([^\"]+)\"/', array(&$this, '_absolutizeURL'), $sHTML);

            //htmlentities
            //$sHTML = str_replace(array('è', 'é', 'ò', 'à', 'ù', 'ì', 'È', 'É', 'Ò', 'À', 'Ù', 'Ì'), array('&egrave;', '&eacute;', '&ograve;', '&agrave;', '&ugrave;', '&igrave;', '&Egrave;', '&Eacute;', '&Ograve;', '&Agrave;', '&Ugrave;', '&Igrave;'), $sHTML);

            $aRes = $this->_convertAndPost($sChatId, $sHTML);

            return $aRes;
        }
    }

    protected function _absolutizeURL($aMatch) {
        if(strpos($aMatch[2], 'http') === 0) {
            return $aMatch[0];
        }
        else {
            return str_replace($aMatch[2], $this->sUrl.$aMatch[2], $aMatch[0]);
        }
    }
}

$oBot = new GenSavBot('1972rty4297hgf9_23ug42386t_20gh86');
$oBot->parse('{"message":{"text":"/help@GenSavBot","chat":{"id":25900594}}}');
