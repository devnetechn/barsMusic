<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// GET - check auth
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'authenticated' => isLoggedIn(),
        'user' => getCurrentUser()
    ]);
    exit;
}

// POST - login
$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([strtolower($username)]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['display_name'] = $user['display_name'];
$_SESSION['role'] = $user['role'];

// Set persistent remember token
$token = setRememberToken($user['id']);

echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role']
    ]
]);
