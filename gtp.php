<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

class MalwareScanner
{
    private $scanRoot = '.';

    private $suspiciousFolders = array(
        'ALFA_DATA', 'alfa_data', 'alfacgiapi', 'bypass', 'shell', 'webshell', 'backdoor'
    );

    private $suspiciousFiles = array(
        'alfa', 'c99', 'r57', 'wso', 'shell', 'backdoor', 'webshell', 'bypass', 'hack'
    );

    private $suspiciousPatterns = array(
        'eval(', 'base64_decode(', 'shell_exec(', 'system(', 'exec(', 'passthru(',
        'proc_open(', 'popen(', 'curl_exec(', 'file_get_contents(', 'file_put_contents(',
        'fopen(', 'fwrite(', 'unlink(', 'chmod(', 'chown(', 'move_uploaded_file(',
        '$_GET', '$_POST', '$_REQUEST'
    );

    // Permission ที่ใช้ซ่อนไฟล์ (execute-only / write-only / locked)
    private $suspiciousDirModes = array(
        0111, 0110, 0101, 0011,   // execute-only — ตัวคลาสสิกที่ใช้ซ่อน
        0333, 0331, 0313,         // write+execute ไม่มี read
        0000                      // ปิดสนิท
    );

    private $results = array();
    private $scannedFiles = 0;
    private $scannedFolders = 0;

    public function __construct()
    {
        echo "<!doctype html><html><head><meta charset='utf-8'>";
        echo "<title>Malware Scanner</title>";
        echo "<style>
            body{font-family:Arial;margin:20px}
            .result{border-left:5px solid #ccc;padding:10px;margin:10px 0}
            .suspicious{border-color:red}
            .medium{border-color:orange}
            pre{background:#f5f5f5;padding:10px;overflow:auto}
            button{cursor:pointer;padding:8px 12px}
        </style>";
        echo "<script type='text/javascript'>
            function toggleAll(source){
                var boxes = document.getElementsByName('delete_list[]');
                var i;
                for(i = 0; i < boxes.length; i++){
                    boxes[i].checked = source.checked;
                }
            }
        </script>";
        echo "</head><body>";
        echo "<h1>Malware Scanner</h1>";
    }

    public function scan()
    {
        $root = realpath($this->scanRoot);

        if ($root === false || !is_dir($root)) {
            echo "<p style='color:red'>Path สแกนไม่ถูกต้อง: " . htmlspecialchars($this->scanRoot, ENT_QUOTES) . "</p>";
            echo "</body></html>";
            return;
        }

        echo "<p>สแกน: <b>" . htmlspecialchars($root, ENT_QUOTES) . "</b></p>";
        $this->scanDir($root);
        $this->showResults();
        echo "</body></html>";
    }

    private function scanDir($dir)
    {
        $entries = array();
        $i = 0;
        $sf = '';
        $perms = false;
        $mode = null;

        if (!is_dir($dir)) {
            return;
        }

        $this->scannedFolders++;

        // ตรวจชื่อโฟลเดอร์
        for ($i = 0; $i < count($this->suspiciousFolders); $i++) {
            $sf = $this->suspiciousFolders[$i];
            if (stripos(basename($dir), $sf) !== false) {
                $this->results[] = array(
                    'type' => 'folder',
                    'path' => realpath($dir),
                    'issue' => "ชื่อโฟลเดอร์ต้องสงสัย (" . $sf . ")",
                    'severity' => 'high'
                );
            }
        }

        // ตรวจ permission ของโฟลเดอร์
        $perms = @fileperms($dir);
        if ($perms !== false) {
            $mode = $perms & 0777;

            // โฟลเดอร์ที่ permission เข้าข่ายซ่อนไฟล์ (0111 ฯลฯ)
            if (in_array($mode, $this->suspiciousDirModes, true)) {
                $this->results[] = array(
                    'type' => 'folder',
                    'path' => $dir,
                    'issue' => sprintf('permission ผิดปกติ (mode 0%o) — มักใช้ซ่อนไฟล์', $mode),
                    'severity' => 'high'
                );
            }
        }

        $entries = @scandir($dir);

        // ถ้า list ไม่ได้ (เช่น 0111) → รายงาน path นี้
        if ($entries === false) {
            $displayMode = ($mode !== null) ? $mode : 0;
            $this->results[] = array(
                'type' => 'folder',
                'path' => $dir,
                'issue' => sprintf('สแกนเข้าไม่ได้ — เส้นทางนี้ permission 0%o (อาจมีไฟล์ซ่อน)', $displayMode),
                'severity' => 'high'
            );
            return;
        }

        foreach ($entries as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $f;

            if (is_dir($path)) {
                $this->scanDir($path);
            } else {
                $this->scanFile($path);
            }
        }
    }

    private function scanFile($file)
    {
        $real = false;
        $name = '';
        $ext = '';
        $i = 0;
        $sf = '';
        $allowedExt = array(
            'php', 'phtml', 'php5', 'php7', 'php8', 'pht', 'phar', 'inc',
            'txt', 'ico', 'jpg', 'jpeg', 'png', 'gif'
        );

        $this->scannedFiles++;
        $real = realpath($file);

        if ($real === false) {
            return;
        }

        $name = basename($real);
        $ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));

        for ($i = 0; $i < count($this->suspiciousFiles); $i++) {
            $sf = $this->suspiciousFiles[$i];
            if (stripos($name, $sf) !== false) {
                $this->results[] = array(
                    'type' => 'file',
                    'path' => $real,
                    'issue' => "ชื่อไฟล์ต้องสงสัย (" . $sf . ")",
                    'severity' => 'high'
                );
            }
        }

        if (in_array($ext, $allowedExt)) {
            $this->scanContent($real);
        }
    }

    private function scanContent($file)
    {
        $c = @file_get_contents($file);
        $count = 0;
        $i = 0;
        $p = '';

        if ($c === false) {
            return;
        }

        for ($i = 0; $i < count($this->suspiciousPatterns); $i++) {
            $p = $this->suspiciousPatterns[$i];
            if (stripos($c, $p) !== false) {
                $count++;
            }
        }

        if ($count >= 3) {
            $this->results[] = array(
                'type' => 'file',
                'path' => $file,
                'issue' => "พบโค้ดอันตราย (" . $count . " จุด)",
                'severity' => 'high',
                'preview' => substr($c, 0, 300)
            );
        } elseif ($count > 0) {
            $this->results[] = array(
                'type' => 'file',
                'path' => $file,
                'issue' => "พบโค้ดน่าสงสัย (" . $count . " จุด)",
                'severity' => 'medium'
            );
        }
    }

    private function showResults()
    {
        $i = 0;
        $r = array();
        $class = '';
        $icon = '';

        if (empty($this->results)) {
            echo "<p style='color:green'>ไม่พบสิ่งผิดปกติ</p>";
            return;
        }

        echo "<form method='post' onsubmit='return confirm(\"ยืนยันการลบไฟล์ที่เลือก?\")'>";
        echo "<p><label><input type='checkbox' onclick='toggleAll(this)'> <b>เลือกทั้งหมด</b></label></p>";

        for ($i = 0; $i < count($this->results); $i++) {
            $r = $this->results[$i];

            if (!in_array($r['severity'], array('high', 'medium'))) {
                continue;
            }

            $class = ($r['severity'] == 'high') ? 'suspicious' : 'medium';
            $icon  = ($r['severity'] == 'high') ? '[HIGH]' : '[MEDIUM]';

            echo "<div class='result " . $class . "'>";
            echo "<input type='checkbox' name='delete_list[]' value='" . htmlspecialchars($r['path'], ENT_QUOTES) . "'> ";
            echo "<b>" . $icon . " " . htmlspecialchars($r['type'], ENT_QUOTES) . ":</b> " . htmlspecialchars($r['path'], ENT_QUOTES) . "<br>";
            echo htmlspecialchars($r['issue'], ENT_QUOTES);

            if (!empty($r['preview'])) {
                echo "<pre>" . htmlspecialchars($r['preview'], ENT_QUOTES) . "</pre>";
            }

            echo "</div>";
        }

        echo "<button type='submit' name='bulk_delete' value='1' style='background:red;color:white'>ลบไฟล์ที่เลือก</button>";
        echo "</form>";

        echo "<p>ไฟล์ที่สแกน: " . $this->scannedFiles . " | โฟลเดอร์: " . $this->scannedFolders . "</p>";
    }

    public function delete($path)
    {
        $real = realpath($path);

        if ($real === false) {
            echo "<p style='color:red'>ไม่พบไฟล์: " . htmlspecialchars($path, ENT_QUOTES) . "</p>";
            return;
        }

        if (is_dir($real)) {
            $this->deleteDir($real);
            echo "<p style='color:green'>ลบโฟลเดอร์: " . htmlspecialchars($real, ENT_QUOTES) . "</p>";
        } else {
            if (@unlink($real)) {
                echo "<p style='color:green'>ลบไฟล์: " . htmlspecialchars($real, ENT_QUOTES) . "</p>";
            } else {
                echo "<p style='color:red'>ลบไม่สำเร็จ: " . htmlspecialchars($real, ENT_QUOTES) . "</p>";
            }
        }
    }

    private function deleteDir($dir)
    {
        $entries = @scandir($dir);
        $p = '';

        if ($entries === false) {
            return;
        }

        foreach ($entries as $f) {
            if ($f == '.' || $f == '..') {
                continue;
            }

            $p = $dir . DIRECTORY_SEPARATOR . $f;

            if (is_dir($p)) {
                $this->deleteDir($p);
            } else {
                @unlink($p);
            }
        }

        @rmdir($dir);
    }
}

/* ===== Bulk delete handler ===== */
if (isset($_POST['bulk_delete']) && isset($_POST['delete_list']) && is_array($_POST['delete_list'])) {
    $s = new MalwareScanner();
    echo "<h2>กำลังลบไฟล์</h2>";

    foreach ($_POST['delete_list'] as $p) {
        $s->delete($p);
    }

    echo "<p><a href='?'>กลับไปสแกนใหม่</a></p></body></html>";
    exit;
}

/* ===== Start scan ===== */
$scanner = new MalwareScanner();
$scanner->scan();
?>
