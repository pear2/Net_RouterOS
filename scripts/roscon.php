#!/usr/bin/env php
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

if (PHP_SAPI !== 'cli' && count(get_included_files()) === 1) {
    header('Content-Type: text/plain;charset=UTF-8');
    echo <<<HEREDOC
For security reasons, this file can not be ran DIRECTLY, except from the
command line. It can be included however, even when not using the command line.
HEREDOC;
    return;
}

//If there's no appropriate autoloader, add one
if (!class_exists('PEAR2\Net\RouterOS\Communicator', true)) {
    $cwd = getcwd();
    chdir(__DIR__);

    //The composer autoloader from this package.
    //Also matched if the bin-dir is changed to a folder that is directly
    //descended from the composer project root.
    $autoloader = stream_resolve_include_path('../vendor/autoload.php');
    if (false !== $autoloader) {
        include_once $autoloader;
    } else {
        //The composer autoloader, when this package is a dependency.
        $autoloader = stream_resolve_include_path(
            (false === ($vendorDir = getenv('COMPOSER_VENDOR_DIR'))
                ? '../../..'
                : $vendorDir) . '/autoload.php'
        );
        unset($vendorDir);
        if (false !== $autoloader) {
            include_once $autoloader;
        } else {
            //PEAR2_Autoload, most probably installed globally.
            $autoloader = stream_resolve_include_path('PEAR2/Autoload.php');
            if (false !== $autoloader) {
                include_once $autoloader;
                Autoload::initialize(
                    realpath('../src')
                );
                Autoload::initialize(
                    realpath('../../Net_Transmitter.git/src')
                );
                Autoload::initialize(
                    realpath('../../Cache_SHM.git/src')
                );
                Autoload::initialize(
                    realpath('../../Console_Color.git/src')
                );
                Autoload::initialize(
                    realpath('../../Console_CommandLine.git/src')
                );
            } else {
                fwrite(
                    STDERR,
                    <<<HEREDOC
No recognized autoloader is available.
Please install this package with Pyrus, PEAR or Composer.
Alternatively, install PEAR2_Autoload, and/or add it to your include_path.
HEREDOC
                );
                chdir($cwd);
                exit(10);
            }
        }
    }

    chdir($cwd);
    unset($autoloader, $cwd);
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
    exit(11);
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
    exit(12);
}

$cmdParser = CommandLine::fromXmlFile($consoleDefFile);
try {
    $cmd = $cmdParser->parse();
} catch (CommandLine\Exception $e) {
    fwrite(
        STDERR,
        'Error when parsing command line: ' . $e->getMessage() . "\n"
    );
    $cmdParser->displayUsage(13);
}

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

$c_colors = array(
    'SEND' => '',
    'SENT' => '',
    'RECV' => '',
    'ERR'  => '',
    'NOTE' => '',
    ''     => ''
);
if ('auto' === $cmd->options['isColored']) {
    $cmd->options['isColored'] = ((strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN'
    || getenv('ANSICON_VER') != false)
    && class_exists('PEAR2\Console\Color', true)) ? 'yes' : 'no';
}
if ('yes' === $cmd->options['isColored']) {
    if (class_exists('PEAR2\Console\Color', true)) {
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
        $c_colors['NOTE'] = new Color(
            Color\Fonts::BLUE,
            Color\Backgrounds::YELLOW
        );
        $c_colors[''] = new Color();

        foreach ($c_colors as $mode => $color) {
            $c_colors[$mode] = ((string)$color) . "\033[K";
        }
    } else {
        fwrite(
            STDERR,
            <<<HEREDOC
Warning: Color was forced, but PEAR2_Console_Color is not available.
         Resuming with colors disabled.
HEREDOC
        );
    }
}

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
            . $previous->getSocketErrorMessage() . "\n\n"
        );
    }
    if ($e instanceof RouterOS\SocketException
        && $e->getCode() === RouterOS\SocketException::CODE_CONNECTION_FAIL
    ) {
        fwrite(
            STDERR,
            <<<HEREDOC
Possible reasons:

1. You haven't enabled the API service at RouterOS or you've enabled it on a
   different TCP port.
   Make sure that the "api" service at "/ip service" is enabled, and with that
   same TCP port (8728 by default or 8729 for "api-ssl").

2. You've mistyped the IP and/or port.
   Check the IP and port you've specified are the ones you intended.

3. Your web server's IP is not in the list of subnets allowed to use the API.
   Check the "address" property at "/ip service".
   If it's empty, that's not the problem for sure. If it's non-empty however,
   make sure your IP is in that list, or is at least matched as part of an
   otherwise larger subnet.

4. The router is not reachable from your web server for some reason.
   Try to reach the router (!!!)from the web server(!!!) by other means
   (e.g. Winbox, ping) using the same IP, and if you're unable to reach it,
   check the network settings on your server, router and any intermediate nodes
   under your control that may affect the connection.

5. Your web server is configured to forbid that outgoing connection.
   If you're the web server administrator, check your web server's firewall's
   settings. If you're on a hosting plan... Typically, shared hosts block all
   outgoing connections, but it's also possible that only connections to that
   port are blocked. Try to connect to a host on a popular port (21, 80, 443,
   etc.), and if successful, change the API service port to that port. If the
   connection fails even then, ask your host to configure their firewall so as
   to allow you to make outgoing connections to the ip:port you've set the API
   service on.

6. The router has a filter/mangle/nat rule that overrides the settings at
   "/ip service".
   This is a very rare scenario, but if you want to be sure, try to disable all
   rules that may cause such a thing, or (if you can afford it) set up a fresh
   RouterOS in place of the existing one, and see if you can connect to it
   instead. If you still can't connect, such a rule is certainly not the (only)
   reason.

HEREDOC
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
   Make sure you have spelled it correctly.

2. The user does not have the "api" privilege.
   Check the permissions of the user's group at "/user group".

3. The user is not allowed to access the router from your web server's IP.
   Make sure your web server's IP address is within the subnets the user is
   allowed to log in from. You can check them at the "address" property
   of the user in the "/user" menu.

4. Mistyped password.
   Make sure you have spelled it correctly.
   If it contains spaces, don't forget to quote the whole password.
   If it contains non-ASCII characters, be careful of your locale.
   It must match that of the terminal you set your password on, or you must
   type the equivalent code points in your current locale, which may display as
   different characters.

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
    $c_sep = ' | ';
    $c_columns = array(
        'mode' => 4,
        'length' => 11,
        'encodedLength' => 12
    );
    $c_columns['contents'] = $cmd->options['size'] - 1//row length
            - array_sum($c_columns)
            - (3/*strlen($c_sep)*/ * count($c_columns));
    fwrite(
        STDOUT,
        implode(
            "\n",
            array(
                implode(
                    $c_sep,
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
                    $c_sep,
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
    
    $printWord = function (
        $mode,
        $word,
        $msg = ''
    ) use (
        $c_sep,
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
        if ('' !== $c_colors['']) {
            $wordFragments = str_replace("\033", "\033[27@", $wordFragments);
        }

        $isAbnormal = 'ERR' === $mode || 'NOTE' === $mode;
        if ($isAbnormal) {
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
                $length,
                $c_columns['length'],
                ' ',
                STR_PAD_LEFT
            ) .
            $c_sep .
            str_pad(
                '0x' . strtoupper($encodedLength),
                $c_columns['encodedLength'],
                ' ',
                STR_PAD_LEFT
            );
        }
        fwrite(
            STDOUT,
            $c_colors[$mode] .
            str_pad($mode, $c_columns['mode'], ' ', STR_PAD_RIGHT) .
            $c_colors[''] .
            "{$c_sep}{$details}{$c_sep}{$c_colors[$mode]}" .
            implode(
                "\n{$c_colors['']}" .
                str_repeat(' ', $c_columns['mode']) .
                $c_sep .
                implode(
                    ($isAbnormal ? '   ' : $c_sep),
                    array(
                        str_repeat(' ', $c_columns['length']),
                        str_repeat(' ', $c_columns['encodedLength'])
                    )
                ) . $c_sep . $c_colors[$mode],
                $wordFragments
            ) . "\n{$c_colors['']}"
        );
    };
} else {
    $printWord = function ($mode, $word, $msg = '') use ($c_colors) {
        if ('' !== $c_colors['']) {
            $word = str_replace("\033", "\033[27@", $word);
            $msg = str_replace("\033", "\033[27@", $msg);
        }

        if ('ERR' === $mode || 'NOTE' === $mode) {
            fwrite(STDERR, "{$c_colors[$mode]}-- {$msg}");
            if ('' !== $word) {
                fwrite(STDERR, ": {$word}");
            }
            fwrite(STDERR, "{$c_colors['']}\n");
        } elseif ('SENT' !== $mode) {
            fwrite(STDOUT, "{$c_colors[$mode]}{$word}{$c_colors['']}\n");
        }
    };
}

//Input/Output cycle
while (true) {

    $prevWord = null;
    $word = '';
    $words = array();


    if (!$com->getTransmitter()->isAvailable()) {
        $printWord('NOTE', '', 'Connection terminated');
        break;
    }

    //Input cycle
    while (true) {
        if ($cmd->options['verbose']) {
            fwrite(
                STDOUT,                
                implode(
                    $c_sep,
                    array(
                        $c_colors['SEND'] .
                        str_pad('SEND', $c_columns['mode'], ' ', STR_PAD_RIGHT)
                        . $c_colors[''],
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

        fwrite(STDOUT, (string)$c_colors['SEND']);

        if ($cmd->options['multiline']) {
            while (true) {
                $line = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
                if (chr(3) === $line) {
                    break;
                }
                if ((chr(3) . chr(3)) === $line) {
                    $word .= chr(3);
                } else {
                    $word .=  $line . PHP_EOL;
                }
                if ($cmd->options['verbose']) {
                    fwrite(
                        STDOUT,
                        "\n{$c_colors['']}" .
                        implode(
                            $c_sep,
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
            }
            if ('' !== $word) {
                $word = substr($word, 0, -strlen(PHP_EOL));
            }
        } else {
            $word = stream_get_line(STDIN, PHP_INT_MAX, PHP_EOL);
        }

        if ($cmd->options['verbose']) {
            fwrite(STDOUT, "\n");
        }
        fwrite(STDOUT, (string)$c_colors['']);

        $words[] = $word;
        if ('w' === $cmd->options['commandMode']) {
            break;
        }
        if ('' === $word) {
            if ('s' === $cmd->options['commandMode']) {
                break;
            } elseif ('' === $prevWord) {//'e' === $cmd->options['commandMode']
                array_pop($words);
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
            if (0 === $e->getFragment()) {
                $printWord('ERR', '', 'Failed to send word');
            } else {
                $printWord(
                    'ERR',
                    substr($word, 0, $e->getFragment()),
                    'Partial word sent'
                );
            }
        }
    }

    //Output cycle
    while (true) {
        if (!$com->getTransmitter()->isAvailable()) {
            break;
        }

        if (!$com->getTransmitter()->isDataAwaiting($cmd->options['time'])) {
            $printWord('NOTE', '', 'Receiving timed out');
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
            if ('' === $e->getFragment()) {
                $printWord('ERR', '', 'Failed to receive word');
            } else {
                $printWord('ERR', $e->getFragment(), 'Partial word received');
            }
            break;
        } catch (RouterOS\NotSupportedException $e) {
            $printWord('ERR', $e->getValue(), 'Unsupported control byte');
            break;
        } catch (E $e) {
            $printWord('ERR', (string)$e, 'Unknown error');
            break;
        }
    }
}
