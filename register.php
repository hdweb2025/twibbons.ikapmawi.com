<?php
include 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
    $hp = mysqli_real_escape_string($conn, $_POST['hp']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE nomor_hp = '$hp'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Nomor HP sudah terdaftar!";
    } else {
        $sql = "INSERT INTO users (nama_lengkap, tahun_alumni, nomor_hp, password) VALUES ('$nama', '$tahun', '$hp', '$pass')";
        if (mysqli_query($conn, $sql)) {
            header("Location: login.php?msg=success");
        }
    }
}
?>
<div class="container">
    <h2>Daftar Alumni</h2>
    <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="nama" placeholder="Nama Lengkap" required>
        <select name="tahun" required>
            <option value="" disabled selected>Pilih Tahun Lulus</option>
            <?php for ($i = 2027; $i >= 1950; $i--): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>
        <input type="text" name="hp" placeholder="Nomor HP/WhatsApp" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" class="btn-primary">Buat Akun</button>
    </form>
    <p class="footer-text">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>