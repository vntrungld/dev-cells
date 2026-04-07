#!/usr/bin/env php
<?php
/**
 * Sample bot for dev-cells. Uses only PHP built-ins.
 * Usage: php bot.php [port]
 */

$port = isset($argv[1]) ? (int)$argv[1] : 3000;

$DIRECTIONS = ['up', 'down', 'left', 'right'];

function manhattan(int $x1, int $y1, int $x2, int $y2): int
{
    return abs($x1 - $x2) + abs($y1 - $y2);
}

function directionToward(int $cx, int $cy, int $tx, int $ty): string
{
    $dx = $tx - $cx;
    $dy = $ty - $cy;
    if (abs($dx) >= abs($dy)) {
        return $dx > 0 ? 'right' : 'left';
    }
    return $dy > 0 ? 'down' : 'up';
}

function decide(array $cell): array
{
    global $DIRECTIONS;

    $x = $cell['x'];
    $y = $cell['y'];
    $energy = $cell['energy'];
    $vision = $cell['vision'] ?? [];
    $food = $vision['food'] ?? [];

    $nearest = null;
    $bestDist = PHP_INT_MAX;
    foreach ($food as $f) {
        $d = manhattan($x, $y, $f['x'], $f['y']);
        if ($d < $bestDist) {
            $bestDist = $d;
            $nearest = $f;
        }
    }

    if ($energy > 30) {
        $dir = $nearest
            ? directionToward($x, $y, $nearest['x'], $nearest['y'])
            : $DIRECTIONS[array_rand($DIRECTIONS)];
        return ['type' => 'clone', 'direction' => $dir];
    }

    if ($nearest) {
        return ['type' => 'move', 'direction' => directionToward($x, $y, $nearest['x'], $nearest['y'])];
    }

    return ['type' => 'move', 'direction' => $DIRECTIONS[array_rand($DIRECTIONS)]];
}

function handleRequest(string $method, string $body): array
{
    if ($method === 'OPTIONS') {
        return [204, ''];
    }

    if ($method !== 'POST') {
        return [405, ''];
    }

    $data = json_decode($body, true);
    if ($data === null) {
        return [400, json_encode(['error' => 'invalid json'])];
    }

    $actions = [];
    foreach ($data['cells'] ?? [] as $cell) {
        $actions[(string)$cell['id']] = decide($cell);
    }

    return [200, json_encode(['actions' => (object)$actions])];
}

// PHP built-in server using stream sockets
$server = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Failed to start server: $errstr ($errno)\n");
    exit(1);
}

echo "Bot running on http://localhost:$port\n";

while ($conn = stream_socket_accept($server, -1)) {
    $request = '';
    while (($line = fgets($conn)) !== false) {
        $request .= $line;
        if (trim($line) === '') break;
    }

    // Parse method
    $method = strtok($request, ' ');

    // Parse headers for Content-Length
    $contentLength = 0;
    if (preg_match('/Content-Length:\s*(\d+)/i', $request, $m)) {
        $contentLength = (int)$m[1];
    }

    // Read body
    $body = '';
    if ($contentLength > 0) {
        $body = fread($conn, $contentLength);
    }

    [$status, $responseBody] = handleRequest($method, $body);

    $statusText = match ($status) {
        200 => 'OK',
        204 => 'No Content',
        400 => 'Bad Request',
        405 => 'Method Not Allowed',
        default => 'OK',
    };

    $headers = "HTTP/1.1 $status $statusText\r\n"
        . "Access-Control-Allow-Origin: *\r\n"
        . "Access-Control-Allow-Methods: POST, OPTIONS\r\n"
        . "Access-Control-Allow-Headers: Content-Type\r\n"
        . "Content-Type: application/json\r\n"
        . "Content-Length: " . strlen($responseBody) . "\r\n"
        . "Connection: close\r\n"
        . "\r\n";

    fwrite($conn, $headers . $responseBody);
    fclose($conn);
}

fclose($server);
