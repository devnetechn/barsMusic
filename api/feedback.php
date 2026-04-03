<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

$db = getDB();
$userId = getUserId();

// GET - admin gets all feedback, user gets own
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isAdmin()) {
        $stmt = $db->query('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 50');
        $feedback = $stmt->fetchAll();
        $unread = $db->query('SELECT COUNT(*) as c FROM feedback WHERE is_read = 0')->fetch()['c'];
        echo json_encode(['feedback' => $feedback, 'unread' => (int)$unread]);
    } else {
        $stmt = $db->prepare('SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        echo json_encode(['feedback' => $stmt->fetchAll()]);
    }
    exit;
}

// POST - submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'submit';

    if ($action === 'submit') {
        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message required']);
            exit;
        }
        $user = getCurrentUser();
        $stmt = $db->prepare('INSERT INTO feedback (user_id, username, message) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $user['display_name'] ?: $user['username'], $message]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: mark as read
    if ($action === 'read' && isAdmin()) {
        $id = $input['id'] ?? 0;
        if ($id === 'all') {
            $db->exec('UPDATE feedback SET is_read = 1 WHERE is_read = 0');
        } else {
            $stmt = $db->prepare('UPDATE feedback SET is_read = 1 WHERE id = ?');
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// DELETE - admin delete feedback
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isAdmin()) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $stmt = $db->prepare('DELETE FROM feedback WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}
