<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);

$baseDir = __DIR__;
$zipFile = $baseDir . '/k9.zip';

echo "<pre>";
echo "BASE DIR : {$baseDir}\n";
echo "ZIP FILE : {$zipFile}\n\n";

/*
|--------------------------------------------------------------------------
| ตรวจสอบไฟล์ ZIP
|--------------------------------------------------------------------------
*/

if (!file_exists($zipFile)) {
    die("ERROR: ไม่พบไฟล์ ZIP\n");
}

if (!is_readable($zipFile)) {
    die("ERROR: อ่านไฟล์ ZIP ไม่ได้\n");
}

if (!is_writable($baseDir)) {
    die("ERROR: โฟลเดอร์ปลายทางเขียนไม่ได้\n");
}

/*
|--------------------------------------------------------------------------
| วิธีที่ 1 : ใช้ ZipArchive
|--------------------------------------------------------------------------
*/

if (class_exists('ZipArchive')) {

    echo "พบ ZipArchive\n";

    $zip = new ZipArchive();

    $result = $zip->open($zipFile);

    if ($result !== true) {
        die("ERROR: เปิด ZIP ไม่ได้ CODE = {$result}\n");
    }

    echo "เปิด ZIP สำเร็จ\n";
    echo "จำนวนไฟล์ : " . $zip->numFiles . "\n";

    $ok = $zip->extractTo($baseDir);

    if ($ok) {

        echo "แตกไฟล์สำเร็จ\n";

    } else {

        echo "ERROR: extractTo() ไม่สำเร็จ\n";

    }

    $zip->close();

} else {

    /*
    |--------------------------------------------------------------------------
    | วิธีที่ 2 : sh3llback ใช้ unzip command
    |--------------------------------------------------------------------------
    */

    echo "ไม่พบ ZipArchive\n";
    echo "กำลังลองใช้ Linux unzip command...\n";

    $command = "unzip -o " . escapeshellarg($zipFile) . " -d " . escapeshellarg($baseDir) . " 2>&1";

    exec($command, $output, $returnCode);

    echo implode("\n", $output);
    echo "\n\n";

    if ($returnCode === 0) {

        echo "แตกไฟล์สำเร็จด้วย unzip command\n";

    } else {

        echo "ERROR: unzip command ทำงานไม่สำเร็จ\n";
        echo "RETURN CODE : {$returnCode}\n";

    }
}

echo "</pre>";
