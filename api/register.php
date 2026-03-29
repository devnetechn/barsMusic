<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$displayName = trim($input['display_name'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}
if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Username must be at least 3 characters']);
    exit;
}
if (strlen($password) < 4) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 4 characters']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username: letters, numbers, underscores only']);
    exit;
}

$username = strtolower($username);
if (empty($displayName)) $displayName = $username;

$db = getDB();

$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Username already taken']);
    exit;
}

$stmt = $db->prepare('INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName, 'user']);
$userId = $db->lastInsertId();

$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;
$_SESSION['display_name'] = $displayName;
$_SESSION['role'] = 'user';

// Set persistent remember token
$token = setRememberToken($userId);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $userId,
        'username' => $username,
        'display_name' => $displayName,
        'role' => 'user'
    ]
]);
