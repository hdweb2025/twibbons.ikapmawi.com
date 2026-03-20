<?php
include 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
    $hp = mysqli_real_escape_string($conn, $_POST['hp']);
    // Password dinonaktifkan sementara, kita beri nilai default agar tidak error di database
    $pass = password_hash('default123', PASSWORD_DEFAULT);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE nomor_hp = '$hp'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Nomor HP sudah terdaftar!";
    } else {
        $sql = "INSERT INTO users (nama_lengkap, tahun_alumni, nomor_hp, password) VALUES ('$nama', '$tahun', '$hp', '$pass')";
        if (mysqli_query($conn, $sql)) {
            header("Location: login.php?msg=success");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Anda Alumni MWI? - Twibbon IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <img src="/assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
        <h2>Kamu Alumni MWI ?</h2>
        <p style="color: #666; font-size: 14px; margin-top: -10px; margin-bottom: 25px;">Luangkan Sejenak untuk Beri Tahu kami, <br> Anda Alumni MWI Tahun Berapa?</p>
    </div>
    <?php if(isset($error)) echo "<p style='color:#e74c3c; background:#fadbd8; padding:12px; border-radius:8px; text-align:center; font-size:14px; margin: 0 auto 20px auto; max-width: 400px;'>$error</p>"; ?>
    <form method="POST">
        <select name="tahun" required>
            <option value="" disabled selected>Alumni Tahun</option>
            <?php for ($i = 2027; $i >= 1950; $i--): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>
        <input type="text" name="nama" placeholder="Nama Lengkap Anda" required>
        <input type="text" name="hp" placeholder="No HP Anda" required>
        <!-- <input type="password" name="password" placeholder="Password" required> -->
        <button type="submit" class="btn-primary">Lanjut ke Twibbon</button>
    </form>
    <p class="footer-text">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
</body>
</html>