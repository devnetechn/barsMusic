<?php
require_once __DIR__ . '/config.php';
requireAdmin(); // Admin only
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$filename = basename($input['filename'] ?? '');

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename required']);
    exit;
}

$filePath = __DIR__ . '/../music/' . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete']);
}
