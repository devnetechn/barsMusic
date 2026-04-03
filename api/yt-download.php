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
    $url = 'https://www.youtube.com/watch?v=' . $videoId;
    $python = getPythonCmd();

    // Build yt-dlp command - use local bin/ ffmpeg first
    $ffmpegDir = __DIR__ . '/../bin';
    if (!file_exists($ffmpegDir . '/ffmpeg.exe') && !file_exists($ffmpegDir . '/ffmpeg')) {
        // Fallback: search system installs
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $ffmpegDir = '';
        if ($isWindows) {
            $candidates = glob('C:\\Users\\*\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg*\\ffmpeg-*\\bin');
            if (!empty($candidates)) {
                $ffmpegDir = $candidates[0];
            }
        }
    }
    $ffmpegArg = (!empty($ffmpegDir) && is_dir($ffmpegDir)) ? '--ffmpeg-location ' . escapeshellarg($ffmpegDir) : '';

    $cmd = sprintf(
        '%s -m yt_dlp %s -x --audio-format mp3 --audio-quality 0 --no-playlist --no-warnings -o %s %s 2>&1',
        escapeshellarg($python),
        $ffmpegArg,
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

// Download cover image to server for faster loading
$localCover = $cover;
if ($cover && strpos($cover, 'http') === 0) {
    $coverDir = __DIR__ . '/../covers/';
    if (!is_dir($coverDir)) mkdir($coverDir, 0755, true);
    $coverFile = $videoId . '.jpg';
    $coverPath = $coverDir . $coverFile;

    if (!file_exists($coverPath)) {
        $ch = curl_init($cover);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($imgData)) {
            file_put_contents($coverPath, $imgData);
            $localCover = '/bars/covers/' . $coverFile;
        }
    } else {
        $localCover = '/bars/covers/' . $coverFile;
    }
}

// Auto-create album for artist
$albumName = $artist . ' Collection';
$albumId = null;
if ($artist && $artist !== 'Unknown Artist') {
    $stmt = $db->prepare('INSERT IGNORE INTO albums (name, artist, cover) VALUES (?, ?, ?)');
    $stmt->execute([$albumName, $artist, $localCover]);
    $stmt = $db->prepare('SELECT id FROM albums WHERE name = ? AND artist = ?');
    $stmt->execute([$albumName, $artist]);
    $row = $stmt->fetch();
    $albumId = $row ? $row['id'] : null;

    // Update album cover if better one available
    if ($albumId && $localCover) {
        $stmt = $db->prepare('UPDATE albums SET cover = ? WHERE id = ? AND (cover IS NULL OR cover = "")');
        $stmt->execute([$localCover, $albumId]);
    }
}

// Save to user's library in DB
$stmt = $db->prepare('INSERT INTO songs (user_id, title, artist, album, album_id, filename, cover, size, source, video_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, $title, $artist, $albumName, $albumId, $outputFile, $localCover, $fileSize, 'youtube', $videoId]);

echo json_encode([
    'success' => true,
    'message' => 'Downloaded',
    'filename' => $outputFile,
    'title' => $title,
    'url' => '/bars/music/' . $outputFile,
    'cover' => $localCover,
    'size' => $fileSize
]);
