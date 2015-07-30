<?php

//this security check should be parametrized
if(($_SERVER["HTTP_X_FORWARDED_SERVER"] != "ssl.altervista.org") || ($_SERVER["HTTP_X_FORWARDED_HOST"] != "nappa85.ssl.altervista.org")) {
    exit(0);
}

require_once('Slim-2.6.2/Slim/Slim.php');

\Slim\Slim::registerAutoloader();

$oApp = new \Slim\Slim();
$oApp->post('/:bot/:secret', function($sBotName, $sToken) {
    require_once('classes/'.$sBotName.'.php');

    $oBot = new $sBotName($sToken);
    $oBot->parse(file_get_contents('php://input'));
});
$oApp->run();
