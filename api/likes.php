<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();

// GET - check liked status or get all liked songs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $songId = $_GET['song_id'] ?? null;
    $songIds = $_GET['song_ids'] ?? null;

    // Batch check
    if ($songIds) {
        $ids = array_filter(explode(',', $songIds));
        if (empty($ids)) {
            echo json_encode(['liked' => new \stdClass()]);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT song_id FROM liked_songs WHERE user_id = ? AND song_id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $ids));
        $likedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = in_array($id, $likedIds);
        }
        echo json_encode(['liked' => $result]);
        exit;
    }

    // Single check
    if ($songId) {
        $stmt = $db->prepare('SELECT id FROM liked_songs WHERE user_id = ? AND song_id = ?');
        $stmt->execute([$userId, $songId]);
        echo json_encode(['liked' => (bool)$stmt->fetch()]);
        exit;
    }

    // Get all liked songs
    $stmt = $db->prepare('SELECT * FROM liked_songs WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    echo json_encode(['songs' => $stmt->fetchAll()]);
    exit;
}

// POST - like a song
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $songId = $input['song_id'] ?? '';
    if (empty($songId)) {
        http_response_code(400);
        echo json_encode(['error' => 'song_id required']);
        exit;
    }

    $stmt = $db->prepare('INSERT IGNORE INTO liked_songs (user_id, song_id, video_id, title, artist, cover, filename) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $songId,
        $input['video_id'] ?? null,
        $input['title'] ?? '',
        $input['artist'] ?? '',
        $input['cover'] ?? null,
        $input['filename'] ?? null
    ]);
    echo json_encode(['success' => true, 'liked' => true]);
    exit;
}

// DELETE - unlike a song
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $songId = $input['song_id'] ?? '';
    if (empty($songId)) {
        http_response_code(400);
        echo json_encode(['error' => 'song_id required']);
        exit;
    }

    $stmt = $db->prepare('DELETE FROM liked_songs WHERE user_id = ? AND song_id = ?');
    $stmt->execute([$userId, $songId]);
    echo json_encode(['success' => true, 'liked' => false]);
    exit;
}
