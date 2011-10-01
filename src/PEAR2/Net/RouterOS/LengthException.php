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
 * @version   SVN: $WCREV$
 * @link      http://netrouteros.sourceforge.net/
 */
/**
 * The namespace declaration.
 */
namespace PEAR2\Net\RouterOS;

/**
 * Exception thrown when there is a problem with a word's length.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 */
class LengthException extends \LengthException implements Exception
{

    /**
     *
     * @var mixed The unsuppported value.
     */
    private $_length = null;

    /**
     * Creates a new LengthException.
     * 
     * @param string    $message  The Exception message to throw.
     * @param int       $code     The Exception code.
     * @param Exception $previous The previous exception used for the exception
     * chaining.
     * @param number    $value    The length.
     */
    public function __construct($message, $code = 0, $previous = null,
        $value = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->_length = $value;
    }

    /**
     * Gets the length.
     * 
     * @return number The length.
     */
    public function getLength()
    {
        return $this->_length;
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
        return parent::__toString() . "\nValue:{$this->_length}";
    }

    // @codeCoverageIgnoreEnd
}