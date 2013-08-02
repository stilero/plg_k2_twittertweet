<?php
/**
 * simpleTwitterClass
 * 
 * A Class Library that handles Tweets through the mobile version of Twitter
 *
 * @version 1.0
 * @author      Daniel Eliasson Stilero AB - http://www.stilero.com
 * @copyright	Copyright (c) 2011 Stilero AB. All rights reserved.
 * @license	GPLv2
* 	Joomla! is free software. This version may have been modified pursuant
* 	to the GNU General Public License, and as distributed it includes or
* 	is derivative of works licensed under the GNU General Public License or
* 	other free or open source software licenses.
 *
 *  This file is part of K2 TwitterTweet. 
 * 
 *     K2 TwitterTweet is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    K2 TwitterTweet is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with K2 TwitterTweet.  If not, see <http://www.gnu.org/licenses/>.
 */

class simpleTwitterClass {
    const HTTP_STATUS_FOUND = 302; //All ok but prefers other options
    const HTTP_STATUS_OK = 200; //Returned on all ok
    const HTTP_STATUS_FORBIDDEN = 403; //Returned from Twitter on duplicate tweets
    var $article;
    var $tweet;
    var $fullTweet;
    var $inBackend;
    var $errorMessage;
    var $errorOccured;
    
    function __construct($config) {
        $this->errorOccured = FALSE;
        $this->errorMessage = "";
        $this->fullTweet = $tweet;
        $this->config = array_merge(
            array(
            'twitterLoginFormURL'   =>      'https://mobile.twitter.com/session/new',
            'twitterLoginPostURL'   =>      'https://mobile.twitter.com/session',
            'twitterDataURL'        =>      'https://mobile.twitter.com/',
            'twitterUsername'       =>      '',
            'twitterPassword'       =>      '',
            'twitterWebAgent'       =>      'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            'twitterMaxHashTags'    =>      3,
            'tinyURLAPI'            =>      'http://tinyurl.com/api-create.php?url=',
            'curlPost'              =>      1,
            'curlSSLVerifyPeer'     =>      false,
            'curlSSLVerifyHost'     =>      2,
            'curlCookieFile'        =>      '',
            'curlReturnTransfer'    =>      1,
            'curlFollowLocation'    =>      0,
            ),
        $config);
    }
    
    public function sendTweet($tweet) {
        $this->fullTweet = $tweet;
        $this->createCookieFile();
        $this->setTwitterLoginFormToken();
        $this->twitterLogin();
        $this->sendMessageToTwitter();
        if( ! $this->errorOccured ){
            return 200;
        }else{
            return $this->errorMessage;
        }
    }

    private function createCookieFile() {
        $cookieFileCreated = tempnam(DS."tmp", "cookies");
        if (!$cookieFileCreated){
            $this->errorMessage = 'Could not create file to store sessions, check your directory permissions.';
            $this->errorOccured = TRUE;
            return FALSE;
        }
        $this->config['curlCookieFile'] = $cookieFileCreated;
    }

    /*
    private function buildTweet() {
        $tinyURL = $this->getTinyURL();
        $fullTextMessage = $this->articleObject->title;
        $hashTagsCommaSeparated = $this->getHashTags();
        $tinyURLLength = count($tinyURL);
        $hashTagsLength = count($hashTagsCommaSeparated);
        $truncatedTweet = substr($fullTextMessage, 0, 140 - $tinyURLLength -1 - $hashTagsLength -1);
        $fullTweet = $truncatedTweet." ".$tinyURL.$hashTagsCommaSeparated;
        $this->fullTweet = $fullTweet;
    }
    */

    private function setTwitterLoginFormToken() {
        if( $this->errorOccured ){
            return false;
        }
        $twitterLoginFormPage = $this->getTwitterLoginFormPage();
        $arrayWithTheTokenInTheMiddle = explode("authenticity_token\" type=\"hidden\" value=\"", $twitterLoginFormPage);
        $arrayWithTheTokenInTheBeginning = explode("\"", $arrayWithTheTokenInTheMiddle[1]);
        $twitterLoginFormToken = $arrayWithTheTokenInTheBeginning[0];
        $this->twitterFormToken = $twitterLoginFormToken;
    }
    
    private function getTwitterLoginFormPage() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,               $this->config['twitterLoginFormURL']); 		
        curl_setopt($ch, CURLOPT_USERAGENT,         $this->config['twitterWebAgent']); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    $this->config['curlReturnTransfer']); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    $this->config['curlFollowLocation']);
        curl_setopt($ch, CURLOPT_COOKIEFILE,        $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR,         $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,    $this->config['curlSSLVerifyHost']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    $this->config['curlSSLVerifyPeer']);
        $twLoginPage = curl_exec ($ch);	
        $twLoginPageResultsArray = curl_getinfo($ch);
        if( $twLoginPageResultsArray['http_code'] != self::HTTP_STATUS_OK ){
            $this->errorMessage = "Failed to contact Twitter (code:".$twLoginPageResultsArray['http_code'].")";
            $this->errorOccured = TRUE;
            curl_close ($ch);
            return FALSE;
        }
        curl_close ($ch);
        return $twLoginPage;
    }

    private function twitterLogin() {
        if( $this->errorOccured ){
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,               $this->config['twitterLoginPostURL']);
        curl_setopt($ch, CURLOPT_USERAGENT,         $this->config['twitterWebAgent']);
        curl_setopt($ch, CURLOPT_POST,              $this->config['curlPost']);
        curl_setopt($ch, CURLOPT_POSTFIELDS,        $this->getTwitterLoginPostfieldsHTTPQuery()); //Build a query from the postarray
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    $this->config['curlReturnTransfer']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    $this->config['curlFollowLocation']);
        curl_setopt($ch, CURLOPT_REFERER,           $this->config['twitterLoginFormURL']);		//set a spoof referrer
        curl_setopt($ch, CURLOPT_COOKIEFILE,        $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR,         $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,    $this->config['curlSSLVerifyHost']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    $this->config['curlSSLVerifyPeer']);
        $twitterLoginResults = curl_exec ($ch);
        $twitterLoginResultsArray = curl_getinfo($ch);
        if( $twitterLoginResultsArray['http_code'] != self::HTTP_STATUS_FOUND ){
            $this->errorMessage = "Failed to login to Twitter (code:".$twitterLoginResultsArray['http_code'].")";
            $this->errorOccured = TRUE;
            curl_close ($ch);
            return FALSE;
        }
        curl_close ($ch);
        return TRUE;
    }

    private function sendMessageToTwitter() {
        if($this->errorOccured){
            return;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,               $this->config['twitterDataURL']);
        curl_setopt($ch, CURLOPT_USERAGENT,         $this->config['twitterWebAgent']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    $this->config['curlReturnTransfer']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    $this->config['curlFollowLocation']);
        curl_setopt($ch, CURLOPT_REFERER,           $this->config['twitterLoginFormURL']);
        curl_setopt($ch, CURLOPT_COOKIEFILE,        $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR,         $this->config['curlCookieFile']);
        curl_setopt($ch, CURLOPT_POST,              $this->config['curlPost']);
        curl_setopt($ch, CURLOPT_POSTFIELDS,        $this->getTweetPostfieldsHTTPQuery());
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,    $this->config['curlSSLVerifyHost']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    $this->config['curlSSLVerifyPeer']);
        $result = curl_exec ($ch);
        $tweetPostResultsArray = curl_getinfo($ch);
        curl_close ($ch);
        //Kill the cookie, we don't need it anymore
        unlink($this->config['curlCookieFile']);
        switch ($tweetPostResultsArray['http_code']) {
            case self::HTTP_STATUS_OK:
                return true;
                break;
            case self::HTTP_STATUS_FOUND:
                return true;
                break;
            default:
                $this->errorMessage = "Tweet Failed (code:".$tweetPostResultsArray['http_code'].")";
                $this->errorOccured = TRUE;
                break;
        }
        return false;
    }

    private function getTinyURL() {
        $fullURL = $this->getFullURL();
        $encodedFullURL = urlencode($fullURL);
        $apiCallForTinyURL = $this->config['tinyURLAPI'].$encodedFullURL;

        $tinyURL = @file_get_contents($apiCallForTinyURL);
        if($tinyURL == ""){
            $tinyURL = $this->getTinyUrlUsinCurl();
        }
        $tinyUrlLength = strlen($tinyURL);
        $fullUrlLength = strlen($fullURL);
        if( ($fullUrlLength < $tinyUrlLength ) || $tinyURL == "" ){
            $tinyURL = $fullURL;
        }
        return $tinyURL;
    }

    private function getTinyUrlUsinCurl() {
        $fullURL = $this->getFullURL();
        $apiCallForTinyURL = $this->config['tinyURLAPI'] . $fullURL;
        $postvars = array('url' => $fullURL );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,               $apiCallForTinyURL); 	 //The auth page to visit
        curl_setopt($ch, CURLOPT_POST,              $this->config['curlPost']);
        curl_setopt($ch, CURLOPT_POSTFIELDS,        http_build_query($postvars)); //Build a query from the postarray
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    $this->config['curlReturnTransfer']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    $this->config['curlFollowLocation']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,    $this->config['curlSSLVerifyHost']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    $this->config['curlSSLVerifyPeer']);
        $tinyURL = curl_exec ($ch);							//Go get the page and put the result in a variable
        $resultArray = curl_getinfo($ch);
        curl_close ($ch);
        switch ($resultArray['http_code']) {
            case self::HTTP_STATUS_OK:
                return $tinyURL;
                break;
            case self::HTTP_STATUS_FOUND:
                return $tinyURL;
                break;
            default:
                $this->errorMessage = JText::_("Errorcode:").$resultArray['http_code'];
                $this->errorOccured = TRUE;
                break;
        }
        return false;
    }
//    private function getK2ItemTags() {
//            $query;
//            $db = &JFactory::getDbo();
//            if( $this->isJoomla16() || $this->isJoomla17()) {
//                $query = $db->getQuery(true);
//                $query->select('name');
//                $query->from('#__k2_tags AS t');
//                $query->innerJoin('#__k2_tags_xref AS x ON x.tagID = t.id');
//                $query->where('x.itemID = '.(int) $this->articleObject->id);
//            }  elseif( $this->isJoomla15() ) {
//                $query = "SELECT ".$db->nameQuote('name').
//                    " FROM ".$db->nameQuote('#__k2_tags')." AS t".
//                    " INNER JOIN " . $db->nameQuote('#__k2_tags_xref')." AS x".
//                    " ON  x.tagID = t.id".
//                    " WHERE x.itemID = " . $db->Quote($this->articleObject->id);
//            }
//            $db->setQuery($query);
//            $k2ItemTags = $db->loadObjectList();
//            return $k2ItemTags;
//    }
//
//    private function getHashTags() {
//        $k2ItemTagsArray = $this->getK2ItemTags();         
//        if(count($k2ItemTagsArray) == 0){
//            return;
//        }
//        $tagsArray;
//        $i = 0;
//        foreach ($k2ItemTagsArray as $key => $value) {
//            if($i++ < $this->config['twitterMaxHashTags'] ){
//                $tagsArray[] = str_replace(" ", "", $value->name) ;
//            }
//        }
//        $tagsArray = ( count($tagsArray) > 0 )? implode(",",$tagsArray):"";
//        $metakeys = str_replace(" ", "", $tagsArray);
//        $hashtags = explode(",", $metakeys);
//        $implodedtags = ( count($hashtags) >0 )? implode(" #", $hashtags):"";
//        $hashtags = (count($hashtags) >0)?" #".$implodedtags:"";
//        return $hashtags;
//    }

    public function setTwitterLoginDetails($twitterUsername, $twitterPassword) {
        $this->config['twitterUsername'] = $twitterUsername;
        $this->config['twitterPassword'] = $twitterPassword;
    }

    private function getTwitterLoginPostfieldsHTTPQuery() {
        $twitterLoginPostFieldsArray = array(
            'username'	=>  $this->config['twitterUsername'],
            'password'  =>  $this->config['twitterPassword']
        );
        $twitterLoginHTTPQuery = http_build_query($twitterLoginPostFieldsArray);
        return $twitterLoginHTTPQuery;           
    }

    private function getTweetPostfieldsHTTPQuery() {
        $tweetPostFieldsArray = array(
            'tweet[text]'           =>  $this->fullTweet,
            'authenticity_token'    =>  $this->twitterFormToken
        );
        $tweetPostHTTPQuery = http_build_query($tweetPostFieldsArray);
        return $tweetPostHTTPQuery;           
    }
}