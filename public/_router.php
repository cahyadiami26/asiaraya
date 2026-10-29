<?php
// ---------------------------------------------------------------------------
// Sandbox preview router (see tools/preview/server.mjs).
// php-wasm's SAPI is single-process and does not refresh superglobals between
// runs, so the request context is provided by the bridge as JSON and the
// superglobals are rebuilt here. Nothing in this file exists in production.
// ---------------------------------------------------------------------------
$ctx = json_decode((string) @file_get_contents('/www/storage/cache/request.json'), true) ?: [];
$method = strtoupper($ctx['method'] ?? 'GET');
$uri = (string) ($ctx['uri'] ?? '/');
$queryString = (string) parse_url($uri, PHP_URL_QUERY);
$path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');

$headers = [];
foreach (($ctx['headers'] ?? []) as $name => $value) {
    $headers[strtolower((string) $name)] = $value;
}

$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD'  => $method,
    'REQUEST_URI'     => $path . ($queryString !== '' ? '?' . $queryString : ''),
    'QUERY_STRING'    => $queryString,
    'HTTP_HOST'       => $headers['host'] ?? 'localhost',
    'HTTP_USER_AGENT' => $headers['user-agent'] ?? '',
    'HTTP_COOKIE'     => $headers['cookie'] ?? '',
    'HTTP_ACCEPT'     => $headers['accept'] ?? 'text/html',
    'HTTP_X_REQUESTED_WITH' => $headers['x-requested-with'] ?? '',
    'CONTENT_TYPE'    => $headers['content-type'] ?? '',
    'REMOTE_ADDR'     => $ctx['ip'] ?? '127.0.0.1',
    'SCRIPT_NAME'     => '/index.php',
    'PHP_SELF'        => '/index.php',
    'SCRIPT_FILENAME' => '/www/public/index.php',
    'HTTPS'           => (($headers['x-forwarded-proto'] ?? '') === 'https') ? 'on' : '',
]);

$_GET = [];
if ($queryString !== '') { parse_str($queryString, $_GET); }

$_COOKIE = [];
foreach (explode(';', (string) ($headers['cookie'] ?? '')) as $pair) {
    $kv = explode('=', trim($pair), 2);
    if (count($kv) === 2 && $kv[0] !== '') { $_COOKIE[urldecode($kv[0])] = urldecode($kv[1]); }
}

$_POST = is_array($ctx['post'] ?? null) ? $ctx['post'] : [];
$_FILES = [];

// ---- session hosted by the bridge (JSON file per session id) --------------
$sessDir = '/www/storage/cache/sessions';
if (!is_dir($sessDir)) { @mkdir($sessDir, 0775, true); }
$sid = $_COOKIE['ASRAYA_HRIS'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $sid)) { $sid = bin2hex(random_bytes(16)); }
$sessionFile = $sessDir . '/sess_' . $sid . '.json';
$_SESSION = is_file($sessionFile) ? (json_decode((string) file_get_contents($sessionFile), true) ?: []) : [];
$GLOBALS['HRIS_EXTERNAL_SESSION'] = true;
$GLOBALS['HRIS_SESSION_ID'] = $sid;
$GLOBALS['HRIS_SESSION_ROTATE'] = false;

register_shutdown_function(function () use ($sessDir, $sid) {
    $finalSid = !empty($GLOBALS['HRIS_SESSION_ROTATE']) ? bin2hex(random_bytes(16)) : ($GLOBALS['HRIS_SESSION_ID'] ?? $sid);
    if (!empty($GLOBALS['HRIS_SESSION_ROTATE'])) { @unlink($sessDir . '/sess_' . $sid . '.json'); }
    @file_put_contents($sessDir . '/sess_' . $finalSid . '.json', json_encode($_SESSION ?? []));
    if (!headers_sent()) {
        header('Set-Cookie: ASRAYA_HRIS=' . $finalSid . '; Path=/; HttpOnly; SameSite=Lax');
    }
});

ob_start();
register_shutdown_function(function () {
    if (ob_get_level() > 0) { echo ob_get_clean(); }
});
require '/www/public/index.php';
