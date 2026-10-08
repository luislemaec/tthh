<?php
// Ping interno FastCGI: comprueba un trabajador FPM sin publicar un phpinfo().
$diagnostic = ($argv[1] ?? '') === '--diagnostic';
$script = '/fpm-ping';
if ($diagnostic) {
    $script = '/tmp/rrhh-fpm-'.bin2hex(random_bytes(8)).'.php';
    file_put_contents($script, '<?php header("Content-Type: application/json"); $s=opcache_get_status(false); echo json_encode(["sapi"=>PHP_SAPI,"php"=>PHP_VERSION,"opcache_loaded"=>extension_loaded("Zend OPcache"),"opcache_enabled"=>$s["opcache_enabled"]??false,"cached_scripts"=>$s["opcache_statistics"]["num_cached_scripts"]??0]);');
    chmod($script, 0644);
    register_shutdown_function(static fn () => unlink($script));
}
$socket = @fsockopen('127.0.0.1', 9000, $errno, $error, 2);
if (! $socket) { exit(1); }
stream_set_timeout($socket, 2);
$record = static function (int $type, string $data): string {
    return pack('CCnnCC', 1, $type, 1, strlen($data), 0, 0).$data;
};
$params = '';
foreach (['REQUEST_METHOD'=>'GET', 'SCRIPT_NAME'=>$script, 'SCRIPT_FILENAME'=>$script, 'REQUEST_URI'=>$script] as $key=>$value) {
    $params .= chr(strlen($key)).chr(strlen($value)).$key.$value;
}
fwrite($socket, $record(1, pack('nCxxxxx', 1, 0)).$record(4, $params).$record(4, '').$record(5, ''));
$output = '';
while (! feof($socket)) {
    $header = fread($socket, 8);
    if (strlen($header) !== 8) { break; }
    $info = unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved', $header);
    $body = '';
    $length = $info['length'] + $info['padding'];
    while (strlen($body) < $length) {
        $part = fread($socket, $length - strlen($body));
        if ($part === false || $part === '') { break 2; }
        $body .= $part;
    }
    if ($info['type'] === 6) { $output .= substr($body, 0, $info['length']); }
    if ($info['type'] === 3) { break; }
}
fclose($socket);
if ($diagnostic) {
    $separator = strpos($output, "\r\n\r\n");
    $data = $separator === false ? null : json_decode(substr($output, $separator + 4), true);
    echo json_encode($data, JSON_PRETTY_PRINT).PHP_EOL;
    exit(($data['sapi'] ?? '') === 'fpm-fcgi' && ($data['opcache_enabled'] ?? false) ? 0 : 1);
}
exit(str_contains($output, 'pong') ? 0 : 1);
