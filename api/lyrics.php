<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$title = $_GET['title'] ?? '';
$artist = $_GET['artist'] ?? '';

if (empty($title)) {
    echo json_encode(['lyrics' => null, 'error' => 'Title required']);
    exit;
}

// Clean up title - remove common YouTube suffixes
$cleanTitle = preg_replace('/\s*[\(\[](official|lyric|audio|music|video|mv|hd|hq|4k|visualizer|lyrics?)[\s\w]*[\)\]]/i', '', $title);
$cleanTitle = preg_replace('/\s*[\(\[]feat\.?[^\)\]]*[\)\]]/i', '', $cleanTitle);
$cleanTitle = preg_replace('/\s*-\s*(official|lyric|audio|music|video).*$/i', '', $cleanTitle);
$cleanTitle = trim($cleanTitle);

// Clean artist
$cleanArtist = $artist;
$cleanArtist = preg_replace('/(VEVO|Official|Music|Records|Topic)$/i', '', $cleanArtist);
$cleanArtist = trim($cleanArtist);

$lyrics = null;

// Try lrclib.net first (free, no API key, has synced lyrics)
$lyrics = fetchLrclib($cleanTitle, $cleanArtist);

// Fallback: try lyrics.ovh
if (!$lyrics) {
    $lyrics = fetchLyricsOvh($cleanTitle, $cleanArtist);
}

if ($lyrics) {
    echo json_encode(['lyrics' => $lyrics, 'source' => 'lrclib']);
} else {
    echo json_encode(['lyrics' => null, 'error' => 'Lyrics not found']);
}

function fetchLrclib($title, $artist) {
    $url = 'https://lrclib.net/api/search?' . http_build_query([
        'track_name' => $title,
        'artist_name' => $artist
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['User-Agent: BarsMusic/1.0'],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data)) return null;

    // Get first result with lyrics
    foreach ($data as $item) {
        // Prefer synced lyrics
        if (!empty($item['syncedLyrics'])) {
            return [
                'text' => $item['plainLyrics'] ?? '',
                'synced' => $item['syncedLyrics'],
                'track' => $item['trackName'] ?? $title,
                'artist' => $item['artistName'] ?? $artist
            ];
        }
        if (!empty($item['plainLyrics'])) {
            return [
                'text' => $item['plainLyrics'],
                'synced' => null,
                'track' => $item['trackName'] ?? $title,
                'artist' => $item['artistName'] ?? $artist
            ];
        }
    }
    return null;
}

function fetchLyricsOvh($title, $artist) {
    if (empty($artist) || $artist === 'Unknown Artist') {
        // Try extracting artist from title (e.g., "Artist - Song")
        if (strpos($title, ' - ') !== false) {
            $parts = explode(' - ', $title, 2);
            $artist = trim($parts[0]);
            $title = trim($parts[1]);
        } else {
            return null;
        }
    }

    $url = 'https://api.lyrics.ovh/v1/' . urlencode($artist) . '/' . urlencode($title);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);
    if (empty($data['lyrics'])) return null;

    return [
        'text' => trim($data['lyrics']),
        'synced' => null,
        'track' => $title,
        'artist' => $artist
    ];
}
