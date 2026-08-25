<?php
$host = "localhost";
$user = "u573188607_twibbonikap";
$pass = "Jt0N*CD~1G!k";
$db   = "u573188607_twibbonikap";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Ensure tables exist
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    template VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if slug column exists, if not add it
$check_slug_column = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'slug'");
if (mysqli_num_rows($check_slug_column) == 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN slug VARCHAR(255) UNIQUE NOT NULL AFTER name");
}

// Check if hole columns exist, if not add them
$check_hole_columns = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'hole_x'");
if (mysqli_num_rows($check_hole_columns) == 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN hole_x INT DEFAULT 0, ADD COLUMN hole_y INT DEFAULT 0, ADD COLUMN hole_w INT DEFAULT 1080, ADD COLUMN hole_h INT DEFAULT 1080");
}

// Populate empty slugs for existing events
$empty_slugs = mysqli_query($conn, "SELECT id, name, template FROM events");
while ($row = mysqli_fetch_assoc($empty_slugs)) {
    $id = $row['id'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $row['name']), '-'));
    
    // Update slug if it's empty or doesn't match the name
    mysqli_query($conn, "UPDATE events SET slug = '$slug' WHERE id = $id AND (slug = '' OR slug IS NULL)");
    
    // Fix template path if it's missing but exists in photos
    if (!file_exists($row['template'])) {
        $possible_path = "uploads/photos/" . $row['name'] . ".png";
        if (file_exists($possible_path)) {
            mysqli_query($conn, "UPDATE events SET template = '$possible_path' WHERE id = $id");
        }
    }
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(255) NOT NULL,
    tahun_alumni INT NOT NULL,
    nomor_hp VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS event_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, event_id)
)");

// Check if is_admin exists, if not add it
$check_admin_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_admin'");
if (mysqli_num_rows($check_admin_column) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
}

// Create a default admin account if it doesn't exist
$admin_hp = '081234567890'; // Ganti dengan nomor HP admin
$admin_pass = 'admin123'; // Ganti dengan password yang kuat
$hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);

$check_admin_exist = mysqli_query($conn, "SELECT id FROM users WHERE nomor_hp = '$admin_hp'");
if (mysqli_num_rows($check_admin_exist) == 0) {
    mysqli_query($conn, "INSERT INTO users (nama_lengkap, tahun_alumni, nomor_hp, password, is_admin) VALUES ('Admin', 2000, '$admin_hp', '$hashed_pass', 1)");
}

// Auto-add Kartini 2026 event if not exists
$kartini_check = mysqli_query($conn, "SELECT id FROM events WHERE slug = 'kartini-2026'");
if (mysqli_num_rows($kartini_check) == 0) {
    mysqli_query($conn, "INSERT INTO events (name, slug, template, hole_x, hole_y, hole_w, hole_h) VALUES ('Kartini 2026', 'kartini-2026', 'uploads/templates/kartini-2026.png', 40, 220, 570, 570)");
} else {
    // Ensure coordinates are set for existing Kartini event
    mysqli_query($conn, "UPDATE events SET hole_x = 40, hole_y = 220, hole_w = 570, hole_h = 570 WHERE slug = 'kartini-2026' AND hole_w = 1080");
}

// Auto-add Idul Adha 2026 event if not exists
$idul_adha_check = mysqli_query($conn, "SELECT id FROM events WHERE slug = 'idul-adha-2026'");
if (mysqli_num_rows($idul_adha_check) == 0) {
    mysqli_query($conn, "INSERT INTO events (name, slug, template, hole_x, hole_y, hole_w, hole_h) VALUES ('Selamat Idul Adha 1447H / 2026M', 'idul-adha-2026', 'uploads/templates/idul-adha-2026.png', 0, 0, 1080, 1080)");
}

// Auto-add Maulid Nabi 2026 event if not exists
$maulid_check = mysqli_query($conn, "SELECT id FROM events WHERE slug = 'maulid-nabi-2026'");
if (mysqli_num_rows($maulid_check) == 0) {
    mysqli_query($conn, "INSERT INTO events (name, slug, template, hole_x, hole_y, hole_w, hole_h) VALUES ('Peringatan Maulid Nabi Muhammad SAW 1448 H / 2026 M', 'maulid-nabi-2026', 'uploads/templates/maulid-nabi-2026.png', 0, 0, 1080, 1080)");
}

// Create settings table for dynamic configuration
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
)");

// Insert default require_registration setting
$setting_check = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'require_registration'");
if (mysqli_num_rows($setting_check) == 0) {
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('require_registration', '0')"); // 0 = nonaktifkan (quick join)
}

// Helper function to get setting
function get_setting($conn, $key, $default = '') {
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        return $row['setting_value'];
    }
    return $default;
}
?>