#!/usr/bin/env php
<?php
// Resize PNG by scale factor (default 1.5) with optional backup
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(2);
}

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];
if ($argc < 2) {
    fwrite(STDERR, "Usage: php resize_palette.php <image-path> [--factor=<float>] [--backup]\n");
    exit(2);
}

$path = $argv[1];
$factor = 1.5;
$backup = false;
for ($i = 2; $i < $argc; $i++) {
    if ($argv[$i] === '--backup') {
        $backup = true;
        continue;
    }
    if (strpos($argv[$i], '--factor=') === 0) {
        $factor = (float) substr($argv[$i], 9);
        continue;
    }
    if ($argv[$i] === '--factor' && isset($argv[$i+1])) {
        $factor = (float) $argv[++$i];
        continue;
    }
}

if (!file_exists($path)) {
    fwrite(STDERR, "Error: file not found: $path\n");
    exit(2);
}

// Ensure GD is available and PNG functions exist
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "Error: GD PNG support is required (imagecreatefrompng).\n");
    exit(3);
}

// Create backup if requested
if ($backup) {
    $info = pathinfo($path);
    $stamp = date('YmdHis');
    $backupPath = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . ".backup.$stamp." . $info['extension'];
    if (!@copy($path, $backupPath)) {
        fwrite(STDERR, "Warning: could not create backup at $backupPath\n");
    } else {
        fwrite(STDOUT, "Created backup: $backupPath\n");
    }
}

$src = imagecreatefrompng($path);
if ($src === false) {
    fwrite(STDERR, "Error: failed to open image as PNG: $path\n");
    exit(4);
}

$w = imagesx($src);
$h = imagesy($src);
$nw = max(1, (int) round($w * $factor));
$nh = max(1, (int) round($h * $factor));

$dst = imagecreatetruecolor($nw, $nh);
// preserve alpha
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);

$res = imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
if (!$res) {
    fwrite(STDERR, "Error: imagecopyresampled failed.\n");
    imagedestroy($src);
    imagedestroy($dst);
    exit(5);
}

// Save back to original path
if (!imagepng($dst, $path)) {
    fwrite(STDERR, "Error: failed to save resized PNG to $path\n");
    imagedestroy($src);
    imagedestroy($dst);
    exit(6);
}

imagedestroy($src);
imagedestroy($dst);

fwrite(STDOUT, sprintf("Resized %s (%dx%d) -> %s (%dx%d)\n", $path, $w, $h, $path, $nw, $nh));
exit(0);
