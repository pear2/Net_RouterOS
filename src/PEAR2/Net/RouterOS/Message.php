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
 * Using transmitters.
 */
use PEAR2\Net\Transmitter as T;

/**
 * Represents a RouterOS message.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 */
abstract class Message
{

    /**
     * @var array An array with message arguments. Keys are the names of the
     * arguments, array values are values for the corresponding argument.
     */
    protected $arguments = array();

    /**
     * @var string An optional tag to associate the message with.
     */
    private $_tag = null;

    /**
     * Sanitizes a name of an argument (message or query one).
     * 
     * @param mixed $name The name to sanitize.
     * 
     * @return string The sanitized name.
     */
    public static function sanitizeArgumentName($name)
    {
        $name = (string) $name;
        if ((empty($name) && $name !== '0')
            || !preg_match('/[^=\s]/s', $name)
        ) {
            throw new InvalidArgumentException(
                'Invalid name of argument supplied.', 200
            );
        }
        return $name;
    }

    /**
     * Sanitizes a value of an argument (message or query one).
     * 
     * @param mixed $value The value to sanitize.
     * 
     * @return string The sanitized value.
     */
    public static function sanitizeArgumentValue($value)
    {
        if (T\StreamTransmitter::isStream($value)) {
            $meta = stream_get_meta_data($value);
            if ($meta['seekable']) {
                return $value;
            }
            throw new InvalidArgumentException(
                'The stream must be seekable.', 201
            );
        } else {
            return (string) $value;
        }
    }

    /**
     * Gets the tag that the message is associated with.
     * 
     * @return string The current tag or NULL if there isn't a tag.
     * @see setTag()
     */
    public function getTag()
    {
        return $this->_tag;
    }

    /**
     * Sets the tag to associate the request with.
     * 
     * Sets the tag to associate the message with. Setting NULL erases the
     * currently set tag.
     * 
     * @param string $tag The tag to set.
     * 
     * @return Message The message object.
     * @see getTag()
     */
    protected function setTag($tag)
    {
        $this->_tag = (null === $tag) ? null : (string) $tag;
        return $this;
    }

    /**
     * Gets the value of an argument.
     * 
     * @param string $name The name of the argument.
     * 
     * @return string|resource The value of the specified argument. Returns NULL
     * if such an argument is not set.
     * @see setArgument()
     */
    public function getArgument($name)
    {
        $name = self::sanitizeArgumentName($name);
        if (array_key_exists($name, $this->arguments)) {
            return $this->arguments[$name];
        }
        return null;
    }

    /**
     * Gets all arguments in an array.
     * 
     * @return array An array with the keys as argument names, and the array
     * values as argument values.
     * @see getArgument()
     * @see setArgument()
     */
    public function getAllArguments()
    {
        return $this->arguments;
    }

    /**
     * Sets an argument for the message.
     * 
     * @param string $name  Name of the argument.
     * @param string $value Value of the argument. Setting the value to NULL
     * removes an argument of this name.
     * 
     * @return Message The message object.
     * @see getArgument()
     */
    protected function setArgument($name, $value = null)
    {
        if (null === $value) {
            unset($this->arguments[self::sanitizeArgumentName($name)]);
        } else {
            $this->arguments[self::sanitizeArgumentName($name)]
                = self::sanitizeArgumentValue($value);
        }
        return $this;
    }

    /**
     * Removes all arguments from the message.
     * 
     * @return Message The message object.
     */
    protected function removeAllArguments()
    {
        $this->arguments = array();
        return $this;
    }

}