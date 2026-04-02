<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo json_encode(['results' => []]);
    exit;
}

// Use yt-dlp search (reliable)
$python = getPythonCmd();
$devnull = getDevNull();
$safeQuery = preg_replace('/[^a-zA-Z0-9 \-]/', '', $query);
$searchArg = escapeshellarg('ytsearch15:' . $safeQuery);
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
    // Skip very long videos (compilations) but allow unknown durations
    if ($dur !== null && $dur > 600) continue;
    $m = $dur ? floor($dur / 60) : 0;
    $s = $dur ? $dur % 60 : 0;
    $results[] = [
        'videoId' => $d['id'],
        'title' => $d['title'] ?? 'Unknown',
        'author' => $d['channel'] ?? $d['uploader'] ?? 'Unknown',
        'duration' => $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT),
        'durationSeconds' => $dur,
        'thumbnail' => 'https://i.ytimg.com/vi/' . $d['id'] . '/hqdefault.jpg',
        'viewCount' => formatViews($d['view_count'] ?? 0)
    ];
    if (count($results) >= 15) break;
}

echo json_encode(['results' => $results]);

function formatViews($count) {
    if ($count >= 1000000) return round($count / 1000000, 1) . 'M views';
    if ($count >= 1000) return round($count / 1000, 1) . 'K views';
    return $count . ' views';
}
