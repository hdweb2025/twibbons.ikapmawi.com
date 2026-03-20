<?php
session_start();
include 'config.php';

// Simple check if user is admin
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$hp = $_SESSION['hp'];
$res = mysqli_query($conn, "SELECT is_admin FROM users WHERE nomor_hp = '$hp'");
$user = mysqli_fetch_assoc($res);

if (!$user['is_admin']) {
    echo "Akses ditolak! Anda bukan admin.";
    exit();
}

// Handle adding new event
if (isset($_POST['add_event'])) {
    $name = mysqli_real_escape_string($conn, $_POST['event_name']);
    $file = $_FILES['template'];
    
    $target_dir = "uploads/templates/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $filename = time() . "_" . basename($file["name"]);
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        mysqli_query($conn, "INSERT INTO events (name, template) VALUES ('$name', '$target_file')");
        $success = "Event berhasil ditambahkan!";
    } else {
        $error = "Gagal mengunggah template.";
    }
}

// Handle deleting event
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $res = mysqli_query($conn, "SELECT template FROM events WHERE id = $id");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        unlink($row['template']);
        mysqli_query($conn, "DELETE FROM events WHERE id = $id");
        header("Location: admin.php");
        exit();
    }
}

$events = mysqli_query($conn, "SELECT * FROM events ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Twibbon IKAPMAWI</title>
    <link rel="icon" href="assets/icon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 600px;">
        <div class="header">
            <img src="assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
            <h2>Panel Admin Twibbon</h2>
        </div>
        <p>Halo, Admin <b><?php echo $_SESSION['user']; ?></b></p>
        
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 30px;">
            <h3>Tambah Event Baru</h3>
            <?php if(isset($success)) echo "<p style='color:green'>$success</p>"; ?>
            <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            <input type="text" name="event_name" placeholder="Nama Event (Contoh: Idul Fitri 2024)" required>
            <label style="display:block; text-align:left; font-size:12px;">Pilih Template PNG (Transparent):</label>
            <input type="file" name="template" accept="image/png" required>
            <button type="submit" name="add_event" class="btn-primary">Tambah Event</button>
        </form>

        <hr>

        <h3>Daftar Event</h3>
        <table style="width:100%; border-collapse: collapse; margin-top:10px;">
            <thead>
                <tr style="background:#f4f4f4;">
                    <th style="padding:10px; border:1px solid #ddd;">Nama Event</th>
                    <th style="padding:10px; border:1px solid #ddd;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($events)): ?>
                <tr>
                    <td style="padding:10px; border:1px solid #ddd;"><?php echo $row['name']; ?></td>
                    <td style="padding:10px; border:1px solid #ddd;">
                        <a href="admin.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus?')" style="color:red;">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="index.php" style="display:block; margin-top:20px;">Lihat Halaman Utama</a>
        <a href="logout.php" style="display:block; margin-top:10px; color:red; font-size:12px;">Keluar</a>
    </div>
</body>
</html>
