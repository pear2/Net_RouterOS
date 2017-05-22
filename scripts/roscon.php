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

//Detect disallowed direct runs of either this file or "roscon".
if (PHP_SAPI !== 'cli') {
    $includedFiles = get_included_files();
    $rosconPos = array_search(
        dirname(__FILE__) . DIRECTORY_SEPARATOR . 'roscon',
        $includedFiles,
        true
    );
    if (false !== $rosconPos) {
        unset($includedFiles[$rosconPos]);
    }

    if (count($includedFiles) === 1) {
        header('Content-Type: text/plain;charset=UTF-8');
        echo <<<HEREDOC
For security reasons, this file can not be ran DIRECTLY, except from the
command line. It can be included however, even when not using the command line.
HEREDOC;
        return;
    }
}

//If there's no appropriate autoloader, add one
if (!class_exists('PEAR2\Net\RouterOS\Communicator', true)) {
    $cwd = getcwd();
    chdir(__DIR__);

    $composerAutoloaderPaths = array();
    $vendorDir = getenv('COMPOSER_VENDOR_DIR');
    if (false !== $vendorDir) {
        $composerAutoloaderPaths[] = $vendorDir . '/autoload.php';
        unset($vendorDir);
    }
    $composerAutoloaderPaths[] = '../vendor/autoload.php';
    $composerAutoloaderPaths[] = '../../../autoload.php';
    foreach ($composerAutoloaderPaths as $autoloaderPath) {
        $autoloader = stream_resolve_include_path($autoloaderPath);
        if (false !== $autoloader) {
            include_once $autoloader;
            if (class_exists('PEAR2\Net\RouterOS\Communicator', true)) {
                break;
            }
            $autoloader = false;
        }
    }
    unset($autoloaderPath, $composerAutoloaderPaths);
    if (false === $autoloader) {
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
                realpath('../../Console_Color.git/src')
            );
            Autoload::initialize(
                realpath('../../Console_CommandLine.git/src')
            );
        } else {
            $phpIniLocation = php_ini_loaded_file();
            $phpIncludePath = get_include_path();
            $defaultDirPyrus = __DIR__ . DIRECTORY_SEPARATOR . 'php';
            $defaultDirPear = __DIR__ . DIRECTORY_SEPARATOR . 'pear';
            fwrite(
                STDERR,
                <<<HEREDOC
No recognized autoloader is available.
Please install this package with Pyrus, PEAR or Composer.

If using PEAR or Pyrus, also install PEAR2_Autoload if it's not installed.

If it's already installed and yet this message appears,
make sure the folder with PHP files is in the list of paths in php.ini's
include_path directive.

The loaded php.ini is at
```
{$phpIniLocation}
```

and the computed include_path value is
```
{$phpIncludePath}
```

If you use the default settings for Pyrus, the folder to add is probably
```
{$defaultDirPyrus}
```

and for PEAR's default settings, the folder to add is probably
```
{$defaultDirPear}
```

HEREDOC
            );
            chdir($cwd);
            exit(10);
        }
    }

    chdir($cwd);
    unset($autoloader, $cwd);
}

//PEAR2_Console_CommandLine is bundled in the archive, but may not be
//available if this package was installed with a package manager.
if (!class_exists('PEAR2\Console\CommandLine', true)) {
    fwrite(
        STDERR,
        <<<HEREDOC
PEAR2_Console_CommandLine was not found.
Please install it with the package manager used to install PEAR2_Net_RouterOS.
(i.e. Pyrus, PEAR or Composer)

HEREDOC
    );
    exit(11);
}

// Locate the data dir, in preference as:
// 1. If outside of PHAR file
// 1.1. The data folder filled at install time by Pyrus.
// 1.2. The PHP_PEAR_DATA_DIR environment variable, if available. 
// 1.3. The data folder filled at install time by PEAR.
// 2. The source layout's data folder, inside a channel/package subfolder
//    (in case this package itself is bundled by another Pyrus package)
// 3. The source layout's data folder, inside a package subfolder
//    (in case this package itself is bundled by another PEAR package)
// 4. The source layout's data folder (used with Composer or from Git).
$dataDirPaths = array();
if (!class_exists('Phar', false) || !Phar::running()) {
    $dataDirPaths[] = '@PEAR2_DATA_DIR@/@PACKAGE_CHANNEL@/@PACKAGE_NAME@';
    if (($pearDataDir = getenv('PHP_PEAR_DATA_DIR'))) {
        $dataDirPaths[] = $pearDataDir . '/@PACKAGE_NAME@';
    }
    $dataDirPaths[] = '@PEAR2_DATA_DIR@/@PACKAGE_NAME@';
}
$dataDirPaths[] = __DIR__ . '/../data/@PACKAGE_CHANNEL@/@PACKAGE_NAME@';
$dataDirPaths[] = __DIR__ . '/../data/@PACKAGE_NAME@';
$dataDirPaths[] = __DIR__ . '/../data';
$dataDir = false;
foreach ($dataDirPaths as $dataDirPath) {
    if (is_dir($dataDirPath)) {
        $dataDir = $dataDirPath;
        break;
    }
}
unset($dataDirPaths);

if (false === $dataDir) {
    fwrite(
        STDERR,
        <<<HEREDOC
Unable to find data dir.

HEREDOC
    );
    exit(11);
}
$consoleDefFile = is_file($dataDir . '/roscon.xml')
    ? $dataDir . '/roscon.xml'
    : false;
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
        "Error when parsing command line: {$e->getMessage()}\n"
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

$cColors = array(
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
        $cColors['SEND'] = new Color(
            Color\Fonts::PURPLE
        );
        $cColors['SENT'] = clone $cColors['SEND'];
        $cColors['SENT']->setStyles(Color\Styles::UNDERLINE, true);
        $cColors['RECV'] = new Color(
            Color\Fonts::GREEN
        );
        $cColors['ERR'] = new Color(
            Color\Fonts::WHITE,
            Color\Backgrounds::RED
        );
        $cColors['NOTE'] = new Color(
            Color\Fonts::BLUE,
            Color\Backgrounds::YELLOW
        );
        $cColors[''] = new Color();

        foreach ($cColors as $mode => $color) {
            $cColors[$mode] = ((string)$color) . "\033[K";
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
        $phpBin = defined('PHP_BINARY')
            ? PHP_BINARY
            : (PHP_BINDIR . DIRECTORY_SEPARATOR .
                (PHP_SAPI === 'cli' ? 'php' : 'php-cgi') .
                (stristr(PHP_OS, 'win') === false ? '' : '.exe'));
        fwrite(
            STDERR,
            <<<HEREDOC
Possible reasons:

1. You haven't enabled the API service at RouterOS or you've enabled it on a
   different TCP port.
   Make sure the "api" service at the "/ip service" menu is enabled,
   and with that same TCP port (8728 by default or 8729 for "api-ssl").

2. You've mistyped the IP and/or port.
   Check the IP and port you've specified are the ones you intended.

3. Your web server's IP is not in the list of subnets allowed to use the API.
   Check the "address" property at the "/ip service" menu.
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
   settings. The binary you need to whitelist is probably
   ```
   {$phpBin}
   ```

   If you're on a hosting plan... Typically, shared hosts block all
   outgoing connections, but it's also possible that only connections to that
   port are blocked. Try to connect to a host on a popular port (21, 80, 443,
   etc.), and if successful, change the API service port to that port. If the
   connection fails even then, ask your host to configure their firewall so as
   to allow you to make outgoing connections to the ip:port you've set the API
   service on.
   
   Note that using the library may require a different binary to be
   whitelisted, depending on how PHP is running as.

6. The router has a firewall filter/mangle/nat rule that overrides the settings
   at the "/ip service" menu.
   Usually, those are rules in the "input" chain.
   Theoretically (rarely in practice), rules in the "prerouting", "dstnat",
   "output" and/or "postrouting" chains can also cause such an effect.
   By default, many RouterBOARD devices have a filter rule in the "input" chain
   that drops any incoming connections to the router from its WAN interface,
   so if your web server is not in the LAN, the connection may be dropped
   because of that.
   If that's the case, either disable that rule (not recommended), or
   explicitly whitelist the API port. You can whitelist the API port on all
   interfaces by issuing the following command from a terminal:
   ```
   /ip firewall filter add \
       place-before=[:pick [find where chain="input"] 0] \
       chain="input" action="accept" \
       protocol="tcp" dst-port=[/ip service get "api" "port"]
   ```

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
   Check the "address" property at the "/user" menu.
   If it's empty, that's not the problem for sure. If it's non-empty however,
   make sure your IP is in that list, or is at least matched as part of an
   otherwise larger subnet.

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
    $cSep = ' | ';
    $cColumns = array(
        'mode' => 4,
        'length' => 11,
        'encodedLength' => 12
    );
    $cColumns['contents'] = $cmd->options['size'] - 1//row length
            - array_sum($cColumns)
            - (3/*strlen($c_sep)*/ * count($cColumns));
    fwrite(
        STDOUT,
        implode(
            "\n",
            array(
                implode(
                    $cSep,
                    array(
                        str_pad(
                            'MODE',
                            $cColumns['mode'],
                            ' ',
                            STR_PAD_RIGHT
                        ),
                        str_pad(
                            'LENGTH',
                            $cColumns['length'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        str_pad(
                            'LENGTH',
                            $cColumns['encodedLength'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        ' CONTENTS'
                    )
                ),
                implode(
                    $cSep,
                    array(
                        str_repeat(' ', $cColumns['mode']),
                        str_pad(
                            '(decoded)',
                            $cColumns['length'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        str_pad(
                            '(encoded)',
                            $cColumns['encodedLength'],
                            ' ',
                            STR_PAD_BOTH
                        ),
                        ''
                    )
                ),
                implode(
                    '-|-',
                    array(
                        str_repeat('-', $cColumns['mode']),
                        str_repeat('-', $cColumns['length']),
                        str_repeat('-', $cColumns['encodedLength']),
                        str_repeat('-', $cColumns['contents'])
                    )
                )
            )
        ) . "\n"
    );

    $cRegexWrap = '/([^\n]{1,' . ($cColumns['contents']) . '})/sS';

    $printWord = function (
        $mode,
        $word,
        $msg = ''
    ) use (
        $cSep,
        $cColumns,
        $cRegexWrap,
        $cColors
    ) {
        $wordFragments = preg_split(
            $cRegexWrap,
            $word,
            null,
            PREG_SPLIT_DELIM_CAPTURE
        );
        for ($i = 0, $l = count($wordFragments); $i < $l; $i += 2) {
            unset($wordFragments[$i]);
        }
        if ('' !== $cColors['']) {
            $wordFragments = str_replace("\033", "\033[27@", $wordFragments);
        }

        $isAbnormal = 'ERR' === $mode || 'NOTE' === $mode;
        if ($isAbnormal) {
            $details = str_pad(
                $msg,
                $cColumns['length'] + $cColumns['encodedLength'] + 3,
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
                $cColumns['length'],
                ' ',
                STR_PAD_LEFT
            ) .
            $cSep .
            str_pad(
                '0x' . strtoupper($encodedLength),
                $cColumns['encodedLength'],
                ' ',
                STR_PAD_LEFT
            );
        }
        fwrite(
            STDOUT,
            $cColors[$mode] .
            str_pad($mode, $cColumns['mode'], ' ', STR_PAD_RIGHT) .
            $cColors[''] .
            "{$cSep}{$details}{$cSep}{$cColors[$mode]}" .
            implode(
                "\n{$cColors['']}" .
                str_repeat(' ', $cColumns['mode']) .
                $cSep .
                implode(
                    ($isAbnormal ? '   ' : $cSep),
                    array(
                        str_repeat(' ', $cColumns['length']),
                        str_repeat(' ', $cColumns['encodedLength'])
                    )
                ) . $cSep . $cColors[$mode],
                $wordFragments
            ) . "\n{$cColors['']}"
        );
    };
} else {
    $printWord = function ($mode, $word, $msg = '') use ($cColors) {
        if ('' !== $cColors['']) {
            $word = str_replace("\033", "\033[27@", $word);
            $msg = str_replace("\033", "\033[27@", $msg);
        }

        if ('ERR' === $mode || 'NOTE' === $mode) {
            fwrite(STDERR, "{$cColors[$mode]}-- {$msg}");
            if ('' !== $word) {
                fwrite(STDERR, ": {$word}");
            }
            fwrite(STDERR, "{$cColors['']}\n");
        } elseif ('SENT' !== $mode) {
            fwrite(STDOUT, "{$cColors[$mode]}{$word}{$cColors['']}\n");
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
                    $cSep,
                    array(
                        $cColors['SEND'] .
                        str_pad('SEND', $cColumns['mode'], ' ', STR_PAD_RIGHT)
                        . $cColors[''],
                        str_pad(
                            '<prompt>',
                            $cColumns['length'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            '<prompt>',
                            $cColumns['encodedLength'],
                            ' ',
                            STR_PAD_LEFT
                        ),
                        ''
                    )
                )
            );
        }

        fwrite(STDOUT, (string)$cColors['SEND']);

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
                        "\n{$cColors['']}" .
                        implode(
                            $cSep,
                            array(
                                str_repeat(' ', $cColumns['mode']),
                                str_repeat(' ', $cColumns['length']),
                                str_repeat(' ', $cColumns['encodedLength']),
                                ''
                            )
                        )
                        . $cColors['SEND']
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
        fwrite(STDOUT, (string)$cColors['']);

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
