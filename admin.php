<?php
session_start();
include 'config.php';

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    // Jika bukan admin, tendang kembali ke halaman utama atau login
    header("Location: /");
    exit();
}

// Ambil data semua user/alumni yang sudah mendaftar (diurutkan dari yang paling baru)
$query = "SELECT nama_lengkap, tahun_alumni, nomor_hp, created_at FROM users ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Halaman Admin - Data Alumni IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container" style="max-width: 1000px;">
    <div class="header">
        <img src="/assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
        <h2>Halaman Admin</h2>
        <p style="color: #666;">Daftar Alumni yang Telah Mengisi Form</p>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lengkap</th>
                    <th>Alumni Tahun</th>
                    <th>Nomor HP</th>
                    <th>Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($row['tahun_alumni']); ?></td>
                            <td><?php echo htmlspecialchars($row['nomor_hp']); ?></td>
                            <td><?php echo date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Belum ada data alumni.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 30px; display: flex; gap: 10px;">
        <a href="/" class="btn-primary" style="text-align: center; text-decoration: none;">Ke Halaman Utama</a>
        <a href="/logout.php" class="btn-reset" style="text-align: center; text-decoration: none; padding: 12px; font-size: 16px;">Keluar</a>
    </div>
</div>
</body>
</html>