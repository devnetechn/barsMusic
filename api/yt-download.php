<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$videoId = $input['videoId'] ?? '';
$title = $input['title'] ?? 'Unknown';
$artist = $input['artist'] ?? 'Unknown Artist';
$cover = $input['cover'] ?? null;

if (empty($videoId) || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid videoId']);
    exit;
}

$musicDir = __DIR__ . '/../music/';
if (!is_dir($musicDir)) mkdir($musicDir, 0755, true);

$db = getDB();
$userId = getUserId();

// Check if user already has this song
$stmt = $db->prepare('SELECT id, filename FROM songs WHERE user_id = ? AND video_id = ?');
$stmt->execute([$userId, $videoId]);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode([
        'success' => true,
        'message' => 'Already in your library',
        'filename' => $existing['filename'],
        'url' => '/bars/music/' . $existing['filename'],
        'size' => 0
    ]);
    exit;
}

// Check if file already exists on disk (another user downloaded it)
$safeTitle = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);
$safeTitle = substr($safeTitle, 0, 100);
$outputFile = $safeTitle . '_' . $videoId . '.mp3';
$outputPath = $musicDir . $outputFile;

if (!file_exists($outputPath)) {
    // Download from YouTube
    $ffmpegDir = 'C:\\Program Files\\Wondershare\\UniConverter 15\\DownloadRes';
    $url = 'https://www.youtube.com/watch?v=' . $videoId;
    $cmd = sprintf(
        'python -m yt_dlp --ffmpeg-location %s -x --audio-format mp3 --audio-quality 0 --no-playlist --no-warnings -o %s %s 2>&1',
        escapeshellarg($ffmpegDir),
        escapeshellarg($outputPath),
        escapeshellarg($url)
    );

    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    if (!file_exists($outputPath)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Download failed',
            'details' => implode("\n", array_slice($output, -3))
        ]);
        exit;
    }
}

$fileSize = filesize($outputPath);

// Save to user's library in DB
$stmt = $db->prepare('INSERT INTO songs (user_id, title, artist, album, filename, cover, size, source, video_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, $title, $artist, 'YouTube', $outputFile, $cover, $fileSize, 'youtube', $videoId]);

echo json_encode([
    'success' => true,
    'message' => 'Downloaded',
    'filename' => $outputFile,
    'title' => $title,
    'url' => '/bars/music/' . $outputFile,
    'size' => $fileSize
]);
