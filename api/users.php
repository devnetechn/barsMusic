<?php
require_once __DIR__ . '/config.php';
requireAdmin();
header('Content-Type: application/json');

$db = getDB();

// GET - list all users
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT id, username, display_name, role, created_at, (SELECT COUNT(*) FROM songs WHERE songs.user_id = users.id) as song_count FROM users ORDER BY created_at DESC');
    echo json_encode(['users' => $stmt->fetchAll()]);
    exit;
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['id'] ?? 0;

    if ($userId == getUserId()) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete yourself']);
        exit;
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND role != ?');
    $stmt->execute([$userId, 'admin']);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}
