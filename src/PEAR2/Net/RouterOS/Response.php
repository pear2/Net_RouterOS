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
 * Represents a RouterOS response.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
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
    private $_type = null;

    /**
     * Extracts a new response from a communicator.
     * 
     * @param Communicator $com      The communicator from which to extract the
     * new response.
     * @param bool         $asStream Whether to populate the argument values
     * with streams instead of strings.
     * 
     * @see getType()
     * @see getArgument()
     */
    public function __construct(Communicator $com, $asStream = false)
    {
        if (!$com->getTransmitter()->isDataAwaiting()) {
            throw new SocketException(
                'No data awaiting. Receiving aborted.', 206
            );
        }
        $this->setType($com->getNextWord());
        if ($asStream) {
            for (
            $word = $com->getNextWordAsStream(), fseek($word, 0, SEEK_END);
                    ftell($word) !== 0;
                    $word = $com->getNextWordAsStream(), fseek(
                        $word, 0, SEEK_END
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
                            $word, $value, 0xFFFFF, $bytesCopied
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
                if (preg_match('/^=([^=]+)=(.*)$/sm', $word, $matches)) {
                    $this->setArgument($matches[1], $matches[2]);
                } elseif (preg_match('/^\.tag=(.*)$/sm', $word, $matches)) {
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
     * @return Response The response object.
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
                'Unrecognized response type.', 207, null, $type
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