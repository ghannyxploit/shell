<?php
/**
 * Script Mandiri untuk Menambahkan Administrator WordPress
 * Letakkan file ini di root direktori WordPress (sejajar dengan wp-config.php)
 */

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wpUser  = trim($_POST['wp_user'] ?? '');
    $wpPass  = $_POST['wp_pass'] ?? '';
    $wpEmail = trim($_POST['wp_email'] ?? '');
    
    // Menggunakan direktori tempat file ini berada
    $targetWpDir = __DIR__;
    $configFile = $targetWpDir . DIRECTORY_SEPARATOR . 'wp-config.php';

    if (!file_exists($configFile)) {
        $message = "❌ Error: File wp-config.php tidak ditemukan di direktori ini. Pastikan skrip berada di root WordPress.";
    } elseif (empty($wpUser) || empty($wpPass) || empty($wpEmail)) {
        $message = "❌ Error: Semua kolom (Username, Password, Email) wajib diisi!";
    } else {
        $configContent = file_get_contents($configFile);
        
        // Ekstraksi kredensial database dari wp-config.php
        preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]/i", $configContent, $mName);
        preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]/i", $configContent, $mUser);
        preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]/i", $configContent, $mPass);
        preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]/i", $configContent, $mHost);
        preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/i", $configContent, $mPrefix);

        $dbName   = $mName[1] ?? '';
        $dbUser   = $mUser[1] ?? '';
        $dbPass   = $mPass[1] ?? '';
        $dbHost   = $mHost[1] ?? 'localhost';
        $prefix   = $mPrefix[1] ?? 'wp_';

        if (empty($dbName) || empty($dbUser)) {
            $message = "❌ Error: Gagal membaca kredensial database dari wp-config.php.";
        } else {
            try {
                $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Cek apakah username sudah terdaftar
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}users WHERE user_login = ?");
                $checkStmt->execute([$wpUser]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    $message = "❌ Error: Username WordPress '{$wpUser}' sudah terdaftar!";
                } else {
                    $now = date('Y-m-d H:i:s');
                    
                    // Masukkan ke tabel users (WordPress menggunakan MD5 untuk kompatibilitas migrasi awal atau native hash, namun wp_set_password lebih aman jika via API. Secara direct DB, WordPress mendukung MD5 atau phpass, namun injeksi standar menggunakan fungsi sandi standar WP. Di sini menggunakan MD5 sesuai kompatibilitas struktur db mentah atau hash standar).
                    // Catatan: WordPress modern menggunakan fungsi phpass, namun penambahan via database langsung sering menggunakan MD5 yang nantinya akan di-rehash otomatis oleh WP saat login pertama, atau menggunakan fungsi hash bawaan.
                    $userSql = "INSERT INTO {$prefix}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status) 
                                VALUES (?, MD5(?), ?, ?, ?, 0)";
                    $userStmt = $pdo->prepare($userSql);
                    $userStmt->execute([$wpUser, $wpPass, $wpUser, $wpEmail, $now]);
                    $userId = $pdo->lastInsertId();

                    // Tambahkan user level (10 untuk admin)
                    $meta1 = $pdo->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, '{$prefix}user_level', '10')");
                    $meta1->execute([$userId]);

                    // Tambahkan capability sebagai administrator
                    $capabilities = serialize(['administrator' => true]);
                    $meta2 = $pdo->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, '{$prefix}capabilities', ?)");
                    $meta2->execute([$userId, $capabilities]);

                    $message = "✅ Sukses! Akun Administrator WordPress '{$wpUser}' berhasil ditambahkan.";
                    $success = true;
                }
            } catch (PDOException $e) {
                $message = "❌ Koneksi Database Gagal: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>WordPress Add Admin Tool</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; padding: 50px; }
        .container { max-width: 400px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #0073aa; border: none; color: white; font-size: 16px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005177; }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { font-size: 12px; color: #d9534f; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <h2>WP Add Admin</h2>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="wp_user">Username:</label>
            <input type="text" id="wp_user" name="wp_user" required>
        </div>
        <div class="form-group">
            <label for="wp_pass">Password:</label>
            <input type="password" id="wp_pass" name="wp_pass" required>
        </div>
        <div class="form-group">
            <label for="wp_email">Gmail / Email:</label>
            <input type="email" id="wp_email" name="wp_email" required>
        </div>
        <button type="submit">Buat Akun Admin</button>
    </form>
    
    <div class="warning">
        ⚠️ <strong>Catatan Keamanan:</strong> Segera hapus file skrip ini dari server setelah selesai digunakan untuk menghindari risiko keamanan.
    </div>
</div>

</body>
</html>
