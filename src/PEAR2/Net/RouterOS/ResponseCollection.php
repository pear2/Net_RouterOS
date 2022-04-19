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
 *
 * @method string getType()
 *     Calls {@link Response::getType()}
 *     on the response pointed by the pointer.
 * @method string[] getUnrecognizedWords()
 *     Calls {@link Response::getUnrecognizedWords()}
 *     on the response pointed by the pointer.
 * @method string|resource|null getProperty(string $name)
 *     Calls {@link Response::getProperty()}
 *     on the response pointed by the pointer.
 * @method string getTag()
 *     Calls {@link Response::getTag()}
 *     on the response pointed by the pointer.
 */
class ResponseCollection implements ArrayAccess, SeekableIterator, Countable
{

    /**
     * An array with all {@link Response} objects.
     *
     * An array with all Response objects.
     *
     * @var Response[]
     */
    protected $responses = array();

    /**
     * An array with each Response object's type.
     *
     * An array with each {@link Response} object's type.
     *
     * @var string[]
     */
    protected $responseTypes = array();

    /**
     * An array with each Response object's tag.
     *
     * An array with each {@link Response} object's tag.
     *
     * @var string[]
     */
    protected $responseTags = array();

    /**
     * An array with positions of responses, based on an property name.
     *
     * The name of each property is the array key, and the array value
     * is another array where the key is the value for that property, and
     * the value is the position of the response. For performance reasons,
     * each key is built only when {@link static::setIndex()} is called with
     * that property, and remains available for the lifetime of this collection.
     *
     * @var array<string,array<string,int>>
     */
    protected $responsesIndex = array();

    /**
     * An array with all distinct properties.
     *
     * An array with all distinct properties across all {@link Response}
     * objects. Created at the first call of {@link static::getPropertyMap()}.
     *
     * @var array<string,int[]>
     */
    protected $propertyMap = null;

    /**
     * A pointer, as required by SeekableIterator.
     *
     * @var int
     */
    protected $position = 0;

    /**
     * Name of property to use as index
     *
     * NULL when disabled.
     *
     * @var string|null
     */
    protected $index = null;

    /**
     * Compare criteria.
     *
     * Used by {@link static::compare()} to determine the order between
     * two responses. See {@link static::orderBy()} for a detailed description
     * of this array's format.
     *
     * @var string[]|array<string,null|int|array<int|callable>>
     */
    protected $compareBy = array();

    /**
     * Creates a new collection.
     *
     * @param Response[] $responses An array of responses, in network order.
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
     * @param int|string|null $offset The offset of the response to seek to.
     *     If the offset is negative, seek to that relative to the end.
     *     If the collection is indexed, you can also supply a value to seek to.
     *     Setting NULL will get the current response's iterator.
     *
     * @return Response|ArrayObject The {@link Response} at the specified
     *     offset, the current response's iterator (which is an ArrayObject)
     *     when NULL is given, or FALSE if the offset is invalid
     *     or the collection is empty.
     */
    public function __invoke($offset = null)
    {
        return null === $offset
            ? $this->current()->getIterator()
            : $this->seek($offset);
    }

    /**
     * Sets a property to be usable as a key in the collection.
     *
     * @param string|null $name The name of the property to use. Future calls
     *     that accept a position will then also be able to search values of
     *     that property for a matching value.
     *     Specifying NULL will disable such lookups (as is by default).
     *     Note that in case this value occurs multiple times within the
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
                    $val = $response->getProperty($name);
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
     * Gets the name of the property used as an index.
     *
     * @return string|null Name of property used as index. NULL when disabled.
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Gets the whole collection as an array.
     *
     * @param bool $useIndex Whether to use the index values as keys for the
     *     resulting array.
     *
     * @return Response[] An array with all responses, in network order.
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
    public function count(): int
    {
        return count($this->responses);
    }

    /**
     * Checks if an offset exists.
     *
     * @param int|string $offset The offset to check. If the
     *     collection is indexed, you can also supply a value to check.
     *     Note that negative numeric offsets are NOT accepted.
     *
     * @return bool TRUE if the offset exists, FALSE otherwise.
     */
    public function offsetExists($offset): bool
    {
        return is_int($offset)
            ? array_key_exists($offset, $this->responses)
            : array_key_exists($offset, $this->responsesIndex[$this->index]);
    }

    /**
     * Gets a {@link Response} from a specified offset.
     *
     * @param int|string $offset The offset of the desired response. If the
     *     collection is indexed, you can also supply the value to search for.
     *
     * @return Response The response at the specified offset.
     */
    public function offsetGet($offset)
    {
        return is_int($offset)
            ? $this->responses[$offset >= 0
            ? $offset
            : count($this->responses) + $offset]
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetUnset($offset)
    {

    }

    /**
     * Resets the pointer to 0, and returns the first response.
     *
     * @return Response|false The first response in the collection,
     *     or FALSE if the collection is empty.
     */
    public function rewind()
    {
        return $this->seek(0);
    }

    /**
     * Moves the position pointer to a specified position.
     *
     * @param int|string $offset The position to move to. If the collection is
     *     indexed, you can also supply a value to move the pointer to.
     *     A non-existent index will move the pointer to "-1".
     *
     * @return Response|false The {@link Response} at the specified position,
     *     or FALSE if the specified position is not valid.
     */
    public function seek($offset)
    {
        $this->position = is_int($offset)
            ? ($offset >= 0
            ? $offset
            : count($this->responses) + $offset)
            : ($this->offsetExists($offset)
            ? $this->responsesIndex[$this->index][$offset]
            : -1);
        return $this->current();
    }

    /**
     * Moves the pointer forward by 1, and gets the next response.
     *
     * @return Response|false The next {@link Response} object,
     *     or FALSE if the position is not valid.
     */
    public function next()
    {
        ++$this->position;
        return $this->current();
    }

    /**
     * Gets the response at the current pointer position.
     *
     * @return Response|false The response at the current pointer position,
     *     or FALSE if the position is not valid.
     */
    public function current()
    {
        return $this->valid() ? $this->responses[$this->position] : false;
    }

    /**
     * Moves the pointer backwards by 1, and gets the previous response.
     *
     * @return Response|false The next {@link Response} object,
     *     or FALSE if the position is not valid.
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
     * @return Response|false The last response in the collection,
     *     or FALSE if the collection is empty.
     */
    public function end()
    {
        $this->position = count($this->responses) - 1;
        return $this->current();
    }

    /**
     * Gets the key at the current pointer position.
     *
     * @return int|false The key at the current pointer position,
     *     i.e. the pointer position itself, or FALSE if the position
     *     is not valid.
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
    public function valid(): bool
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Gets all distinct property names.
     *
     * Gets all distinct property names across all responses.
     *
     * @return array<string,int[]> An array with
     *     all distinct property names as keys, and
     *     the indexes at which they occur as values.
     */
    public function getPropertyMap()
    {
        if (null === $this->propertyMap) {
            $properties = array();
            foreach ($this->responses as $index => $response) {
                $names = array_keys($response->getIterator()->getArrayCopy());
                foreach ($names as $name) {
                    if (!isset($properties[$name])) {
                        $properties[$name] = array();
                    }
                    $properties[$name][] = $index;
                }
            }
            $this->propertyMap = $properties;
        }
        return $this->propertyMap;
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
     * Order resones by criteria.
     *
     * @param string[]|array<string,null|int|array<int|callable>> $criteria The
     *     criteria to order responses by. It takes the
     *     form of an array where each key is the name of the property to use
     *     as (N+1)th sorting key. The value of each member can be either NULL
     *     (for that property, sort normally in ascending order), a single sort
     *     order constant (SORT_ASC or SORT_DESC) to sort normally in the
     *     specified order, an array where the first member is an order
     *     constant, and the second one is sorting flags (same as built in PHP
     *     array functions) or a callback.
     *     If a callback is provided, it must accept two arguments
     *     (the two values to be compared), and return -1, 0 or 1 if the first
     *     value is respectively less than, equal to or greater than the second
     *     one.
     *     Each key of $criteria can also be numeric, in which case the
     *     value is the name of the property, and sorting is done normally in
     *     ascending order.
     *
     * @return static A new collection with the responses sorted in the
     *     specified order.
     */
    public function orderBy(array $criteria)
    {
        $this->compareBy = $criteria;
        $sortedResponses = $this->responses;
        usort($sortedResponses, array($this, 'compare'));
        return new static($sortedResponses);
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

    /**
     * Compares two responses.
     *
     * Compares two responses, based on criteria defined in
     * {@link static::$compareBy}.
     *
     * @param Response $itemA The response to compare.
     * @param Response $itemB The response to compare $a against.
     *
     * @return int Returns 0 if the two responses are equal according to every
     *     criteria specified, -1 if $a should be placed before $b, and 1 if $b
     *     should be placed before $a.
     */
    protected function compare(Response $itemA, Response $itemB)
    {
        foreach ($this->compareBy as $name => $spec) {
            if (!is_string($name)) {
                $name = $spec;
                $spec = null;
            }

            $members = array(
                0 => $itemA->getProperty($name),
                1 => $itemB->getProperty($name)
            );

            if (is_callable($spec)) {
                uasort($members, $spec);
            } elseif ($members[0] === $members[1]) {
                continue;
            } else {
                $flags = SORT_REGULAR;
                $order = SORT_ASC;
                if (is_array($spec)) {
                    list($order, $flags) = $spec;
                } elseif (null !== $spec) {
                    $order = $spec;
                }

                if (SORT_ASC === $order) {
                    asort($members, $flags);
                } else {
                    arsort($members, $flags);
                }
            }
            return (key($members) === 0) ? -1 : 1;
        }

        return 0;
    }
}
