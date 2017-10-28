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
				continue;
			}

			if($oSession->storedValue($aLocation['chat_id'], 'last_message_for_'.$sLocation)) {
				$aDeleteMessage = $oSession->retrieveValue($aLocation['chat_id'], 'last_message_for_'.$sLocation);
			}
			else {
				$aDeleteMessage = null;
			}

			//to be used in GymHuntrBot::_formatTimestamp
			$oSession->storeValue($aLocation['chat_id'], 'location', $aLocation);

			$oSession->storeValue($aLocation['chat_id'], 'last_message_for_'.$sLocation, $this->oBot->_formatRaids($aLocation['chat_id'], $aGyms, 'RAIDs in '.ucfirst($sLocation), false, $aDeleteMessage));
		}
	}
}
