<?php

require_once(__DIR__.'/Bot.php');

/**
 * PoGoVe Bot class
 */
class PoGoVeBot extends Bot {
	protected function about($aJson) {
		return $this->sendMessage($this->getChatId($aJson), 'Test');
	}

	protected function help($aJson) {
		return $this->sendMessage($this->getChatId($aJson), "/start - to start the bot\n\n/suggest - Suggest an improvement to the developer\nYou can pass an inline argument, or call the command and insert the subject when asked.\nFor example:\n/suggest I have an idea for an improvement!");
	}

	protected function start($aJson) {
		return $this->sendMessage($this->getChatId($aJson), 'Welcome to PoGoVeBot!', null, false, true, null, $this->getProfilesKeyboard($aJson));
	}

	protected function catchAll($aJson, $sText) {
		if(($this->getEntityType($aJson) == 'callback_query') && array_key_exists('data', $aJson['callback_query'])) {
			//callback_data can be at max 64 bytes, so we use an array instead of an object to save bytes
			$aData = json_decode($aJson['callback_query']['data'], true);
			if(is_array($aData)) {
				return $this->{$aData[0]}($aJson, $aData);
			}
		}
	}

	protected function _describeProfile($aProfile) {
		$sDescription = 'Profile *'.$aProfile['name']."*\nNotify Pokemon ";
		if($aProfile['pokemon']['enabled']) {
			$sDescription .= "\xE2\x9C\x85\n";
			$sDescription .= "\xF0\x9F\x9A\xB6 Min Dist ".$aProfile['pokemon']['default']['min_dist']." KM\n";
			$sDescription .= "\xF0\x9F\x9A\x97 Max Dist ".(($aProfile['pokemon']['default']['max_dist'] == 'inf')?'Infinite':$aProfile['pokemon']['default']['max_dist'])." KM\n";
// 			$sDescription .= 'Min CP '.$aProfile['pokemon']['default']['min_cp']."\n";
// 			$sDescription .= 'Max CP '.$aProfile['pokemon']['default']['max_cp']."\n";
			$sDescription .= "\xF0\x9F\x9A\xAE Min IV ".$aProfile['pokemon']['default']['min_iv']."%\n";
			$sDescription .= "\xF0\x9F\x9A\xAF Max IV ".$aProfile['pokemon']['default']['max_iv']."%\n";
			$sDescription .= 'Notify only Exceptions '.($aProfile['pokemon']['default']['ignore_missing']?"\xE2\x9C\x85":"\xE2\x9D\x8E")."\n";
		}
		else {
			$sDescription .= "\xE2\x9D\x8E\n";
		}

		$sDescription .= 'Notify eggs ';
		if($aProfile['eggs']['enabled']) {
			$sDescription .= "\xE2\x9C\x85\n";
			$sDescription .= 'Min level '.implode('', array_fill(0, $aProfile['eggs']['min_level'], "\xE2\xAD\x90"))."\n";
			$sDescription .= 'Max level '.implode('', array_fill(0, $aProfile['eggs']['max_level'], "\xE2\xAD\x90"))."\n";
		}
		else {
			$sDescription .= "\xE2\x9D\x8E\n";
		}

		$sDescription .= 'Notify RAIDs ';
		if($aProfile['raids']['enabled']) {
			$sDescription .= "\xE2\x9C\x85\n";
			$sDescription .= "\xF0\x9F\x9A\xB6 Min Dist ".$aProfile['raids']['default']['min_dist']." KM\n";
			$sDescription .= "\xF0\x9F\x9A\x97 Max Dist ".(($aProfile['raids']['default']['max_dist'] == 'inf')?'Infinite':$aProfile['raids']['default']['max_dist'])." KM\n";
			$sDescription .= 'Notify only Exceptions '.($aProfile['default']['ignore_missing']?"\xE2\x9C\x85":"\xE2\x9D\x8E")."\n";
		}
		else {
			$sDescription .= "\xE2\x9D\x8E\n";
		}

		return $sDescription;
	}

	protected function getProfilesKeyboard($aJson) {
		$oSession = Session::getSingleton();

		$aKeyboard = array(
			'inline_keyboard' => array()
		);

		if($oSession->storedValue($this->getChatId($aJson), 'profiles')) {
			foreach($oSession->retrieveValue($this->getChatId($aJson), 'profiles') as $sId => $aProfile) {
				if($aProfile['active']) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => $aProfile['name'],
							'callback_data' => '["edit_profile",'.$sId.']'
						)
					);
				}
				else {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => $aProfile['name'],
							'callback_data' => '["edit_profile",'.$sId.']'
						),
						array(
							'text' => "\xE2\x9C\xA8",
							'callback_data' => '["activate_profile",'.$sId.']'
						),
						array(
							'text' => "\xE2\x9D\x8C",
							'callback_data' => '["delete_profile",'.$sId.']'
						)
					);
				}
			}
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => 'Add new Profile',
				'callback_data' => '["add_profile"]'
			)
		);

		return $aKeyboard;
	}

	protected function activate_profile($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);
		$sName = '';

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		foreach($aProfiles as $sId => &$aProfile) {
			if($aProfile['active']) {
				$aProfile['active'] = false;
			}
			elseif($sId == $aData[1]) {
				$aProfile['active'] = true;
				$sName = $aProfile['name'];
			}
		}
		$oSession->storeValue($sChatId, 'profiles', $aProfiles);

		return $this->sendMessage($this->getChatId($aJson), 'Profile *'.$sName.'* set as active', null, false, true, 'Markdown', $this->getProfilesKeyboard($aJson));
	}

	protected function add_profile($aJson, $aData, $sName = null) {
		$sChatId = $this->getChatId($aJson);
		if(is_null($sName)) {
			$aJson['callback_query']['message']['text'] = '/add_profile '.json_encode($aData);//workaround
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($sChatId, 'Please insert a name for the new Profile', $this->getMessageId($aJson), true));
		}

		$this->recursivelyDeleteStoredMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));

		$oSession = Session::getSingleton();
		if($oSession->storedValue($sChatId, 'profiles')) {
			$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		}
		else {
			$aProfiles = array();
		}
		//init defaults here
		$aProfiles[] = array(
			'name' => $sName,
			'active' => empty($aProfiles),
			'pokemon' => array(
				'enabled' => true,
				'default' => array(
					'min_dist' => 0,
					'max_dist' => 5,
// 					'min_cp' => 0,
// 					'max_cp' => 4760,
					'min_iv' => 90,
					'max_iv' => 100,
					'ignore_missing' => false
				),
				'exceptions' => array()
			),
			'eggs' => array(
				'enabled' => false,
				'min_level' => 1,
				'max_level' => 5
			),
			'raids' => array(
				'enabled' => false,
				'default' => array(
					'min_dist' => 0,
					'max_dist' => 5,
					'ignore_missing' => false
				),
				'exceptions' => array()
			)
		);
		$oSession->storeValue($sChatId, 'profiles', $aProfiles);

		$aKeys = array_keys($aProfiles);
		return $this->edit_profile($aJson, array(
			'edit_profile',
			array_pop($aKeys)
		));
	}

	protected function edit_profile($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		$aProfile = $aProfiles[$aData[1]];

		return $this->sendMessage($sChatId, $this->_describeProfile($aProfile), null, false, true, 'Markdown', $this->getProfileKeyboard($aData[1], $aProfile));
	}

	protected function getProfileKeyboard($sProfileId, $aProfile) {
		$aKeyboard = array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => 'Edit global settings',
						'callback_data' => '["edit_global",'.$sProfileId.']'
					)
				)
			)
		);

		if($aProfile['pokemon']['enabled']) {
			foreach($aProfile['pokemon']['exceptions'] as $sId => $aException) {
				$aKeyboard['inline_keyboard'][] = array(
					array(
						'text' => "\xF0\x9F\x9A\x80 ".$this->aConfig['pokemon'][$sId],
						'callback_data' => '["edit_pokemon_exception",'.$sProfileId.','.$sId.']'
					),
					array(
						'text' => "\xE2\x9D\x8C",
						'callback_data' => '["delete_pokemon_exception",'.$sProfileId.','.$sId.']'
					)
				);
			}

			$aKeyboard['inline_keyboard'][] = array(
				array(
					'text' => "\xF0\x9F\x9A\x80 Add Pokemon exception",
					'callback_data' => '["add_pokemon_exception",'.$sProfileId.']'
				)
			);
		}

		if($aProfile['raids']['enabled']) {
			foreach($aProfile['raids']['exceptions'] as $sId => $bNotify) {
				$aKeyboard['inline_keyboard'][] = array(
					array(
						'text' => "\xF0\x9F\x8D\xB3 ".$this->aConfig['pokemon'][$sId].' '.($bNotify?"\xE2\x9C\x85":"\xE2\x9D\x8E"),
						'callback_data' => '["edit_raids_exception",'.$sProfileId.','.$sId.']'
					),
					array(
						'text' => "\xE2\x9D\x8C",
						'callback_data' => '["delete_raids_exception",'.$sProfileId.','.$sId.']'
					)
				);
			}

			$aKeyboard['inline_keyboard'][] = array(
				array(
					'text' => "\xF0\x9F\x8D\xB3 Add RAID exception",
					'callback_data' => '["add_raids_exception",'.$sProfileId.']'
				)
			);
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\xAC\x85 Back",
				'callback_data' => '["start"]'
			)
		);

		return $aKeyboard;
	}

	protected function delete_profile($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		$aProfile = $aProfiles[$aData[1]];

		if($aData[2]) {
			unset($aProfiles[$aData[1]]);
			$oSession->storeValue($sChatId, 'profiles', $aProfiles);

			return $this->sendMessage($this->getChatId($aJson), 'Profile *'.$aProfile['name'].'* deleted', null, false, true, 'Markdown', $this->getProfilesKeyboard($aJson));
		}
		else {
			return $this->sendMessage($sChatId, 'Are you sure you want to delete profile *'.$aProfile['name'].'*?', null, false, true, 'Markdown', $this->getConfirmDeleteProfileKeyboard($aData[1]));
		}
	}

	protected function getConfirmDeleteProfileKeyboard($sProfileId) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => 'Yes',
						'callback_data' => '["delete_profile",'.$sProfileId.',true]'
					),
					array(
						'text' => 'No',
						'callback_data' => '["start"]'
					)
				)
			)
		);
	}

	protected function edit_global($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		$aProfile =& $aProfiles[$aData[1]];

		if(!empty($aData[2])) {
			switch($aData[2]) {
				case 'on-off':
					$aProfile[$aData[3]]['enabled'] = !$aProfile[$aData[3]]['enabled'];
					break;
				case 'default':
					$aProfile[$aData[3]]['default'][$aData[4]] = $aData[5];
					break;
				case 'set':
					$aProfile[$aData[3]][$aData[4]] = $aData[5];
					break;
			}
			$oSession->storeValue($sChatId, 'profiles', $aProfiles);
		}

		return $this->sendMessage($sChatId, $this->_describeProfile($aProfile), null, false, true, 'Markdown', $this->getEditProfileKeyboard($aData[1], $aProfile));
	}

	protected function getEditProfileKeyboard($sProfileId, $aProfile) {
		$aKeyboard = array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => ($aProfile['pokemon']['enabled']?'Disable':'Enable').' notify Pokemon',
						'callback_data' => '["edit_global",'.$sProfileId.',"on-off","pokemon"]'
					)
				)
			)
		);

		if($aProfile['pokemon']['enabled']) {
			$aValues = array(
				0 => '0 KM',
				2 => '2 KM',
				5 => '5 KM',
				10 => '10 KM'
			);
			foreach($aValues as $sValue => $sDescription) {
				if(($aProfile['pokemon']['default']['min_dist'] != $sValue) && (($sValue <= $aProfile['pokemon']['default']['max_dist']) || ($aProfile['pokemon']['default']['max_dist'] == 'inf'))) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\xB6 Set Min Dist to ".$sDescription,
							'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","min_dist",'.json_encode($sValue).']'
						)
					);
				}
			}

			$aValues = array(
				2 => '2 KM',
				5 => '5 KM',
				10 => '10 KM',
				'inf' => 'Infinite'
			);
			foreach($aValues as $sValue => $sDescription) {
				if(($aProfile['pokemon']['default']['max_dist'] != $sValue) && (($sValue >= $aProfile['pokemon']['default']['min_dist']) || ($sValue == 'inf'))) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\x97 Set Max Dist to ".$sDescription,
							'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","max_dist",'.json_encode($sValue).']'
						)
					);
				}
			}

// 			if($aProfile['pokemon']['default']['min_cp'] != 0) {
// 				$aKeyboard['inline_keyboard'][] = array(
// 					array(
// 						'text' => 'Set Min CP to 0',
// 						'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","min_cp",'.json_encode($sValue).']'
// 					)
// 				);
// 			}

			for($sValue = 0; $sValue <= 100; $sValue += 10) {
				if(($aProfile['pokemon']['default']['min_iv'] != $sValue) && ($sValue <= $aProfile['pokemon']['default']['max_iv'])) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\xAE Set Min IV to ".$sValue.'%',
							'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","min_iv",'.$sValue.']'
						)
					);
				}
			}

			for($sValue = 0; $sValue <= 100; $sValue += 10) {
				if(($aProfile['pokemon']['default']['max_iv'] != $sValue) && ($sValue >= $aProfile['pokemon']['default']['min_iv'])) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\xAF Set Max IV to ".$sValue.'%',
							'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","max_iv",'.$sValue.']'
						)
					);
				}
			}

			$aKeyboard['inline_keyboard'][] = array(
				array(
					'text' => ($aProfile['pokemon']['default']['ignore_missing']?'Disable':'Enable').' Exceptions only',
					'callback_data' => '["edit_global",'.$sProfileId.',"default","pokemon","ignore_missing",'.($aProfile['pokemon']['default']['ignore_missing']?'false':'true').']'
				)
			);
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => ($aProfile['eggs']['enabled']?'Disable':'Enable').' notify Eggs',
				'callback_data' => '["edit_global",'.$sProfileId.',"on-off","eggs"]'
			)
		);

		if($aProfile['eggs']['enabled']) {
			for($sValue = 1; $sValue <= 5; $sValue++) {
				if(($aProfile['eggs']['min_level'] != $sValue) && ($sValue <= $aProfile['eggs']['max_level'])) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => 'Set Min level to '.implode('', array_fill(0, $sValue, "\xE2\xAD\x90")),
							'callback_data' => '["edit_global",'.$sProfileId.',"set","eggs","min_level",'.$sValue.']'
						)
					);
				}
			}

			for($sValue = 1; $sValue <= 5; $sValue++) {
				if(($aProfile['eggs']['max_level'] != $sValue) && ($sValue >= $aProfile['eggs']['min_level'])) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => 'Set Max level to '.implode('', array_fill(0, $sValue, "\xE2\xAD\x90")),
							'callback_data' => '["edit_global",'.$sProfileId.',"set","eggs","max_level",'.$sValue.']'
						)
					);
				}
			}
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => ($aProfile['raids']['enabled']?'Disable':'Enable').' notify RAIDs',
				'callback_data' => '["edit_global",'.$sProfileId.',"on-off","raids"]'
			)
		);

		if($aProfile['raids']['enabled']) {
			$aValues = array(
				0 => '0 KM',
				2 => '2 KM',
				5 => '5 KM',
				10 => '10 KM'
			);
			foreach($aValues as $sValue => $sDescription) {
				if(($aProfile['raids']['default']['min_dist'] != $sValue) && (($sValue <= $aProfile['raids']['default']['max_dist']) || ($aProfile['raids']['default']['max_dist'] == 'inf'))) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\xB6 Set Min Dist to ".$sDescription,
							'callback_data' => '["edit_global",'.$sProfileId.',"default","raids","min_dist",'.json_encode($sValue).']'
						)
					);
				}
			}

			$aValues = array(
				2 => '2 KM',
				5 => '5 KM',
				10 => '10 KM',
				'inf' => 'Infinite'
			);
			foreach($aValues as $sValue => $sDescription) {
				if(($aProfile['raids']['default']['max_dist'] != $sValue) && (($sValue >= $aProfile['raids']['default']['min_dist']) || ($sValue == 'inf'))) {
					$aKeyboard['inline_keyboard'][] = array(
						array(
							'text' => "\xF0\x9F\x9A\x97 Set Max Dist to ".$sDescription,
							'callback_data' => '["edit_global",'.$sProfileId.',"default","raids","max_dist",'.json_encode($sValue).']'
						)
					);
				}
			}

			$aKeyboard['inline_keyboard'][] = array(
				array(
					'text' => ($aProfile['raids']['default']['ignore_missing']?'Disable':'Enable').' Exceptions only',
					'callback_data' => '["edit_global",'.$sProfileId.',"default","raids","ignore_missing",'.($aProfile['raids']['default']['ignore_missing']?'false':'true').']'
				)
			);
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\xAC\x85 Back",
				'callback_data' => '["edit_profile",'.$sProfileId.']'
			)
		);

		return $aKeyboard;
	}

	protected function add_pokemon_exception($aJson, $aData) {
		return call_user_func_array(array(&$this, '_addException'), array_merge(array('pokemon'), func_get_args()));
	}

	protected function add_raids_exception($aJson, $aData) {
		return call_user_func_array(array(&$this, '_addException'), array_merge(array('raids'), func_get_args()));
	}

	protected function _addException($sKey, $aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		if(is_string($aData)) {
			$aData = json_decode($aData);
		}

		//workaround for retry on pokemon name
		$aArgs = func_get_args();
		if(count($aArgs) > 3) {
			$sPokemon = strtolower(array_pop($aArgs));
		}
		else {
			$sPokemon = null;
		}

		if(is_null($sPokemon) && empty($aData[2])) {
			$aJson['callback_query']['message']['text'] = '/add_'.$sKey.'_exception '.json_encode($aData);//workaround
			$this->storeMessage($aJson);
			return $this->storeMessage($this->sendMessage($sChatId, 'Please insert a pokemon name', $this->getMessageId($aJson), true));
		}

		$iPokemonId = null;
		if(empty($aData[2])) {
			//search for most similar name
			$aMatches = array();
			$bExactMatch = false;
			foreach($this->aConfig['pokemon'] as $sId => $sName) {
				$sDownCasedName = strtolower($sName);
				if($sDownCasedName == $sPokemon) {
					$bExactMatch = true;
					$iPokemonId = $sId;
					break;
				}

				$iMatch = similar_text($sDownCasedName, $sPokemon);
				//must match al least 90% of inserted name
				if($iMatch >= floor(strlen($sPokemon) * 0.9)) {
					$aMatches[$sId] = $iMatch;
				}
			}

			if(!$bExactMatch) {
				if(empty($aMatches)) {
					$aJson['callback_query']['message']['text'] = '/add_'.$sKey.'_exception '.json_encode($aData);//workaround
					$this->storeMessage($aJson);
					return $this->storeMessage($this->sendMessage($sChatId, 'Can\'t find a pokemon named *'.$sPokemon."*.\nPlease insert a pokemon name", $this->getMessageId($aJson), true, true, 'Markdown'));
				}

				natsort($aMatches);
				$aMatches = array_reverse($aMatches, true);

				return $this->sendMessage($sChatId, 'What pokemon did you mean with *'.$sPokemon.'*?', null, false, true, 'Markdown', $this->getPokemonKeyboard($sKey, $aData[1], $aMatches));
			}

			$aData[2] = $iPokemonId;
		}

		$this->recursivelyDeleteStoredMessage($this->getChatId($aJson), $this->getReplyToMessageId($aJson));

		return call_user_func_array(array(&$this, 'edit_'.$sKey.'_exception'), array($aJson, $aData));
	}

	protected function getPokemonKeyboard($sKey, $sProfileId, $aMatches) {
		$aKeyboard = array(
			'inline_keyboard' => array()
		);

		foreach($aMatches as $sPokemonId => $iMatch) {
			if(!array_key_exists($sPokemonId, $this->aConfig['pokemon'])) {
				continue;
			}

			$aKeyboard['inline_keyboard'][] = array(
				array(
					'text' => $this->aConfig['pokemon'][$sPokemonId],
					'callback_data' => '["add_'.$sKey.'_exception",'.$sProfileId.','.$sPokemonId.']'
				)
			);
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\xAC\x85 Back",
				'callback_data' => '["edit_profile",'.$sProfileId.']'
			)
		);

		return $aKeyboard;
	}

	protected function edit_pokemon_exception($aJson, $aData) {
		$sPokemon = $this->aConfig['pokemon'][$aData[2]];

		return $this->sendMessage($this->getChatId($aJson), 'Pokemon exceptions for *'.$sPokemon.'*', null, false, true, 'Markdown', $this->getEditPokemonExceptionKeyboard($aJson, $aData[1], $aData[2]));
	}

	protected function getEditPokemonExceptionKeyboard($aJson, $sProfileId, $iPokemonId) {
		$aKeyboard = array(
			'inline_keyboard' => array()
		);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($this->getChatId($aJson), 'profiles');

		if(array_key_exists($iPokemonId, $aProfiles[$sProfileId]['pokemon']['exceptions'])) {
			foreach($aProfiles[$sProfileId]['pokemon']['exceptions'][$iPokemonId] as $iKey => $aException) {
				$aKeyboard['inline_keyboard'][] = array(
					array(
						'text' => 'Min level '.$aException['min_level']."\nMin IV ".$aException['min_iv'],
						'callback_data' => '["edit_pokemon_exception_sub",'.$sProfileId.','.$iPokemonId.','.$iKey.']'
					),
					array(
						'text' => "\xE2\x9D\x8C",
						'callback_data' => '["delete_pokemon_exception_sub",'.$sProfileId.','.$iPokemonId.','.$iKey.']'
					)
				);
				
			}
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\x9E\x95 Add new",
				'callback_data' => '["add_pokemon_exception_sub",'.$sProfileId.','.$iPokemonId.']'
			)
		);
		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\xAC\x85 Back",
				'callback_data' => '["edit_profile",'.$sProfileId.']'
			)
		);

		return $aKeyboard;
	}

	protected function edit_raids_exception($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		$aProfiles[$aData[1]]['raids']['exceptions'][$aData[2]] = !$aProfiles[$aData[1]]['raids']['exceptions'][$aData[2]];
		$oSession->storeValue($sChatId, 'profiles', $aProfiles);

		return $this->edit_profile($aJson, $aData);
	}

	protected function delete_pokemon_exception($aJson, $aData) {
		return $this->_deleteException('pokemon', $aJson, $aData);
	}

	protected function delete_raids_exception($aJson, $aData) {
		return $this->_deleteException('raids', $aJson, $aData);
	}

	protected function _deleteException($sKey, $aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');

		if($aData[3]) {
			unset($aProfiles[$aData[1]][$sKey]['exceptions'][$aData[2]]);
			$oSession->storeValue($sChatId, 'profiles', $aProfiles);

			return $this->sendMessage($this->getChatId($aJson), ucfirst($sKey).' exception for *'.$this->aConfig['pokemon'][$aData[2]].'* deleted', null, false, true, 'Markdown', $this->getProfileKeyboard($aData[1], $aProfiles[$aData[1]]));
		}
		else {
			return $this->sendMessage($sChatId, 'Are you sure you want to delete '.ucfirst($sKey).' exception for *'.$this->aConfig['pokemon'][$aData[2]].'*?', null, false, true, 'Markdown', $this->getConfirmDeleteExceptionKeyboard($sKey, $aData[1], $aData[2]));
		}
	}

	protected function getConfirmDeleteExceptionKeyboard($sKey, $sProfileId, $sPokemonId) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => 'Yes',
						'callback_data' => '["delete_'.$sKey.'_exception",'.$sProfileId.','.$sPokemonId.',true]'
					),
					array(
						'text' => 'No',
						'callback_data' => '["edit_profile",'.$sProfileId.']'
					)
				)
			)
		);
	}

	protected function add_pokemon_exception_sub($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');

		//setup defaults
		$aProfiles[$aData[1]]['pokemon']['exceptions'][$aData[2]][] = array(
			'min_level' => 0,
			'min_iv' => 0
		);
		$oSession->storeValue($sChatId, 'profiles', $aProfiles);

		$aKeys = array_keys($aProfiles[$aData[1]]['pokemon']['exceptions'][$aData[2]]);
		$aData[3] = array_pop($aKeys);
		return $this->edit_pokemon_exception_sub($aJson, $aData);
	}

	protected function edit_pokemon_exception_sub($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');
		$aException =& $aProfiles[$aData[1]]['pokemon']['exceptions'][$aData[2]][$aData[3]];

		if(!empty($aData[4])) {
			$aException[$aData[4]] = $aData[5];
			$oSession->storeValue($sChatId, 'profiles', $aProfiles);
		}

		return $this->sendMessage($this->getChatId($aJson), 'Pokemon exception for *'.$this->aConfig['pokemon'][$aData[2]]."*:\nMin level ".$aException['min_level']."\nMin IV ".$aException['min_iv'], null, false, true, 'Markdown', $this->getEditPokemonExceptionSubKeyboard($aData[1], $aData[2], $aData[3], $aException));
	}

	protected function getEditPokemonExceptionSubKeyboard($sProfileId, $sPokemonId, $sKey, $aException) {
		$aKeyboard = array(
			'inline_keyboard' => array()
		);

		for($sValue = 0; $sValue <= 40; $sValue += 5) {
			if($aException['min_level'] != $sValue) {
				$aKeyboard['inline_keyboard'][] = array(
					array(
						'text' => 'Set Min Level to '.$sValue,
						'callback_data' => '["edit_pokemon_exception_sub",'.$sProfileId.','.$sPokemonId.','.$sKey.',"min_level",'.$sValue.']'
					)
				);
			}
		}

		for($sValue = 0; $sValue <= 100; $sValue += 10) {
			if($aException['min_iv'] != $sValue) {
				$aKeyboard['inline_keyboard'][] = array(
					array(
						'text' => 'Set Min IV to '.$sValue,
						'callback_data' => '["edit_pokemon_exception_sub",'.$sProfileId.','.$sPokemonId.','.$sKey.',"min_iv",'.$sValue.']'
					)
				);
			}
		}

		$aKeyboard['inline_keyboard'][] = array(
			array(
				'text' => "\xE2\xAC\x85 Back",
				'callback_data' => '["edit_pokemon_exception",'.$sProfileId.','.$sPokemonId.']'
			)
		);
		return $aKeyboard;
	}

	protected function delete_pokemon_exception_sub($aJson, $aData) {
		$sChatId = $this->getChatId($aJson);

		$oSession = Session::getSingleton();
		$aProfiles = $oSession->retrieveValue($sChatId, 'profiles');

		if($aData[4]) {
			unset($aProfiles[$aData[1]]['pokemon']['exceptions'][$aData[2]][$aData[3]]);
			$oSession->storeValue($sChatId, 'profiles', $aProfiles);

			return $this->sendMessage($this->getChatId($aJson), 'Pokemon exception for *'.$this->aConfig['pokemon'][$aData[2]].'* deleted', null, false, true, 'Markdown', $this->getEditPokemonExceptionKeyboard($aJson, $aData[1], $aData[2]));
		}
		else {
			return $this->sendMessage($sChatId, 'Are you sure you want to delete Pokemon exception for *'.$this->aConfig['pokemon'][$aData[2]].'*?', null, false, true, 'Markdown', $this->getConfirmDeletePokemonExceptionSubKeyboard($aData[1], $aData[2], $aData[3]));
		}
	}

	protected function getConfirmDeletePokemonExceptionSubKeyboard($sProfileId, $sPokemonId, $sKey) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => 'Yes',
						'callback_data' => '["delete_pokemon_exception_sub",'.$sProfileId.','.$sPokemonId.','.$sKey.',true]'
					),
					array(
						'text' => 'No',
						'callback_data' => '["edit_pokemon_exception",'.$sProfileId.','.$sPokemonId.']'
					)
				)
			)
		);
	}
}
