<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();

// GET - list user's songs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $sync = isset($_GET['sync']);

    // Admin sync: return ALL songs from all users
    if ($sync && isAdmin()) {
        $stmt = $db->query('SELECT DISTINCT title, artist, album, filename, cover, size, duration, source, video_id, created_at FROM songs ORDER BY created_at DESC');
        $songs = $stmt->fetchAll();

        foreach ($songs as &$song) {
            $song['url'] = '/bars/music/' . $song['filename'];
        }

        echo json_encode(['songs' => $songs]);
        exit;
    }

    if ($q !== '') {
        // Search with LIKE for speed
        $like = '%' . $q . '%';
        $stmt = $db->prepare('SELECT id, title, artist, album, filename, cover, size, duration, source, video_id, created_at FROM songs WHERE user_id = ? AND (title LIKE ? OR artist LIKE ?) ORDER BY created_at DESC LIMIT 20');
        $stmt->execute([$userId, $like, $like]);
    } else {
        $stmt = $db->prepare('SELECT id, title, artist, album, filename, cover, size, duration, source, video_id, created_at FROM songs WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
    }
    $songs = $stmt->fetchAll();

    // Add URL for each song
    foreach ($songs as &$song) {
        $song['url'] = '/bars/music/' . $song['filename'];
    }

    echo json_encode(['songs' => $songs]);
    exit;
}

// POST - add a song record (after upload/download)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $stmt = $db->prepare('INSERT INTO songs (user_id, title, artist, album, filename, cover, size, duration, source, video_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $input['title'] ?? 'Unknown',
        $input['artist'] ?? 'Unknown Artist',
        $input['album'] ?? 'Unknown Album',
        $input['filename'] ?? '',
        $input['cover'] ?? null,
        $input['size'] ?? 0,
        $input['duration'] ?? 0,
        $input['source'] ?? 'upload',
        $input['video_id'] ?? null
    ]);

    $songId = $db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $songId]);
    exit;
}

// DELETE - remove a song
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $songId = $input['id'] ?? 0;

    // Only delete own songs
    $stmt = $db->prepare('DELETE FROM songs WHERE id = ? AND user_id = ?');
    $stmt->execute([$songId, $userId]);

    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}
