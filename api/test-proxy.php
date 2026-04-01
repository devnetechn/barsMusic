<?php
header('Content-Type: application/json');
echo json_encode([
    'file' => __FILE__,
    'dir' => __DIR__,
    'time' => date('H:i:s'),
    'ffmpeg_test' => file_exists(__DIR__ . '/../bin/ffmpeg.exe') ? 'FOUND' : 'NOT FOUND',
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
]);
