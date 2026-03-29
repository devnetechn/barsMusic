<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$queries = [
    'trending pop songs 2025',
    'popular OPM songs',
    'top hits 2025',
    'best OPM love songs',
    'popular english songs',
    'viral tiktok songs 2025',
    'new OPM releases',
    'top billboard hits',
    'trending filipino music',
    'popular RnB songs'
];

shuffle($queries);
$selectedQueries = array_slice($queries, 0, 3);
$results = [];
$seenIds = [];

foreach ($selectedQueries as $query) {
    $searchResults = innertubeSearch($query, 10);
    foreach ($searchResults as $r) {
        if (in_array($r['videoId'], $seenIds)) continue;

        $titleLower = strtolower($r['title']);
        if (preg_match('/(nonstop|compilation|playlist|mix |1 hour|medley|mashup|top \d+|best of)/i', $titleLower)) continue;

        $seenIds[] = $r['videoId'];
        $results[] = $r;
        if (count($results) >= 10) break 2;
    }
}

shuffle($results);
echo json_encode(['results' => array_slice($results, 0, 10)]);

function innertubeSearch($query, $limit = 10) {
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
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

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

            if ($secs > 420 || $secs < 60) continue;

            $results[] = [
                'videoId' => $videoId,
                'title' => $video['title']['runs'][0]['text'] ?? 'Unknown',
                'author' => $video['ownerText']['runs'][0]['text'] ?? 'Unknown',
                'duration' => $durationText,
                'durationSeconds' => $secs,
                'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
                'viewCount' => $video['viewCountText']['simpleText'] ?? ''
            ];
            if (count($results) >= $limit) break 2;
        }
    }
    return $results;
}
