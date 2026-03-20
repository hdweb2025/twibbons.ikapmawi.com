<?php
session_start();
include 'config.php';

if (isset($_POST['login'])) {
    $hp = $_POST['hp'];
    // $pass = $_POST['password']; // Password dinonaktifkan sementara

    $result = mysqli_query($conn, "SELECT * FROM users WHERE nomor_hp = '$hp'");
    $user = mysqli_fetch_assoc($result);

    // Login hanya dengan mencocokkan Nomor HP saja
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user['nama_lengkap'];
        $_SESSION['tahun'] = $user['tahun_alumni'];
        $_SESSION['hp'] = $user['nomor_hp'];
        $_SESSION['is_admin'] = $user['is_admin']; // Store admin status in session
        
        // Redirect based on redirect parameter or admin status
        if (isset($_GET['redirect'])) {
            header("Location: /" . $_GET['redirect']);
        } elseif ($user['is_admin']) {
            header("Location: /admin.php");
        } else {
            header("Location: /");
        }
    } else {
        echo "<script>alert('Nomor HP belum terdaftar!');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI" style="margin-top: 25px;">
            <h2>Login Alumni</h2>
        </div>
        <form method="GET" action="login.php" style="display:none;">
            <?php if(isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?php echo $_GET['redirect']; ?>">
            <?php endif; ?>
        </form>
        <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
            <p style="text-align: center; color: #666; font-size: 14px; margin-top: -10px; margin-bottom: 20px;">Masuk dengan Nomor HP Anda, <br> untuk memulai membuat Twibbon</p>
            <input type="text" name="hp" placeholder="Nomor HP" required>
            <!-- <input type="password" name="password" placeholder="Password" required> -->
            <button type="submit" name="login" class="btn-primary">Masuk</button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="/register.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" class="btn-link">Belum punya akun? Daftar</a>
            </div>
        </form>
    </div>
</body>
</html>