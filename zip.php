<?php
$baseDir = __DIR__;
$zipFile = $baseDir . '/op.zip';

if (!file_exists($zipFile)) {
    die("❌ ไม่พบไฟล์ ZIP: op.zip");
}

$zip = new ZipArchive();

if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($baseDir);
    $zip->close();

    echo "✅ แตกไฟล์สำเร็จในโฟลเดอร์: {$baseDir}";
} else {
    echo "❌ ไม่สามารถเปิดไฟล์ ZIP ได้";
}
