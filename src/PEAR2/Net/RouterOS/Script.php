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
 * Values at {@link Script::escapeValue()} can be casted from this type.
 */
use DateTime;

/**
 * Values at {@link Script::escapeValue()} can be casted from this type.
 */
use DateInterval;

/**
 * Used at {@link Script::escapeValue()} to get the proper time.
 */
use DateTimeZone;

/**
 * Used to reliably write to streams at {@link Script::prepare()}.
 */
use PEAR2\Net\Transmitter\Stream;

/**
 * Used to catch DateTime and DateInterval exceptions at
 * {@link Script::parseValue()}.
 */
use Exception as E;

/**
 * Scripting class.
 *
 * Provides functionality related to parsing and composing RouterOS scripts and
 * values.
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class Script
{
    /**
     * Parses a value from a RouterOS scripting context.
     *
     * Turns a value from RouterOS into an equivalent PHP value, based on
     * determining the type in the same way RouterOS would determine it for a
     * literal.
     *
     * This method is intended to be the very opposite of
     * {@link static::escapeValue()}. That is, results from that method, if
     * given to this method, should produce equivalent results.
     *
     * @param string $value The value to be parsed. Must be a literal of a
     *     value, e.g. what {@link static::escapeValue()} will give you.
     *
     * @return mixed Depending on RouterOS type detected:
     *     - "nil" (the string "[]") or "nothing" (empty string) - NULL.
     *     - "number" - int or double for large values.
     *     - "bool" - a boolean.
     *     - "array" - an array, with the keys and values processed recursively.
     *     - "str" - a string.
     *     - "time" - a {@link DateInterval} object.
     *     - "date" (pseudo type; string in the form "M/j/Y") - a DateTime
     *         object with the specified date, at midnight UTC time.
     *     - "datetime" (pseudo type; string in the form "M/j/Y H:i:s") - a
     *         DateTime object with the specified date and UTC time.
     *     - Unrecognized type - treated as an unquoted string.
     */
    public static function parseValue($value)
    {
        $value = static::parseValueToSimple($value);
        if (!is_string($value)) {
            return $value;
        } elseif ('{' === $value[0] && '}' === $value[strlen($value) - 1]) {
            $value = static::parseValueToArray($value);
            if (!is_string($value)) {
                return $value;
            }
        } elseif ('"' === $value[0] && '"' === $value[strlen($value) - 1]) {
            return str_replace(
                array('\"', '\\\\', "\\\n", "\\\r\n", "\\\r"),
                array('"', '\\'),
                substr($value, 1, -1)
            );
        }

        $value = static::parseValueToObject($value);
        if (!is_string($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * Parses a RouterOS value into a PHP simple type.
     * 
     * Parses a RouterOS value into a PHP simple type. "Simple" types being
     * scalar types, plus NULL.
     * 
     * @param string $value The value to be parsed. Must be a literal of a
     *     value, e.g. what {@link static::escapeValue()} will give you.
     * 
     * @return string|bool|int|double|null Depending on RouterOS type detected:
     *     - "nil" (the string "[]") or "nothing" (empty string) - NULL.
     *     - "number" - int or double for large values.
     *     - "bool" - a boolean.
     *     - Unrecognized type - treated as an unquoted string.
     */
    public static function parseValueToSimple($value)
    {
        $value = (string)$value;

        if (in_array($value, array('', '[]'), true)) {
            return null;
        } elseif (in_array($value, array('true', 'false', 'yes', 'no'), true)) {
            return $value === 'true' || $value === 'yes';
        } elseif ($value === (string)($num = (int)$value)
            || $value === (string)($num = (double)$value)
        ) {
            return $num;
        }
        return $value;
    }

    /**
     * Parses a RouterOS value into a PHP object.
     * 
     * Parses a RouterOS value into a PHP object.
     * 
     * @param string $value The value to be parsed. Must be a literal of a
     *     value, e.g. what {@link static::escapeValue()} will give you.
     * 
     * @return string|DateInterval|DateTime Depending on RouterOS type detected:
     *     - "time" - a {@link DateInterval} object.
     *     - "date" (pseudo type; string in the form "M/j/Y") - a DateTime
     *         object with the specified date, at midnight UTC time.
     *     - "datetime" (pseudo type; string in the form "M/j/Y H:i:s") - a
     *         DateTime object with the specified date and UTC time.
     *     - Unrecognized type - treated as an unquoted string.
     */
    public static function parseValueToObject($value)
    {
        $value = (string)$value;
        if ('' === $value) {
            return $value;
        }

        if (preg_match(
            '/^
                (?:(\d+)w)?
                (?:(\d+)d)?
                (?:(\d+)(?:\:|h))?
                (?|
                    (\d+)\:
                    (\d*(?:\.\d{1,9})?)
                |
                    (?:(\d+)m)?
                    (?:(\d+|\d*\.\d{1,9})s)?
                    (?:((?5))ms)?
                    (?:((?5))us)?
                    (?:((?5))ns)?
                )
            $/x',
            $value,
            $time
        )) {
            $days = isset($time[2]) ? (int)$time[2] : 0;
            if (isset($time[1])) {
                $days += 7 * (int)$time[1];
            }
            if (empty($time[3])) {
                $time[3] = 0;
            }
            if (empty($time[4])) {
                $time[4] = 0;
            }
            if (empty($time[5])) {
                $time[5] = 0;
            }
            
            $subsecondTime = 0.0;
            //@codeCoverageIgnoreStart
            // No PHP version currently supports sub-second DateIntervals,
            // meaning this section is untestable, since no version constraints
            // can be specified for test inputs.
            // All inputs currently use integer seconds only, making this
            // section unreachable during tests.
            // Nevertheless, this section exists right now, in order to provide
            // such support as soon as PHP has it.
            if (!empty($time[6])) {
                $subsecondTime += ((double)$time[6]) / 1000;
            }
            if (!empty($time[7])) {
                $subsecondTime += ((double)$time[7]) / 1000000;
            }
            if (!empty($time[8])) {
                $subsecondTime += ((double)$time[8]) / 1000000000;
            }
            //@codeCoverageIgnoreEnd

            $secondsSpec = $time[5] + $subsecondTime;
            try {
                return new DateInterval(
                    "P{$days}DT{$time[3]}H{$time[4]}M{$secondsSpec}S"
                );
                //@codeCoverageIgnoreStart
                // See previous ignored section's note.
                //
                // This section is added for backwards compatibility with current
                // PHP versions, when in the future sub-second support is added.
                // In that event, the test inputs for older versions will be
                // expected to get a rounded up result of the sub-second data.
            } catch (E $e) {
                $secondsSpec = (int)round($secondsSpec);
                return new DateInterval(
                    "P{$days}DT{$time[3]}H{$time[4]}M{$secondsSpec}S"
                );
            }
            //@codeCoverageIgnoreEnd
        } elseif (preg_match(
            '#^
                (?<mon>jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)
                /
                (?<day>\d\d?)
                /
                (?<year>\d{4})
                (?:
                    \s+(?<time>\d{2}\:\d{2}:\d{2})
                )?
            $#uix',
            $value,
            $date
        )) {
            if (!isset($date['time'])) {
                $date['time'] = '00:00:00';
            }
            try {
                return new DateTime(
                    $date['year'] .
                    '-' . ucfirst($date['mon']) .
                    "-{$date['day']} {$date['time']}",
                    new DateTimeZone('UTC')
                );
            } catch (E $e) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Parses a RouterOS value into a PHP array.
     * 
     * Parses a RouterOS value into a PHP array.
     * 
     * @param string $value The value to be parsed. Must be a literal of a
     *     value, e.g. what {@link static::escapeValue()} will give you.
     * 
     * @return string|array Depending on RouterOS type detected:
     *     - "array" - an array, with the keys and values processed recursively.
     *     - Unrecognized type - treated as an unquoted string.
     */
    public static function parseValueToArray($value)
    {
        $value = (string)$value;
        if ('{' === $value[0] && '}' === $value[strlen($value) - 1]) {
            $value = substr($value, 1, -1);
            if ('' === $value) {
                return array();
            }
            $parsedValue = preg_split(
                '/
                    (\"(?:\\\\\\\\|\\\\"|[^"])*\")
                    |
                    (\{[^{}]*(?2)?\})
                    |
                    ([^;=]+)
                /sx',
                $value,
                null,
                PREG_SPLIT_DELIM_CAPTURE
            );
            $result = array();
            $newVal = null;
            $newKey = null;
            for ($i = 0, $l = count($parsedValue); $i < $l; ++$i) {
                switch ($parsedValue[$i]) {
                case '':
                    break;
                case ';':
                    if (null === $newKey) {
                        $result[] = $newVal;
                    } else {
                        $result[$newKey] = $newVal;
                    }
                    $newKey = $newVal = null;
                    break;
                case '=':
                    $newKey = static::parseValueToSimple($parsedValue[$i - 1]);
                    $newVal = static::parseValue($parsedValue[++$i]);
                    break;
                default:
                    $newVal = static::parseValue($parsedValue[$i]);
                }
            }
            if (null === $newKey) {
                $result[] = $newVal;
            } else {
                $result[$newKey] = $newVal;
            }
            return $result;
        }
        return $value;
    }

    /**
     * Prepares a script.
     *
     * Prepares a script for eventual execution by prepending parameters as
     * variables to it.
     *
     * This is particularly useful when you're creating scripts that you don't
     * want to execute right now (as with {@link static::exec()}, but instead
     * you want to store it for later execution, perhaps by supplying it to
     * "/system scheduler".
     *
     * @param string|resource     $source The source of the script, as a string
     *     or stream. If a stream is provided, reading starts from the current
     *     position to the end of the stream, and the pointer stays at the end
     *     after reading is done.
     * @param array<string,mixed> $params An array of parameters to make
     *     available in the script as local variables.
     *     Variable names are array keys, and variable values are array values.
     *     Array values are automatically processed with
     *     {@link static::escapeValue()}. Streams are also supported, and are
     *     processed in chunks, each with
     *     {@link static::escapeString()}. Processing starts from the current
     *     position to the end of the stream, and the stream's pointer stays at
     *     the end after reading is done.
     *
     * @return resource A new PHP temporary stream with the script as contents,
     *     with the pointer back at the start.
     *
     * @see static::append()
     */
    public static function prepare(
        $source,
        array $params = array()
    ) {
        $resultStream = fopen('php://temp', 'r+b');
        static::append($resultStream, $source, $params);
        rewind($resultStream);
        return $resultStream;
    }

    /**
     * Appends a script.
     *
     * Appends a script to an existing stream.
     *
     * @param resource            $stream An existing stream to write the
     *     resulting script to.
     * @param string|resource     $source The source of the script, as a string
     *     or stream. If a stream is provided, reading starts from the current
     *     position to the end of the stream, and the pointer stays at the end
     *     after reading is done.
     * @param array<string,mixed> $params An array of parameters to make
     *     available in the script as local variables.
     *     Variable names are array keys, and variable values are array values.
     *     Array values are automatically processed with
     *     {@link static::escapeValue()}. Streams are also supported, and are
     *     processed in chunks, each with
     *     {@link static::escapeString()}. Processing starts from the current
     *     position to the end of the stream, and the stream's pointer stays at
     *     the end after reading is done.
     *
     * @return int The number of bytes written to $stream is returned,
     *     and the pointer remains where it was after the write
     *     (i.e. it is not seeked back, even if seeking is supported).
     */
    public static function append(
        $stream,
        $source,
        array $params = array()
    ) {
        $writer = new Stream($stream, false);
        $bytes = 0;

        foreach ($params as $pname => $pvalue) {
            $pname = static::escapeString($pname);
            $bytes += $writer->send(":local \"{$pname}\" ");
            if (Stream::isStream($pvalue)) {
                $reader = new Stream($pvalue, false);
                $chunkSize = $reader->getChunk(Stream::DIRECTION_RECEIVE);
                $bytes += $writer->send('"');
                while ($reader->isAvailable() && $reader->isDataAwaiting()) {
                    $bytes += $writer->send(
                        static::escapeString(fread($pvalue, $chunkSize))
                    );
                }
                $bytes += $writer->send("\";\n");
            } else {
                $bytes += $writer->send(static::escapeValue($pvalue) . ";\n");
            }
        }

        $bytes += $writer->send($source);
        return $bytes;
    }

    /**
     * Escapes a value for a RouterOS scripting context.
     *
     * Turns any native PHP value into an equivalent whole value that can be
     * inserted as part of a RouterOS script.
     *
     * DateInterval objects will be casted to RouterOS' "time" type.
     * 
     * DateTime objects will be casted to a string following the "M/d/Y H:i:s"
     * format. If the time is exactly midnight (including microseconds), and
     * the timezone is UTC, the string will include only the "M/d/Y" date.
     *
     * Unrecognized types (i.e. resources and other objects) are casted to
     * strings.
     *
     * @param mixed $value The value to be escaped.
     *
     * @return string A string representation that can be directly inserted in a
     *     script as a whole value.
     */
    public static function escapeValue($value)
    {
        switch(gettype($value)) {
        case 'NULL':
            $value = '';
            break;
        case 'integer':
            $value = (string)$value;
            break;
        case 'boolean':
            $value = $value ? 'true' : 'false';
            break;
        case 'array':
            if (0 === count($value)) {
                $value = '({})';
                break;
            }
            $result = '';
            foreach ($value as $key => $val) {
                $result .= ';';
                if (!is_int($key)) {
                    $result .= static::escapeValue($key) . '=';
                }
                $result .= static::escapeValue($val);
            }
            $value = '{' . substr($result, 1) . '}';
            break;
        case 'object':
            if ($value instanceof DateTime) {
                $usec = $value->format('u');
                $usec = '000000' === $usec ? '' : '.' . $usec;
                $value = '00:00:00.000000 UTC' === $value->format('H:i:s.u e')
                    ? $value->format('M/d/Y')
                    : $value->format('M/d/Y H:i:s') . $usec;
            }
            if ($value instanceof DateInterval) {
                if (false === $value->days || $value->days < 0) {
                    $value = $value->format('%r%dd%H:%I:%S');
                } else {
                    $value = $value->format('%r%ad%H:%I:%S');
                }
                break;
            }
            //break; intentionally omitted
        default:
            $value = '"' . static::escapeString((string)$value) . '"';
            break;
        }
        return $value;
    }

    /**
     * Escapes a string for a RouterOS scripting context.
     *
     * Escapes a string for a RouterOS scripting context. The value can then be
     * surrounded with quotes at a RouterOS script (or concatenated onto a
     * larger string first), and you can be sure there won't be any code
     * injections coming from it.
     *
     * @param string $value Value to be escaped.
     *
     * @return string The escaped value.
     */
    public static function escapeString($value)
    {
        return preg_replace_callback(
            '/[^\\_A-Za-z0-9]+/S',
            array(__CLASS__, '_escapeCharacters'),
            $value
        );
    }

    /**
     * Escapes a character for a RouterOS scripting context.
     *
     * Escapes a character for a RouterOS scripting context. Intended to only be
     * called for non-alphanumeric characters.
     *
     * @param string $chars The matches array, expected to contain exactly one
     *     member, in which is the whole string to be escaped.
     *
     * @return string The escaped characters.
     */
    private static function _escapeCharacters($chars)
    {
        $result = '';
        for ($i = 0, $l = strlen($chars[0]); $i < $l; ++$i) {
            $result .= '\\' . str_pad(
                strtoupper(dechex(ord($chars[0][$i]))),
                2,
                '0',
                STR_PAD_LEFT
            );
        }
        return $result;
    }
}
