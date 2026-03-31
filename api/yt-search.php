<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo json_encode(['results' => []]);
    exit;
}

// Use YouTube InnerTube API - much faster than yt-dlp
$postData = json_encode([
    'context' => [
        'client' => [
            'clientName' => 'WEB',
            'clientVersion' => '2.20240101.00.00',
            'hl' => 'en',
            'gl' => 'PH'
        ]
    ],
    'query' => $query,
    'params' => 'EgIQAQ%3D%3D' // filter: videos only
]);

$ch = curl_init('https://www.youtube.com/youtubei/v1/search?prettyPrint=false');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    // Fallback to yt-dlp if InnerTube fails
    echo json_encode(['results' => ytdlpSearch($query)]);
    exit;
}

$data = json_decode($response, true);
$results = [];

// Parse InnerTube response
$contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];

foreach ($contents as $section) {
    $items = $section['itemSectionRenderer']['contents'] ?? [];
    foreach ($items as $item) {
        $video = $item['videoRenderer'] ?? null;
        if (!$video) continue;

        $videoId = $video['videoId'] ?? '';
        if (empty($videoId)) continue;

        // Parse duration
        $durationText = $video['lengthText']['simpleText'] ?? '';
        $durationSecs = parseDuration($durationText);

        // Skip long videos (>10min) and very short (<30s)
        if ($durationSecs > 600 || $durationSecs < 30) continue;

        // Parse view count
        $viewText = $video['viewCountText']['simpleText'] ?? $video['viewCountText']['runs'][0]['text'] ?? '';

        $results[] = [
            'videoId' => $videoId,
            'title' => $video['title']['runs'][0]['text'] ?? 'Unknown',
            'author' => $video['ownerText']['runs'][0]['text'] ?? 'Unknown',
            'duration' => $durationText,
            'durationSeconds' => $durationSecs,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
            'viewCount' => $viewText
        ];

        if (count($results) >= 15) break 2;
    }
}

echo json_encode(['results' => $results]);

function parseDuration($text) {
    // "3:45" or "1:02:30"
    $parts = array_reverse(explode(':', $text));
    $secs = 0;
    foreach ($parts as $i => $p) {
        $secs += intval($p) * pow(60, $i);
    }
    return $secs;
}

// Fallback if InnerTube fails
function ytdlpSearch($query) {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $python = $isWindows ? 'python' : 'python3';
    $devnull = $isWindows ? 'NUL' : '/dev/null';
    $cmd = sprintf(
        '%s -m yt_dlp "ytsearch10:%s" --flat-playlist --dump-json --no-warnings 2>%s',
        $python,
        str_replace('"', '', $query),
        $devnull
    );
    $output = [];
    exec($cmd, $output);
    $results = [];
    foreach ($output as $line) {
        $d = json_decode($line, true);
        if (!$d || empty($d['id'])) continue;
        $dur = $d['duration'] ?? 0;
        if ($dur > 480 || $dur < 30) continue;
        $m = floor($dur / 60);
        $s = $dur % 60;
        $results[] = [
            'videoId' => $d['id'],
            'title' => $d['title'] ?? 'Unknown',
            'author' => $d['channel'] ?? $d['uploader'] ?? 'Unknown',
            'duration' => $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT),
            'durationSeconds' => $dur,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $d['id'] . '/hqdefault.jpg',
            'viewCount' => formatViewsFallback($d['view_count'] ?? 0)
        ];
        if (count($results) >= 15) break;
    }
    return $results;
}

function formatViewsFallback($count) {
    if ($count >= 1000000) return round($count / 1000000, 1) . 'M views';
    if ($count >= 1000) return round($count / 1000, 1) . 'K views';
    return $count . ' views';
}
