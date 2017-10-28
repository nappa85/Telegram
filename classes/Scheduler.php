<?php

abstract class Scheduler {

	/**
	 * Bot instance
	 * @type Bot
	 */
	protected $oBot = null;

	/*
	 * Scheduler's configuration
	 * @type array
	 */
	protected $aConfig;

	/**
	 * Instantiates the Scheduler
	 */
	public function __construct() {
		$sClass = get_called_class();
		$this->aConfig = parse_ini_file(__DIR__.'/'.$sClass.'.ini.php', true);

		$sBotClassname = str_replace('Scheduler', 'Bot', $sClass);

		//this is a waste of resources, but I don't know hot to do it otherwise
		$aBotConfig = parse_ini_file(__DIR__.'/'.$sBotClassname.'.ini.php', true);

		require_once(__DIR__.'/'.$sBotClassname.'.php');
		$this->oBot = new $sBotClassname($aBotConfig['SECRET']);
	}

	/**
	 * Runs scheduled operations
	 */
	abstract public function run();
}
