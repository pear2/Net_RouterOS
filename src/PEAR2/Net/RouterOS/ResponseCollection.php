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
 * Implemented by this class.
 */
use ArrayAccess;

/**
 * Implemented by this class.
 */
use Countable;

/**
 * Implemented by this class.
 */
use SeekableIterator;

/**
 * Represents a collection of RouterOS responses.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class ResponseCollection implements ArrayAccess, SeekableIterator, Countable
{
    
    /**
     * @var array An array with all {@link Response} objects.
     */
    protected $responses = array();
    
    /**
     * @var array An array with each {@link Response} object's type.
     */
    protected $responseTypes = array();
    
    /**
     * @var array An array with each {@link Response} object's tag.
     */
    protected $responseTags = array();

    /**
     * @var array An array with positions of responses, based on an argument
     * name. The name of each argument is the array key, and the array value is
     * another array where the key is the value for that argument, and the value
     * is the posistion of the response. For performance reasons, each key is
     * built only when {@link static::setIndex()} is called with that argument,
     * and remains available for the lifetime of this collection.
     */
    protected $responsesIndex = array();
    
    /**
     * @var array An array with all distinct arguments across all
     *     {@link Response} objects. Created at the first call of
     *     {@link static::getArgumentMap()}.
     */
    protected $argumentMap = null;
    
    /**
     * @var int A pointer, as required by SeekableIterator.
     */
    protected $position = 0;

    /**
     * @var string|null Name of argument to use as index. NULL when disabled.
     */
    protected $index = null;
    
    /**
     * Creates a new collection.
     * 
     * @param array $responses An array of responses, in network order.
     */
    public function __construct(array $responses)
    {
        $pos = 0;
        foreach ($responses as $response) {
            if ($response instanceof Response) {
                $this->responseTypes[$pos] = $response->getType();
                $this->responseTags[$pos] = $response->getTag();
                $this->responses[$pos++] = $response;
            }
        }
    }

    /**
     * A shorthand gateway.
     * 
     * This is a magic PHP method that allows you to call the object as a
     * function. Depending on the argument given, one of the other functions in
     * the class is invoked and its returned value is returned by this function.
     * 
     * @param int|string $offset The offset of the response to seek to.
     *     If the collection is indexed, you can also supply a value to seek to.
     *     Setting NULL will seek to the last response.
     * 
     * @return Response The {@link Response} at the specified index, last
     *     reponse if no index is provided or FALSE if the index is invalid or the
     *     collection is empty.
     */
    public function __invoke($offset = null)
    {
        return null === $offset ? $this->end() : $this->seek($offset);
    }

    /**
     * Sets an argument to be usable as a key in the collection.
     * 
     * @param string|null $name The name of the argument to use. Future calls
     *     that accept a position will then also be able to search that argument
     *     for a matching value.
     *     Specifying NULL will disable such lookups (as is by default).
     *     Note that in case this value occures multiple times within the
     *     collection, only the last matching response will be accessible by
     *     that value.
     * 
     * @return $this The object itself.
     */
    public function setIndex($name)
    {
        if (null !== $name) {
            $name = (string)$name;
            if (!isset($this->responsesIndex[$name])) {
                $this->responsesIndex[$name] = array();
                foreach ($this->responses as $pos => $response) {
                    $val = $response->getArgument($name);
                    if (null !== $val) {
                        $this->responsesIndex[$name][$val] = $pos;
                    }
                }
            }
        }
        $this->index = $name;
        return $this;
    }

    /**
     * Gets the name of the argument used as an index.
     * 
     * @return string|null Name of argument to use as index. NULL when disabled.
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Gets the whole collection as an array.
     * 
     * @param bool $useIndex Whether to use the index values as keys for the
     * resulting array.
     * 
     * @return array An array with all responses, in network order.
     */
    public function toArray($useIndex = false)
    {
        if ($useIndex) {
            $positions = $this->responsesIndex[$this->index];
            asort($positions, SORT_NUMERIC);
            $positions = array_flip($positions);
            return array_combine(
                $positions,
                array_intersect_key($this->responses, $positions)
            );
        }
        return $this->responses;
    }

    /**
     * Counts the responses in the collection.
     * 
     * @return int The number of responses in the collection.
     */
    public function count()
    {
        return count($this->responses);
    }

    /**
     * Checks if an offset exists.
     * 
     * @param int|string $offset The offset to check.
     * 
     * @return bool TRUE if the offset exists, FALSE otherwise.
     */
    public function offsetExists($offset)
    {
        return is_int($offset)
            ? array_key_exists($offset, $this->responses)
            : array_key_exists($offset, $this->responsesIndex[$this->index]);
    }

    /**
     * Gets a {@link Response} from a specified offset.
     * 
     * @param int|string $offset The offset of the desired response. If the
     * collection is indexed, you can also supply the value to search for.
     * 
     * @return Response The response at the specified offset.
     */
    public function offsetGet($offset)
    {
        return is_int($offset)
            ? $this->responses[$offset]
            : $this->responses[$this->responsesIndex[$this->index][$offset]];
    }

    /**
     * N/A
     * 
     * This method exists only because it is required for ArrayAccess. The
     * collection is read only.
     * 
     * @param int|string $offset N/A
     * @param Response   $value  N/A
     * 
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        
    }

    /**
     * N/A
     * 
     * This method exists only because it is required for ArrayAccess. The
     * collection is read only.
     * 
     * @param int|string $offset N/A
     * 
     * @return void
     */
    public function offsetUnset($offset)
    {
        
    }

    /**
     * Resets the pointer to 0, and returns the first response.
     * 
     * @return Response The first response in the collection, or FALSE if the
     *     collection is empty.
     */
    public function rewind()
    {
        return $this->seek(0);
    }

    /**
     * Moves the position pointer to a specified position.
     * 
     * @param int|string $position The position to move to. If the collection is
     * indexed, you can also supply a value to move the pointer to.
     * 
     * @return Response The {@link Response} at the specified position, or FALSE
     *     if the specified position is not valid.
     */
    public function seek($position)
    {
        $this->position = is_int($position)
            ? $position
            : $this->responsesIndex[$this->index][$position];
        return $this->current();
    }

    /**
     * Moves the pointer forward by 1, and gets the next response.
     * 
     * @return Response The next {@link Response} object, or FALSE if the
     *     position is not valid.
     */
    public function next()
    {
        ++$this->position;
        return $this->current();
    }

    /**
     * Gets the response at the current pointer position.
     * 
     * @return Response The response at the current pointer position, or FALSE
     *     if the position is not valid.
     */
    public function current()
    {
        return $this->valid() ? $this->responses[$this->position] : false;
    }

    /**
     * Moves the pointer backwards by 1, and gets the previous response.
     * 
     * @return Response The next {@link Response} object, or FALSE if the
     *     position is not valid.
     */
    public function prev()
    {
        --$this->position;
        return $this->current();
    }

    /**
     * Moves the pointer to the last valid position, and returns the last
     * response.
     * 
     * @return Response The last response in the collection, or FALSE if the
     *     collection is empty.
     */
    public function end()
    {
        $this->position = count($this->responses) - 1;
        return $this->current();
    }

    /**
     * Gets the key at the current pointer position.
     * 
     * @return int The key at the current pointer position, i.e. the pointer
     *     position itself, or FALSE if the position is not valid.
     */
    public function key()
    {
        return $this->valid() ? $this->position : false;
    }

    /**
     * Checks if the pointer is still pointing to an existing offset.
     * 
     * @return bool TRUE if the pointer is valid, FALSE otherwise.
     */
    public function valid()
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Gets all distinct argument names.
     * 
     * Gets all distinct argument names across all responses.
     * 
     * @return array An array with all distinct argument names as keys, and the
     *     indexes at which they occur as values.
     */
    public function getArgumentMap()
    {
        if (null === $this->argumentMap) {
            $arguments = array();
            foreach ($this->responses as $index => $response) {
                foreach (array_keys($response->getAllArguments()) as $name) {
                    if (!isset($arguments[$name])) {
                        $arguments[$name] = array();
                    }
                    $arguments[$name][] = $index;
                }
            }
            $this->argumentMap = $arguments;
        }
        return $this->argumentMap;
    }

    /**
     * Gets all responses of a specified type.
     * 
     * @param string $type The response type to filter by. Valid values are the
     *     Response::TYPE_* constants.
     * 
     * @return static A new collection with responses of the
     *     specified type.
     */
    public function getAllOfType($type)
    {
        $result = array();
        foreach (array_keys($this->responseTypes, $type, true) as $index) {
            $result[] = $this->responses[$index];
        }
        return new static($result);
    }

    /**
     * Gets all responses with a specified tag.
     * 
     * @param string $tag The tag to filter by.
     * 
     * @return static A new collection with responses having the
     *     specified tag.
     */
    public function getAllTagged($tag)
    {
        $result = array();
        foreach (array_keys($this->responseTags, $tag, true) as $index) {
            $result[] = $this->responses[$index];
        }
        return new static($result);
    }

    /**
     * Gets the last {@link Response} in the collection.
     * 
     * @return Response The last response in the collection or FALSE if the
     *     collection is empty.
     */
    public function getLast()
    {
        $offset = count($this->responses) - 1;
        return $offset >= 0 ? $this->responses[$offset] : false;
    }

    /**
     * Calls a method of the response pointed by the pointer.
     * 
     * Calls a method of the response pointed by the pointer. This is a magic
     * PHP method, thanks to which any function you call on the collection that
     * is not defined will be redirected to the response.
     * 
     * @param string $method The name of the method to call.
     * @param array  $args   The arguments to pass to the method.
     * 
     * @return mixed Whatever the called function returns.
     */
    public function __call($method, array $args)
    {
        return call_user_func_array(
            array($this->current(), $method),
            $args
        );
    }
}
