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

// POST - login with rate limiting
// Simple rate limit: max 10 attempts per minute per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/bars_login_' . md5($ip);
$attempts = 0;
if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true);
    if ($data && time() - $data['time'] < 60) {
        $attempts = $data['count'];
    }
}
if ($attempts >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many login attempts. Try again in 1 minute.']);
    exit;
}

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
    // Track failed attempt
    file_put_contents($rateLimitFile, json_encode(['count' => $attempts + 1, 'time' => time()]));
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
