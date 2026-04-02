<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo json_encode(['results' => [], 'type' => 'none']);
    exit;
}

// Fetch more results to detect artist vs title search
$results = innertubeSearch($query, 20);

if (empty($results)) {
    $results = ytdlpSearch($query, 20);
}

// Detect if this is an artist search
// If many results have the query in the author name, it's an artist search
$queryLower = strtolower(trim($query));
$artistMatches = 0;
foreach ($results as $r) {
    if (stripos($r['author'], $queryLower) !== false ||
        similar_text(strtolower($r['author']), $queryLower) / max(strlen($r['author']), strlen($queryLower)) > 0.6) {
        $artistMatches++;
    }
}

$isArtistSearch = count($results) > 0 && ($artistMatches / count($results)) >= 0.4;

if ($isArtistSearch) {
    // Artist search: show all results, sorted by views
    $limit = 20;
    $type = 'artist';
} else {
    // Title search: show top 10, ranked by relevance
    $limit = 10;
    $type = 'title';
    $queryWords = array_filter(explode(' ', $queryLower));
    usort($results, function($a, $b) use ($queryWords) {
        $scoreA = 0;
        $scoreB = 0;
        $titleA = strtolower($a['title']);
        $titleB = strtolower($b['title']);
        foreach ($queryWords as $word) {
            if (stripos($titleA, $word) !== false) $scoreA++;
            if (stripos($titleB, $word) !== false) $scoreB++;
        }
        return $scoreB - $scoreA;
    });
}

echo json_encode([
    'results' => array_slice($results, 0, $limit),
    'type' => $type
]);

function innertubeSearch($query, $limit = 20) {
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) return [];

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
            $secs = parseDuration($durationText);
            if ($secs > 600) continue;

            $viewText = $video['viewCountText']['simpleText'] ?? $video['shortViewCountText']['simpleText'] ?? '';

            $results[] = [
                'videoId' => $videoId,
                'title' => $video['title']['runs'][0]['text'] ?? 'Unknown',
                'author' => $video['ownerText']['runs'][0]['text'] ?? 'Unknown',
                'duration' => $durationText,
                'durationSeconds' => $secs,
                'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
                'viewCount' => $viewText
            ];
            if (count($results) >= $limit) break 2;
        }
    }
    return $results;
}

function ytdlpSearch($query, $limit = 20) {
    $python = getPythonCmd();
    $devnull = getDevNull();
    $safeQuery = preg_replace('/[^a-zA-Z0-9 \-]/', '', $query);
    $searchArg = escapeshellarg('ytsearch' . $limit . ':' . $safeQuery);
    $cmd = sprintf(
        '%s -m yt_dlp %s --flat-playlist --dump-json --no-warnings 2>%s',
        escapeshellarg($python),
        $searchArg,
        $devnull
    );
    $output = [];
    exec($cmd, $output);
    $results = [];
    foreach ($output as $line) {
        $d = json_decode($line, true);
        if (!$d || empty($d['id'])) continue;
        $dur = $d['duration'] ?? null;
        if ($dur !== null && $dur > 600) continue;
        $m = $dur ? floor($dur / 60) : 0;
        $s = $dur ? $dur % 60 : 0;
        $results[] = [
            'videoId' => $d['id'],
            'title' => $d['title'] ?? 'Unknown',
            'author' => $d['channel'] ?? $d['uploader'] ?? 'Unknown',
            'duration' => $dur ? $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT) : '',
            'durationSeconds' => $dur ?? 0,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $d['id'] . '/hqdefault.jpg',
            'viewCount' => formatViews($d['view_count'] ?? 0)
        ];
        if (count($results) >= $limit) break;
    }
    return $results;
}

function parseDuration($text) {
    $parts = array_reverse(explode(':', $text));
    $secs = 0;
    foreach ($parts as $i => $p) $secs += intval($p) * pow(60, $i);
    return $secs;
}

function formatViews($count) {
    if ($count >= 1000000) return round($count / 1000000, 1) . 'M views';
    if ($count >= 1000) return round($count / 1000, 1) . 'K views';
    return $count . ' views';
}
