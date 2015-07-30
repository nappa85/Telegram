<?php

//this security check should be parametrized
if(($_SERVER["HTTP_X_FORWARDED_SERVER"] != "ssl.altervista.org") || ($_SERVER["HTTP_X_FORWARDED_HOST"] != "nappa85.ssl.altervista.org")) {
    exit(0);
}

if(preg_match('/^\/Telegram\/([^\/]+)\/([^\/]+)/', $_SERVER['SCRIPT_URL'], $aMatch)) {
    require_once('classes/'.$aMatch[1].'.php');

    $oBot = new $aMatch[1]($aMatch[2]);
    $oBot->parse(file_get_contents('php://input'));
}
