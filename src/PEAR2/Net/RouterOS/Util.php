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
     * @var array The entries in the current menu.
     */
    protected $entryCache = null;

    /**
     * Escapes a string for a RouterOS scripting context.
     * 
     * Escapes a string for a RouterOS scripting context. The value can be
     * surrounded with quotes at a RouterOS script, and you can be sure there
     * won't be any code injections.
     * 
     * @param string $value Value to be escaped.
     * 
     * @return string The escaped value.
     */
    public static function escapeString($value)
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9]/S',
            array(self, '_escapeCharacter'),
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
            strtoupper(dechex(ord($char))),
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
     *     it's relative to the current menu.
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
        $this->entryCache = null;
        return $oldMenu;
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
            $this->refreshEntryCache();
            foreach ($this->entryCache as $entry) {
                $idList .= $entry->getArgument('.id') . ',';
            }
        }
        foreach (func_get_args() as $criteria) {
            if ($criteria instanceof Query) {
                foreach ($this->client->sendSync(
                    new Request($this->menu . '/print .proplist=.id', $criteria)
                ) as $response) {
                    $idList .= $response->getArgument('.id') . ',';
                }
            } else {
                $this->refreshEntryCache();
                if (is_callable($criteria)) {
                    foreach ($this->entryCache as $entry) {
                        if ($criteria($entry)) {
                            $idList .= $entry->getArgument('.id') . ',';
                        }
                    }
                } elseif (is_int($criteria)) {
                    $idList = $this->entryCache[$criteria]->getArgument('.id')
                        . ',';
                } else {
                    $criteria = (string)$criteria;
                    if ($criteria === (string)(int)$criteria) {
                        $idList .= $this->entryCache[(int)$criteria]
                            ->getArgument('.id') . ',';
                    } elseif (false === strpos($criteria, ',')) {
                        $idList .= $criteria . ',';
                    } else {
                        $criteriaArr = explode(',', $criteria);
                        array_filter(
                            $criteriaArr,
                            function ($value) use (&$idList) {
                                if ('' === $value) {
                                    return false;
                                }
                                if ('*' === $value[0]) {
                                    $idList .= $value . ',';
                                    return false;
                                }
                                return true;
                            }
                        );
                        $idList .= call_user_func_array(
                            array($this, 'find'),
                            $criteriaArr
                        );
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
     *     targeting. Can also be a name or ID.
     * @param string $value_name The name of the value you want to get.
     * 
     * @return string|null|bool The value of the specified property. If the
     *     property is not set, NULL will be returned. If no such entry exists,
     *     FALSE will be returned.
     */
    public function get($number, $value_name)
    {
        if ('disabled' === $value_name) {
            $this->entryCache = null;
        }
        $this->refreshEntryCache();
        if (is_int($number) || ((string)$number === (string)(int)$number)) {
            if (isset($this->entryCache[(int)$number])) {
                return $this->entryCache[(int)$number]->getArgument(
                    $value_name
                );
            }
            return false;
        }

        $number = (string)$number;
        foreach ($this->entryCache as $entry) {
            if ($entry->getArgument('name') === $number
                || $entry->getArgument('.id') === $number
            ) {
                return $entry->getArgument($value_name);
            }
        }
        return false;
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
        $this->entryCache = null;
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
        $this->entryCache = null;
        return $this->client->sendSync($setRequest);
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
        $this->entryCache = null;
        return $this->client->sendSync($addRequest)->getArgument('ret');
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
        $this->entryCache = null;
        return $this->client->sendSync($moveRequest);
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
        $result = $this->client->sendSync($request);

        if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
            $request = new Request('/file/set');
            $request->setArgument('numbers', $filename)
                ->setArgument('contents', $data);
            $result = $this->client->sendSync($request);
            if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
                return true;
            }
        }
        return false;
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
     *     as the variable "_", which you can overwrite here.
     * @param string $policy Allows you to specify a policy the script must
     *     follow. Has the same format as in terminal. If left empty, the script
     *     has no restrictions.
     * @param string $name   The script is executed after being saved in
     *     "/system script" under a random name, and is removed after execution.
     *     To eliminate any possibility of name clashes, you can specify your
     *     own name.
     * 
     * @return ResponseCollection returns the response collection of the run,
     *     allowing you to inspect errors, if any.
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

        $params += array('_', $name);
        foreach ($params as $pname => $pvalue) {
            $pname = static::escapeString($pname);
            $pvalue = static::escapeString($pvalue);
            $finalSource .= ":local \"{$pname}\" \"{$pvalue}\"\n";
        }
        $finalSource .= $source;
        $request->setArgument('source', $finalSource);
        $result = $this->client->sendSync($request);

        if (0 === count($result->getAllOfType(Response::TYPE_ERROR))) {
            $request = new Request('/system/script/run');
            $request->setArgument('number', $name);
            $result = $this->client->sendSync($request);

            $request = new Request('/system/script/remove');
            $request->setArgument('numbers', $name);
            $this->client->sendSync($request);
        }

        return $result;
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
     * Refresh the entry cache, if needed.
     * 
     * @return void
     */
    protected function refreshEntryCache()
    {
        if (null === $this->entryCache) {
            $entryCache = array();
            foreach ($this->client->sendSync(
                new Request($this->menu . '/print')
            )->getAllOfType(Response::TYPE_DATA) as $response) {
                $entryCache[
                    hexdec(substr($response->getArgument('.id'), 1))
                ] = $response;
            }
            ksort($entryCache, SORT_NUMERIC);
            $this->entryCache = SplFixedArray::fromArray(
                array_values($entryCache)
            );
        }
    }
}
