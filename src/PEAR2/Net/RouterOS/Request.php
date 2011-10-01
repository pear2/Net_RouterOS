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
 * Represents a RouterOS request.
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://netrouteros.sourceforge.net/
 */
class Request extends Message
{

    /**
     * @var string The command to be executed.
     */
    private $_command;

    /**
     * @var Query A query for the command.
     */
    private $_query = null;

    /**
     * Creates a request to send to RouterOS.
     * 
     * @param string $command The command to send.
     * 
     * @see setCommand()
     * @see setArgument()
     * @see setTag()
     */
    public function __construct($command)
    {
        $this->setCommand($command);
    }

    /**
     * Sets the command to send to RouterOS.
     * 
     * Sets the command to send to RouterOS. The command can use the API or CLI
     * syntax of RouterOS, but either way, it must be absolute (begin  with a
     * "/") and without arguments.
     * 
     * @param string $command The command to send.
     * 
     * @return Request The request object.
     * @see getCommand()
     * @see setArgument()
     */
    public function setCommand($command)
    {
        $command = (string) $command;
        if (strpos($command, '/') !== 0) {
            throw new InvalidArgumentException(
                'Commands must be absolute.', 202
            );
        }
        if (substr_count($command, '/') === 1) {
            //Command line syntax convertion
            $cmdParts = preg_split('#[\s/]+#sm', $command);
            $cmdRes = array($cmdParts[0]);
            for ($i = 1, $n = count($cmdParts); $i < $n; $i++) {
                if ('..' === $cmdParts[$i]) {
                    $delIndex = count($cmdRes) - 1;
                    if ($delIndex < 1) {
                        throw new InvalidArgumentException(
                            'Unable to resolve command', 203
                        );
                    }
                    unset($cmdRes[$delIndex]);
                    $cmdRes = array_values($cmdRes);
                } else {
                    $cmdRes[] = $cmdParts[$i];
                }
            }
            $command = implode('/', $cmdRes);
        }
        if (!preg_match('#^/\S+$#sm', $command)) {
            throw new InvalidArgumentException(
                'Invalid command supplied.', 204
            );
        }
        $this->_command = $command;
        return $this;
    }

    /**
     * Gets the command that will be send to RouterOS.
     * 
     * Gets the command that will be send to RouterOS in its API syntax.
     * 
     * @return string The command to send.
     * @see setCommand()
     */
    public function getCommand()
    {
        return $this->_command;
    }

    /**
     * Sets the query to send with the command.
     * 
     * @param Query $query The query to be set. Setting NULL will remove the
     * currently associated query.
     * 
     * @return Request The request object.
     * @see getQuery()
     */
    public function setQuery(Query $query = null)
    {
        $this->_query = $query;
        return $this;
    }

    /**
     * Gets the currently associated query
     * 
     * @return Query The currently associated query.
     * @see setQuery()
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * Sets the tag to associate the request with.
     * 
     * Sets the tag to associate the request with. Setting NULL erases the
     * currently set tag.
     * 
     * @param string $tag The tag to set.
     * 
     * @return Request The request object.
     * @see getTag()
     */
    public function setTag($tag)
    {
        return parent::setTag($tag);
    }

    /**
     * Sets an argument for the request.
     * 
     * @param string $name  Name of the argument.
     * @param string $value Value of the argument. Setting the value to NULL
     * removes an argument of this name.
     * 
     * @return Request The request object.
     * @see getArgument()
     */
    public function setArgument($name, $value = null)
    {
        return parent::setArgument($name, $value);
    }

    /**
     * Removes all arguments from the request.
     * 
     * @return Request The request object.
     */
    public function removeAllArguments()
    {
        return parent::removeAllArguments();
    }

    /**
     * Sends a request over a communicator.
     * 
     * @param Communicator $com The communicator to send the request over.
     * 
     * @return int The number of bytes sent.
     * @see Client::sendSync()
     * @see Client::sendAsync()
     */
    public function send(Communicator $com)
    {
        if (!$com->getTransmitter()->isAcceptingData()) {
            throw new SocketException(
                'Transmitter is invalid. Sending aborted.', 205
            );
        }
        $bytes = 0;
        $bytes += $com->sendWord($this->getCommand());
        if (null !== ($tag = $this->getTag())) {
            $bytes += $com->sendWord('.tag=' . $tag);
        }
        foreach ($this->getAllArguments() as $name => $value) {
            $prefix = '=' . $name . '=';
            if (is_string($value)) {
                $bytes += $com->sendWord($prefix . $value);
            } else {
                $bytes += $com->sendWordFromStream($prefix, $value);
            }
        }
        $query = $this->getQuery();
        if ($query instanceof Query) {
            $bytes += $query->send($com);
        }
        $bytes += $com->sendWord('');
        return $bytes;
    }

}