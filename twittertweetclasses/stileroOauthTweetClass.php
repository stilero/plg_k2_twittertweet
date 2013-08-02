<?php

/**
 * A Class for sending Tweet through the Twitter OAuth protocol
 *
 * @version 1.1
 * @author danieleliasson Stilero AB - http://www.stilero.com
 * @copyright 2011-dec-22 Stilero AB
 * @license GPLv2
 * 
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * 
 * This file is part of K2TwitterTweet
 * 
 * K2TwitterTweet is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or 
 * (at your option) any later version.
 * 
 * K2TwitterTweet is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with K2TwitterTweet.  
 * If not, see <http://www.gnu.org/licenses/>.
 */

class stileroOauthTweetClass {   
    var $config;
    var $tweet;
    var $oauthSignature;
    var $oauthBaseString;
    var $oauthSigningKey;
    var $oauthHeader;
    var $httpHeader;
    var $httpPostVars;
    
    function __construct($config) {
        $this->config = array_merge(
            array(
                'oauthSignMethod'       =>  'HMAC-SHA1',
                'oauthTimestamp'        =>  '',
                'oauthVersion'          =>  '',
                'oauthRequestMethod'    => 'POST',
                'oauthNonce'            => '',
                'oauthTimestamp'        => '',
                'oauthConsumerKey'      => '',
                'oauthConsumerSecret'   => '',
                'oauthUserKey'          => '',
                'oauthUserSecret'       => '',
                'tweetAPIUrl'           => 'https://api.twitter.com/1.1/statuses/update.json',
                'curlUserAgent'         =>  'oauthTweet - www.stilero.com',
                'curlConnectTimeout'    =>  30,
                'curlTimeout'           =>  10,
                'curlReturnTransf'      =>  true,
                'curlSSLVerifyPeer'     =>  false,
                'curlFollowLocation'    =>  false,
                'curlProxy'             =>  false,
                'curlProxyPassword'     =>  false,
                'curlEncoding'          =>  false,
                'curlHeader'            =>  false,
                'curlHeaderOut'         =>  true,
                'debug'                 =>  false,
                'eol'                   =>  "<br /><br />"
            ),
            $config
         ); 
    }
      
    public function sendTweet($tweetStatusMessage) {
        $this->tweet = $tweetStatusMessage;
        $this->prepareBeforeSendingRequest();
        $returnCode = $this->sendRequestToTwitter();
        return $returnCode;
    }
    private function prepareBeforeSendingRequest() {
        $this->httpPostVars = http_build_query(
            array( 'status'    => $this->tweet )
        );
        $this->buildOauthBaseString();
        $this->prepareHeaders();
    }
  
    private function buildOauthBaseString(){
        $this->buildOauthTimestamp();
        $this->buildOauthNonce();
        $requestParameters = array(
            //'include_entities'         =>  true,
            'oauth_consumer_key'       =>  $this->config['oauthConsumerKey'],
            'oauth_nonce'              =>  $this->config['oauthNonce'],
            'oauth_signature_method'   =>  $this->config['oauthSignMethod'],
            'oauth_timestamp'          =>  $this->config['oauthTimestamp'],
            'oauth_token'              =>  $this->config['oauthUserKey'],
            'oauth_version'            =>  $this->config['oauthVersion'],
            'status'                    =>  $this->tweet
            );
        $encodedParameters = rawurlencode($this->encodeRequest($requestParameters));
        $encodedAPIUrl = rawurlencode($this->config['tweetAPIUrl']);
        $this->oauthBaseString = $this->config['oauthRequestMethod'] ."&". 
            $encodedAPIUrl ."&".
            $encodedParameters;
    }
    
    private function encodeRequest($requestParameters) {
        foreach ($requestParameters as $key => $val) {
            $encodedSignArray[] = rawurlencode($key) . "=" . rawurlencode($val);
        }
        $encodedSignature = implode("&", $encodedSignArray);
        return $encodedSignature;
    }
    
    private function buildOauthSigningKey() {
        $this->oauthSigningKey = $this->config['oauthConsumerSecret'] .'&'. $this->config['oauthUserSecret'];
    }
    
    private function buildOauthSignature() {
        $this->buildOauthSigningKey();
        $rawEncodedSignature =  rawurlencode( 
            base64_encode(
                hash_hmac( 'sha1', $this->oauthBaseString, $this->oauthSigningKey, true )
            )
        );
        $searchFor = array('+', '%7E', '%25');
        $replaceWith = array(' ', '~', '%');
        $this->oauthSignature = str_ireplace( $searchFor, $replaceWith, $rawEncodedSignature );
    }
    
    private function buildOauthNonce($length=12) {
        if($this->config['oauthNonce'] != ''){
            return;
        }
        $characters = array_merge(range(0,9), range('A','Z'), range('a','z'));
        $length = $length > count($characters) ? count($characters) : $length;
        shuffle($characters);
        $this->config['oauthNonce'] = md5(substr(implode($characters), 0, $length));
    }

    private function buildOauthTimestamp() {
        if($this->config['oauthTimestamp'] != '' ) {
            return;
        }
        $this->config['oauthTimestamp'] =  time();
    }
    
    private function buildOauthHeaderString() {
        $this->buildOauthNonce();
        $this->buildOauthTimestamp();
        $this->buildOauthSignature();
        $headerStrPrefix = "Oauth ";
        $headerStrParams = array(
            'oauth_consumer_key'        =>  $this->config['oauthConsumerKey'],  
            'oauth_nonce'               =>  $this->config['oauthNonce'],
            'oauth_signature'           =>  $this->oauthSignature,
            'oauth_signature_method'    =>  $this->config['oauthSignMethod'],
            'oauth_timestamp'           =>  $this->config['oauthTimestamp'],
            'oauth_token'               =>  $this->config['oauthUserKey'],
            'oauth_version'             =>  $this->config['oauthVersion']
        );
        foreach ($headerStrParams as $key => $value) {
            $headerStrArray[] = rawurldecode($key).'="'.rawurlencode($value).'"';
        }
        $oauthHeaderStr = implode(", ", $headerStrArray);
        $searchFor = array('+', '%7E', '%25');
        $replaceWith = array(' ', '~', '%');
        $oauthHeaderStr = str_ireplace( $searchFor, $replaceWith, $oauthHeaderStr );
        $this->oauthHeader = "OAuth ".$oauthHeaderStr;
    }
    
    private function prepareHeaders() {
        $this->buildOauthHeaderString();
        $this->httpHeader['Authorization'] = $this->oauthHeader;
        $this->httpHeader['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->httpHeader['Content-Length'] = strlen($this->httpPostVars);
        $this->httpHeader['Expect'] = '';
        foreach ($this->httpHeader as $key => $value) {
            $headerArr[] = trim($key .': '. $value);
        }
        $this->httpHeader = $headerArr;
    }
    
    private function sendRequestToTwitter() {
        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, array(
            CURLOPT_USERAGENT       =>  $this->config['curlUserAgent'],
            CURLOPT_CONNECTTIMEOUT  =>  $this->config['curlConnectTimeout'],
            CURLOPT_TIMEOUT         =>  $this->config['curlTimeout'],
            CURLOPT_RETURNTRANSFER  =>  $this->config['curlReturnTransf'],
            CURLOPT_SSL_VERIFYPEER  =>  $this->config['curlSSLVerifyPeer'],
            CURLOPT_FOLLOWLOCATION  =>  $this->config['curlFollowLocation'],
            CURLOPT_PROXY           =>  $this->config['curlProxy'],
            CURLOPT_ENCODING        =>  $this->config['curlEncoding'],
            CURLOPT_URL             =>  $this->config['tweetAPIUrl'],
            CURLOPT_POST            =>  TRUE,
            CURLOPT_POSTFIELDS      =>  $this->httpPostVars,
            CURLOPT_HTTPHEADER      =>  $this->httpHeader,
            CURLOPT_HEADER          =>  $this->config['curlHeader'],
            CURLINFO_HEADER_OUT     =>  $this->config['curlHeaderOut'],
            CURLOPT_CUSTOMREQUEST   =>  $this->config['oauthRequestMethod']
        ));
        if ($this->config['curlProxyPassword'] !== false) {
            curl_setopt($curlHandler, CURLOPT_PROXYUSERPWD, $this->config['curl_proxyuserpwd']);
        }
        $twitterResponse = curl_exec($curlHandler);
        //$twitterResponseCode = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
        $twitterResultsArray = curl_getinfo($curlHandler);
        $twitterResponseCode = $twitterResultsArray['http_code'];
        curl_close($curlHandler);
        return $twitterResponseCode;
    }

}

