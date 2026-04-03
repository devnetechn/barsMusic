<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();
$method = $_SERVER['REQUEST_METHOD'];

// GET - list user's playlists
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    // Single playlist with songs
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            http_response_code(404);
            echo json_encode(['error' => 'Playlist not found']);
            exit;
        }

        // Get songs in playlist
        $stmt = $db->prepare('
            SELECT ps.id as item_id, ps.song_id, ps.video_id, ps.title, ps.artist, ps.cover, ps.duration, ps.is_downloaded, ps.position,
                   s.filename, s.size
            FROM playlist_songs ps
            LEFT JOIN songs s ON ps.song_id = s.id
            ORDER BY ps.position ASC, ps.id ASC
        ');
        // Oops, need to filter by playlist_id
        $stmt = $db->prepare('
            SELECT ps.id as item_id, ps.song_id, ps.video_id, ps.title, ps.artist, ps.cover, ps.duration, ps.is_downloaded, ps.position,
                   s.filename, s.size, s.title as song_title, s.artist as song_artist, s.cover as song_cover
            FROM playlist_songs ps
            LEFT JOIN songs s ON ps.song_id = s.id
            WHERE ps.playlist_id = ?
            ORDER BY ps.position ASC, ps.id ASC
        ');
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();

        // Merge song data
        foreach ($items as &$item) {
            if ($item['song_id'] && $item['song_title']) {
                $item['title'] = $item['title'] ?: $item['song_title'];
                $item['artist'] = $item['artist'] ?: $item['song_artist'];
                $item['cover'] = $item['cover'] ?: $item['song_cover'];
                $item['is_downloaded'] = 1;
            }
            $item['thumbnail'] = $item['cover'] ?: ($item['video_id'] ? 'https://i.ytimg.com/vi/' . $item['video_id'] . '/hqdefault.jpg' : null);
            unset($item['song_title'], $item['song_artist'], $item['song_cover']);
        }

        $playlist['items'] = $items;
        $playlist['total'] = count($items);
        $playlist['downloaded'] = count(array_filter($items, fn($i) => $i['is_downloaded']));

        echo json_encode(['playlist' => $playlist]);
        exit;
    }

    // All playlists
    $stmt = $db->prepare('
        SELECT p.*,
            (SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = p.id) as total,
            (SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = p.id AND is_downloaded = 1) as downloaded,
            (SELECT COALESCE(ps2.cover, CASE WHEN ps2.video_id IS NOT NULL THEN CONCAT("https://i.ytimg.com/vi/", ps2.video_id, "/hqdefault.jpg") ELSE NULL END) FROM playlist_songs ps2 WHERE ps2.playlist_id = p.id AND (ps2.cover IS NOT NULL OR ps2.video_id IS NOT NULL) ORDER BY ps2.position ASC LIMIT 1) as first_song_cover
        FROM playlists p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ');
    $stmt->execute([$userId]);
    $playlists = $stmt->fetchAll();
    // Use first_song_cover as fallback if no custom cover
    foreach ($playlists as &$pl) {
        if (empty($pl['cover']) && !empty($pl['first_song_cover'])) {
            $pl['cover'] = $pl['first_song_cover'];
        }
        unset($pl['first_song_cover']);
    }
    echo json_encode(['playlists' => $playlists]);
    exit;
}

// POST - create playlist or add item
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    // Create playlist
    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name required']);
            exit;
        }
        $cover = $input['cover'] ?? null;
        $stmt = $db->prepare('INSERT INTO playlists (user_id, name, cover) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $cover]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }

    // Update playlist cover
    if ($action === 'update_cover') {
        $playlistId = $input['playlist_id'] ?? 0;
        $cover = $input['cover'] ?? null;

        $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$playlistId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not your playlist']);
            exit;
        }

        $stmt = $db->prepare('UPDATE playlists SET cover = ? WHERE id = ?');
        $stmt->execute([$cover, $playlistId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Add item to playlist
    if ($action === 'add_item') {
        $playlistId = $input['playlist_id'] ?? 0;
        $songId = $input['song_id'] ?? null;
        $videoId = $input['video_id'] ?? null;
        $title = $input['title'] ?? '';
        $artist = $input['artist'] ?? '';
        $cover = $input['cover'] ?? null;
        $duration = $input['duration'] ?? '';
        $filename = $input['filename'] ?? null;

        // Try to resolve song_id from filename if not provided
        if (!$songId && $filename) {
            $stmt = $db->prepare('SELECT id FROM songs WHERE filename = ? LIMIT 1');
            $stmt->execute([$filename]);
            $row = $stmt->fetch();
            if ($row) $songId = $row['id'];
        }
        // Also try from video_id
        if (!$songId && $videoId) {
            $stmt = $db->prepare('SELECT id FROM songs WHERE video_id = ? LIMIT 1');
            $stmt->execute([$videoId]);
            $row = $stmt->fetch();
            if ($row) $songId = $row['id'];
        }

        // Verify playlist belongs to user
        $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$playlistId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not your playlist']);
            exit;
        }

        // Check duplicate
        if ($videoId) {
            $stmt = $db->prepare('SELECT id FROM playlist_songs WHERE playlist_id = ? AND video_id = ?');
            $stmt->execute([$playlistId, $videoId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Already in playlist']);
                exit;
            }
        }

        $isDownloaded = $songId ? 1 : 0;
        $pos = 0;
        $stmt = $db->prepare('SELECT MAX(position) as max_pos FROM playlist_songs WHERE playlist_id = ?');
        $stmt->execute([$playlistId]);
        $row = $stmt->fetch();
        $pos = ($row['max_pos'] ?? 0) + 1;

        $stmt = $db->prepare('INSERT INTO playlist_songs (playlist_id, song_id, video_id, title, artist, cover, duration, is_downloaded, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$playlistId, $songId, $videoId, $title, $artist, $cover, $duration, $isDownloaded, $pos]);

        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }

    // Download all in playlist
    if ($action === 'download_all') {
        $playlistId = $input['playlist_id'] ?? 0;

        $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$playlistId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not your playlist']);
            exit;
        }

        // Get undownloaded items
        $stmt = $db->prepare('SELECT * FROM playlist_songs WHERE playlist_id = ? AND is_downloaded = 0 AND video_id IS NOT NULL');
        $stmt->execute([$playlistId]);
        $items = $stmt->fetchAll();

        echo json_encode(['items' => $items, 'count' => count($items)]);
        exit;
    }
}

// DELETE
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'delete_playlist';

    if ($action === 'delete_playlist') {
        $id = $input['id'] ?? 0;
        $stmt = $db->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
        exit;
    }

    if ($action === 'remove_item') {
        $itemId = $input['item_id'] ?? 0;
        $stmt = $db->prepare('DELETE ps FROM playlist_songs ps JOIN playlists p ON ps.playlist_id = p.id WHERE ps.id = ? AND p.user_id = ?');
        $stmt->execute([$itemId, $userId]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
        exit;
    }
}
