<?php

require_once('Bot.php');

/**
 * GymHuntr Bot class
 */
class GymHuntrBot extends Bot {
	protected $sHashCheck = '57b34b3eca72eed3178b785dcca4289g4';//actually seems to be hardcoded
	protected $sMonster = '83jhs';//actually seems to be hardcoded

	protected function about($aJson) {
		return $this->sendMessage($this->getChatId($aJson), 'This bot is an interface to GymHuntr and allows basic queries on it', null, false, true, null, $this->getKeyboard());
	}

	protected function help($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "/start - to start the bot\n\n/listraid - Lists all RAID in the area\n\n/searchraid - Search for a given raid boss on the area\n\n/searchperson - Search for a given nickname on the area\n\n/suggest - Suggest an improvement to the developer\nYou can pass an inline argument, or call the command and insert the subject when asked.\nFor example:\n/suggest I have an idea for an improvement!", null, false, true, null, $this->getKeyboard());
	}

	protected function start($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "Welcome to GymHuntrBot!", null, false, true, null, $this->getKeyboard());
	}

	protected function catchAll($aJson, $sText) {
		if($aJson['message']['location']) {
			$this->sendChatAction($this->getChatId($aJson), 'typing');

			$_SESSION['location'] = $aJson['message']['location'];

			//launch a scan ignoring the result
			$this->_getGyms($_SESSION['location']['latitude'], $_SESSION['location']['longitude']);

			return $this->sendMessage($this->getChatId($aJson), 'Location acquired', null, false, true, null, $this->getKeyboard());
		}

		switch($sText) {
			case 'List RAIDs':
				$aJson['message']['text'] = '/listraid';//history can't call catchAll workaround 
				return $this->listraid($aJson);
			case 'Search RAID':
				$aJson['message']['text'] = '/searchraid';//history can't call catchAll workaround 
				return $this->searchraid($aJson);
			case 'Search Person':
				$aJson['message']['text'] = '/searchperson';//history can't call catchAll workaround 
				return $this->searchperson($aJson);
		}
	}

	protected function listraid($aJson) {
		$this->sendChatAction($this->getChatId($aJson), 'typing');

		if(empty($_SESSION['location'])) {
			return $this->sendMessage($this->getChatId($aJson), 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		$aGyms = $this->_getGyms($_SESSION['location']['latitude'], $_SESSION['location']['longitude']);
		if($aGyms === false) {
			return $this->sendMessage($this->getChatId($aJson), 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
		}
		if(empty($aGyms['raids'])) {
			return $this->sendMessage($this->getChatId($aJson), 'No RAIDs on your area', null, false, true, null, $this->getKeyboard());
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($this->getChatId($aJson), 'Unable to update, using cached data.');
		}

		$aFoundRaids = array();
		foreach($aGyms['raids'] as $sRaid) {
			$aRaid = json_decode($sRaid, true);
			if($aRaid) {
				$aFoundRaids[] = (empty($aRaid['raid_boss_id'])?"\xF0\x9F\x8D\xB3":$this->aConfig['pokemon'][$aRaid['raid_boss_id']]).' '.implode('', array_fill(0, $aRaid['raid_level'], "\xE2\xAD\x90")).' from '.date('Y-m-d H:i:s', $aRaid['raid_battle_ms'] / 1000).' UTC to '.date('Y-m-d H:i:s', $aRaid['raid_end_ms'] / 1000).' UTC at '.$this->_findGym($aRaid['gym_id'], $aGyms['gyms']);
			}
		}

		return $this->sendMessage($this->getChatId($aJson), "Current RAIDs in your area:\n".implode("\n", $aFoundRaids), null, false, true, 'Markdown', $this->getKeyboard());
	}

	protected function searchraid($aJson, $sRaidBoss = null) {
		$this->sendChatAction($this->getChatId($aJson), 'typing');

		if(empty($_SESSION['location'])) {
			return $this->sendMessage($this->getChatId($aJson), 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		if(empty($sRaidBoss)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Now insert a RAID boss to search for', $this->getMessageId($aJson), true));
		}
		else {
			$iRaidBossId = null;
			$sRaidBoss = strtolower($sRaidBoss);
			foreach($this->aConfig['pokemon'] as $iId => $sName) {
				if($sRaidBoss == strtolower($sName)) {
					$iRaidBossId = $iId;
					break;
				}
			}

			if(is_null($iRaidBossId)) {
				$this->storeMessage($aJson);
				return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Can\'t find a Pokemon named '.$sRaidBoss, $this->getMessageId($aJson), true));
			}
		}

		$aGyms = $this->_getGyms($_SESSION['location']['latitude'], $_SESSION['location']['longitude']);
		if($aGyms === false) {
			$this->sendMessage($this->getChatId($aJson), 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
		}
		if(empty($aGyms['raids'])) {
			$this->sendMessage($this->getChatId($aJson), 'No RAIDs on your area', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($this->getChatId($aJson), 'Unable to update, using cached data.');
		}

		$aFiltered = array_filter($aGyms['raids'], function($sRaid) use ($iRaidBossId) { return preg_match('/\"raid_boss_id"\:'.$iRaidBossId.'\,/i', $sRaid); });
		$aFoundRaids = array();
		foreach($aFiltered as $sRaid) {
			$aRaid = json_decode($sRaid, true);
			if($aRaid) {
				$aFoundRaids[] = 'from '.date('Y-m-d H:i:s', $aRaid['raid_battle_ms'] / 1000).' UTC to '.date('Y-m-d H:i:s', $aRaid['raid_end_ms'] / 1000).' UTC at '.$this->_findGym($aRaid['gym_id'], $aGyms['gyms']);
			}
		}

		if(empty($aFoundRaids)) {
			$this->sendMessage($this->getChatId($aJson), $sRaidBoss.' not found on your area RAIDs', null, false, true, null, $this->getKeyboard());
		}
		else {
			$this->sendMessage($this->getChatId($aJson), $sRaidBoss." found on those gyms:\n".implode("\n", $aFoundRaids), null, false, true, 'Markdown', $this->getKeyboard());
		}
		return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
	}

	protected function searchperson($aJson, $sNickName = null) {
		$this->sendChatAction($this->getChatId($aJson), 'typing');

		if(empty($_SESSION['location'])) {
			return $this->sendMessage($this->getChatId($aJson), 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		if(empty($sNickName)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($this->getChatId($aJson), 'Now insert a nickname to search for', $this->getMessageId($aJson), true));
		}

		$aGyms = $this->_getGyms($_SESSION['location']['latitude'], $_SESSION['location']['longitude']);
		if($aGyms === false) {
			$this->sendMessage($this->getChatId($aJson), 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
		}
		if(empty($aGyms['gyms'])) {
			$this->sendMessage($this->getChatId($aJson), 'No gyms on your area', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($this->getChatId($aJson), 'Unable to update, using cached data.');
		}

		$aFiltered = array_filter($aGyms['gyms'], function($sGym) use ($sNickName) { return preg_match('/\"trainer_name"\:\"'.preg_quote($sNickName, '/').'\"/i', $sGym); });
		$aFoundGyms = array();
		foreach($aFiltered as $sGym) {
			$aGym = json_decode($sGym, true);
			if($aGym) {
				$aFoundGyms[] = '['.$aGym['gym_name'].'](http://www.google.com/maps/place/'.$aGym['longitude'].','.$aGym['latitude'].')';
			}
		}

		if(empty($aFoundGyms)) {
			$this->sendMessage($this->getChatId($aJson), $sNickName.' not found on your area', null, false, true, null, $this->getKeyboard());
		}
		else {
			$this->sendMessage($this->getChatId($aJson), $sNickName." found on those gyms:\n".implode("\n", $aFoundGyms), null, false, true, 'Markdown', $this->getKeyboard());
		}
		return $this->recursivelyDeleteMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));
	}

	protected function getKeyboard() {
		return array(
			'keyboard' => array(
				array(
					array(
						'text' => 'Set Location',
						'request_location' => true
					)
				),
				array(
					array(
						'text' => 'List RAIDs'
					),
					array(
						'text' => 'Search RAID'
					),
					array(
						'text' => 'Search Person'
					)
				)
			),
			'resize_keyboard' => true
		);
	}

	function _getProxy() {
		static $sProxy = null;

		if(is_null($sProxy)) {
			$sHtml = file_get_contents('http://www.gatherproxy.com/embed/?t=Transparent&p=&c=Italy');

			if(preg_match_all('/gp\.insertPrx\(([^\)]+)\)\;/', $sHtml, $aJSONs)) {
				$aProxyes = array();
				foreach($aJSONs[1] as $sJSON) {
					$aProxy = json_decode($sJSON, true);
					if($aProxy) {
						$aProxyes[$aProxy['PROXY_IP'].':'.hexdec($aProxy['PROXY_PORT'])] = $aProxy['PROXY_TIME'];
					}
				}
				natsort($aProxyes);

				$sProxy = array_keys($aProxyes)[0];
			}
		}

		return $sProxy;
	}

	protected function _callGymHuntr($sUrl, $aAdditionalHeaders = array(), $bReturnHeadersAndCookies = true) {
		$aParams = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_HEADER => $bReturnHeadersAndCookies,
			CURLOPT_URL => $sUrl,
			CURLOPT_PROXY => $this->_getProxy(),
			CURLOPT_HTTPHEADER => array_merge(
				array(
					'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0',
					'Accept-Language: en-US,en;q=0.5',
					'DNT: 1',
					'Connection: keep-alive'
				),
				$aAdditionalHeaders
			)
		);

		$rCurl = curl_init();
		curl_setopt_array($rCurl, $aParams);

		$sResponse = curl_exec($rCurl);
		curl_close($rCurl);

		if(($sResponse === false) || !$bReturnHeadersAndCookies) {
			return $sResponse;
		}

		$aHeaders = array();
		if(preg_match_all('/^([^\s\:]+)\:\s+(.+)$/m', $sResponse, $aMatches, PREG_SET_ORDER)) {
			foreach($aMatches as $aMatch) {
				$aHeaders[$aMatch[1]] = trim($aMatch[2]);
			}
		}

		$aCookies = array();
		if(preg_match_all('/^Set\-Cookie\:\s+([^\=]+)\=([^\;]+)\;/m', $sResponse, $aMatches, PREG_SET_ORDER)) {
			foreach($aMatches as $aMatch) {
				$aCookies[$aMatch[1]] = trim($aMatch[2]);
			}
		}

		return array($sResponse, $aHeaders, $aCookies);
	}

	/**
	 * Example output:
	 * {
	 * "gyms":["{\"enabled\":true,\"gym_id\":\"690bd9f74170420ebb1a97306041b926.16\",\"gym_name\":\"Donald in the Window\",\"gym_points\":1196,\"team_id\":1,\"gym_inid\":\"594840c54710440d9a883e68\",\"longitude\":45.439589,\"latitude\":12.337143,\"location\":[45.439589,12.337143],\"inside\":[{\"trainer_name\":\"BettaVe\",\"pokemon_id\":78,\"cp\":1399}],\"url\":\"http://lh6.ggpht.com/O8OqBy1b9C1isn1vLogtGdvY4xq3IDX8GjGdlUu-3-eUDFgQE32A1JME0qt38dgAYR9Jgv9qUT0E2JKpE5SX\",\"lastseen\":\"2017-10-17T13:30:39.075Z\"}"],
	 * "pokestops":["{\"pokestop_id\":\"682a6c28bb004292b17b1d531139eb44.16\",\"longitude\":45.439516,\"latitude\":12.338619}"],
	 * "raids": ["{\"_id\":\"59e6059859c9133b93f72c02\",\"gym_id\":\"e2c050d361324dc69d7c3b9e5d8e9100.16\",\"raid_battle_ms\":1508246900667,\"raid_end_ms\":1508250500667,\"raid_level\":2,\"raid_boss_id\":125,\"raid_boss_cp\":12390}"]
	 * }
	 */
	protected function _getGyms($fLatitude, $fLongitude) {
		//call index to obtain __cfduid cookie
		list($sResponse, $aHeaders, $aCookies) = $this->_callGymHuntr(
			'https://gymhuntr.com/#'.$fLatitude.','.$fLongitude,
			array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Upgrade-Insecure-Requests: 1'
			)
		);
		$sCFDUID = $aCookies['__cfduid'];

		//authorise call
		list($sResponse, $aHeaders, $aCookies) = $this->_callGymHuntr(
			'https://api.gymhuntr.com/api/authorise?'.http_build_query(array(
				'latitude' => $fLatitude,
				'longitude' => $fLongitude
			)),
			array(
				'Accept: * /*',
				'Referer: https://gymhuntr.com/',
				'Origin: https://gymhuntr.com',
				'Cookie: __cfduid='.$sCFDUID
			)
		);

		$iTime = time();

		//check for updates
		$aUpdate = json_decode($this->_callGymHuntr(
			'https://api.gymhuntr.com/api/check?'.http_build_query(array(
				'latitude' => $fLatitude,
				'longitude' => $fLongitude,
				'hashCheck' => $this->sHashCheck,
				'monster' => $this->sMonster,
				'timeUntil' => (intval($fLatitude) + intval($fLongitude)) * intval('34.00969645770158') + intval('-118.49647521972658') + $iTime,
				'time' => $iTime
			)),
			array(
				'Accept: */*',
				'Referer: https://gymhuntr.com/',
				'Origin: https://gymhuntr.com',
				'Cookie: __cfduid='.$sCFDUID.'; cf_uid='.$aCookies['cf_uid']
			),
			false
		), true);

		//scan gyms
		$aScan = json_decode($this->_callGymHuntr(
			'https://api.gymhuntr.com/api/gyms?'.http_build_query(array(
				'latitude' => $fLatitude,
				'longitude' => $fLongitude,
				'hashCheck' => $this->sHashCheck,
				'monster' => $this->sMonster,
				'timeUntil' => ($fLatitude * $aHeaders['cf-id']) + ($fLongitude * $aHeaders['cf-id']) + $iTime,
				'time' => $iTime
			)),
			array(
				'Accept: */*',
				'Referer: https://gymhuntr.com/',
				'Origin: https://gymhuntr.com',
				'Cookie: __cfduid='.$sCFDUID.'; cf_uid='.$aCookies['cf_uid']
			),
			false
		), true);
		if($aScan === false) {
			return false;
		}

		$aScan['update'] = $aUpdate;
		return $aScan;
	}

	protected function _findGym($sGymId, $aGyms) {
		$aFiltered = array_filter($aGyms, function($sGym) use ($sGymId) { return preg_match('/\"gym_id"\:\"'.preg_quote($sGymId, '/').'\"/', $sGym); });
		if(count($aFiltered) == 1) {
			$aGym = json_decode(current($aFiltered), true);
			return '['.$aGym['gym_name'].'](http://www.google.com/maps/place/'.$aGym['longitude'].','.$aGym['latitude'].')';
		}
	}
}
