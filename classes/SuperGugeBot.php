<?php

require_once(__DIR__.'/Bot.php');

/**
* SuperGugeBot class
*/
class SuperGugeBot extends Bot {
	protected function about($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "L'importante è sborrare!");
	}

	protected function help($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "SuperGugeBot non da spiegazioni.");
	}

	public function catchAll($aJson, $sText) {
		$sChatId = $this->getChatId($aJson);

		switch(rand(0, 10)) {
			case 0:
			case 1:
			case 2:
				return $this->_sendVoice($sChatId);
			case 3:
			case 4:
			case 5:
				return $this->_sendPorn($sChatId, $sText);
		}
	}

	protected function _sendVoice($sChatId) {
		$this->sendChatAction($sChatId, 'record_audio');

		$aMedias = $this->getMediaList('*.ogg');
		return $this->sendVoice($sChatId, $this->getMedia($aMedias[rand(0, count($aMedias) - 1)]));
	}

	protected function _sendPorn($sChatId, $sText) {
		$rCurl = curl_init();
		curl_setopt_array($rCurl, array(
			CURLOPT_URL => 'http://www.pornhub.com/gifs/search?search='.urlencode($sText),
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
			CURLOPT_REFERER => 'http://www.pornhub.com/gifs/',
		));
		$sResponse = curl_exec($rCurl);
		curl_close($rCurl);

		if(preg_match_all('/\<a href\=\"\/gif\/(\d+)\"\>/', $sResponse, $aMatches, PREG_PATTERN_ORDER)) {
			$iIndex = rand(0, count($aMatches[1]) - 1);

			$rCurl = curl_init();
			curl_setopt_array($rCurl, array(
				CURLOPT_URL => 'http://www.pornhub.com/gif/'.$aMatches[1][$iIndex],
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0',
				CURLOPT_REFERER => 'http://www.pornhub.com/gifs/search?search='.urlencode($sText),
			));
			$sResponse = curl_exec($rCurl);
			curl_close($rCurl);

			if(preg_match('/\<div[^\>]+data\-mp4\=\"([^\"]+)\"/', $sResponse, $aMatch)) {
				$this->sendChatAction($sChatId, 'upload_video');

				return $this->sendDocument($sChatId, $aMatch[1]);
			}
		}
	}
}
