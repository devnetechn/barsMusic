<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$title = $_GET['title'] ?? '';
$artist = $_GET['artist'] ?? '';

if (empty($title) && empty($artist)) {
    echo json_encode(['results' => []]);
    exit;
}

// Build search query for related songs
$query = $artist && $artist !== 'Unknown Artist' && $artist !== 'Unknown'
    ? "$artist songs"
    : "$title related songs";

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
    'params' => 'EgIQAQ%3D%3D'
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
    echo json_encode(['results' => []]);
    exit;
}

$data = json_decode($response, true);
$results = [];
$contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];

foreach ($contents as $section) {
    $items = $section['itemSectionRenderer']['contents'] ?? [];
    foreach ($items as $item) {
        $video = $item['videoRenderer'] ?? null;
        if (!$video) continue;
        $videoId = $video['videoId'] ?? '';
        if (empty($videoId)) continue;

        $durationText = $video['lengthText']['simpleText'] ?? '';
        $parts = array_reverse(explode(':', $durationText));
        $secs = 0;
        foreach ($parts as $i => $p) $secs += intval($p) * pow(60, $i);
        if ($secs > 600 || $secs < 30) continue;

        $results[] = [
            'videoId' => $videoId,
            'title' => $video['title']['runs'][0]['text'] ?? 'Unknown',
            'author' => $video['ownerText']['runs'][0]['text'] ?? 'Unknown',
            'duration' => $durationText,
            'durationSeconds' => $secs,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg'
        ];
        if (count($results) >= 10) break 2;
    }
}

echo json_encode(['results' => $results]);
