<?php
namespace wcf\data\comment\response;
use wcf\data\comment\Comment;
use wcf\data\comment\CommentEditor;
use wcf\data\comment\CommentList;
use wcf\data\object\type\ObjectTypeCache;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\UserInputException;
use wcf\system\user\activity\event\UserActivityEventHandler;
use wcf\system\user\notification\UserNotificationHandler;
use wcf\system\WCF;

/**
 * Executes comment response-related actions.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2013 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.comment.response
 * @category	Community Framework
 */
class CommentResponseAction extends AbstractDatabaseObjectAction {
	/**
	 * @see	\wcf\data\AbstractDatabaseObjectAction::$allowGuestAccess
	 */
	protected $allowGuestAccess = array('loadResponses');
	
	/**
	 * @see	\wcf\data\AbstractDatabaseObjectAction::$className
	 */
	protected $className = 'wcf\data\comment\response\CommentResponseEditor';
	
	/**
	 * comment object
	 * @var	\wcf\data\comment\Comment
	 */
	public $comment = null;
	
	/**
	 * comment manager object
	 * @var	\wcf\system\comment\manager\ICommentManager
	 */
	public $commentManager = null;
	
	/**
	 * @see	\wcf\data\AbstractDatabaseObjectAction::delete()
	 */
	public function delete() {
		if (empty($this->objects)) {
			$this->readObjects();
		}
		
		if (empty($this->objects)) {
			return 0;
		}
		
		// read object type ids for comments
		$commentIDs = array();
		foreach ($this->objects as $response) {
			$commentIDs[] = $response->commentID;
		}
		
		$commentList = new CommentList();
		$commentList->getConditionBuilder()->add("comment.commentID IN (?)", array($commentIDs));
		$commentList->readObjects();
		$comments = $commentList->getObjects();
		
		// update counters
		$processors = $responseIDs = $updateComments = array();
		foreach ($this->objects as $response) {
			$objectTypeID = $comments[$response->commentID]->objectTypeID;
			
			if (!isset($processors[$objectTypeID])) {
				$objectType = ObjectTypeCache::getInstance()->getObjectType($objectTypeID);
				$processors[$objectTypeID] = $objectType->getProcessor();
				$responseIDs[$objectTypeID] = array();
			}
			
			$processors[$objectTypeID]->updateCounter($comments[$response->commentID]->objectID, -1);
			$responseIDs[$objectTypeID][] = $response->responseID;
			
			if (!isset($updateComments[$response->commentID])) {
				$updateComments[$response->commentID] = 0;
			}
			
			$updateComments[$response->commentID]++;
		}
		
		// remove responses
		$count = parent::delete();
		
		// update comment responses and cached response ids
		foreach ($comments as $comment) {
			$commentEditor = new CommentEditor($comment);
			$commentEditor->updateResponseIDs();
			$commentEditor->updateCounters(array(
				'responses' => -1 * $updateComments[$comment->commentID]
			));
		}
		
		foreach ($responseIDs as $objectTypeID => $objectIDs) {
			// remove activity events
			$objectType = ObjectTypeCache::getInstance()->getObjectType($objectTypeID);
			if (UserActivityEventHandler::getInstance()->getObjectTypeID($objectType->objectType.'.response.recentActivityEvent')) {
				UserActivityEventHandler::getInstance()->removeEvents($objectType->objectType.'.response.recentActivityEvent', $objectIDs);
			}
			
			// delete notifications
			if (UserNotificationHandler::getInstance()->getObjectTypeID($objectType->objectType.'.response.notification')) {
				UserNotificationHandler::getInstance()->deleteNotifications('commentResponse', $objectType->objectType.'.response.notification', array(), $objectIDs);
				UserNotificationHandler::getInstance()->deleteNotifications('commentResponseOwner', $objectType->objectType.'.response.notification', array(), $objectIDs);
			}
		}
		
		return $count;
	}
	
	/**
	 * Validates parameters to load responses for a given comment id.
	 */
	public function validateLoadResponses() {
		$this->readInteger('commentID', false, 'data');
		$this->readInteger('lastResponseTime', false, 'data');
		$this->readBoolean('loadAllResponses', true, 'data');
		
		$this->comment = new Comment($this->parameters['data']['commentID']);
		if (!$this->comment->commentID) {
			throw new UserInputException('commentID');
		}
		
		$this->commentManager = ObjectTypeCache::getInstance()->getObjectType($this->comment->objectTypeID)->getProcessor();
		if (!$this->commentManager->isAccessible($this->comment->objectID)) {
			throw new PermissionDeniedException();
		}
	}
	
	/**
	 * Returns parsed responses for given comment id.
	 * 
	 * @return	array
	 */
	public function loadResponses() {
		// get response list
		$responseList = new StructuredCommentResponseList($this->commentManager, $this->comment);
		$responseList->getConditionBuilder()->add("comment_response.time > ?", array($this->parameters['data']['lastResponseTime']));
		if (!$this->parameters['data']['loadAllResponses']) $responseList->sqlLimit = 50;
		$responseList->readObjects();
		
		$lastResponseTime = 0;
		foreach ($responseList as $response) {
			if (!$lastResponseTime) {
				$lastResponseTime = $response->time;
			}
			
			$lastResponseTime = max($lastResponseTime, $response->time);
		}
		
		WCF::getTPL()->assign(array(
			'likeData' => (MODULE_LIKE ? $responseList->getLikeData() : array()),
			'responseList' => $responseList
		));
		
		return array(
			'commentID' => $this->comment->commentID,
			'lastResponseTime' => $lastResponseTime,
			'template' => WCF::getTPL()->fetch('commentResponseList')
		);
	}
}
