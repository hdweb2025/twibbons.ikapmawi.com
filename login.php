<?php
session_start();
include 'config.php';

if (isset($_POST['login'])) {
    $hp = $_POST['hp'];
    $pass = $_POST['password'];

    $result = mysqli_query($conn, "SELECT * FROM users WHERE nomor_hp = '$hp'");
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user'] = $user['nama_lengkap'];
        $_SESSION['tahun'] = $user['tahun_alumni'];
        $_SESSION['hp'] = $user['nomor_hp']; // Store HP for admin check
        
        // Redirect based on admin status
        if ($user['is_admin']) {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
    } else {
        echo "<script>alert('Nomor HP atau Password salah!');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
            <h2>Login Alumni</h2>
        </div>
        <form method="POST">
            <input type="text" name="hp" placeholder="Nomor HP" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit" name="login" class="btn-primary">Masuk</button>
        </form>
    </div>
</body>
</html>