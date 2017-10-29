<?php

require_once(__DIR__.'/Scheduler.php');

/**
 * GymHuntr Scheduler class
 */
class GymHuntrScheduler extends Scheduler {

	/**
	 * Runs scheduled operations
	 */
	public function run() {
		$oSession = Session::getSingleton(get_called_class(), 'scheduler');

		foreach($this->aConfig['locations'] as $sLocation) {
			$aLocation = $this->aConfig[$sLocation];

			$aGyms = $this->oBot->_getGyms($aLocation['latitude'], $aLocation['longitude']);

			if(empty($aGyms) || empty($aGyms['raids'])) {
				//to delete stored message
				$aGyms = array('raids' => array());
			}

			if($oSession->storedValue($aLocation['chat_id'], 'last_message_for_'.$sLocation)) {
				$aDeleteMessage = $oSession->retrieveValue($aLocation['chat_id'], 'last_message_for_'.$sLocation);
			}
			else {
				$aDeleteMessage = null;
			}

			//to be used in GymHuntrBot::_formatTimestamp
			$oSession->storeValue($aLocation['chat_id'], 'location', $aLocation);

			$aResult = $this->oBot->_formatRaids($aLocation['chat_id'], $aGyms, 'RAIDs in '.ucfirst($sLocation), false, $aDeleteMessage);
			if($aResult === false) {
				$oSession->deleteValue($aLocation['chat_id'], 'last_message_for_'.$sLocation);
			}
			else {
				$oSession->storeValue($aLocation['chat_id'], 'last_message_for_'.$sLocation, $aResult);
			}
		}
	}

	public function test() {
		echo "Paste this text on https://www.darrinward.com/lat-long/\n";

		$aAllGyms = $aGymByLocations = array();

		foreach($this->aConfig['locations'] as $sLocation) {
			$aLocation = $this->aConfig[$sLocation];

			echo "{$aLocation['latitude']},{$aLocation['longitude']}\n";

			$aGyms = $this->oBot->_getGyms($aLocation['latitude'], $aLocation['longitude']);
			foreach($aGyms['gyms'] as $sGym) {
				$aGym = json_decode($sGym, true);
				if($aGym) {
					$aAllGyms[$aGym['gym_id']] = $aGym;
					$aGymByLocations[$sLocation][] = $aGym['gym_id'];
				}
			}
		}

		$aIntersections = array();
		foreach($aGymByLocations as $sLocation1 => $aGymIds1) {
			foreach($aGymByLocations as $sLocation2 => $aGymIds2) {
				if(($sLocation1 == $sLocation2) || array_key_exists($sLocation2.'+'.$sLocation1, $aIntersections)) {
					continue;
				}

				$aIntersections[$sLocation1.'+'.$sLocation2] = array_intersect($aGymIds1, $aGymIds2);
				foreach($aIntersections[$sLocation1.'+'.$sLocation2] as $sGymId) {
					$aGym = $aAllGyms[$sGymId];
					echo "{$aGym['longitude']},{$aGym['latitude']}\n";
				}
			}
		}
	}
}

//TEST
//$oScheduler = new GymHuntrScheduler();
//$oScheduler->test();
