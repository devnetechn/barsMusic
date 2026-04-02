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
$selectedQueries = array_slice($queries, 0, 2);
$results = [];
$seenIds = [];

foreach ($selectedQueries as $query) {
    $searchResults = innertubeSearch($query, 8);
    foreach ($searchResults as $r) {
        if (in_array($r['videoId'], $seenIds)) continue;
        if (preg_match('/(nonstop|compilation|playlist|mix |1 hour|medley|mashup|top \d+|best of)/i', $r['title'])) continue;
        $seenIds[] = $r['videoId'];
        $results[] = $r;
        if (count($results) >= 10) break 2;
    }
}

// Fallback to yt-dlp if InnerTube returned nothing
if (empty($results)) {
    $python = getPythonCmd();
    $devnull = getDevNull();
    $safeQuery = preg_replace('/[^a-zA-Z0-9 \-]/', '', $selectedQueries[0]);
    $searchArg = escapeshellarg('ytsearch10:' . $safeQuery);
    $cmd = sprintf('%s -m yt_dlp %s --flat-playlist --dump-json --no-warnings 2>%s',
        escapeshellarg($python), $searchArg, $devnull);
    $output = [];
    exec($cmd, $output);
    foreach ($output as $line) {
        $d = json_decode($line, true);
        if (!$d || empty($d['id'])) continue;
        $dur = $d['duration'] ?? null;
        if ($dur !== null && $dur > 420) continue;
        $m = $dur ? floor($dur / 60) : 0;
        $s = $dur ? $dur % 60 : 0;
        $results[] = [
            'videoId' => $d['id'],
            'title' => $d['title'] ?? 'Unknown',
            'author' => $d['channel'] ?? $d['uploader'] ?? 'Unknown',
            'duration' => $dur ? $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT) : '',
            'durationSeconds' => $dur ?? 0,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $d['id'] . '/hqdefault.jpg',
            'viewCount' => ''
        ];
        if (count($results) >= 10) break;
    }
}

shuffle($results);
echo json_encode(['results' => array_slice($results, 0, 10)]);

function innertubeSearch($query, $limit = 10) {
    $postData = json_encode([
        'context' => [
            'client' => [
                'clientName' => 'WEB',
                'clientVersion' => '2.20260101.00.00',
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
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
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
