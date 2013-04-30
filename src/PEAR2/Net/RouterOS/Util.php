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
 * Used as a holder for the entry cache.
 */
use SplFixedArray;

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
    protected $objClient;

    /**
     * @var string The current menu.
     */
    protected $menu = '/';

    /**
     * @var SplFixedArray An array with the numbers of entries in the current menu as
     *     keys, and the corresponding IDs as values.
     */
    protected $idCache = null;
    
    /**
     * Escapes a value for a RouterOS scripting context.
     * 
     * Turns any PHP value into an equivalent whole value that can be inserted
     * as part of a RouterOS script.
     * @param mixed $value The value to be escaped.
     * 
     * @return string A string representation that can be directly inserted in a
     *     script as a whole value.
     */
    public static function escapeValue($value)
    {
        switch(gettype($value)) {
        case 'integer':
            $value = (string)$value;
            break;
        case 'boolean':
            $value = $value ? 'true' : 'false';
            break;
        case 'array':
            $result = '{';
            foreach ($value as $val) {
                $result .= static::escapeValue($val) . ';';
            }
            $value = rtrim($result, ';') . '}';
            break;
        case 'null':
            $value = '';
            break;
        case 'object':
            if ($value instanceof DateTime) {
                $usec = $value->format('u');
                if ('0' === $usec) {
                    unset($usec);
                }
                $unixEpoch = new DateTime('1970-01-01 00:00:00.000000');
                $value = $unixEpoch->diff($value);
            }
            if ($value instanceof DateInterval) {
                $result = '';
                $daysTotal = $value->format('a');
                if ($daysTotal >= 7) {
                    $result = ($daysTotal / 7) . 'w';
                    $daysRemaining = ($daysTotal % 7);
                    if ($daysRemaining > 0) {
                        $result = $daysRemaining . 'd';
                    }
                } else {
                    $result = $daysTotal . 'd';
                }
                $value = $result . $value->format('H:I:S');
                if (isset($usec)) {
                    $value .= '.' . str_pad($usec, 6, '0', STR_PAD_LEFT);
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
        $this->objClient = $client;
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
        $this->idCache = null;
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
     *     Values that are not strings will be converted to their scripting
     *     equivalents. DateTime and DateInterval objects will be casted to
     *     RouterOS' "time" type.
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
            $finalSource .= ":local \"{$pname}\" {$pvalue}\n";
        }
        $finalSource .= $source . "\n";
        $request->setArgument('source', $finalSource);
        $result = $this->objClient->sendSync($request);

        if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
            $request = new Request('/system/script/run');
            $request->setArgument('number', $name);
            $result = $this->objClient->sendSync($request);

            $request = new Request('/system/script/remove');
            $request->setArgument('numbers', $name);
            $this->objClient->sendSync($request);
        }

        return $result;
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
        $idList = '';
        if (func_num_args() === 0) {
            $this->refreshIdCache();
            $idList .= implode(',', $this->idCache->toArray()) . ',';
        }
        foreach (func_get_args() as $criteria) {
            if ($criteria instanceof Query) {
                foreach ($this->objClient->sendSync(
                    new Request($this->menu . '/print .proplist=.id', $criteria)
                ) as $response) {
                    $idList .= $response->getArgument('.id') . ',';
                }
            } else {
                $this->refreshIdCache();
                if (is_callable($criteria)) {
                    foreach ($this->objClient->sendSync(
                        new Request($this->menu . '/print')
                    ) as $response) {
                        if ($criteria($response)) {
                            $idList .= $response->getArgument('.id') . ',';
                        }
                    }
                } elseif (is_int($criteria)) {
                    $idList = $this->idCache[$criteria] . ',';
                } else {
                    $criteria = (string)$criteria;
                    if ($criteria === (string)(int)$criteria) {
                        $idList .= $this->idCache[(int)$criteria] . ',';
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
                        $idList .= call_user_func_array(
                            array($this, 'find'),
                            $criteriaArr
                        ) . ',';
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
            $this->refreshIdCache();
            if (isset($this->idCache[(int)$number])) {
                $number = $this->idCache[(int)$number];
            }
            return false;
        }

        $request = new Request(
            $this->menu . '/print',
            Query::where('.id', (string)$number)
        );
        $request->setArgument('.proplist', $value_name);
        $responses = $this->objClient->sendSync($request)
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
        $this->idCache = null;
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
        $setRequest->setArgument('numbers', $this->find($numbers));
        return $this->objClient->sendSync($setRequest);
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
     * Adds a new entry at the current menu.
     * 
     * @param array $values An array with the names of each property as an array
     *     key, and the value as an array value.
     * 
     * @return string The new entry's ID.
     */
    public function add(array $values)
    {
        $addRequest = new Request($this->menu . '/add');
        foreach ($values as $name => $value) {
            $addRequest->setArgument($name, $value);
        }
        $this->idCache = null;
        return $this->objClient->sendSync($addRequest)->getArgument('ret');
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
        $moveRequest->setArgument('numbers', $this->find($numbers))
            ->setArgument(
                'destination',
                strstr($this->find($destination), ',', true)
            );
        $this->idCache = null;
        return $this->objClient->sendSync($moveRequest);
    }

    /**
     * Puts a file on RouterOS's file system.
     * 
     * @param string $filename The filename to write data in.
     * @param string $data     The data the file is going to have.
     * 
     * @return bool TRUE on success, FALSE on failure.
     */
    public function filePutContents($filename, $data)
    {
        $request = new Request(
            '/file/print .proplist=""',
            Query::where('name', $filename)
        );
        $request->setArgument('file', $filename);
        $result = $this->objClient->sendSync($request);

        if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
            $request = new Request('/file/set');
            $request->setArgument('numbers', $filename)
                ->setArgument('contents', $data);
            $result = $this->objClient->sendSync($request);
            if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
                return true;
            }
        }
        return false;
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
        return $this->objClient->sendSync($bulkRequest);
    }

    /**
     * Refresh the id cache, if needed.
     * 
     * @return void
     */
    protected function refreshIdCache()
    {
        if (null === $this->idCache) {
            $idCache = array();
            foreach ($this->objClient->sendSync(
                new Request($this->menu . '/print .proplist=.id')
            )->getAllOfType(Response::TYPE_DATA) as $response) {
                $id =$response->getArgument('.id');
                $idCache[hexdec(substr($id, 1))] = $id;
            }
            ksort($idCache, SORT_NUMERIC);
            $this->idCache = SplFixedArray::fromArray(array_values($idCache));
        }
    }
}
