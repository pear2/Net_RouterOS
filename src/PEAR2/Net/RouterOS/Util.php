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
 * Values at {@link Util::exec()} can be casted from this type.
 */
use DateTime;

/**
 * Values at {@link Util::exec()} can be casted from this type.
 */
use DateInterval;

/**
 * Utility class.
 * 
 * Abstracts away frequently used functionality (particularly CRUD operations)
 * in convinient to use methods by wrapping around a connection.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class Util
{
    /**
     * @var Client The connection to wrap around.
     */
    protected $client;

    /**
     * @var string The current menu.
     */
    protected $menu = '/';

    /**
     * @var array An array with the numbers of entries in the current menu as
     *     keys, and the corresponding IDs as values.
     */
    protected $idCache = null;
    
    /**
     * Escapes a value for a RouterOS scripting context.
     * 
     * Turns any PHP value into an equivalent whole value that can be inserted
     * as part of a RouterOS script.
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
                $value = '{}';
                break;
            }
            $result = '';
            foreach ($value as $val) {
                $result .= ';' . static::escapeValue($val);
            }
            $value = '{' . substr($result, 1) . '}';
            break;
        case 'object':
            if ($value instanceof DateTime) {
                $usec = $value->format('u');
                if ('000000' === $usec) {
                    unset($usec);
                }
                $unixEpoch = new DateTime('1970-01-01 00:00:00.000000');
                $value = $unixEpoch->diff($value);
            }
            if ($value instanceof DateInterval) {
                if (false === $value->days || $value->days < 0) {
                    $value = $value->format('%r')
                        . ($value->y * 365 + $value->m * 12 + $value->d)
                        . $value->format('d%H:%I:%S');
                } else {
                    $value = $value->format('%r%ad%H:%I:%S');
                }
                if (isset($usec)) {
                    $value .= '.' . $usec;
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
     * Escapes a string for a RouterOS scripting context. The value can be
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
            '/[^\\_A-Za-z0-9]/S',
            array(__CLASS__, '_escapeCharacter'),
            $value
        );
    }
    
    /**
     * Escapes a character for a RouterOS scripting context.
     * 
     * Escapes a character for a RouterOS scripting context. Intended to only be
     * called for non-alphanumeric characters.
     * 
     * @param string $char The character to be escaped.
     * 
     * @return string The escaped character.
     */
    private static function _escapeCharacter($char)
    {
        return '\\' . str_pad(
            strtoupper(dechex(ord($char[0]))),
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Creates a new Util instance.
     * 
     * Wraps around a connection to provide convinience methods.
     * 
     * @param Client $client The connection to wrap around.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    
    /**
     * Changes the current menu.
     * 
     * Changes the current menu.
     * 
     * @param string $newMenu The menu to change to. Can be specified with API
     *     or CLI syntax and can be either absolute or relative. If relative,
     *     it's relative to the current menu, which by default is the root.
     * 
     * @return string The old menu. If an empty string is given for a new menu,
     *     no change is performed, and this function returns the current menu.
     */
    public function changeMenu($newMenu = '')
    {
        $newMenu = (string)$newMenu;
        if ('' === $newMenu) {
            return $this->menu;
        }
        $oldMenu = $this->menu;
        $menuRequest = new Request('/menu');
        if ('/' === $newMenu[0]) {
            $this->menu = $menuRequest->setCommand($newMenu)->getCommand();
        } else {
            $this->menu = $menuRequest->setCommand(
                '/' . str_replace('/', ' ', substr($this->menu, 1)) . ' ' .
                str_replace('/', ' ', $newMenu)
            )->getCommand();
        }
        $this->clearIdCache();
        return $oldMenu;
    }

    /**
     * Executes a RouterOS script.
     * 
     * Executes a RouterOS script, written as a string.
     * Note that in cases of errors, the line numbers will be off, because the
     * script is executed at the current menu as context, with the specified
     * variables pre declared. This is achieved by prepending 1+count($params)
     * lines before your actual script.
     * 
     * @param string $source A script to execute.
     * @param array  $params An array of local variables to make available in
     *     the script. Variable names are array keys, and variable values are
     *     array values. Note that the script's (generated) name is always added
     *     as the variable "_", which you can overwrite from here.
     *     Native PHP types will be converted to their RouterOS equivalents.
     *     DateTime and DateInterval objects will be casted to RouterOS' "time"
     *     type. Other types are casted to strings.
     * @param string $policy Allows you to specify a policy the script must
     *     follow. Has the same format as in terminal. If left NULL, the script
     *     has no restrictions.
     * @param string $name   The script is executed after being saved in
     *     "/system script" under a random name (prefixed with the computer's
     *     name), and is removed after execution. To eliminate any possibility
     *     of name clashes, you can specify your own name.
     * 
     * @return ResponseCollection returns the response collection of the run,
     *     allowing you to inspect errors, if any. If the script was not added
     *     successfully before execution, the ResponseCollection from the add
     *     attempt is going to be returned.
     */
    public function exec(
        $source,
        array $params = array(),
        $policy = null,
        $name = null
    ) {
        return $this->_exec($source, $params, $policy, $name);
    }
    
    /**
     * Clears the ID cache.
     * 
     * Normally, the ID cache improves performance when targeting entries by a
     * number. If you're using both Util's methods and other means (e.g.
     * {@link Client} or {@link Util::exec()}) to add/move/remove entries, the
     * cache may end up being out of date. By calling this method right before
     * targeting an entry with a number, you can ensure number accuracy.
     * 
     * Note that Util's {@link move()} and {@link remove()} methods
     * automatically clear the cache before returning, while {@link add()} adds
     * the new entry's ID to the cache as the next number. A change in the menu
     * also clears the cache.
     * 
     * Note also that the cache is being rebuilt unconditionally every time you
     * use {@link find()} with a callback.
     * 
     * @return $this The Util object itself.
     */
    public function clearIdCache()
    {
        $this->idCache = null;
        return $this;
    }

    /**
     * Finds the IDs of entries at the current menu.
     * 
     * Finds the IDs of entries based on specified criteria, and returns them as
     * a comma separated string, ready for insertion at a "numbers" argument.
     * 
     * Accepts zero or more criteria as arguments. If zero arguments are
     * specified, returns all entries' IDs. The value of each criteria can be a
     * number (just as in Winbox), a literal ID to be included, a {@link Query}
     * object, or a callback. If a callback is specified, it is called for each
     * entry, with the entry as an argument. If it returns a true value, the
     * item's ID is included in the result. Every other value is casted to a
     * string. A string is treated as a comma separated values of IDs, numbers
     * or callback names. Non-existent callback names are instead placed in the
     * result, which may be useful in menus that accept identifiers other than
     * IDs, but note that it can cause errors on other menus.
     * 
     * @return string A comma separated list of all entries matching the
     *     specified criteria.
     */
    public function find()
    {
        if (func_num_args() === 0) {
            if (null === $this->idCache) {
               $idCache = $this->client->sendSync(
                   new Request($this->menu . '/find')
               )->getArgument('ret');
               $this->idCache = explode(',', $idCache);
               return $idCache;
            }
            return implode(',', $this->idCache);
        }
        $idList = '';
        foreach (func_get_args() as $criteria) {
            if ($criteria instanceof Query) {
                foreach ($this->client->sendSync(
                    new Request($this->menu . '/print .proplist=.id', $criteria)
                ) as $response) {
                    $idList .= $response->getArgument('.id') . ',';
                }
            } elseif (is_callable($criteria)) {
                $idCache = array();
                foreach ($this->client->sendSync(
                    new Request($this->menu . '/print')
                ) as $response) {
                    if ($criteria($response)) {
                        $idList .= $response->getArgument('.id') . ',';
                    }
                    $idCache[] = $response->getArgument('.id');
                }
                $this->idCache = $idCache;
            } else {
                $this->find();
                if (is_int($criteria)) {
                    if (isset($this->idCache[$criteria])) {
                        $idList = $this->idCache[$criteria] . ',';
                    }
                } else {
                    $criteria = (string)$criteria;
                    if ($criteria === (string)(int)$criteria) {
                        if (isset($this->idCache[(int)$criteria])) {
                            $idList .= $this->idCache[(int)$criteria] . ',';
                        }
                    } elseif (false === strpos($criteria, ',')) {
                        $idList .= $criteria . ',';
                    } else {
                        $criteriaArr = explode(',', $criteria);
                        for ($i = count($criteriaArr) - 1; $i >= 0; --$i) {
                            if ('' === $criteriaArr[$i]) {
                                unset($criteriaArr[$i]);
                            } elseif ('*' === $criteriaArr[$i][0]) {
                                $idList .= $criteriaArr[$i] . ',';
                                unset($criteriaArr[$i]);
                            }
                        }
                        if (!empty($criteriaArr)) {
                            $idList .= call_user_func_array(
                                array($this, 'find'),
                                $criteriaArr
                            ) . ',';
                        }
                    }
                }
            }
        }
        return rtrim($idList, ',');
    }

    /**
     * Gets a value of a specified entry at the current menu.
     * 
     * @param int    $number     A number identifying the entry you're
     *     targeting. Can also be an ID or (in some menus) name.
     * @param string $value_name The name of the value you want to get.
     * 
     * @return string|null|bool The value of the specified property. If the
     *     property is not set, NULL will be returned. If no such entry exists,
     *     FALSE will be returned.
     */
    public function get($number, $value_name)
    {
        if (is_int($number) || ((string)$number === (string)(int)$number)) {
            $this->find();
            if (isset($this->idCache[(int)$number])) {
                $number = $this->idCache[(int)$number];
            } else {
                return false;
            }
        }

        $number = (string)$number;
        $request = new Request(
            $this->menu . '/print',
            Query::where('.id', $number)->orWhere('name', $number)
        );
        $request->setArgument('.proplist', $value_name);
        $responses = $this->client->sendSync($request)
            ->getAllOfType(Response::TYPE_DATA);

        if (0 === count($responses)) {
            return false;
        }
        return $responses->getArgument($value_name);
    }

    /**
     * Enables all entries at the current menu matching certain criteria.
     * 
     * Zero or more arguments can be specified, each being a criteria.
     * If zero arguments are specified, enables all entries.
     * See {@link find()} for a description of what criteria are accepted.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function enable()
    {
        return $this->doBulk('enable', func_get_args());
    }

    /**
     * Disables all entries at the current menu matching certain criteria.
     * 
     * Zero or more arguments can be specified, each being a criteria.
     * If zero arguments are specified, disables all entries.
     * See {@link find()} for a description of what criteria are accepted.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function disable()
    {
        return $this->doBulk('disable', func_get_args());
    }

    /**
     * Removes all entries at the current menu matching certain criteria.
     * 
     * Zero or more arguments can be specified, each being a criteria.
     * If zero arguments are specified, removes all entries.
     * See {@link find()} for a description of what criteria are accepted.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function remove()
    {
        $result = $this->doBulk('remove', func_get_args());
        $this->clearIdCache();
        return $result;
    }

    /**
     * Sets new values.
     * 
     * Sets new values on certain properties on all entries at the current menu
     * which match certain criteria.
     * 
     * @param mixed $numbers   Targeted entries. Can be any criteria accepted by
     *     {@link find()}.
     * @param array $newValues An array with the names of each property to set
     *     as an array key, and the new value as an array value.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function set($numbers, array $newValues)
    {
        $setRequest = new Request($this->menu . '/set');
        foreach ($newValues as $name => $value) {
            $setRequest->setArgument($name, $value);
        }
        return $this->client->sendSync(
            $setRequest->setArgument('numbers', $this->find($numbers))
        );
    }

    /**
     * Alias of {@link set()}
     * 
     * @param mixed $numbers   Targeted entries. Can be any criteria accepted by
     *     {@link find()}.
     * @param array $newValues An array with the names of each changed property
     *     as an array key, and the new value as an array value.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function edit($numbers, array $newValues)
    {
        return $this->set($numbers, $newValues);
    }

    /**
     * Unsets a value of a specified entry at the current menu.
     * 
     * Equivalent of scripting's "unset" command. The "Value" part in the method
     * name is added because "unset" is a language construct, and thus a
     * reserved word.
     * 
     * @param mixed  $numbers    Targeted entries. Can be any criteria accepted
     *     by {@link find()}.
     * @param string $value_name The name of the value you want to unset.
     * 
     * @return ResponseCollection
     */
    public function unsetValue($numbers, $value_name)
    {
        $unsetRequest = new Request($this->menu . '/unset');
        return $this->client->sendSync(
            $unsetRequest->setArgument('numbers', $this->find($numbers))
                ->setArgument('value-name', $value_name)
        );
    }

    /**
     * Adds a new entry at the current menu.
     * 
     * @param array $values     Accepts one or more entries to add to the
     *     current menu. The data about each entry is specified as an array with
     *     the names of each property as an array key, and the value as an array
     *     value.
     * @param array $values,... Additional entries.
     * 
     * @return string A comma separated list of the new entries' IDs.
     */
    public function add(array $values)
    {
        $addRequest = new Request($this->menu . '/add');
        $idList = '';
        foreach (func_get_args() as $values) {
            foreach ($values as $name => $value) {
                $addRequest->setArgument($name, $value);
            }
            $id = $this->client->sendSync($addRequest)->getArgument('ret');
            if (null !== $this->idCache) {
                $this->idCache[] = $id;
            }
            $idList .= $id . ',';
            $addRequest->removeAllArguments();
        }
        return rtrim($idList, ',');
    }

    /**
     * Moves entries at the current menu before a certain other entry.
     * 
     * Moves entries before a certain other entry. Note that the "move"
     * command is not available on all menus. As a rule of thumb, if the order
     * of entries in a menu is irrelevant to their interpretation, there won't
     * be a move command on that menu. If in doubt, check from a terminal.
     * 
     * @param mixed $numbers     Targeted entries. Can be any criteria accepted
     *     by {@link find()}.
     * @param mixed $destination Entry before which the targeted entries will be
     *     moved to. Can be any criteria accepted by {@link find()}. If multiple
     *     entries match the criteria, the targeted entries will move above the
     *     first match.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    public function move($numbers, $destination)
    {
        $moveRequest = new Request($this->menu . '/move');
        $moveRequest->setArgument('numbers', $this->find($numbers));
        $destination = $this->find($destination);
        if (false !== strpos($destination, ',')) {
            $destination = strstr($destination, ',', true);
        }
        $moveRequest->setArgument('destination', $destination);
        $this->clearIdCache();
        return $this->client->sendSync($moveRequest);
    }

    /**
     * Puts a file on RouterOS's file system.
     * 
     * Puts a file on RouterOS's file system, regardless of the current menu.
     * Note that this is a VERY VERY VERY time consuming method - it takes a
     * minimum of a little over 4 seconds, most of which are in sleep. It waits
     * 2 seconds after a file is first created (required to actually start
     * writing to the file), and another 2 seconds after its contents is written
     * (performed in order to verify success afterwards). If you want an
     * efficient way of transferring files, use (T)FTP.
     * 
     * @param string $filename  The filename to write data in.
     * @param string $data      The data the file is going to have.
     * @param bool   $overwrite Whether to overwrite the file if it exists.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public function filePutContents($filename, $data, $overwrite = false)
    {
        $printRequest = new Request(
            '/file/print .proplist=""',
            Query::where('name', $filename)
        );
        if (!$overwrite && count($this->client->sendSync($printRequest)) > 1) {
            return false;
        }
        $result = $this->client->sendSync(
            $printRequest->setArgument('file', $filename)
        );
        if (count($result->getAllOfType(Response::TYPE_ERROR)) > 0) {
            return false;
        }
        //Required for RouterOS to write the initial file.
        sleep(2);
        $setRequest = new Request('/file/set contents=""');
        $setRequest->setArgument('numbers', $filename);
        $this->client->sendSync($setRequest);
        $this->client->sendSync($setRequest->setArgument('contents', $data));
        //Required for RouterOS to write the file's new contents.
        sleep(2);
        return strlen($data) == $this->client->sendSync(
            $printRequest->setArgument('file', null)
                ->setArgument('.proplist', 'size')
        )->getArgument('size');
    }

    /**
     * Gets the contents of a specified file.
     * 
     * @param string $filename      The name of the file to get the contents of.
     * @param string $tmpScriptName In order to get the file's contents, a
     *     script is created at "/system script" with a random name, the
     *     source of which is then overwriten with the file's contents, and
     *     finally retrieved. To eliminate any possibility of name clashes, you
     *     can specify your own name for the script.
     * 
     * @return string|bool The contents of the file or FALSE if there is no such
     *     file.
     */
    public function fileGetContents($filename, $tmpScriptName = null)
    {
        $checkRequest = new Request(
            '/file/print',
            Query::where('name', $filename)
        );
        if (1 === count($this->client->sendSync($checkRequest))) {
            return false;
        }
        $contents = $this->_exec(
            '/system script set $"_" source=[/file get $filename contents]',
            array('filename' => $filename),
            null,
            $tmpScriptName,
            true
        );
        return $contents;
    }

    /**
     * Performs an action on a bulk of entries at the current menu.
     * 
     * @param string $what What action to perform.
     * @param array  $args Zero or more arguments can be specified, each being
     *     a criteria. If zero arguments are specified, removes all entries.
     *     See {@link find()} for a description of what criteria are accepted.
     * 
     * @return ResponseCollection returns the response collection, allowing you
     *     to inspect errors, if any.
     */
    protected function doBulk($what, array $args = array())
    {
        $bulkRequest = new Request($this->menu . '/' . $what);
        $bulkRequest->setArgument(
            'numbers',
            call_user_func_array(array($this, 'find'), $args)
        );
        return $this->client->sendSync($bulkRequest);
    }

    /**
     * Executes a RouterOS script.
     * 
     * Same as the public equivalent, with the addition of allowing you to get
     * the contents of the script post execution, instead of removing it.
     * 
     * @param string $source A script to execute.
     * @param array  $params An array of local variables to make available in
     *     the script. Variable names are array keys, and variable values are
     *     array values. Note that the script's (generated) name is always added
     *     as the variable "_", which you can overwrite from here.
     *     Native PHP types will be converted to their RouterOS equivalents.
     *     DateTime and DateInterval objects will be casted to RouterOS' "time"
     *     type. Other types are casted to strings.
     * @param string $policy Allows you to specify a policy the script must
     *     follow. Has the same format as in terminal. If left NULL, the script
     *     has no restrictions.
     * @param string $name   The script is executed after being saved in
     *     "/system script" under a random name (prefixed with the computer's
     *     name), and is removed after execution. To eliminate any possibility
     *     of name clashes, you can specify your own name.
     * @param bool   $get    Whether to keep the script after execution.
     * 
     * @return ResponseCollection|string If the script was not added
     *     successfully before execution, the ResponseCollection from the add
     *     attempt is going to be returned. Otherwise, the (generated) name of
     *     the script.
     */
    private function _exec(
        $source,
        array $params = array(),
        $policy = null,
        $name = null,
        $get = false
    ) {
        $request = new Request('/system/script/add');
        if (null === $name) {
            $name = uniqid(gethostname(), true);
        }
        $request->setArgument('name', $name);
        $request->setArgument('policy', $policy);

        $finalSource = '/' . str_replace('/', ' ', substr($this->menu, 1))
            . "\n";

        $params += array('_' => $name);
        foreach ($params as $pname => $pvalue) {
            $pname = static::escapeString($pname);
            $pvalue = static::escapeValue($pvalue);
            $finalSource .= ":local \"{$pname}\" {$pvalue};\n";
        }
        $finalSource .= $source . "\n";
        $request->setArgument('source', $finalSource);
        $result = $this->client->sendSync($request);

        if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
            $request = new Request('/system/script/run');
            $request->setArgument('number', $name);
            $result = $this->client->sendSync($request);

            if ($get) {
                $result = $this->client->sendSync(
                    new Request(
                        '/system/script/print .proplist="source"',
                        Query::where('name', $name)
                    )
                )->getArgument('source');
            }
            $request = new Request('/system/script/remove');
            $request->setArgument('numbers', $name);
            $this->client->sendSync($request);
        }

        return $result;
    }
}
