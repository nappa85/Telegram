<?php

require_once('Bot.php');

/**
* Facebook Bot class
*/
abstract class FacebookBot extends Bot {
    protected function _curl($sChatId, $aOpts) {
	$this->sendChatAction($sChatId, 'typing');

	$rCurl = curl_init();
	curl_setopt_array($rCurl, $aOpts);
	$sResponse = curl_exec($rCurl);
	curl_close($rCurl);

	return $sResponse;
    }

    protected function _getCookies($sResponse) {
	if(empty($_SESSION['cookies'])) {
	    $_SESSION['cookies'] = array();
	}

	if(preg_match_all('/Set\-Cookie\:\s+([^\=]+)=([^\;]+)/', $sResponse, $aMatches, PREG_SET_ORDER)) {
	    foreach($aMatches as $aMatch) {
		$_SESSION['cookies'][$aMatch[1]] = $aMatch[2];
	    }
	}

	$aCookies = array();
	foreach($_SESSION['cookies'] as $sKey => $sValue) {
	    $aCookies[] = $sKey.'='.$sValue;
	}
	return implode('; ', $aCookies);
    }

    protected function _getContent($sChatId, $sUrl, $sCookies, $iBack = 0, $iYear = 0) {
	//scan the desired page
	$sResponse = $this->_curl($sChatId, array(
	    CURLOPT_URL => 'https://m.facebook.com'.$sUrl,
	    CURLOPT_COOKIE => $sCookies,
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_SSL_VERIFYHOST => 2,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_USERAGENT => 'Nokia-MIT-Browser/3.0',
	    CURLOPT_REFERER => 'https://m.facebook.com/',
	));

	//collect bottom links
	if(preg_match_all('/\<div\s+class\=\"\w{1}\"\>\<a[^\>]+href\=\"([^\"]+)\"\>[\s\w]+\<\/a\>/', $sResponse, $aMatches)) {
	    $aLinks = array();
	    foreach($aMatches[1] as $sLink) {
		if(strpos($sLink, $this->aConfig['params']['profile']) === 0) {
		    $aLinks[] = $sLink;
		}
	    }

	    if($iYear > 0) {
		//select random year
		if($iYear < count($aLinks)) {
		    return $this->_getContent($sChatId, html_entity_decode($aLinks[$iYear]), $sCookies, $iBack);
		}
		else {
		    //if random exceeded, use last link (often "year of birth")
		    return $this->_getContent($sChatId, html_entity_decode($aLinks[count($aLinks) - 1]), $sCookies, $iBack);
		}
	    }
	    elseif($iBack > 0) {
		//go back as many times as desired
		return $this->_getContent($sChatId, html_entity_decode($aLinks[0]), $sCookies, $iBack - 1);
	    }
	    else {
		//get page random content
		$aPosts = preg_split('/\<div[^\>]+id\=\"\w{1}_\d{1}_\d{1}\"[^\>]*\>/', $sResponse);
		unset($aPosts[0]);

		//clean last post
		$iIndex = count($aPosts);
		if(preg_match_all('/\<[\/]{0,1}div/', $aPosts[$iIndex], $aMatches, PREG_OFFSET_CAPTURE)) {
		    $iOpenDiv = 0;
		    foreach($aMatches[0] as $aMatch) {
			$iOpenDiv += ($aMatch[0] == '<div'?1:-1);
			if($iOpenDiv < 0) {
			    $aPosts[$iIndex] = substr($aPosts[$iIndex], 0, $aMatch[1]);
			    break;
			}
		    }
		}

		//clean username from posts
// 		foreach($aPosts as $iIndex => $sPost) {
// 		    $aPosts[$iIndex] = preg_replace('/\<h3[\s\S]+\<\/h3\>/', '', $sPost);
// 
// 		    //strip date and like-share-comment buttons
// 		    if(preg_match_all('/\<div/', $sPost, $aMatches, PREG_OFFSET_CAPTURE)) {
// 			$aPosts[$iIndex] = substr($sPost, 0, $aMatches[0][count($aMatches[0]) - 2][1]);
// 		    }
// 		}

		//select random post
		$iIndex = rand(1, count($aPosts));
		$aRes = array();

		//get post text (if present)
		if(preg_match_all('/\<p\>([\s\S]+)\<\/p\>/', $aPosts[$iIndex], $aMatches)) {
		    $sText = trim(html_entity_decode(strip_tags($aMatches[1][0]), ENT_QUOTES, 'UTF-8'));
		    if(!empty($sText)) {
			$aRes['text'] = $sText;
		    }
		}
// 		$aRes['text'] = html_entity_decode(strip_tags($aPosts[$iIndex]));

		//get post image (if present)
		if(!empty($aRes['text']) && preg_match_all('/\<img[^\>]+src="([^\"]+)\"/', $aPosts[$iIndex], $aMatches)) {
		    foreach($aMatches[1] as $sImg) {
			//skip like image
			if(strpos($sImg, 'rsrc.php') === false) {
			    $aRes['img'] = html_entity_decode($sImg);
			    $aRes['text'] .= "\n".$aRes['img'];//debug
			    break;
			}
		    }
		}

		return $aRes;
	    }
	}

	return false;
    }

    public function _login($sChatId) {
	//retrieve login page
	$sResponse = $this->_curl($sChatId, array(
	    CURLOPT_URL => 'https://m.facebook.com/',
	    CURLOPT_HEADER => 1,
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_SSL_VERIFYHOST => 2,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_USERAGENT => 'Nokia-MIT-Browser/3.0',
	    CURLOPT_REFERER => 'https://www.google.com/',
	));

	if(!preg_match('/\<form[^\>]+id\=\"login_form\"[^\>]+action\=\"([^\"]+)\"[^\>]*\>([\s\S]+)\<\/form\>/', $sResponse, $aMatch)) {
	    return $this->sendMessage($sChatId, 'An error happened trying to access Facebook');
	}

	$sUrl = $aMatch[1];
	if(!preg_match_all('/\<input[^\>]+name\=\"([^\"]+)\"[^\>]+value\=\"([^\"]+)\"/', $aMatch[2], $aMatches, PREG_SET_ORDER)) {
	    return $this->sendMessage($sChatId, 'An error happened trying to access Facebook');
	}

	$aParams = array();
	foreach($aMatches as $aMatch) {
	    $aParams[$aMatch[1]] = $aMatch[2];
	}
	$aParams['email'] = $this->aConfig['params']['email'];
	$aParams['pass'] = $this->aConfig['params']['pass'];

	//perform user login
	$sResponse = $this->_curl($sChatId, array(
	    CURLOPT_URL => $sUrl,
	    CURLOPT_POST => 1,
	    CURLOPT_POSTFIELDS => $aParams,
	    CURLOPT_COOKIE => $this->_getCookies($sResponse),
	    CURLOPT_HEADER => 1,
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_SSL_VERIFYHOST => 2,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_USERAGENT => 'Nokia-MIT-Browser/3.0',
	    CURLOPT_REFERER => 'https://m.facebook.com/',
	));

	if(!preg_match('/Location\:\s+(\S+)/', $sResponse, $aMatch)) {
	    return $this->sendMessage($sChatId, 'An error happened trying to access Facebook');
	}

	//obtain user's cookie
	$sResponse = $this->_curl($sChatId, array(
	    CURLOPT_URL => $aMatch[1],
	    CURLOPT_POST => 1,
	    CURLOPT_POSTFIELDS => $aParams,
	    CURLOPT_COOKIE => $this->_getCookies($sResponse),
	    CURLOPT_HEADER => 1,
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_SSL_VERIFYHOST => 2,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_USERAGENT => 'Nokia-MIT-Browser/3.0',
	    CURLOPT_REFERER => 'https://m.facebook.com/',
	));
    }

    public function get($aJson = null) {
	$sChatId = $this->getChatId($aJson);

	$aRes = array();
	$iCount = 10;
	while((count($aRes) == 0) && ($iCount > 0)) {
	    if(empty($_SESSION['cookies'])) {
		$this->_login($sChatId);
	    }

	    $aRes = $this->_getContent($sChatId, $this->aConfig['params']['profile'], $this->_getCookies($sResponse), rand(0, 10), rand(0, 10));
	    if($aRes === false) {
		$_SESSION['cookies'] = array();
	    }

	    $iCount--;
	}

	if(count($aRes) == 0) {
	    return $this->sendMessage($sChatId, 'An error happened trying to access Facebook');
	}

	if(empty($aRes['img'])) {
	    return $this->sendMessage($sChatId, $aRes['text']);
	}
	else {
	    return $this->sendPhoto($sChatId, $aRes['img'], $aRes['text']);
	}
    }
}
