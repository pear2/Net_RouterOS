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
 * Implements this interface.
 */
use Countable;

/**
 * Implements this interface.
 */
use IteratorAggregate;

/**
 * Required for IteratorAggregate::getIterator() to work properly with foreach.
 */
use ArrayObject;
use Traversable;

/**
 * Represents a RouterOS message.
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
abstract class Message implements IteratorAggregate, Countable
{

    /**
     * An array with message attributes.
     *
     * Each array key is the the name of an attribute,
     * and the corresponding array value is the value for that attribute.
     *
     * @var array<string,string|resource>
     */
    protected $attributes = array();

    /**
     * An optional tag to associate the message with.
     *
     * @var string
     */
    private $_tag = null;

    /**
     * A shorthand gateway.
     *
     * This is a magic PHP method that allows you to call the object as a
     * function. Depending on the argument given, one of the other functions in
     * the class is invoked and its returned value is returned by this function.
     *
     * @param string|null $name The name of an attribute to get the value of,
     *     or NULL to get the tag.
     *
     * @return string|resource The value of the specified attribute,
     *     or the tag if NULL is provided.
     */
    public function __invoke($name = null)
    {
        if (null === $name) {
            return $this->getTag();
        }
        return $this->getAttribute($name);
    }

    /**
     * Sanitizes a name of an attribute (message or query one).
     *
     * @param mixed $name The name to sanitize.
     *
     * @return string The sanitized name.
     */
    public static function sanitizeAttributeName($name)
    {
        $name = (string) $name;
        if ((empty($name) && $name !== '0')
            || preg_match('/[=\s]/s', $name)
        ) {
            throw new InvalidArgumentException(
                'Invalid name of argument supplied.',
                InvalidArgumentException::CODE_NAME_INVALID
            );
        }
        return $name;
    }

    /**
     * Sanitizes a value of an attribute (message or query one).
     *
     * @param mixed $value The value to sanitize.
     *
     * @return string|resource The sanitized value.
     */
    public static function sanitizeAttributeValue($value)
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
     *
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
     * @return $this The message object.
     *
     * @see getTag()
     */
    protected function setTag($tag)
    {
        $this->_tag = (null === $tag) ? null : (string) $tag;
        return $this;
    }

    /**
     * Gets the value of an attribute.
     *
     * @param string $name The name of the attribute.
     *
     * @return string|resource|null The value of the specified attribute.
     *     Returns NULL if such an attribute is not set.
     *
     * @see setAttribute()
     */
    protected function getAttribute($name)
    {
        $name = self::sanitizeAttributeName($name);
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        return null;
    }

    /**
     * Gets all arguments in an array.
     *
     * @return ArrayObject An ArrayObject with the keys being argument names,
     *     and the array values being argument values.
     *
     * @see getArgument()
     * @see setArgument()
     */
    public function getIterator(): Traversable
    {
        return new ArrayObject($this->attributes);
    }

    /**
     * Counts the number of attributes.
     *
     * @return int The number of attributes.
     */
    public function count(): int
    {
        return count($this->attributes);
    }

    /**
     * Sets an attribute for the message.
     *
     * @param string               $name  Name of the attribute.
     * @param string|resource|null $value Value of the attribute as a string or
     *     seekable stream.
     *     Setting the value to NULL removes an argument of this name.
     *     If a seekable stream is provided, it is sent from its current
     *     position to its end, and the pointer is seeked back to its current
     *     position after sending.
     *     Non seekable streams, as well as all other types, are casted to a
     *     string.
     *
     * @return $this The message object.
     *
     * @see getArgument()
     */
    protected function setAttribute($name, $value = '')
    {
        if (null === $value) {
            unset($this->attributes[self::sanitizeAttributeName($name)]);
        } else {
            $this->attributes[self::sanitizeAttributeName($name)]
                = self::sanitizeAttributeValue($value);
        }
        return $this;
    }

    /**
     * Removes all attributes from the message.
     *
     * @return $this The message object.
     */
    protected function removeAllAttributes()
    {
        $this->attributes = array();
        return $this;
    }
}
