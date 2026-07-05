<?php
/**
 * Proxy для подачи файлов из /uploads/
 * Используется вместо прямого доступа к файлам
 */

// Получаем имя файла
$file = isset($_GET['f']) ? basename($_GET['f']) : '';

if (!$file || !preg_match('/^art_.*\.(jpg|png|gif|webp)$/i', $file)) {
    http_response_code(400);
    die('Invalid file');
}

$filepath = __DIR__ . '/uploads/' . $file;

// Проверяем существование файла
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Определяем тип контента
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mimes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

$mime = $mimes[$ext] ?? 'application/octet-stream';

// Отправляем файл
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: public, max-age=86400');
header('Accept-Ranges: bytes');

readfile($filepath);
exit;
?>
