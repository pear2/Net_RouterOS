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
 * A RouterOS communicator.
 * 
 * Implementation of the RouterOS API protocol. Unlike the other classes in this
 * package, this class doesn't provide any conviniences beyond the low level
 * implementation details (automatic word length encoding/decoding, charset
 * translation and data integrity), and because of that, its direct usage is
 * strongly discouraged.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 * @see      Client
 */
class Communicator
{
    /**
     * Used when getting/setting all (default) charsets.
     */
    const CHARSET_ALL = -1;
    
    /**
     * Used when getting/setting the (default) remote charset.
     * 
     * The remote charset is the charset in which RouterOS stores its data.
     * If you want to keep compatibility with your Winbox, this charset should
     * match the default charset from your Windows' regional settings.
     */
    const CHARSET_REMOTE = 0;
    
    /**
     * Used when getting/setting the (default) local charset.
     * 
     * The local charset is the charset in which the data from RouterOS will be
     * returned as. This charset should match the charset of the place the data
     * will eventually be written to.
     */
    const CHARSET_LOCAL = 1;
    
    /**
     * @var array An array with the default charset types as keys, and the
     * default charsets as values.
     */
    protected static $defaultCharsets = array(
        self::CHARSET_REMOTE => null,
        self::CHARSET_LOCAL  => null
    );
    
    /**
     * @var array An array with the current charset types as keys, and the
     * current charsets as values.
     */
    protected $charsets = array();

    /**
     * @var SocketClientTransmitter The transmitter for the connection.
     */
    protected $trans;

    /**
     * Creates a new connection with the specified options.
     * 
     * @param string   $host    Hostname (IP or domain) of the RouterOS server.
     * @param int      $port    The port on which the RouterOS server provides
     * the API service.
     * @param bool     $persist Whether or not the connection should be a
     * persistent one.
     * @param float    $timeout The timeout for the connection.
     * @param string   $key     A string that uniquely identifies the
     * connection.
     * @param resource $context A context for the socket.
     * 
     * @see sendWord()
     */
    public function __construct($host, $port = 8728, $persist = false,
        $timeout = null, $key = '', $context = null
    ) {
        $this->trans = new T\SocketClientTransmitter(
            $host, $port, $persist, $timeout, $key, $context
        );
        $this->setCharset(
            self::getDefaultCharset(self::CHARSET_ALL), self::CHARSET_ALL
        );
    }
    
    /**
     * Uses iconv to convert a stream from one charset to another.
     * 
     * @param string   $in_charset  The charset of the stream.
     * @param string   $out_charset The desired resulting charset.
     * @param resource $stream      The stream to convert.
     * 
     * @return resource A new stream that uses the $out_charset. The stream is a
     * subset from the original stream, from its current position to its end.
     */
    public static function iconvStream($in_charset, $out_charset, $stream)
    {
        $bytes = 0;
        $result = fopen('php://temp', 'r+b');
        $iconvFilter = stream_filter_append(
            $result, 'convert.iconv.' . $in_charset . '.' . $out_charset,
            STREAM_FILTER_WRITE
        );
        
        flock($stream, LOCK_SH);
        while (!feof($stream)) {
            $bytes += stream_copy_to_stream($stream, $result, 0xFFFFF);
        }
        fseek($stream, -$bytes, SEEK_CUR);
        flock($stream, LOCK_UN);
        
        stream_filter_remove($iconvFilter);
        rewind($result);
        return $result;
    }
    
    /**
     * Sets the default charset(s) for new connections.
     * 
     * @param mixed $charset     The charset to set. If $charsetType is
     * {@link CHARSET_ALL}, you can supply either a string to use for all
     * charsets, or an array with the charset types as keys, and the charsets as
     * values.
     * @param int   $charsetType Which charset to set. Valid values are the
     * CHARSET_* constants. Any other value is treated as
     * {@link CHARSET_ALL}.
     * 
     * @return string|array The old charset. If $charsetType is
     * {@link CHARSET_ALL}, the old values will be returned as an array with the
     * types as keys, and charsets as values.
     * @see setCharset()
     */
    public static function setDefaultCharset(
        $charset, $charsetType = self::CHARSET_ALL
    ) {
        if (array_key_exists($charsetType, self::$defaultCharsets)) {
             $oldCharset = self::$defaultCharsets[$charsetType];
             self::$defaultCharsets[$charsetType] = $charset;
             return $oldCharset;
        } else {
            $oldCharsets = self::$defaultCharsets;
            self::$defaultCharsets = is_array($charset) ? $charset : array_fill(
                0, count(self::$defaultCharsets), $charset
            );
            return $oldCharsets;
        }
    }
    
    /**
     * Gets the default charset(s).
     * 
     * @param int $charsetType Which charset to get. Valid values are the
     * CHARSET_* constants. Any other value is treated as {@link CHARSET_ALL}.
     * 
     * @return string|array The current charset. If $charsetType is
     * {@link CHARSET_ALL}, the current values will be returned as an array with
     * the types as keys, and charsets as values.
     * @see setDefaultCharset()
     */
    public static function getDefaultCharset($charsetType)
    {
        return array_key_exists($charsetType, self::$defaultCharsets)
            ? self::$defaultCharsets[$charsetType] : self::$defaultCharsets;
    }
    
    /**
     * Sets the charset(s) for this connection.
     * 
     * Sets the charset(s) for this connection. The specified charset(s) will be
     * used for all future words. When sending, {@link CHARSET_LOCAL} is
     * converted to {@link CHARSET_REMOTE}, and when receiving,
     * {@link CHARSET_REMOTE} is converted to {@link CHARSET_LOCAL}. Setting
     * NULL to either charset will disable charset convertion, and data will be
     * both sent and received "as is".
     * 
     * @param mixed $charset     The charset to set. If $charsetType is
     * {@link CHARSET_ALL}, you can supply either a string to use for all
     * charsets, or an array with the charset types as keys, and the charsets as
     * values.
     * @param int   $charsetType Which charset to set. Valid values are the
     * Communicator::CHARSET_* constants. Any other value is treated as
     * {@link CHARSET_ALL}.
     * 
     * @return string|array The old charset. If $charsetType is
     * {@link CHARSET_ALL}, the old values will be returned as an array with the
     * types as keys, and charsets as values.
     * @see setDefaultCharset()
     */
    public function setCharset($charset, $charsetType = self::CHARSET_ALL)
    {
        if (array_key_exists($charsetType, $this->charsets)) {
             $oldCharset = $this->charsets[$charsetType];
             $this->charsets[$charsetType] = $charset;
             return $oldCharset;
        } else {
            $oldCharsets = $this->charsets;
            $this->charsets = is_array($charset) ? $charset : array_fill(
                0, count($this->charsets), $charset
            );
            return $oldCharsets;
        }
    }
    
    /**
     * Gets the charset(s) for this connection.
     * 
     * @param int $charsetType Which charset to get. Valid values are the
     * CHARSET_* constants. Any other value is treated as {@link CHARSET_ALL}.
     * 
     * @return string|array The current charset. If $charsetType is
     * {@link CHARSET_ALL}, the current values will be returned as an array with
     * the types as keys, and charsets as values.
     * @see getDefaultCharset()
     * @see setCharset()
     */
    public function getCharset($charsetType)
    {
        return array_key_exists($charsetType, $this->charsets)
            ? $this->charsets[$charsetType] : $this->charsets;
    }

    /**
     * Gets the transmitter for this connection.
     * 
     * @return PEAR2\Net\Transmitter\SocketClientTransmitter The transmitter for
     * this connection.
     */
    public function getTransmitter()
    {
        return $this->trans;
    }

    /**
     * Sends a word.
     * 
     * Sends a word and automatically encodes its length when doing so.
     * 
     * @param string $word The word to send.
     * 
     * @return int The number of bytes sent.
     * @see sendWordFromStream()
     * @see getNextWord()
     */
    public function sendWord($word)
    {
        if (null !== ($remoteCharset = $this->getCharset(self::CHARSET_REMOTE))
            && null !== ($localCharset = $this->getCharset(self::CHARSET_LOCAL))
        ) {
            $word = iconv(
                $localCharset,
                $remoteCharset . '//IGNORE//TRANSLIT',
                $word
            );
        }
        $length = strlen($word);
        static::verifyLengthSupport($length);
        return $this->trans->send(self::encodeLength($length) . $word);
    }

    /**
     * Sends a word based on a stream.
     * 
     * Sends a word based on a stream and automatically encodes its length when
     * doing so. The stream is read from its current position to its end, and
     * then returned to its current position. Because of those operations, the
     * supplied stream must be seekable.
     * 
     * @param string   $prefix A string to prepend before the stream contents.
     * @param resource $stream The stream to send.
     * 
     * @return int The number of bytes sent.
     * @see sendWord()
     */
    public function sendWordFromStream($prefix, $stream)
    {
        if (null !== ($remoteCharset = $this->getCharset(self::CHARSET_REMOTE))
            && null !== ($localCharset = $this->getCharset(self::CHARSET_LOCAL))
        ) {
            $prefix = iconv(
                $localCharset,
                $remoteCharset . '//IGNORE//TRANSLIT',
                $prefix
            );
            $stream = self::iconvStream(
                $localCharset,
                $remoteCharset . '//IGNORE//TRANSLIT',
                $stream
            );
        }
        
        flock($stream, LOCK_SH);
        
        $streamPosition = (double) sprintf('%u', ftell($stream));
        fseek($stream, 0, SEEK_END);
        $streamLength = ((double) sprintf('%u', ftell($stream)))
            - $streamPosition;
        fseek($stream, $streamPosition, SEEK_SET);
        $totalLength = strlen($prefix) + $streamLength;
        static::verifyLengthSupport($totalLength);

        $bytes = $this->trans->send(self::encodeLength($totalLength) . $prefix);
        $bytes += $this->trans->sendStream($stream);
        
        flock($stream, LOCK_UN);
        return $bytes;
    }

    /**
     * Verifies that the length is supported.
     * 
     * Verifies if the specified length is supported by the API. Throws a
     * {@link LengthException} if that's not the case. Currently, RouterOS
     * supports words up to 0xFFFFFFF in length, so that's the only check
     * performed.
     * 
     * @param int $length The length to verify.
     * 
     * @return void
     */
    protected static function verifyLengthSupport($length)
    {
        if ($length > 0xFFFFFFF) {
            throw new LengthException(
                'Words with length above 0xFFFFFFF are not supported.', 10,
                null, $length
            );
        }
    }

    /**
     * Encodes the length as requred by the RouterOS API.
     * 
     * @param int $length The length to encode
     * 
     * @return string The encoded length
     */
    public static function encodeLength($length)
    {
        if ($length < 0) {
            throw new LengthException(
                'Length must not be negative.', 11, null, $length
            );
        } elseif ($length < 0x80) {
            return chr($length);
        } elseif ($length < 0x4000) {
            return pack('n', $length |= 0x8000);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            return pack('n', $length >> 8) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            return pack('N', $length |= 0xE0000000);
        } elseif ($length <= 0xFFFFFFFF) {
            return chr(0xF0) . pack('N', $length);
        } elseif ($length <= 0x7FFFFFFFF) {
            $length = 'f' . base_convert($length, 10, 16);
            return chr(hexdec(substr($length, 0, 2))) .
                pack('N', hexdec(substr($length, 2)));
        }
        throw new LengthException(
            'Length must not be above 0x7FFFFFFFF.', 12, null, $length
        );
    }

    /**
     * Get the next word in queue as a string.
     * 
     * Get the next word in queue as a string, after automatically decoding its
     * length.
     * 
     * @return string The word.
     * @see close()
     */
    public function getNextWord()
    {
        $word = $this->trans->receive(self::decodeLength($this->trans), 'word');
        if (null !== ($remoteCharset = $this->getCharset(self::CHARSET_REMOTE))
            && null !== ($localCharset = $this->getCharset(self::CHARSET_LOCAL))
        ) {
            $word = iconv(
                $remoteCharset,
                $localCharset . '//IGNORE//TRANSLIT',
                $word
            );
        }
        return $word;
    }

    /**
     * Get the next word in queue as a stream.
     * 
     * Get the next word in queue as a stream, after automatically decoding its
     * length.
     * 
     * @return resource The word, as a stream.
     * @see close()
     */
    public function getNextWordAsStream()
    {
        $filters = array();
        if (null !== ($remoteCharset = $this->getCharset(self::CHARSET_REMOTE))
            && null !== ($localCharset = $this->getCharset(self::CHARSET_LOCAL))
        ) {
            $filters[
                'convert.iconv.' .
                $remoteCharset . '.' . $localCharset . '//IGNORE//TRANSLIT'
            ] = array();
        }
        $stream = $this->trans->receiveStream(
            self::decodeLength($this->trans), $filters, 'stream word'
        );
        return $stream;
    }

    /**
     * Decodes the lenght of the incoming message.
     * 
     * Decodes the lenght of the incoming message, as specified by the RouterOS
     * API.
     * 
     * @param PEAR2\Net\Transmitter\StreamTransmitter $trans The transmitter
     * from which to decode the length of the incoming message.
     * 
     * @return int The decoded length
     */
    public static function decodeLength(T\StreamTransmitter $trans)
    {
        $byte = ord($trans->receive(1, 'initial length byte'));
        if ($byte & 0x80) {
            if (($byte & 0xC0) === 0x80) {
                return (($byte & 077) << 8 ) + ord($trans->receive(1));
            } elseif (($byte & 0xE0) === 0xC0) {
                $u = unpack('n~', $trans->receive(2));
                return (($byte & 037) << 16 ) + $u['~'];
            } elseif (($byte & 0xF0) === 0xE0) {
                $u = unpack('n~/C~~', $trans->receive(3));
                return (($byte & 017) << 24 ) + ($u['~'] << 8) + $u['~~'];
            } elseif (($byte & 0xF8) === 0xF0) {
                $u = unpack('N~', $trans->receive(4));
                return (($byte & 007) * 0x100000000/* '<< 32' or '2^32' */)
                    + (double) sprintf('%u', $u['~']);
            }
            throw new NotSupportedException(
                'Unknown control byte encountered.', 13, null, $byte
            );
        } else {
            return $byte;
        }
    }

    /**
     * Closes the opened connection, even if it is a persistent one.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public function close()
    {
        return $this->trans->close();
    }

}