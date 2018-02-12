<?php

require_once(__DIR__.'/Bot.php');

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
		return $this->sendMessage($this->getChatId($aJson), "/start - to start the bot\n\n/listraid - Lists all RAID in the area\n\n/searchraid - Search for a given raid boss on the area\n\n/listgyms - Lists all Gyms in the area\n\n/searchperson - Search for a given nickname on the area\n\n/suggest - Suggest an improvement to the developer\nYou can pass an inline argument, or call the command and insert the subject when asked.\nFor example:\n/suggest I have an idea for an improvement!", null, false, true, null, $this->getKeyboard());
	}

	protected function start($aJson) {
		//$this->suggest($aJson, 'Bot started on chat '.json_encode($aJson['message']['chat']));//debug
		return $this->sendMessage($this->getChatId($aJson), "Welcome to GymHuntrBot!", null, false, true, null, $this->getKeyboard());
	}

	protected function catchAll($aJson, $sText) {
		if($aJson['message']['location']) {
			$sChatId = $this->getChatId($aJson);
			$this->sendChatAction($sChaId, 'typing');

			$oSession = Session::getSingleton();
			$oSession->storeValue($sChatId, 'location', $aJson['message']['location']);

			//launch a scan ignoring the result
			$this->_getGyms($aJson['message']['location']['latitude'], $aJson['message']['location']['longitude']);

			return $this->sendMessage($sChatId, 'Location acquired', null, false, true, null, $this->getKeyboard());
		}

		switch($sText) {
			case 'List RAIDs':
				$aJson['message']['text'] = '/listraid';//history can't call catchAll workaround 
				return $this->listraid($aJson);
			case 'Search RAID':
				$aJson['message']['text'] = '/searchraid';//history can't call catchAll workaround 
				return $this->searchraid($aJson);
			case 'List Gyms':
				$aJson['message']['text'] = '/listgyms';//history can't call catchAll workaround 
				return $this->listgyms($aJson);
			case 'Search Person':
				$aJson['message']['text'] = '/searchperson';//history can't call catchAll workaround 
				return $this->searchperson($aJson);
		}
	}

	protected function listraid($aJson) {
		$sChatId = $this->getChatId($aJson);
		$this->sendChatAction($sChatId, 'typing');

		$oSession = Session::getSingleton();
		if(!$oSession->storedValue($sChatId, 'location')) {
			return $this->sendMessage($sChatId, 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		$aLocation = $oSession->retrieveValue($sChatId, 'location');
		$aGyms = $this->_getGyms($aLocation['latitude'], $aLocation['longitude']);
		if($aGyms === false) {
			return $this->sendMessage($sChatId, 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
		}
		if(empty($aGyms['raids'])) {
			return $this->sendMessage($sChatId, 'No RAIDs on your area', null, false, true, null, $this->getKeyboard());
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($sChatId, 'Unable to update, using cached data.');
		}

		return $this->_formatRaids($sChatId, $aGyms, 'Current RAIDs in your area');
	}

	protected function searchraid($aJson, $sRaidBoss = null) {
		$sChatId = $this->getChatId($aJson);
		$this->sendChatAction($sChatId, 'typing');

		$oSession = Session::getSingleton();
		if(!$oSession->storedValue($sChatId, 'location')) {
			return $this->sendMessage($sChatId, 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		if(empty($sRaidBoss)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($sChatId, 'Now insert a RAID boss to search for', $this->getMessageId($aJson), true));
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
				return $this->storeMessage($this->sendMessage($sChatId, 'Can\'t find a Pokemon named '.$sRaidBoss, $this->getMessageId($aJson), true));
			}
		}

		$aLocation = $oSession->retrieveValue($sChatId, 'location');
		$aGyms = $this->_getGyms($aLocation['latitude'], $aLocation['longitude']);
		if($aGyms === false) {
			$this->sendMessage($sChatId, 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
		}
		if(empty($aGyms['raids'])) {
			$this->sendMessage($sChatId, 'No RAIDs on your area', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($sChatId, 'Unable to update, using cached data.');
		}

		$aFiltered = array_filter($aGyms['raids'], function($sRaid) use ($iRaidBossId) { return preg_match('/\"raid_boss_id"\:'.$iRaidBossId.'\,/i', $sRaid); });
		$aFoundRaids = array();
		foreach($aFiltered as $sRaid) {
			$aRaid = json_decode($sRaid, true);
			if($aRaid) {
				$aFoundRaids[] = 'from '.static::_formatTimestamp($sChatId, $aRaid['raid_battle_ms'] / 1000).' to '.static::_formatTimestamp($sChatId, $aRaid['raid_end_ms'] / 1000).' at '.static::_findGym($aRaid['gym_id'], $aGyms['gyms']);
			}
		}

		if(empty($aFoundRaids)) {
			$this->sendMessage($sChatId, $sRaidBoss.' not found on your area RAIDs', null, false, true, null, $this->getKeyboard());
		}
		else {
			$this->sendMessage($sChatId, $sRaidBoss." found on those gyms:\n".implode("\n", $aFoundRaids), null, false, true, 'Markdown', $this->getKeyboard());
		}
		return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
	}

	protected function listgyms($aJson) {
		$sChatId = $this->getChatId($aJson);
		$this->sendChatAction($sChatId, 'typing');

		$oSession = Session::getSingleton();
		if(!$oSession->storedValue($sChatId, 'location')) {
			return $this->sendMessage($sChatId, 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		$aLocation = $oSession->retrieveValue($sChatId, 'location');
		$aGyms = $this->_getGyms($aLocation['latitude'], $aLocation['longitude']);

		if($aGyms === false) {
			return $this->sendMessage($sChatId, 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
		}
		if(empty($aGyms['gyms'])) {
			return $this->sendMessage($sChatId, 'No Gyms on your area', null, false, true, null, $this->getKeyboard());
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($sChatId, 'Unable to update, using cached data.');
		}

		$aFoundGyms = array();
		foreach($aGyms['gyms'] as $sGym) {
			$aGym = json_decode($sGym, true);
			if($aGym) {
				$aFoundGyms[] = static::_formatGym($aGym);
			}
		}

		return $this->sendMessage($sChatId, "Current Gyms in your area:\n".implode("\n", $aFoundGyms), null, false, true, 'Markdown', $this->getKeyboard());
	}

	protected function searchperson($aJson, $sNickName = null) {
		$sChatId = $this->getChatId($aJson);
		$this->sendChatAction($sChatId, 'typing');

		$oSession = Session::getSingleton();
		if(!$oSession->storedValue($sChatId, 'location')) {
			return $this->sendMessage($sChatId, 'Please first send your location', null, false, true, null, $this->getKeyboard());
		}

		if(empty($sNickName)) {
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($sChatId, 'Now insert a nickname to search for', $this->getMessageId($aJson), true));
		}

		$aLocation = $oSession->retrieveValue($sChatId, 'location');
		$aGyms = $this->_getGyms($aLocation['latitude'], $aLocation['longitude']);
		if($aGyms === false) {
			$this->sendMessage($sChatId, 'Something went wrong, please retry', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
		}
		if(empty($aGyms['gyms'])) {
			$this->sendMessage($sChatId, 'No gyms on your area', null, false, true, null, $this->getKeyboard());
			return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
		}

		if(($aGyms['update']['response'] == 'failed') && ($aGyms['update']['responseMsg'] != 'This area has been scanned recently, try again later.')) {
			$this->sendMessage($sChatId, 'Unable to update, using cached data.');
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
			$this->sendMessage($sChatId, $sNickName.' not found on your area', null, false, true, null, $this->getKeyboard());
		}
		else {
			$this->sendMessage($sChatId, $sNickName." found on those gyms:\n".implode("\n", $aFoundGyms), null, false, true, 'Markdown', $this->getKeyboard());
		}
		return $this->recursivelyDeleteStoredMessage($sChatId, $this->getReplyToMessageId($aJson));
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
					)
				),
				array(
					array(
						'text' => 'List Gyms'
					),
					array(
						'text' => 'Search Person'
					)
				)
			),
			'resize_keyboard' => true
		);
	}

	public static function _formatGym($aGym) {
		return '['.$aGym['gym_name'].'](http://www.google.com/maps/place/'.$aGym['longitude'].','.$aGym['latitude'].') owned by '.static::_getTeamIcon($aGym['team_id']).' prestige '.$aGym['gym_points'];
	}

	protected static function _getTeamIcon($iTeamId) {
		switch($iTeamId) {
			case 1:
				return "\xE2\x9D\x84";//blue
			case 2:
				return "\xE2\x9D\xA4";//red
			case 3:
				return "\xE2\x9A\xA1";//yellow
			default:
				return "\xE2\x9A\xAA";//empty
		}
	}

	protected function _getProxy($bInvalidateProxy = false) {
		static $aProxies = null;
		static $sProxy = null;

		if(is_null($aProxies)) {
			$sHtml = file_get_contents('http://www.gatherproxy.com/embed/?t=Transparent&p=&c=Italy');

			if(preg_match_all('/gp\.insertPrx\(([^\)]+)\)\;/', $sHtml, $aJSONs)) {
				$aProxys = array();
				foreach($aJSONs[1] as $sJSON) {
					$aProxy = json_decode($sJSON, true);
					if($aProxy) {
						$aProxys[$aProxy['PROXY_IP'].':'.hexdec($aProxy['PROXY_PORT'])] = $aProxy['PROXY_TIME'];
					}
				}
				natsort($aProxys);

				$aProxies = array_reverse(array_keys($aProxys));
			}
		}

		if(is_null($sProxy) || $bInvalidateProxy) {
			$sProxy = array_pop($aProxies);
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
			CURLOPT_TIMEOUT => 10,
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
	public function _getGyms($fLatitude, $fLongitude, $iRetries = 0) {
		//always change proxy at every call
		$this->_getProxy(true);

		//gymhuntr is blocking multiple request on the same longitude and latitude, so we'll change it a bit
		$fLatitude += rand(-1000, 1000) / 100000;
		$fLongitude += rand(-1000, 1000) / 100000;

		//call index to obtain __cfduid cookie
		list($sResponse, $aHeaders, $aCookies) = $this->_callGymHuntr(
			'https://gymhuntr.com/#'.$fLatitude.','.$fLongitude,
			array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Upgrade-Insecure-Requests: 1'
			)
		);
		if(!is_array($aCookies) || !array_key_exists('__cfduid', $aCookies)) {
			if($iRetries < 5) {
				$this->_getProxy(true);
				return $this->_getGyms($fLatitude, $fLongitude, ++$iRetries);
			}
			else {
				return false;
			}
		}
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
		if(!is_array($aCookies) || !array_key_exists('cf_uid', $aCookies)) {
			if($iRetries < 5) {
				$this->_getProxy(true);
				return $this->_getGyms($fLatitude, $fLongitude, ++$iRetries);
			}
			else {
				return false;
			}
		}

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

	protected static function _findGym($sGymId, $aGyms) {
		$aFiltered = array_filter($aGyms, function($sGym) use ($sGymId) { return preg_match('/\"gym_id"\:\"'.preg_quote($sGymId, '/').'\"/', $sGym); });
		if(count($aFiltered) == 1) {
			$aGym = json_decode(current($aFiltered), true);
			return '['.$aGym['gym_name'].'](http://www.google.com/maps/place/'.$aGym['longitude'].','.$aGym['latitude'].')';
		}
	}

	public function _formatRaids($sChatId, $aGyms, $sMessage, $bKeyboard = true, $aDeleteMessage = null, $sFilterGymId = null, $bSingleNotify = false) {
		$oSession = Session::getSingleton();
		$aNotifiedRaids = $oSession->retrieveValue($sChatId, 'notifiedRaids');
		if(empty($aNotifiedRaids)) {
			$aNotifiedRaids = array();
		}

		$aFoundRaids = array();
		foreach($aGyms['raids'] as $sRaid) {
			$aRaid = json_decode($sRaid, true);
			if($aRaid && (is_null($sFilterGymId) || ($sFilterGymId == $aRaid['gym_id'])) && (!$bSingleNotify || !in_array($aRaid['_id'], $aNotifiedRaids))) {
				$aNotifiedRaids[] = $aRaid['_id'];
				$aFoundRaids[] = (empty($aRaid['raid_boss_id'])?"\xF0\x9F\x8D\xB3":$this->aConfig['pokemon'][$aRaid['raid_boss_id']]).' '.implode('', array_fill(0, $aRaid['raid_level'], "\xE2\xAD\x90")).' from '.static::_formatTimestamp($sChatId, $aRaid['raid_battle_ms'] / 1000).' to '.static::_formatTimestamp($sChatId, $aRaid['raid_end_ms'] / 1000).' at '.static::_findGym($aRaid['gym_id'], $aGyms['gyms']);
			}
		}

		if(!is_null($aDeleteMessage)) {
			$this->deleteMessage($sChatId, $this->getMessageId($aDeleteMessage));
		}

		if(empty($aFoundRaids)) {
			return false;
		}

		if($bSingleNotify) {
			$oSession->storeValue($sChatId, 'notifiedRaids', $aNotifiedRaids);
		}

		return $this->sendMessage($sChatId, "{$sMessage}:\n".implode("\n", $aFoundRaids), null, false, true, 'Markdown', $bKeyboard?$this->getKeyboard():null);
	}

	protected static function _formatTimestamp($sChatId, $iTimestamp) {
		$oSession = Session::getSingleton();
		$aLocation = $oSession->retrieveValue($sChatId, 'location');

		$oDateTime = new DateTime('@'.intval($iTimestamp));
		$oDateTime->setTimezone(static::_get_nearest_timezone($aLocation['latitude'], $aLocation['longitude'], static::_getCountryCode($aLocation['latitude'], $aLocation['longitude'])));
		return $oDateTime->format('Y-m-d H:i:s');
	}

	protected static function _getCountryCode($fLatitude, $fLongitude) {
		$aResults = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?latlng='.$fLatitude.','.$fLongitude.'&sensor=false'), true);

		if(is_array($aResults)) {
			foreach($aResults['results'] as $aResult) {
				foreach($aResult['address_components'] as $aComponent) {
					if(($aComponent['types'][0] == 'country') && ($aComponent['types'][0] == 'political')) {
						return $aComponent['short_name'];
					}
				}
			}
		}

		return null;
	}

	//from https://stackoverflow.com/questions/3126878/get-php-timezone-name-from-latitude-and-longitude?noredirect=1&lq=1
	protected static function _get_nearest_timezone($cur_lat, $cur_long, $country_code = '') {
		$timezone_ids = ($country_code) ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country_code)
										: DateTimeZone::listIdentifiers();

		$time_zone = null;
		if($timezone_ids && is_array($timezone_ids) && isset($timezone_ids[0])) {

			$tz_distance = 0;

			//only one identifier?
			if (count($timezone_ids) == 1) {
				$time_zone = new DateTimeZone($timezone_ids[0]);
			} else {

				foreach($timezone_ids as $timezone_id) {
					$timezone = new DateTimeZone($timezone_id);
					$location = $timezone->getLocation();
					$tz_lat   = $location['latitude'];
					$tz_long  = $location['longitude'];

					$theta    = $cur_long - $tz_long;
					$distance = (sin(deg2rad($cur_lat)) * sin(deg2rad($tz_lat))) 
					+ (cos(deg2rad($cur_lat)) * cos(deg2rad($tz_lat)) * cos(deg2rad($theta)));
					$distance = acos($distance);
					$distance = abs(rad2deg($distance));
					// echo '<br />'.$timezone_id.' '.$distance; 

					if (!$time_zone || $tz_distance > $distance) {
						$time_zone   = $timezone;
						$tz_distance = $distance;
					} 

				}
			}
		}

		return $time_zone;
	}
}
