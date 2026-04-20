<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$baseDir = __DIR__;
$zipFile = $baseDir . '/gogo.zip';

if (!file_exists($zipFile)) {
    die("ไม่พบไฟล์ ZIP: gogo.zip");
}

$unzipBin = trim((string) shell_exec('command -v unzip 2>/dev/null'));
if ($unzipBin === '') {
    die("ไม่พบคำสั่ง unzip บนเซิร์ฟเวอร์");
}

$cmd = $unzipBin . ' -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($baseDir) . ' 2>&1';
$output = shell_exec($cmd);

echo "<pre>" . htmlspecialchars($output ?? '', ENT_QUOTES, 'UTF-8') . "</pre>";
echo "แตกไฟล์เสร็จแล้ว";
