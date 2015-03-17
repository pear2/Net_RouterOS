<?php

/**
 * ~~summary~~
 *
 * ~~description~~
 *
 * PHP version 5
 *
 * @category  Net
 * @package   PEAR2_Net_RouterOS
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   GIT: $Id$
 * @link      http://pear2.php.net/PEAR2_Net_RouterOS
 */
/**
 * The namespace declaration.
 */
namespace PEAR2\Net\RouterOS;

/**
 * Base of this class.
 */
use RuntimeException;

/**
 * Exception thrown when the request/response cycle goes an unexpected way.
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class DataFlowException extends RuntimeException implements Exception
{
    const CODE_INVALID_CREDENTIALS = 10000;
    const CODE_TAG_REQUIRED = 10500;
    const CODE_TAG_UNIQUE = 10501;
    const CODE_UNKNOWN_REQUEST = 10900;
    const CODE_CANCEL_FAIL = 11200;
}
