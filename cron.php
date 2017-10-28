<?php

$aSchedulers = glob(__DIR__.'/classes/*?Scheduler.php');
foreach($aSchedulers as $sScheduler) {
	require_once($sScheduler);
	$sClassName = basename($sScheduler, '.php');

	$oScheduler = new $sClassName();
	$oScheduler->run();
}
