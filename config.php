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
    template VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(255) NOT NULL,
    tahun_alumni INT NOT NULL,
    nomor_hp VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if is_admin exists, if not add it
$check_admin_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_admin'");
if (mysqli_num_rows($check_admin_column) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
}

// Set a default admin if none exists (using the first user as admin for initial setup)
$check_admin = mysqli_query($conn, "SELECT id FROM users WHERE is_admin = 1");
if (mysqli_num_rows($check_admin) == 0) {
    mysqli_query($conn, "UPDATE users SET is_admin = 1 LIMIT 1");
}
?>