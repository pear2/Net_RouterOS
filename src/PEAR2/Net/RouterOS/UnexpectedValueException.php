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

use UnexpectedValueException as U;

/**
 * Exception thrown when encountering an invalid value in a function argument.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class UnexpectedValueException extends U implements Exception
{
    const CODE_CALLBACK_INVALID = 10502;
    const CODE_ACTION_UNKNOWN = 30100;
    const CODE_RESPONSE_TYPE_UNKNOWN = 50100;

    /**
     * @var mixed The unexpected value.
     */
    private $_value;

    /**
     * Creates a new UnexpectedValueException.
     * 
     * @param string     $message  The Exception message to throw.
     * @param int        $code     The Exception code.
     * @param \Exception $previous The previous exception used for the exception
     *     chaining.
     * @param mixed      $value    The unexpected value.
     */
    public function __construct(
        $message,
        $code = 0,
        $previous = null,
        $value = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->_value = $value;
    }

    /**
     * Gets the unexpected value.
     * 
     * @return mixed The unexpected value.
     */
    public function getValue()
    {
        return $this->_value;
    }

    // @codeCoverageIgnoreStart
    // String representation is not reliable in testing

    /**
     * Returns a string representation of the exception.
     * 
     * @return string The exception as a string.
     */
    public function __toString()
    {
        return parent::__toString() . "\nValue:{$this->_value}";
    }

    // @codeCoverageIgnoreEnd
}
