<?php
use PEAR2\Net\RouterOS as ROS;

if (count(get_included_files()) > 1) {
    die("The pseudo server needs to run as a separate executable.");
}

ini_set('memory_limit', -1);

function hex2bin($hex, $raw = false)
{
    $result = '';
    $length = strlen($hex);
    if ($raw) {
        for ($i = 0; $i < $length; $i++) {
            $result .= str_pad(dechex(ord($hex[$i])), 2, '0', STR_PAD_LEFT);
        }
        return hex2bin($result);
    } elseif (preg_match('/^([0-9]|[A-F])+$/i', $hex)) {
        for ($i = 0; $i < $length; $i++) {
            $result .= str_pad(decbin(hexdec($hex[$i])), 4, '0', STR_PAD_LEFT);
        }
        return $result;
    } else {
        return false;
    }
}

$configName = __DIR__ . DIRECTORY_SEPARATOR . 'configuration.xml';
$configLocation = realpath($configName);
if ($configLocation === false) {
    die(
        "Configuration file not found. It needs to be in the same directory as
         this file and be called '{$configName}'."
    );
}
$config = new DOMDocument();
try {
    $config->load($configLocation);
    $configXPath = new DOMXPath($config);
    $port = $configXPath->query(
            '/phpunit/php/const[@name="PSEUDO_SERVER_PORT"][last()]/@value'
        )->item(0)->nodeValue;
} catch (Exception $e) {
    die("Failed parsing configuration.");
}

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR .
        '../src/PEAR2/Net/RouterOS/Communicator.php')
    ? : 'PEAR2/Net/RouterOS/Communicator.php';

$socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if (!is_resource($socket)) {
    die("Failed to start server...\n{$errno}: {$errstr}");
}
echo "Server started...\n";
//define('SERVER_BUFFER', 512
//    * 1024 //k
//    * 1024 //m
//);
define('PSEUDO_SERVER_CONNECTION_TIMEOUT', 2
    * 60//m
    * 60//h
);
while ($conn = @stream_socket_accept($socket, 4 * 60, $peername)) {
    //stream_set_blocking($conn, 1);
    $hostPortCombo = explode(':', $peername);
    if ($hostPortCombo[0] === '127.0.0.1') {
        echo "Connected with {$peername}...\n";
        echo "Creating temporary storage...\n";
        $requestBuffer = fopen('php://temp', 'r+b');
        stream_set_read_buffer($conn, 11);
        stream_set_timeout($conn, PSEUDO_SERVER_CONNECTION_TIMEOUT);
        echo "Receving...\n";
        $specialCommand = false;
        $num = 0;
        $rq = 1;
        do {
            $content = fread($conn, 11);
            $contentLength = strlen($content);
            if (0 !== $contentLength) {
                fwrite($requestBuffer, $content);
            }
            if (ftell($requestBuffer) > 10) {
                echo "Dealing with request {$rq}...\n";
                $rbPos = ftell($requestBuffer);
                fseek($requestBuffer, 0, SEEK_SET);
                $raw = substr(fread($requestBuffer, 11), 1);
                var_dump($raw);

                echo "Normalizing the buffer...\n";
                $remains = stream_get_contents($requestBuffer, -1, -1);
                rewind($requestBuffer);
                ftruncate($requestBuffer, 0);
                fwrite($requestBuffer, $remains);

                if (strpos($raw, 'q') === 0) {
                    echo "User requested termination.\n";
                    break;
                } elseif (strpos($raw, 'c') === 0) {
                    echo "Control byte test.\n";
                    $controlByteToSend = substr($raw, 1, 2);
                    $resLength = pack('C', hexdec($controlByteToSend));
                    $resLengthSize = strlen($resLength);
                    $sent = $lengthBytesSent = 0;
                    while ($lengthBytesSent < $resLengthSize) {
                        $lengthBytesSent += fwrite($conn,
                            substr($resLength, $lengthBytesSent));
                        $sent += $lengthBytesSent;
                        echo "{$sent} bytes in total sent for this request.\n";
                    }
                } elseif (strpos($raw, 's') === 0) {
                    echo "Request sending test.\n";
                    $nextRequestBuffer = fopen('php://temp', 'r+b');
                    $incomingRequestLength = (double) base_convert(
                            substr($raw, 1), 16, 10
                    );
                    fwrite($nextRequestBuffer, $remains);
                    fseek($requestBuffer, 0, SEEK_SET);
                    ftruncate($requestBuffer, 0);
                    fseek($nextRequestBuffer, 0, SEEK_END);

                    $lengthBytePortionLength = strlen(
                        ROS\Communicator::encodeLength(
                            $incomingRequestLength
                        )
                    );
                    $incomingBytes = $lengthBytePortionLength
                        + $incomingRequestLength;
                    $bytesReceived = ftell($nextRequestBuffer);
                    var_dump($bytesReceived);
                    while ($bytesReceived < $incomingBytes) {
                        $bytesReceivedNow = fwrite($nextRequestBuffer,
                            fread($conn,
                                min($incomingBytes, 0xFFFFF)));
                        if (0 !== $bytesReceivedNow) {
                            $bytesReceived += $bytesReceivedNow;
                            echo "{$bytesReceived} bytes received in total for this request.\n";
                        }
                    }
                    echo "Done receiving.\n";

                    $response = base_convert($bytesReceived
                        - $lengthBytePortionLength, 10, 16
                    );
                    var_dump($response);
                    $responseBytes = strlen($response);
                    $responseLengthPortion = ROS\Communicator::
                        encodeLength(
                            $responseBytes
                    );
                    $responseBytes += strlen($responseLengthPortion);
                    $rawResponse = $responseLengthPortion . $response;
                    $sentBytes = 0;
                    while ($sentBytes < $responseBytes) {
                        $sentNow = fwrite($conn,
                            substr($rawResponse, $sentBytes));
                        if (0 !== $sentNow) {
                            $sentBytes += $sentNow;
                            echo "{$sentBytes} bytes sent in total for this request.\n";
                        }
                    }
                } elseif (strpos($raw, 'r') === 0) {
                    echo "Response returning test.\n";
                    $length = (double) base_convert(substr($raw, 1), 16, 10);
                    var_dump($length);

                    $resLength = ROS\Communicator::encodeLength(
                            $length
                    );
                    $resLengthSize = strlen($resLength);
                    var_dump(hex2bin($resLength, true));
                    $sent = $lengthBytesSent = 0;
                    while ($lengthBytesSent < $resLengthSize) {
                        $lengthBytesSent += fwrite($conn,
                            substr($resLength, $lengthBytesSent));
                        $sent += $lengthBytesSent;
                        echo "{$sent} bytes in total sent for this request.\n";
                    }
                    $resLengthSent = 0;
                    while ($resLengthSent < $length) {
                        $resLengthSentNow = fwrite(
                            $conn,
                            str_pad('t', min(0xFFFFF, $length - $resLengthSent),
                                't'
                            )
                        );
                        if (0 !== $resLengthSentNow) {
                            $resLengthSent += $resLengthSentNow;
                            $sent = $resLengthSize + $resLengthSent;
                            echo "{$sent} bytes in total sent for this request.\n";
                        }
                    }
                } elseif (strpos($raw, 'i') === 0) {
                    echo "Incomplete response test.\n";
                    $length = (double) base_convert(substr($raw, 1), 16, 10);
                    var_dump($length);

                    $resLength = ROS\Communicator::encodeLength(
                            $length
                    );
                    $resLengthSize = strlen($resLength);
                    $sent = $lengthBytesSent = 0;
                    while ($lengthBytesSent < $resLengthSize) {
                        $lengthBytesSent += fwrite($conn,
                            substr($resLength, $lengthBytesSent));
                        $sent += $lengthBytesSent;
                        echo "{$sent} bytes in total sent for this request.\n";
                    }
                    fflush($conn);
                    $resLengthSent = 0;
                    while ($resLengthSent < $length - 1/* missing byte */) {
                        $resLengthSentNow = fwrite($conn,
                            str_pad('t',
                                min(0xFFFFF,
                                    $length
                                    - $resLengthSent
                                    - 1/* missing byte */
                                ), 't'
                            )
                        );
                        if (0 !== $resLengthSentNow) {
                            $resLengthSent += $resLengthSentNow;
                            $sent = $resLengthSize + $resLengthSent;
                            echo "{$sent} bytes in total sent for this request.\n";
                        }
                    }
                    break;
                } elseif (strpos($raw, 'p') === 0) {
                    echo "Premature disconnect test.\n";
                    $nextRequestBuffer = fopen('php://temp', 'r+b');
                    $incomingRequestLength = (double) base_convert(
                            substr($raw, 1), 16, 10
                    );
                    fwrite($nextRequestBuffer, $remains);
                    fseek($requestBuffer, 0, SEEK_SET);
                    ftruncate($requestBuffer, 0);
                    fseek($nextRequestBuffer, 0, SEEK_END);

                    $lengthBytePortionLength = strlen(
                        ROS\Communicator::encodeLength(
                            $incomingRequestLength
                        )
                    );
                    $incomingBytes = $lengthBytePortionLength
                        + $incomingRequestLength;
                    $bytesReceived = ftell($nextRequestBuffer);
                    var_dump($bytesReceived);
                    $incomingBytes /= 4; //Receive only 1/4 of the request.
                    while ($bytesReceived < $incomingBytes) {
                        $bytesReceivedNow = fwrite($nextRequestBuffer,
                            fread($conn,
                                min($incomingBytes, 0xFFFFF)));
                        if (0 !== $bytesReceivedNow) {
                            $bytesReceived += $bytesReceivedNow;
                            echo "{$bytesReceived} bytes received in total for this request.\n";
                        }
                    }
                    //sleep(5);
                    break;
                }
                echo "Done dealing with request {$rq}.\n";
                $rq++;
            }
            $meta = stream_get_meta_data($conn);
        } while (!$meta['timed_out']);
        echo "Terminating connection with {$peername}...\n";
        fclose($conn);
        echo "Done!\n";
        continue;
        //}
    } else {
        echo "Access attempt from {$peername}\n";
    }
}
echo "Closing server...\n";
fclose($socket);
echo "Closed";
?>
