<?php
namespace wcf\util\exception;

/**
 * Denotes failure to perform secure crypto.
 * 
 * @author	Tim Duesterhus
 * @copyright	2001-2015 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	util.exception
 * @category	Community Framework
 */
class CryptoException extends \Exception {
	/**
	 * @see	\Exception::__construct()
	 */
	public function __construct($message, $previous) {
		parent::__construct($message, 0, $previous);
	}
}
