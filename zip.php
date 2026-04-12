<?php
$baseDir = __DIR__;
$zipFile = $baseDir . '/geeg8.zip';

if (!file_exists($zipFile)) {
    die("❌ ไม่พบไฟล์ ZIP: geeg8.zip");
}

$zip = new ZipArchive();

if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($baseDir);
    $zip->close();

    echo "✅ แตกไฟล์สำเร็จในโฟลเดอร์: {$baseDir}";
} else {
    echo "❌ ไม่สามารถเปิดไฟล์ ZIP ได้";
}
