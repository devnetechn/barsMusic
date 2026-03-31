<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$videoId = $_GET['id'] ?? '';

if (empty($videoId) || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid videoId']);
    exit;
}

$url = 'https://www.youtube.com/watch?v=' . $videoId;

// Get best audio stream URL using yt-dlp
$python = getPythonCmd();
$devnull = getDevNull();
$cmd = sprintf(
    '%s -m yt_dlp -f bestaudio --get-url --no-warnings %s 2>%s',
    escapeshellarg($python),
    escapeshellarg($url),
    $devnull
);

$output = [];
exec($cmd, $output);

$streamUrl = trim($output[0] ?? '');

if (empty($streamUrl)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not get stream URL']);
    exit;
}

echo json_encode([
    'success' => true,
    'url' => $streamUrl,
    'videoId' => $videoId
]);
