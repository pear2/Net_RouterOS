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
 * The whole application is around that.
 */
use PEAR2\Net\RouterOS;

/**
 * Used for parsing the command line arguments.
 */
use PEAR2\Console\CommandLine;

/**
 * Used for error handling when connecting or receiving.
 */
use PEAR2\Net\Transmitter\SocketException as SE;

/**
 * Used as a "catch all" for errors when connecting.
 */
use Exception as E;

//If there's no appropriate autoloader, add one
if (!class_exists('PEAR2\Net\RouterOS\Communicator', true)) {
    include_once 'PEAR2/Autoload.php';
    \PEAR2\Autoload::initialize(realpath('../src'));
    \PEAR2\Autoload::initialize(realpath('../../Net_Transmitter.git/src'));
}

// Locate the data dir, in preference as:
// 1. The data folder at "mypear" (filled at install time by Pyrus/PEAR)
// 2. The source layout's data folder (also used from PHAR)
// 3. The PHP_PEAR_DATA_DIR environment variable, if available.
$dataFolder = realpath('@PEAR2_DATA_DIR@/@PACKAGE_CHANNEL@/@PACKAGE_NAME@')
    ?: (realpath(__DIR__ . '/../data') ?:
        (false != ($pearDataDir = getenv('PHP_PEAR_DATA_DIR'))
            ? realpath($pearDataDir . '/@PACKAGE_CHANNEL@/@PACKAGE_NAME@')
            : false
        )
    );
if (false === $dataFolder) {
    fwrite(
        STDERR,
        'Unable to find data dir.'
    );
    exit(10);
}
$consoleDefFile = realpath($dataFolder . '/roscon.xml');
if (false === $consoleDefFile) {
    fwrite(
        STDERR,
        'Unable to find console definition file. Check your data dir setting.'
    );
    exit(11);
}

try {
    $cmdParser = CommandLine::fromXmlFile($consoleDefFile);
    $cmd = $cmdParser->parse();
} catch (CommandLine\Exception $e) {
    fwrite(
        STDERR,
        'Error when parsing command line: ' . $e->getMessage()
    );
    exit(12);
}
$cmd->options['size'] = $cmd->options['size'] ?: 80;
$cmd->options['commandMode'] = $cmd->options['commandMode'] ?: 's';
$cmd->options['replyMode'] = $cmd->options['replyMode'] ?: 's';
$comTimeout = null === $cmd->options['conTime']
    ? (null === $cmd->options['time']
            ? (int)ini_get('default_socket_timeout')
            : $cmd->options['time'])
    : $cmd->options['conTime'];
$cmd->options['time'] = $cmd->options['time'] ?: 3;
$comContext = null === $cmd->options['caPath']
    ? null
    : stream_context_create(
        is_file($cmd->options['caPath'])
        ? array(
            'ssl' => array(
                'verify_peer' => true,
                'cafile' => $cmd->options['caPath'])
          )
        : array(
            'ssl' => array(
                'verify_peer' => true,
                'capath' => $cmd->options['caPath'])
          )
    );

try {
    $com = new RouterOS\Communicator(
        $cmd->args['hostname'],
        $cmd->options['portNum'],
        false,
        $comTimeout,
        '',
        (string)$cmd->options['crypto'],
        $comContext
    );
} catch (E $e) {
    fwrite(STDERR, "Error upon connecting: " . $e->getMessage());
    $previous = $e->getPrevious();
    if ($previous instanceof SE) {
        fwrite(
            STDERR,
            "\nDetails: (" . $previous->getSocketErrorNumber() .
            ') ' . $previous->getSocketErrorMessage()
        );
    }
    return;
}
if (null !== $cmd->args['username']) {
    try {
        if (!RouterOS\Client::login(
            $com,
            $cmd->args['username'],
            (string)$cmd->args['password'],
            $comTimeout
        )) {
            fwrite(
                STDERR,
<<<HEREDOC
Login refused. Possible reasons:
1. No such username.
2. Mistyped password.
3. The user does not have the "api" privilege.
HEREDOC
            );
            return;
        }
    } catch (RouterOS\SocketException $e) {
        fwrite(STDERR, "Error upon login: " . $e->getMessage());
        return;
    }
}

if ($cmd->options['verbose']) {
    fwrite(STDOUT, "MODE |   LENGTH   |    LENGTH    | CONTENTS\n");
    fwrite(STDOUT, "     |  (decoded) |   (encoded)  |");
    $columns = array(
        'mode' => 4,
        'length' => 10,
        'encodedLength' => 12
    );
    $columns['contents'] = $cmd->options['size'] - 1//row length
            - array_sum($columns)
            - (3/*strlen(' | ')*/ * count($columns));
    fwrite(
        STDOUT,
        "\n" .
        implode(
            '-|-',
            array(
                str_repeat('-', $columns['mode']),
                str_repeat('-', $columns['length']),
                str_repeat('-', $columns['encodedLength']),
                str_repeat('-', $columns['contents'])
            )
        ) . "\n"
    );

    define(
        'PEAR2\Net\RouterOS\REGEX_WRAP',
        '/([^\n]{1,' . ($columns['contents'])
        . '})/sS'
    );
}

$printWord = $cmd->options['verbose']
    ? function ($mode, $word, $msg = '') use ($columns) {
    $wordFragments = preg_split(
        RouterOS\REGEX_WRAP,
        $word,
        null,
        PREG_SPLIT_DELIM_CAPTURE
    );
    for ($i = 0, $l = count($wordFragments); $i < $l; $i += 2) {
        unset($wordFragments[$i]);
    }

    if ('ERR' === $mode) {
        $details = str_pad(
            $msg,
            $columns['length'] + $columns['encodedLength'] + 3,
            ' ',
            STR_PAD_BOTH
        );
    } else {
        $length = strlen($word);
        $lengthBytes = RouterOS\Communicator::encodeLength($length);
        $encodedLength = '';
        for ($i = 0, $l = strlen($lengthBytes); $i < $l; ++$i) {
            $encodedLength .= str_pad(
                dechex(ord($lengthBytes[$i])),
                2,
                '0',
                STR_PAD_LEFT
            );
        }

        $details = str_pad(
            '0x' . strtoupper(dechex($length)),
            $columns['length'],
            ' ',
            STR_PAD_LEFT
        ) .
        ' | ' .
        str_pad(
            '0x' . strtoupper($encodedLength),
            $columns['encodedLength'],
            ' ',
            STR_PAD_LEFT
        );
    }
    fwrite(
        STDOUT,
        str_pad($mode, $columns['mode'], ' ', STR_PAD_RIGHT) . ' | ' .
        $details . ' | ' .
        implode(
            "\n" . 
            str_repeat(' ', $columns['mode']) .
            ' | ' .
            implode(
                ('ERR' === $mode ? '   ' : ' | '),
                array(
                    str_repeat(' ', $columns['length']),
                    str_repeat(' ', $columns['encodedLength'])
                )
            ) . ' | ',
            $wordFragments
        ) . "\n"
    );
    }
    : function ($mode, $word, $msg = '') {
    if ('ERR' === $mode) {
        fwrite(STDERR, "{$msg}: {$word}\n");
    } elseif ('SENT' !== $mode) {
        fwrite(STDOUT, $word);
    }
    };

//Input/Output cycle
while (true) {

    $prevWord = null;
    $word = '';
    $words = array();

    //Input cycle
    while (true) {
        if ($cmd->options['verbose']) {
            fwrite(
                STDOUT,
                implode(
                    ' | ',
                    array(
                        str_pad('SEND', $columns['mode'], ' ', STR_PAD_RIGHT),
                        str_pad(
                            '<prompt>',
                            $columns['length'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            '<prompt>',
                            $columns['encodedLength'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        ''
                    )
                )
            );
        }

        if ($cmd->options['multiline']) {
            while (true) {
                $line = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
                if (chr(3) === $line) {
                    break;
                }
                $word .= PHP_EOL;
                if ((chr(3) . chr(3)) === $line) {
                    $word .= chr(3);
                    continue;
                }
                $word .= $line;
            }
        } else {
            $word = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
        }

        $words[] = $word;
        if ('w' === $cmd->options['commandMode']) {
            break;
        }
        if ('' === $word) {
            if ('s' === $cmd->options['commandMode'] || '' === $prevWord) {
                break;
            }
        }
        $prevWord = $word;
        $word = '';
    }

    //Input flush
    foreach ($words as $word) {
        $com->sendWord($word);
        $printWord('SENT', $word);
    }

    //Output cycle
    while (true) {
        if (!$com->getTransmitter()->isDataAwaiting($cmd->options['time'])) {
            break;
        }

        try {
            $word = $com->getNextWord();
            $printWord('RECV', $word);

            if ('w' === $cmd->options['replyMode']
                || ('s' === $cmd->options['replyMode'] && '' === $word)
            ) {
                break;
            }
        } catch (SE $e) {
            $printWord('ERR', $e->getFragment(), 'Incomplete word');
            break;
        } catch (RouterOS\NotSupportedException $e) {
            $printWord('ERR', $e->getValue(), 'Unsupported control byte');
            break;
        } catch (E $e) {
            $printWord('ERR', (string)$e, 'Unknown error');
            break;
        }
    }

    if (!$com->getTransmitter()->isAvailable()) {
        break;
    }
}