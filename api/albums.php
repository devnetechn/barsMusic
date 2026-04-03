<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;

    // Single album with songs
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM albums WHERE id = ?');
        $stmt->execute([$id]);
        $album = $stmt->fetch();
        if (!$album) {
            http_response_code(404);
            echo json_encode(['error' => 'Album not found']);
            exit;
        }

        $stmt = $db->prepare('
            SELECT id, title, artist, filename, cover, size, video_id, created_at
            FROM songs WHERE album_id = ?
            ORDER BY title ASC
        ');
        $stmt->execute([$id]);
        $songs = $stmt->fetchAll();

        foreach ($songs as &$song) {
            $song['url'] = '/bars/music/' . $song['filename'];
        }

        $album['songs'] = $songs;
        $album['total'] = count($songs);
        echo json_encode(['album' => $album]);
        exit;
    }

    // All albums
    $stmt = $db->query('
        SELECT a.*, COUNT(s.id) as total
        FROM albums a
        LEFT JOIN songs s ON s.album_id = a.id
        GROUP BY a.id
        HAVING total > 0
        ORDER BY total DESC
    ');
    echo json_encode(['albums' => $stmt->fetchAll()]);
    exit;
}
