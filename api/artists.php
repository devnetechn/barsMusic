<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();
$showAll = isAdmin(); // Admin sees all, users see own

// GET - list artists or get artist songs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $artist = $_GET['artist'] ?? null;

    // Single artist - get all songs
    if ($artist) {
        if ($showAll) {
            $stmt = $db->prepare('
                SELECT DISTINCT s.id, s.title, s.artist, s.album, s.filename, s.cover, s.size, s.video_id, s.created_at
                FROM songs s WHERE s.artist LIKE ? ORDER BY s.title ASC
            ');
            $stmt->execute(['%' . $artist . '%']);
        } else {
            $stmt = $db->prepare('
                SELECT DISTINCT s.id, s.title, s.artist, s.album, s.filename, s.cover, s.size, s.video_id, s.created_at
                FROM songs s WHERE s.artist LIKE ? AND s.user_id = ? ORDER BY s.title ASC
            ');
            $stmt->execute(['%' . $artist . '%', $userId]);
        }
        $stmt->execute(['%' . $artist . '%']);
        $songs = $stmt->fetchAll();

        // Add URL for each song
        foreach ($songs as &$song) {
            $song['url'] = '/bars/music/' . $song['filename'];
        }

        // Get cover from first song that has one
        $cover = null;
        foreach ($songs as $s) {
            if (!empty($s['cover'])) { $cover = $s['cover']; break; }
        }

        echo json_encode([
            'artist' => $artist,
            'cover' => $cover,
            'songs' => $songs,
            'total' => count($songs)
        ]);
        exit;
    }

    // All artists with song counts and covers
    if ($showAll) {
        $stmt = $db->query('
            SELECT s.artist, COUNT(DISTINCT s.id) as total,
                (SELECT s2.cover FROM songs s2 WHERE s2.artist = s.artist AND s2.cover IS NOT NULL AND s2.cover != "" LIMIT 1) as cover
            FROM songs s
            WHERE s.artist IS NOT NULL AND s.artist != "" AND s.artist != "Unknown Artist"
            GROUP BY s.artist ORDER BY total DESC
        ');
    } else {
        $stmt = $db->prepare('
            SELECT s.artist, COUNT(DISTINCT s.id) as total,
                (SELECT s2.cover FROM songs s2 WHERE s2.artist = s.artist AND s2.user_id = ? AND s2.cover IS NOT NULL AND s2.cover != "" LIMIT 1) as cover
            FROM songs s
            WHERE s.artist IS NOT NULL AND s.artist != "" AND s.artist != "Unknown Artist" AND s.user_id = ?
            GROUP BY s.artist ORDER BY total DESC
        ');
        $stmt->execute([$userId, $userId]);
    }
    $artists = $stmt->fetchAll();

    echo json_encode(['artists' => $artists]);
    exit;
}
