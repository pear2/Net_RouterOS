<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5.3
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
 * Used as a "catch all" for errors when connecting.
 */
use Exception as E;

/**
 * Used to register dependency paths, if needed.
 */
use PEAR2\Autoload;

/**
 * Used for coloring the output, if the "--colors" argument is specified.
 */
use PEAR2\Console\Color;

/**
 * Used for parsing the command line arguments.
 */
use PEAR2\Console\CommandLine;

/**
 * The whole application is around that.
 */
use PEAR2\Net\RouterOS;

/**
 * Used for error handling when connecting or receiving.
 */
use PEAR2\Net\Transmitter\SocketException as SE;

//If there's no appropriate autoloader, add one
if (!class_exists('PEAR2\Net\RouterOS\Communicator', true)) {
    include_once 'PEAR2/Autoload.php';
    Autoload::initialize(realpath(__DIR__ . '/../src'));
    Autoload::initialize(realpath(__DIR__ . '/../../Net_Transmitter.git/src'));
    Autoload::initialize(realpath(__DIR__ . '/../../Console_Color.git/src'));
}

// Locate the data dir, in preference as:
// 1. The data folder at "mypear" (filled at install time by Pyrus/PEAR)
// 2. The source layout's data folder (also used from PHAR)
// 3. The PHP_PEAR_DATA_DIR environment variable, if available.
$dataDir = realpath('@PEAR2_DATA_DIR@/@PACKAGE_CHANNEL@/@PACKAGE_NAME@')
    ?: (realpath(__DIR__ . '/../data') ?:
        (false != ($pearDataDir = getenv('PHP_PEAR_DATA_DIR'))
            ? realpath($pearDataDir . '/@PACKAGE_CHANNEL@/@PACKAGE_NAME@')
            : false
        )
    );
if (false === $dataDir) {
    fwrite(
        STDERR,
        'Unable to find data dir.'
    );
    exit(10);
}
$consoleDefFile = realpath($dataDir . '/roscon.xml');
if (false === $consoleDefFile) {
    fwrite(
        STDERR,
        <<<HEREDOC
The console definition file (roscon.xml) was not found at the data dir, which
was found to be at
{$dataDir}
HEREDOC
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

$c_colors = array(
    'SEND' => '',
    'SENT' => '',
    'RECV' => '',
    'ERR'  => '',
    ''     => ''
);
if ($cmd->options['colors']) {
    $c_colors['SENT'] = new Color(
        Color\Fonts::BLACK,
        Color\Backgrounds::PURPLE
    );
    $c_colors['SEND'] = clone $c_colors['SENT'];
    $c_colors['SEND']->setStyles(Color\Styles::UNDERLINE, true);
    $c_colors['RECV'] = new Color(
        Color\Fonts::BLACK,
        Color\Backgrounds::GREEN
    );
    $c_colors['ERR'] = new Color(
        Color\Fonts::WHITE,
        Color\Backgrounds::RED
    );
    $c_colors[''] = new Color();
    
    //fwrite(STDOUT, $c_colors['']);
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
    fwrite(STDERR, "Error upon connecting: {$e->getMessage()}\n");
    $previous = $e->getPrevious();
    if ($previous instanceof SE) {
        fwrite(
            STDERR,
            "Details: ({$previous->getSocketErrorNumber()}) "
            . $previous->getSocketErrorMessage()
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
3. The user does not have the "api" privilege. Check the username's group, and
   it's permissions at "/user groups".
2. Mistyped password. If the password contains non-ASCII characters, be careful
   of your locale settings - either they must match those of the terminal you
   set your password on, or you must type the equivalent code points in your
   locale, which may display as different characters.

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
    $c_columns = array(
        'mode' => 4,
        'length' => 10,
        'encodedLength' => 12
    );
    $c_columns['contents'] = $cmd->options['size'] - 1//row length
            - array_sum($c_columns)
            - (3/*strlen(' | ')*/ * count($c_columns));
    fwrite(
        STDOUT,
        implode(
            "\n",
            array(
                implode(
                    ' | ',
                    array(
                        str_pad(
                            'MODE',
                            $c_columns['mode'],
                            ' ',
                            STR_PAD_RIGHT
                        ),
                        str_pad(
                            'LENGTH',
                            $c_columns['length'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        str_pad(
                            'LENGTH',
                            $c_columns['encodedLength'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        ' CONTENTS'
                    )
                ),
                implode(
                    ' | ',
                    array(
                        str_repeat(' ', $c_columns['mode']),
                        str_pad(
                            '(decoded)',
                            $c_columns['length'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        str_pad(
                            '(encoded)',
                            $c_columns['encodedLength'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        ''
                    )
                ),
                implode(
                    '-|-',
                    array(
                        str_repeat('-', $c_columns['mode']),
                        str_repeat('-', $c_columns['length']),
                        str_repeat('-', $c_columns['encodedLength']),
                        str_repeat('-', $c_columns['contents'])
                    )
                )
            )
        ) . "\n"
    );

    $c_regexWrap = '/([^\n]{1,' . ($c_columns['contents']) . '})/sS';
}

$printWord = $cmd->options['verbose']
    ? function (
        $mode,
        $word,
        $msg = ''
    ) use (
        $c_columns,
        $c_regexWrap,
        $c_colors
        ) {
    $wordFragments = preg_split(
        $c_regexWrap,
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
            $c_columns['length'] + $c_columns['encodedLength'] + 3,
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
            $c_columns['length'],
            ' ',
            STR_PAD_LEFT
        ) .
        ' | ' .
        str_pad(
            '0x' . strtoupper($encodedLength),
            $c_columns['encodedLength'],
            ' ',
            STR_PAD_LEFT
        );
    }
    fwrite(
        STDOUT,
        str_pad($mode, $c_columns['mode'], ' ', STR_PAD_RIGHT) .
        " | {$details} | {$c_colors[$mode]}" .
        implode(
            "\n{$c_colors['']}" .
            str_repeat(' ', $c_columns['mode']) .
            ' | ' .
            implode(
                ('ERR' === $mode ? '   ' : ' | '),
                array(
                    str_repeat(' ', $c_columns['length']),
                    str_repeat(' ', $c_columns['encodedLength'])
                )
            ) . ' | ' . $c_colors[$mode],
            $wordFragments
        ) . "\n{$c_colors['']}"
    );
    }
    : function ($mode, $word, $msg = '') use ($c_colors) {
    if ('ERR' === $mode) {
        fwrite(STDERR, "{$c_colors[$mode]}{$msg}: {$word}{$c_colors['']}");
    } elseif ('SENT' !== $mode) {
        fwrite(STDOUT, "{$c_colors[$mode]}{$word}{$c_colors['']}\n");
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
                        str_pad('SEND', $c_columns['mode'], ' ', STR_PAD_RIGHT),
                        str_pad(
                            '<prompt>',
                            $c_columns['length'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            '<prompt>',
                            $c_columns['encodedLength'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        ''
                    )
                )
            );
        }

        fwrite(STDOUT, $c_colors['SEND']);

        if ($cmd->options['multiline']) {
            $line = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
            if (chr(3) !== $line) {
                if ('' === $line) {
                    $word .= PHP_EOL;
                } else {
                    $word .= $line;
                }
                while (true) {
                    if ($cmd->options['verbose']) {
                        fwrite(
                            STDOUT,
                            "\n{$c_colors['']}" .
                            implode(
                                ' | ',
                                array(
                                    str_repeat(' ', $c_columns['mode']),
                                    str_repeat(' ', $c_columns['length']),
                                    str_repeat(' ', $c_columns['encodedLength']),
                                    ''
                                )
                            )
                            . $c_colors['SEND']
                        );
                    }
                    $line = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
                    if (chr(3) === $line) {
                        break;
                    }
                    $word .= PHP_EOL;
                    if ((chr(3) . chr(3)) === $line) {
                        $word .= chr(3);
                    } else {
                        $word .= $line;
                    }
                }
            }
        } else {
            $word = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
        }

        if ($cmd->options['verbose']) {
            fwrite(STDOUT, "\n");
        }
        fwrite(STDOUT, $c_colors['']);

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
        try {
            $com->sendWord($word);
            $printWord('SENT', $word);
        } catch (SE $e) {
            $printWord('ERR', $word, 'Failed to send word');
        }
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
