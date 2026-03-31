<?php
// Make session cookie persistent (30 days)
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_path', '/');

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);

ini_set('session.cookie_samesite', $isSecure ? 'None' : 'Lax');
ini_set('session.cookie_secure', $isSecure ? 1 : 0);
session_start();

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// MySQL connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=bars_music;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    return $pdo;
}

function isLoggedIn() {
    if (!empty($_SESSION['user_id'])) return true;

    // Try to restore from remember token (sent via header or cookie)
    $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_COOKIE['bars_remember'] ?? '';
    if (!empty($token)) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, display_name, role FROM users WHERE remember_token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}

function setRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $db = getDB();
    $stmt = $db->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    $stmt->execute([$token, $userId]);
    return $token;
}

function clearRememberToken() {
    if (!empty($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Login required']);
        exit;
    }
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'display_name' => $_SESSION['display_name'],
        'role' => $_SESSION['role']
    ];
}

// Create admin if not exists
function ensureAdmin() {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute(['bars']);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare('INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)');
        $stmt->execute(['bars', password_hash('admin123', PASSWORD_DEFAULT), "Bar's", 'admin']);
    }
}
ensureAdmin();

// Python path helper - auto-detect or use full path for Windows services
function getPythonCmd() {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if (!$isWindows) return 'python3';

    // Try common Python install locations for Windows
    $paths = [
        'python',
        'C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
        'C:\\Python312\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python310\\python.exe',
    ];

    foreach ($paths as $p) {
        $out = [];
        exec(escapeshellarg($p) . ' --version 2>&1', $out, $ret);
        if ($ret === 0) return $p;
    }

    return 'python'; // fallback
}

function getDevNull() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
}
