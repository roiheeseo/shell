<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = __DIR__;
$zipFile = $baseDir . '/op.zip';

echo "<pre>";

if (!file_exists($zipFile)) {
    die("ไม่พบไฟล์ op.zip");
}

if (!is_readable($zipFile)) {
    die("อ่านไฟล์ op.zip ไม่ได้");
}

if (!is_writable($baseDir)) {
    die("โฟลเดอร์ปลายทางเขียนไม่ได้");
}

$command = 'unzip -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($baseDir) . ' 2>&1';
$output = shell_exec($command);

echo "คำสั่งที่รัน:\n$command\n\n";
echo "ผลลัพธ์:\n";
echo htmlspecialchars($output);

echo "</pre>";
?>
