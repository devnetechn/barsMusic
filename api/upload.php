<?php
require_once __DIR__ . '/config.php';
requireAuth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$musicDir = __DIR__ . '/../music/';
if (!is_dir($musicDir)) {
    mkdir($musicDir, 0755, true);
}

$maxSize = 50 * 1024 * 1024;
$uploaded = [];
$errors = [];
$db = getDB();
$userId = getUserId();

if (empty($_FILES['music'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded']);
    exit;
}

$files = $_FILES['music'];
if (!is_array($files['name'])) {
    $files = [
        'name' => [$files['name']],
        'type' => [$files['type']],
        'tmp_name' => [$files['tmp_name']],
        'error' => [$files['error']],
        'size' => [$files['size']]
    ];
}

for ($i = 0; $i < count($files['name']); $i++) {
    $name = $files['name'][$i];
    $size = $files['size'][$i];
    $tmpName = $files['tmp_name'][$i];
    $error = $files['error'][$i];

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = ['file' => $name, 'error' => 'Upload error: ' . $error];
        continue;
    }
    if ($size > $maxSize) {
        $errors[] = ['file' => $name, 'error' => 'File too large (max 50MB)'];
        continue;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $title = pathinfo($name, PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);

    // Check duplicate by title for same user
    $stmt = $db->prepare('SELECT id, filename FROM songs WHERE user_id = ? AND title = ? AND source = ?');
    $stmt->execute([$userId, $title, 'upload']);
    $existing = $stmt->fetch();
    if ($existing) {
        $uploaded[] = [
            'id' => $existing['id'],
            'filename' => $existing['filename'],
            'title' => $title,
            'size' => $size,
            'url' => '/bars/music/' . $existing['filename'],
            'duplicate' => true
        ];
        continue;
    }

    $uniqueName = $safeName . '_' . uniqid() . '.' . $ext;
    $destPath = $musicDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destPath)) {
        // Save to database
        $stmt = $db->prepare('INSERT INTO songs (user_id, title, artist, album, filename, size, source) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, 'Unknown Artist', 'Unknown Album', $uniqueName, $size, 'upload']);
        $songId = $db->lastInsertId();

        $uploaded[] = [
            'id' => $songId,
            'filename' => $uniqueName,
            'title' => $title,
            'size' => $size,
            'url' => '/bars/music/' . $uniqueName
        ];
    } else {
        $errors[] = ['file' => $name, 'error' => 'Failed to save'];
    }
}

echo json_encode([
    'success' => count($uploaded) > 0,
    'uploaded' => $uploaded,
    'errors' => $errors
]);
