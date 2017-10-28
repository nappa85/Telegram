<?php

//this security check should be parametrized
if(($_SERVER["HTTP_X_FORWARDED_PROTO"] != "https") || ($_SERVER["HTTP_X_FORWARDED_PORT"] != "443")) {
    exit(0);
}

if(preg_match('/^\/Telegram\/([^\/]+)\/([^\/]+)/', $_SERVER['REQUEST_URI'], $aMatch)) {
    require_once(__DIR__.'/classes/'.$aMatch[1].'.php');

    $oBot = new $aMatch[1]($aMatch[2]);
    $oBot->parse(file_get_contents('php://input'));
}
