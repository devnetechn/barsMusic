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

$python = getPythonCmd();
$devnull = getDevNull();

foreach ($selectedQueries as $query) {
    $safeQuery = preg_replace('/[^a-zA-Z0-9 \-]/', '', $query);
    $searchArg = escapeshellarg('ytsearch8:' . $safeQuery);
    $cmd = sprintf(
        '%s -m yt_dlp %s --flat-playlist --dump-json --no-warnings 2>%s',
        escapeshellarg($python),
        $searchArg,
        $devnull
    );
    $output = [];
    exec($cmd, $output);

    foreach ($output as $line) {
        $d = json_decode($line, true);
        if (!$d || empty($d['id'])) continue;

        $dur = $d['duration'] ?? null;
        if ($dur !== null && $dur > 420) continue;

        if (in_array($d['id'], $seenIds)) continue;

        $title = $d['title'] ?? 'Unknown';
        if (preg_match('/(nonstop|compilation|playlist|mix |1 hour|medley|mashup|top \d+|best of)/i', $title)) continue;

        $seenIds[] = $d['id'];
        $m = $dur ? floor($dur / 60) : 0;
        $s = $dur ? $dur % 60 : 0;
        $results[] = [
            'videoId' => $d['id'],
            'title' => $title,
            'author' => $d['channel'] ?? $d['uploader'] ?? 'Unknown',
            'duration' => $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT),
            'durationSeconds' => $dur,
            'thumbnail' => 'https://i.ytimg.com/vi/' . $d['id'] . '/hqdefault.jpg',
            'viewCount' => formatViews($d['view_count'] ?? 0)
        ];
        if (count($results) >= 10) break 2;
    }
}

shuffle($results);
echo json_encode(['results' => array_slice($results, 0, 10)]);

function formatViews($count) {
    if ($count >= 1000000) return round($count / 1000000, 1) . 'M views';
    if ($count >= 1000) return round($count / 1000, 1) . 'K views';
    return $count . ' views';
}
