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
 * Represents a collection of RouterOS responses.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 */
class ResponseCollection implements \ArrayAccess, \SeekableIterator, \Countable
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
     * @var array An array with all distinct arguments across all
     * {@link Response} objects. Created at the first call of
     * {@link getArgumentMap()}.
     */
    protected $argumentMap = null;
    
    /**
     * @var int A pointer, as required by SeekableIterator.
     */
    protected $position = 0;
    
    /**
     * Creates a new collection.
     * 
     * @param array $responses An array of responses, in network order.
     */
    public function __construct(array $responses)
    {
        $index = 0;
        foreach ($responses as $response) {
            if ($response instanceof Response) {
                $this->responseTypes[$index] = $response->getType();
                $this->responseTags[$index] = $response->getTag();
                $this->responses[$index++] = $response;
            }
        }
    }
    
    /**
     * Gets the whole collection as an array.
     * 
     * @return array An array with all responses, in network order.
     */
    public function toArray()
    {
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
     * @param int $offset The offset to check.
     * 
     * @return bool TRUE if the offset exists, FALSE otherwise.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->responses);
    }

    /**
     * Gets a {@link Response} from a specified offset.
     * 
     * @param int $offset The offset of the desired response.
     * 
     * @return Response The response at the specified offset.
     */
    public function offsetGet($offset)
    {
        return $this->responses[$offset];
    }

    /**
     * N/A
     * 
     * This method exists only because it is required for ArrayAccess. The
     * collection is read only.
     * 
     * @param int      $offset N/A
     * @param Response $value  N/A
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
     * @param int $offset N/A
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
     * collection is empty.
     */
    public function rewind()
    {
        return $this->seek(0);
    }

    /**
     * Moves the position pointer to a specified position.
     * 
     * @param int $position The position to move to.
     * 
     * @return Response The {@link Response} at the specified position, or FALSE
     * if the specified position is not valid.
     */
    public function seek($position)
    {
        $this->position = $position;
        return $this->current();
    }

    /**
     * Moves the pointer forward by 1, and gets the next response.
     * 
     * @return Response The next {@link Response} object, or FALSE if the
     * position is not valid.
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
     * if the position is not valid.
     */
    public function current()
    {
        return $this->valid() ? $this->responses[$this->position] : false;
    }

    /**
     * Gets the key at the current pointer position.
     * 
     * @return int The key at the current pointer position, i.e. the pointer
     * position itself, or FALSE if the position is not valid.
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
     * indexes at which they occur as values.
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
     * Response::TYPE_* constants.
     * 
     * @return ResponseCollection A new collection with responses of the
     * specified type.
     */
    public function getAllOfType($type)
    {
        $result = array();
        foreach (array_keys($this->responseTypes, $type, true) as $index) {
            $result[] = $this->responses[$index];
        }
        return new ResponseCollection($result);
    }
    
    /**
     * Gets all responses with a specified tag.
     * 
     * @param string $tag The tag to filter by.
     * 
     * @return ResponseCollection A new collection with responses having the
     * specified tag.
     */
    public function getAllTagged($tag)
    {
        $result = array();
        foreach (array_keys($this->responseTags, $tag, true) as $index) {
            $result[] = $this->responses[$index];
        }
        return new ResponseCollection($result);
    }
    
    /**
     * Gets the last {@link Response} in the collection.
     * 
     * @return Response The last response in the collection.
     */
    public function getLast()
    {
        return $this->responses[count($this->responses) - 1];
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
            array($this->current(), $method), $args
        );
    }

}