<?php
/* stlTweetControllerClass
 * 
 * This class handles all Twitter specific controls and all databasecommunication.
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
 *  This file is part of TwitterTweet. 
 * 
 *     TwitterTweet is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    TwitterTweet is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with TwitterTweet.  If not, see <http://www.gnu.org/licenses/>.
 */

class stlTweetControllerClass extends twShareControllerClass{
    const HTTP_STATUS_FOUND = 302; //All ok but prefers other options
    const HTTP_STATUS_OK = 200; //Returned on all ok
    const HTTP_STATUS_FORBIDDEN = 403; //Returned from Twitter on duplicate tweets
    var $twitterFormToken;
    var $fullTweet;

    
    function __construct($config) {
        parent::__construct($config);
        $this->config = array_merge(  
            array(
            'basicTweetClassFile'   =>      'simpleTwitterClass.php',
            'basicTweetClassName'   =>      'simpleTwitterClass',
            'oauthTweetClassFile'   =>      'stileroOauthTweetClass.php',
            'oauthTweetClassName'   =>      'stileroOauthTweetClass',
            'useOauth'              =>      TRUE,
            'tinyURLAPI'            =>      'http://tinyurl.com/api-create.php?url=',
            'twitterLoginFormURL'   =>      'https://mobile.twitter.com/session/new',
            'twitterLoginPostURL'   =>      'https://mobile.twitter.com/session',
            'twitterDataURL'        =>      'https://mobile.twitter.com/',
            'twitterUsername'       =>      '',
            'twitterPassword'       =>      '',
            'twitterWebAgent'       =>      'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            'twitterMaxHashTags'    =>      3,
            'oauthConsumerKey'          =>      '',
            'oauthConsumerSecret'       =>      '',
            'oauthUserKey'            =>      '',
            'oauthUserSecret'           =>      '',
            'curlPost'              =>      1,
            'curlSSLVerifyPeer'     =>      false,
            'curlSSLVerifyHost'     =>      2,
            'curlCookieFile'        =>      '',
            'curlReturnTransfer'    =>      1,
            'curlFollowLocation'    =>      0,
            'articlesNewerThan'     =>      '',
            'sectionsToTweet'       =>      '',
            'tweetDelay'            =>      '',
            'showDebugInfo'         =>      1,
            'useMetaAsHashTag'      =>      ''
            ),
        $config
        );
    }
    
    public function tweet() {

            $this->buildTweet();
            if( $this->error != FALSE) {
            return false;
            }
            $code = $this->doTweet();
//        }
        
        if( $code == self::HTTP_STATUS_OK ){
            $this->saveLogToDB();
            return true;
        }  else {
            $this->error['message'] = $this->config['pluginLangPrefix'].'ERRORCODE_'.$code;
            $this->error['type'] = 'error';
            return false;
        }
    }

    public function doTweet() {
        $this->initializeClasses();
        if($this->error != FALSE ){
            $this->error['message'] = $this->config['pluginLangPrefix'].'FAILED_INIT_CLASSES';
            $this->error['type'] = 'error';
            return;
        }
        $code = $this->twTweetClass->sendTweet($this->fullTweet);
        return $code;
    }
        
    public function initializeClasses() {
        if($this->error != FALSE ){
            return;
        }
        if($this->config['useOauth']) {
            $className = $this->config['oauthTweetClassName'];
            $this->twTweetClass = new $className(
                array(
                'oauthConsumerKey'    => $this->config['oauthConsumerKey'],
                'oauthConsumerSecret' => $this->config['oauthConsumerSecret'],
                'oauthUserKey'      => $this->config['oauthUserKey'],
                'oauthUserSecret'     => $this->config['oauthUserSecret']
                )
            );
        }else{
           $className = $this->config['basicTweetClassName'];
           $this->twTweetClass = new $className( 
                array(
                'twitterUsername'   =>      $this->config['twitterUsername'],
                'twitterPassword'   =>      $this->config['twitterPassword']
                )
            ); 
        }
    }
    
    public function isServerSupportingRequiredFunctions(){
        if($this->error != FALSE ){
            return FALSE;
        }
        if( ! function_exists( curl_init ) || ! function_exists(file_get_contents) ){
            $this->error['message'] = $this->config['pluginLangPrefix'].'NO_CURL_SUPPORT';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }
    
    public function isServerSafeModeDisabled (){
        if(ini_get('safe_mode')){
            $this->error['message'] = $this->config['pluginLangPrefix'].'SERVER_IN_SAFE_MODE';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }

    public function isLoginDetailsEntered() {
        if($this->error != FALSE ){
            return FALSE;
        }
        if( $this->config['twitterUsername'] == "" || $this->config['twitterPassword'] == ""){
            $this->error['message'] = $this->config['pluginLangPrefix'].'NOLOGINDETAILS';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }
    
    public function isOauthDetailsEntered() {
        if($this->error != FALSE ){
            return FALSE;
        }
        if( $this->config['oauthConsumerKey'] == "" || $this->config['oauthConsumerSecret'] == "" || $this->config['oauthUserKey'] == "" || $this->config['oauthUserSecret'] == ""){
            $this->error['message'] = $this->config['pluginLangPrefix'].'NOOAUTHDETAILS';
            $this->error['type'] = 'error';
            return FALSE;
        }
    }

    public function getTinyURL() {
        $fullURL = $this->articleObject->full_url;
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

    public function getTinyUrlUsinCurl() {
        $fullURL = $this->articleObject->full_url;
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
                $this->error['message'] = 'Tiny URL Errorcode:'.$resultArray['http_code'];
                $this->error['type'] = 'error';
                break;
        }
        return false;
    }

    public function getHashTags() {
        if( ! $this->config['useMetaAsHashTag']) {
            return '';
        }
        $k2ItemTagsArray = $this->articleObject->tags; 
//        if(count($k2ItemTagsArray) == 0){
//            return;
//        }
        if($this->config['defaultHashTag'] != ""){
            $defaultTag = str_replace('#', '', $this->config['defaultHashTag']);
            if(count($k2ItemTagsArray) == 0){
                $k2ItemTagsArray[]=$defaultTag;
            }else{
                array_unshift($k2ItemTagsArray, $defaultTag);
            }
        }
        $i = 0;
        foreach ($k2ItemTagsArray as $key => $value) {
            if($i++ < $this->config['twitterMaxHashTags'] ){
                $tagsArray[] = trim($value);
            }
        }
        $hashTags = " #".implode(" #", $tagsArray);      
        return $hashTags;
    }

    public function buildTweet() {
        $tinyURL = $this->getTinyURL();
        $fullTextMessage = $this->articleObject->title;
        $hashTagsCommaSeparated = $this->getHashTags();
        $tinyURLLength = count($tinyURL);
        $hashTagsLength = count($hashTagsCommaSeparated);
        $truncatedTweet = substr($fullTextMessage, 0, 140 - $tinyURLLength -1 - $hashTagsLength -1);
        $fullTweet = $truncatedTweet." ".$tinyURL.$hashTagsCommaSeparated;
        $this->fullTweet = $fullTweet;
    }

    public function setTwitterLoginDetails($twitterUsername, $twitterPassword) {
        $this->config['twitterUsername'] = $twitterUsername;
        $this->config['twitterPassword'] = $twitterPassword;
    }

}

