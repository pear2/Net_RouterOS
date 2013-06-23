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
 * Represents a RouterOS message.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
abstract class Message
{

    /**
     * @var array An array with message arguments. Keys are the names of the
     *     arguments, array values are values for the corresponding argument.
     */
    protected $arguments = array();

    /**
     * @var string An optional tag to associate the message with.
     */
    private $_tag = null;
    
    /**
     * A shorthand gateway.
     * 
     * This is a magic PHP method that allows you to call the object as a
     * function. Depending on the argument given, one of the other functions in
     * the class is invoked and its returned value is returned by this function.
     * 
     * @param string $name The name of an argument to get the value of, or NULL
     *     to get all arguments as an array.
     * 
     * @return string|array The value of the specified argument, or an array of
     *     all arguments if NULL is provided.
     */
    public function __invoke($name = null)
    {
        if (null === $name) {
            return $this->getAllArguments();
        }
        return $this->getArgument($name);
    }

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
                'Invalid name of argument supplied.',
                InvalidArgumentException::CODE_NAME_INVALID
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
        if (Communicator::isSeekableStream($value)) {
            return $value;
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
     * @return self|Message The message object.
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
     *     if such an argument is not set.
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
     *     values as argument values.
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
     *     removes an argument of this name.
     * 
     * @return self|Message The message object.
     * @see getArgument()
     */
    protected function setArgument($name, $value = '')
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
     * @return self|Message The message object.
     */
    protected function removeAllArguments()
    {
        $this->arguments = array();
        return $this;
    }
}
