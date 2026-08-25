<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Jika sudah login sebagai admin, langsung arahkan ke dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
    header("Location: /admin/dashboard");
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error_msg = 'Silahkan masukkan Nomor HP/Username dan Password!';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE (nomor_hp = ? OR nama_lengkap = ?) LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user) {
            // Cek apakah akun memiliki hak akses admin
            if ($user['is_admin'] != 1) {
                $error_msg = 'Akun ini tidak memiliki hak akses administrator!';
            } else {
                $password_ok = false;

                // Verifikasi dengan password_verify (hash bcrypt)
                if (password_verify($password, $user['password'])) {
                    $password_ok = true;
                } 
                // Fallback jika password di DB masih plaintext
                elseif ($password === $user['password']) {
                    $password_ok = true;
                    // Auto-rehash password ke bcrypt untuk keamanan
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $up_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_stmt, "si", $new_hash, $user['id']);
                    mysqli_stmt_execute($up_stmt);
                    mysqli_stmt_close($up_stmt);
                }

                if ($password_ok) {
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['user']     = $user['nama_lengkap'];
                    $_SESSION['tahun']    = $user['tahun_alumni'];
                    $_SESSION['hp']       = $user['nomor_hp'];
                    $_SESSION['is_admin'] = 1;

                    header("Location: /admin/dashboard");
                    exit();
                } else {
                    $error_msg = 'Password yang Anda masukkan salah!';
                }
            }
        } else {
            $error_msg = 'Akun administrator tidak ditemukan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Administrator - Twibbon IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a5c2e">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .login-card {
            max-width: 440px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            padding: 32px 28px;
        }
        .admin-badge-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f5e9;
            color: var(--primary-color);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .toggle-password-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-btn:hover {
            color: var(--primary-color);
        }
        .input-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
        }
        .input-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 92, 46, 0.12);
        }
        .alert-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">

<div class="login-card">
    <div style="text-align: center; margin-bottom: 22px;">
        <a href="/" style="display: inline-block;">
            <img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI" style="max-width: 150px; height: auto;">
        </a>
        <div style="margin-top: 14px;">
            <span class="admin-badge-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Portal Administrator
            </span>
        </div>
        <h2 style="font-size: 20px; font-weight: 700; color: var(--primary-color); margin-top: 4px; margin-bottom: 4px;">Masuk Admin</h2>
        <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Silakan login untuk mengelola event, media & twibbon</p>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div><?php echo htmlspecialchars($error_msg); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/login">
        <div class="input-group">
            <label for="identifier">Nomor HP / Username Admin</label>
            <input type="text" id="identifier" name="identifier" placeholder="Contoh: 081234567890 atau Admin" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" required autofocus>
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required style="padding-right: 42px;">
                <button type="button" class="toggle-password-btn" id="togglePasswordBtn" title="Tampilkan/Sembunyikan Password">
                    <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
        </div>

        <button type="submit" name="admin_login" class="btn-primary" style="width: 100%; padding: 13px; font-size: 15px; font-weight: 600; margin-top: 10px;">
            Masuk ke Dashboard →
        </button>

        <div style="text-align: center; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <a href="/" style="font-size: 13px; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali ke Halaman Utama
            </a>
        </div>
    </form>
</div>

<script>
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function () {
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                passInput.type = 'password';
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        });
    }
</script>
</body>
</html>
