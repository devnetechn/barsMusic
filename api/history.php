<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();
$method = $_SERVER['REQUEST_METHOD'];

// GET - get play history
if ($method === 'GET') {
    $limit = intval($_GET['limit'] ?? 20);
    $limit = min($limit, 50);

    $type = $_GET['type'] ?? 'recent';

    if ($type === 'top_artists') {
        // Get top artists by play count
        $stmt = $db->prepare('
            SELECT artist, COUNT(*) as play_count, MAX(cover) as cover
            FROM play_history
            WHERE user_id = ? AND artist != "Unknown Artist" AND artist != "Unknown"
            GROUP BY artist
            ORDER BY play_count DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        echo json_encode(['artists' => $stmt->fetchAll()]);
        exit;
    }

    // Recent plays (deduplicated)
    $stmt = $db->prepare('
        SELECT ph.*, s.filename
        FROM play_history ph
        LEFT JOIN songs s ON ph.song_id = s.id
        WHERE ph.user_id = ?
        ORDER BY ph.played_at DESC
        LIMIT 100
    ');
    $stmt->execute([$userId]);
    $all = $stmt->fetchAll();

    // Deduplicate by title
    $seen = [];
    $unique = [];
    foreach ($all as $row) {
        $key = strtolower($row['title']);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $row;
            if (count($unique) >= $limit) break;
        }
    }

    echo json_encode(['history' => $unique]);
    exit;
}

// POST - record a play
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $title = $input['title'] ?? '';
    $artist = $input['artist'] ?? 'Unknown Artist';
    $cover = $input['cover'] ?? null;
    $songId = $input['song_id'] ?? null;
    $videoId = $input['video_id'] ?? null;

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title required']);
        exit;
    }

    $stmt = $db->prepare('INSERT INTO play_history (user_id, song_id, video_id, title, artist, cover) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $songId, $videoId, $title, $artist, $cover]);

    echo json_encode(['success' => true]);
    exit;
}
