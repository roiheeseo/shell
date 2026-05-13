<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
set_time_limit(0);

$baseDir = __DIR__;
$zipFile = $baseDir . '/fa.zip';

echo "<pre>";
echo "BASE DIR: {$baseDir}\n";
echo "ZIP FILE: {$zipFile}\n";

if (!class_exists('ZipArchive')) {
    die("ERROR: PHP ไม่มี ZipArchive หรือยังไม่ได้เปิด zip extension");
}

if (!file_exists($zipFile)) {
    die("ERROR: ไม่พบไฟล์ ZIP: {$zipFile}");
}

if (!is_readable($zipFile)) {
    die("ERROR: อ่านไฟล์ ZIP ไม่ได้");
}

if (!is_dir($baseDir)) {
    die("ERROR: โฟลเดอร์ปลายทางไม่มีอยู่จริง");
}

if (!is_writable($baseDir)) {
    die("ERROR: โฟลเดอร์ปลายทางเขียนไฟล์ไม่ได้");
}

$zip = new ZipArchive();
$result = $zip->open($zipFile);

if ($result !== true) {
    die("ERROR: เปิดไฟล์ ZIP ไม่ได้, code = " . $result);
}

echo "เปิด ZIP สำเร็จ\n";
echo "จำนวนไฟล์ใน ZIP: " . $zip->numFiles . "\n";

$ok = $zip->extractTo($baseDir);

if ($ok) {
    echo "แตกไฟล์สำเร็จในโฟลเดอร์: {$baseDir}\n";
} else {
    echo "ERROR: extractTo() ไม่สำเร็จ\n";
}

$zip->close();
echo "</pre>";
