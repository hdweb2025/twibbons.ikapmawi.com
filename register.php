<?php
include 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $tahun = $_POST['tahun'];
    $hp = $_POST['hp'];
    // Password dinonaktifkan sementara, kita beri nilai default agar tidak error di database
    $pass = password_hash('default123', PASSWORD_DEFAULT);

    $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE nomor_hp = ?");
    mysqli_stmt_bind_param($stmt_check, "s", $hp);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $error = "Nomor HP sudah terdaftar!";
    } else {
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, tahun_alumni, nomor_hp, password) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "ssss", $nama, $tahun, $hp, $pass);
        if (mysqli_stmt_execute($stmt_insert)) {
            mysqli_stmt_close($stmt_insert);
            mysqli_stmt_close($stmt_check);
            header("Location: login.php?msg=success");
            exit();
        }
        mysqli_stmt_close($stmt_insert);
    }
    mysqli_stmt_close($stmt_check);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Alumni MWI - Twibbon IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a5c2e">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container" style="max-width: 460px;">
    <div class="header">
        <a href="/" style="display:inline-block;"><img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI"></a>
        <h2>Kamu Alumni MWI?</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px; margin-bottom: 20px;">Luangkan sejenak untuk beritahu kami,<br> Anda alumni MWI tahun berapa?</p>
    </div>
    <?php if(isset($error)) echo "<p style='color:#e74c3c; background:#fadbd8; padding:12px; border-radius:8px; text-align:center; font-size:14px; margin: 0 auto 20px auto; max-width: 400px;'>$error</p>"; ?>
    <form method="POST">
        <!-- Modern Searchable Alumni Year Dropdown -->
        <div class="custom-select-wrapper">
            <input type="hidden" name="tahun" id="tahunRegister" required>
            <div class="custom-select-trigger placeholder" tabindex="0" role="combobox" aria-expanded="false">
                <span class="custom-select-text">Alumni Tahun Berapa?</span>
                <svg class="custom-select-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            <div class="custom-select-dropdown">
                <div class="custom-select-search-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" class="custom-select-search-input" placeholder="Ketik tahun (contoh: 2018)..." autocomplete="off">
                </div>
                <div class="custom-select-options">
                    <?php 
                    $current_decade = null;
                    for ($i = 2027; $i >= 1950; $i--): 
                        $decade_label = 'Tahun ' . (floor($i / 10) * 10) . '-an';
                        if ($current_decade !== $decade_label):
                            $current_decade = $decade_label;
                    ?>
                        <div class="custom-select-decade"><?php echo $decade_label; ?></div>
                    <?php endif; ?>
                        <div class="custom-select-option" data-value="<?php echo $i; ?>" data-label="<?php echo $i; ?>">
                            <span>Tahun <?php echo $i; ?></span>
                            <span style="font-size: 11px; color: var(--text-muted);">Alumni</span>
                        </div>
                    <?php endfor; ?>
                    <div class="custom-select-no-results" style="display: none;">Tahun tidak ditemukan</div>
                </div>
            </div>
        </div>

        <input type="text" name="nama" placeholder="Nama Lengkap Anda" required>
        <input type="text" name="hp" placeholder="No HP Anda" required>
        <!-- <input type="password" name="password" placeholder="Password" required> -->
        <button type="submit" class="btn-primary">Lanjut ke Twibbon</button>
    </form>
    <p class="site-footer" style="margin-top: 15px;">Sudah punya akun? <a href="login.php" class="btn-link"><b>Masuk</b></a></p>
</div>

<script src="/dropdown.js"></script>
</body>
</html>