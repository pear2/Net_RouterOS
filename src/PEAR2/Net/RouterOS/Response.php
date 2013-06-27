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
 * Refers to transmitter direction constants.
 */
use PEAR2\Net\Transmitter as T;

/**
 * Represents a RouterOS response.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class Response extends Message
{
    
    /**
     * The last response for a request.
     */
    const TYPE_FINAL = '!done';
    
    /**
     * A response with data.
     */
    const TYPE_DATA = '!re';
    
    /**
     * A response signifying error.
     */
    const TYPE_ERROR = '!trap';
    
    /**
     * A response signifying a fatal error, due to which the connection would be
     * terminated.
     */
    const TYPE_FATAL = '!fatal';

    /**
     * @var array An array of unrecognized words in network order.
     */
    protected $unrecognizedWords = array();

    /**
     * @var string The response type.
     */
    private $_type;

    /**
     * Extracts a new response from a communicator.
     * 
     * @param Communicator $com        The communicator from which to extract
     *     the new response.
     * @param bool         $asStream   Whether to populate the argument values
     *     with streams instead of strings.
     * @param int          $timeout_s  If a response is not immediatly
     *     available, wait this many seconds. If NULL, wait indefinetly.
     * @param int          $timeout_us Microseconds to add to the waiting time.
     * @param Registry     $reg        An optional registry to sync the
     *     response with.
     * 
     * @see getType()
     * @see getArgument()
     */
    public function __construct(
        Communicator $com,
        $asStream = false,
        $timeout_s = 0,
        $timeout_us = null,
        Registry $reg = null
    ) {
        if (null === $reg) {
            if ($com->getTransmitter()->isPersistent()) {
                $old = $com->getTransmitter()
                    ->lock(T\Stream::DIRECTION_RECEIVE);
                try {
                    $this->_receive($com, $asStream, $timeout_s, $timeout_us);
                    $com->getTransmitter()->lock($old, true);
                } catch (SocketException $e) {
                    $com->getTransmitter()->lock($old, true);
                    throw $e;
                }
            } else {
                $this->_receive($com, $asStream, $timeout_s, $timeout_us);
            }
        } else {
            while (null === ($response = $reg->getNextResponse())) {
                $newResponse = new self($com, true, $timeout_s, $timeout_us);
                $tagInfo = $reg::parseTag($newResponse->getTag());
                $newResponse->setTag($tagInfo[1]);
                if (!$reg->add($newResponse, $tagInfo[0])) {
                    $response = $newResponse;
                    break;
                }
            }
            
            $this->_type = $response->_type;
            $this->arguments = $response->arguments;
            $this->unrecognizedWords = $response->unrecognizedWords;
            $this->setTag($response->getTag());
            
            if (!$asStream) {
                foreach ($this->arguments as $name => $value) {
                    $this->setArgument(
                        $name,
                        stream_get_contents($value)
                    );
                }
                foreach ($response->unrecognizedWords as $i => $value) {
                    $this->unrecognizedWords[$i] = stream_get_contents($value);
                }
            }
        }
    }
    
    /**
     * Extracts a new response from a communicator.
     * 
     * This is the function that performs the actual receiving, while the
     * constructor is also involved in locks and registry sync.
     * 
     * @param Communicator $com        The communicator from which to extract
     *     the new response.
     * @param bool         $asStream   Whether to populate the argument values
     *     with streams instead of strings.
     * @param int          $timeout_s  If a response is not immediatly
     *     available, wait this many seconds. If NULL, wait indefinetly.
     * @param int          $timeout_us Microseconds to add to the waiting time.
     * 
     * @return void
     */
    private function _receive(
        Communicator $com,
        $asStream = false,
        $timeout_s = 0,
        $timeout_us = null
    ) {
        if (!$com->getTransmitter()->isDataAwaiting(
            $timeout_s,
            $timeout_us
        )
        ) {
            throw new SocketException(
                'No data within the time limit',
                SocketException::CODE_NO_DATA
            );
        }
        $this->setType($com->getNextWord());
        if ($asStream) {
            for (
            $word = $com->getNextWordAsStream(), fseek($word, 0, SEEK_END);
                    ftell($word) !== 0;
                    $word = $com->getNextWordAsStream(), fseek(
                        $word,
                        0,
                        SEEK_END
                    )
            ) {
                rewind($word);
                $ind = fread($word, 1);
                if ('=' === $ind || '.' === $ind) {
                    $prefix = stream_get_line($word, null, '=');
                }
                if ('=' === $ind) {
                    $value = fopen('php://temp', 'r+b');
                    $bytesCopied = ftell($word);
                    while (!feof($word)) {
                        $bytesCopied += stream_copy_to_stream(
                            $word,
                            $value,
                            0xFFFFF,
                            $bytesCopied
                        );
                    }
                    rewind($value);
                    $this->setArgument($prefix, $value);
                    continue;
                }
                if ('.' === $ind && 'tag' === $prefix) {
                    $this->setTag(stream_get_contents($word, -1, -1));
                    continue;
                }
                rewind($word);
                $this->unrecognizedWords[] = $word;
            }
        } else {
            for ($word = $com->getNextWord(); '' !== $word;
                    $word = $com->getNextWord()
            ) {
                if (preg_match('/^=([^=]+)=(.*)$/sS', $word, $matches)) {
                    $this->setArgument($matches[1], $matches[2]);
                } elseif (preg_match('/^\.tag=(.*)$/sS', $word, $matches)) {
                    $this->setTag($matches[1]);
                } else {
                    $this->unrecognizedWords[] = $word;
                }
            }
        }
    }

    /**
     * Sets the response type.
     * 
     * Sets the response type. Valid values are the TYPE_* constants.
     * 
     * @param string $type The new response type.
     * 
     * @return self|Response The response object.
     * @see getType()
     */
    protected function setType($type)
    {
        switch ($type) {
        case self::TYPE_FINAL:
        case self::TYPE_DATA:
        case self::TYPE_ERROR:
        case self::TYPE_FATAL:
            $this->_type = $type;
            return $this;
        default:
            throw new UnexpectedValueException(
                'Unrecognized response type.',
                UnexpectedValueException::CODE_RESPONSE_TYPE_UNKNOWN,
                null,
                $type
            );
        }
    }

    /**
     * Gets the response type.
     * 
     * @return string The response type.
     * @see setType()
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Gets a list of unrecognized words.
     * 
     * @return array The list of unrecognized words.
     */
    public function getUnrecognizedWords()
    {
        return $this->unrecognizedWords;
    }
}
