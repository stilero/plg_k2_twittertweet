<?php
/**
 * @version     2.2
 * @package	TwitterTweet K2 Plugin (K2 plugin)
 * @author      Daniel Eliasson Stilero AB - http://www.stilero.com
 * @copyright	Copyright (c) 2011 Stilero AB. All rights reserved.
 * @license	GPLv2
* 	Joomla! is free software. This version may have been modified pursuant
* 	to the GNU General Public License, and as distributed it includes or
* 	is derivative of works licensed under the GNU General Public License or
* 	other free or open source software licenses.
 *
 *  This file is part of K2 TwitterTweet.

    K2 TwitterTweet is free software: you can redistribute it and/or modify
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

// no direct access
defined('_JEXEC') or die ('Restricted access');

// Load the K2 Plugin API
JLoader::register('K2Plugin', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_k2'.DS.'lib'.DS.'k2plugin.php');
//require_once JPATH_ADMINISTRATOR.DS.'components'.DS.'com_k2'.DS.'lib'.DS.'k2plugin.php';

class plgK2Twittertweet extends K2Plugin {
    var $k2Item;
    var $config;
    var $twitterFormToken;
    var $fullTweet;
    var $inBackend;
    var $errorOccured;
    var $CheckClass;
    const HTTP_STATUS_FOUND = '302';
    const HTTP_STATUS_OK = '200';
    const HTTP_STATUS_UNAUTHORIZED = '401'; //Returned from Twitter on wrong OAuth details
    const HTTP_STATUS_FORBIDDEN = '403'; //Returned from Twitter on duplicate tweets

    function plgK2Twittertweet( & $subject, $params) {
        parent::__construct($subject, $params);
        $language = JFactory::getLanguage();
        $language->load('plg_k2_twittertweet', JPATH_ADMINISTRATOR, 'en-GB', true);
        $language->load('plg_k2_twittertweet', JPATH_ADMINISTRATOR, null, true);
        $this->errorOccured = FALSE;
        $this->config = array(
        'shareLogTableName'     =>      '#__k2_twittertweet_tweeted',
        'twitterUsername'       =>      $this->params->def('username'),
        'twitterPassword'       =>      $this->params->def('password'),
        'oauthConsumerKey'      =>      $this->params->def('oauth_consumer_key'),
        'oauthConsumerSecret'   =>      $this->params->def('oauth_consumer_secret'),
        'oauthUserKey'          =>      $this->params->def('oauth_user_key'),
        'oauthUserSecret'       =>      $this->params->def('oauth_user_secret'),
        'twitterMaxHashTags'    =>      3,
        'useOauth'              =>      $this->params->def('oauth_enabled'),
        'twControllerClassName' =>      'stlTweetControllerClass',
        'categoriesToShare'     =>      $this->params->def('section_id'),
        'shareDelay'            =>      $this->params->def('delay'),
        'articlesNewerThan'     =>      $this->params->def('items_newer_than'),
        'useMetaAsHashTag'      =>      $this->params->def('metahash'),
        'defaultHashTag'        =>      $this->params->def('default_hash'),
        'postOnSave'            =>      $this->params->def('post_on_save')
        );            
    }

    public function onK2AfterDisplayContent( & $item, & $params, $limitstart) {
        $this->inBackend = FALSE;
        $this->setupClasses();
        $articleObject = $this->getArticleObjectFromK2Item($item);
        $this->CheckClass->setArticleObject($articleObject);
        $this->sendTweet();
        return;
    }
	
    public function onAfterK2Save( &$item, $isNew ) {
        $this->inBackend = true;
        $this->setupClasses();
        $articleObject = $this->getArticleObjectFromK2Item($item);
        $this->CheckClass->setArticleObject($articleObject);
        $this->sendTweet();
        return;
    }
        
    public function setupClasses() {
        //Load the classes
        define('CLASS_FOLDER', dirname(__FILE__).DS.'twittertweetclasses'.DS);
        
//        require_once CLASS_FOLDER.'stlShareControllerClass.php';
//        require_once CLASS_FOLDER.'stlTweetControllerClass.php';
//        require_once CLASS_FOLDER.'simpleTwitterClass.php';
//        require_once CLASS_FOLDER.'stileroOauthTweetClass.php';
        JLoader::register('stlTweetControllerClass', CLASS_FOLDER.'stlTweetControllerClass.php');
        JLoader::register('twShareControllerClass', CLASS_FOLDER.'ShareControllerClass.php', true);
        JLoader::register('simpleTwitterClass', CLASS_FOLDER.'simpleTwitterClass.php');
        JLoader::register('stileroOauthTweetClass', CLASS_FOLDER.'stileroOauthTweetClass.php');
        $this->CheckClass = new $this->config['twControllerClassName']( 
            array(
            'twitterUsername'       =>      $this->config['twitterUsername'],
            'twitterPassword'       =>      $this->config['twitterPassword'],
            'useOauth'              =>      $this->config['useOauth'],
            'oauthConsumerKey'      =>      $this->config['oauthConsumerKey'],
            'oauthConsumerSecret'   =>      $this->config['oauthConsumerSecret'],
            'oauthUserKey'          =>      $this->config['oauthUserKey'],
            'oauthUserSecret'       =>      $this->config['oauthUserSecret'],
            'shareLogTableName'     =>      $this->config['shareLogTableName'],
            'pluginLangPrefix'      =>      'PLG_CONTENT_K2_TWITTERTWEET_',
            'categoriesToShare'     =>      $this->config['categoriesToShare'],
            'shareDelay'            =>      $this->config['shareDelay'],
            'articlesNewerThan'     =>      $this->config['articlesNewerThan'],
            'useMetaAsHashTag'      =>      $this->config['useMetaAsHashTag'],
            'defaultHashTag'        =>      $this->config['defaultHashTag']
            )
        );
    }
        
    public function sendTweet() {
        if( !$this->isInitialChecksOK() ) {
            $this->displayMessage(JText::_($this->CheckClass->error['message']) , $this->CheckClass->error['type']);
            return;
        }
        if ($this->CheckClass->tweet() ) {
            $tweet = urldecode($this->CheckClass->fullTweet);
            $this->displayMessage(JText::_('Tweeted: ').$tweet);
            return;
        }else{
            $this->displayMessage(JText::_($this->CheckClass->error['message']) , $this->CheckClass->error['type']);
            return;
        }
    }
               
    public function getArticleObjectFromK2Item($k2Item) {
        $articleObject = new stdClass();
        $articleObject->id = $k2Item->id;
        $articleObject->language= $k2Item->language;
        $articleObject->link = $k2Item->link;
        $articleObject->full_url = $this->getFullURL($k2Item->id);
        $articleObject->tags = $this->getK2ItemTags($k2Item->id);
        $articleObject->title = $k2Item->title;
        $articleObject->catid = $k2Item->catid;
        $articleObject->access = $k2Item->access;
        $articleObject->publish_up = $k2Item->publish_up;
        $articleObject->published = $k2Item->published; 
        return $articleObject;
    }

    public function getK2ItemTags($k2ItemID) {
        $query;
        $db = &JFactory::getDbo();
        if( $this->CheckClass->isJoomla16() || $this->CheckClass->isJoomla17()) {
            $query = $db->getQuery(true);
            $query->select('name');
            $query->from('#__k2_tags AS t');
            $query->innerJoin('#__k2_tags_xref AS x ON x.tagID = t.id');
            $query->where('x.itemID = '.(int) $k2ItemID);
        }  elseif( $this->CheckClass->isJoomla15() ) {
            $query = "SELECT ".$db->nameQuote('name').
                " FROM ".$db->nameQuote('#__k2_tags')." AS t".
                " INNER JOIN " . $db->nameQuote('#__k2_tags_xref')." AS x".
                " ON  x.tagID = t.id".
                " WHERE x.itemID = " . $db->Quote($k2ItemID);
        }
        $db->setQuery($query);
        //$k2ItemTags = $db->loadObjectList();
        $k2ItemTags = $db->loadResultArray ();
        return $k2ItemTags;
    }

    public function displayMessage($msg, $messageType = "") {
        $isSetToDisplayMessages = ($this->params->def('pingmessages')==0)?false:true;
        if( ! $isSetToDisplayMessages || ! $this->inBackend ){
            return;
        }else{
            JFactory::getApplication()->enqueueMessage( $msg, $messageType);
        }
    }
        
    private function doInitialChecks() {
        $this->CheckClass->isServerSupportingRequiredFunctions();
        $this->CheckClass->isServerSafeModeDisabled();
        if ( $this->config['useOauth'] ){
            $this->CheckClass->isOauthDetailsEntered();
        }else{
            $this->CheckClass->isLoginDetailsEntered();
        }
        $this->CheckClass->isArticleObjectIncluded();
        $this->CheckClass->isItemActive();
        $this->CheckClass->isItemPublished();
        $this->CheckClass->isItemNewEnough();
        $this->CheckClass->isItemPublic();
        $this->CheckClass->isCategoryToShare();
        $this->CheckClass->prepareTables();
        $this->CheckClass->isSharingToEarly();
        if ( !$this->config['postOnSave'] && !$this->inBackend){
            $this->CheckClass->isItemAlreadyShared();
        }
        return $this->CheckClass->error;
    }
        
    public function isInitialChecksOK() {
        $errorMessage = $this->doInitialChecks();
        if ( $errorMessage != FALSE ) {
            return FALSE;
        }
        return TRUE;
    }
        
    public function getFullURL($k2ItemID) {
        $urlQuery = "?option=com_k2&view=item&id=".$k2ItemID;
        $fullURL = JURI::root()."index.php".$urlQuery;
        return $fullURL;
    }
 
} // END K2 CLASS
