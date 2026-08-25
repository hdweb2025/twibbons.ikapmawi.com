<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Verifikasi hak akses Administrator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: /admin/login");
    exit();
}

$current_admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

// Pastikan direktori uploads/templates ada dan writable
$templates_dir = dirname(__DIR__) . '/uploads/templates/';
if (!is_dir($templates_dir)) {
    mkdir($templates_dir, 0755, true);
}

// --------------------------------------------------------------------------
// Form Handler: Tambah Event Baru
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_event'])) {
    $name   = trim($_POST['event_name'] ?? '');
    $slug   = trim($_POST['event_slug'] ?? '');
    $hole_x = intval($_POST['hole_x'] ?? 0);
    $hole_y = intval($_POST['hole_y'] ?? 0);
    $hole_w = intval($_POST['hole_w'] ?? 1080);
    $hole_h = intval($_POST['hole_h'] ?? 1080);

    if (empty($name)) {
        $error_msg = 'Nama Event wajib diisi!';
    } else {
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
        }

        // Cek keunikan slug
        $check_slug = mysqli_query($conn, "SELECT id FROM events WHERE slug = '$slug'");
        if (mysqli_num_rows($check_slug) > 0) {
            $slug = $slug . '-' . time();
        }

        // Cek file upload template
        if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Silahkan pilih file gambar template Twibbon (PNG transparan)!';
        } else {
            $file = $_FILES['template_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'png') {
                $error_msg = 'File template harus berformat PNG dengan background transparan!';
            } else {
                $clean_filename = $slug . '_' . time() . '.png';
                $dest_path      = $templates_dir . $clean_filename;
                $db_path        = 'uploads/templates/' . $clean_filename;

                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO events (name, slug, template, hole_x, hole_y, hole_w, hole_h) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "sssiiii", $name, $slug, $db_path, $hole_x, $hole_y, $hole_w, $hole_h);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_msg = "Event '<b>" . htmlspecialchars($name) . "</b>' berhasil ditambahkan!";
                    } else {
                        $error_msg = "Gagal menyimpan event ke database: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_msg = 'Gagal mengunggah file template ke server.';
                }
            }
        }
    }
}

// --------------------------------------------------------------------------
// Form Handler: Update / Edit Event
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_event'])) {
    $event_id = intval($_POST['event_id'] ?? 0);
    $name     = trim($_POST['event_name'] ?? '');
    $slug     = trim($_POST['event_slug'] ?? '');
    $hole_x   = intval($_POST['hole_x'] ?? 0);
    $hole_y   = intval($_POST['hole_y'] ?? 0);
    $hole_w   = intval($_POST['hole_w'] ?? 1080);
    $hole_h   = intval($_POST['hole_h'] ?? 1080);

    if ($event_id <= 0 || empty($name)) {
        $error_msg = 'Data event tidak valid!';
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug ?: $name), '-'));

        // Cek duplikasi slug di event lain
        $check_slug = mysqli_query($conn, "SELECT id FROM events WHERE slug = '$slug' AND id != $event_id");
        if (mysqli_num_rows($check_slug) > 0) {
            $slug = $slug . '-' . time();
        }

        // Ambil data event saat ini
        $curr_res = mysqli_query($conn, "SELECT template FROM events WHERE id = $event_id");
        $curr_event = mysqli_fetch_assoc($curr_res);
        $template_db_path = $curr_event['template'] ?? '';

        // Cek apakah ada file template baru yang diupload
        if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['template_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'png') {
                $clean_filename = $slug . '_' . time() . '.png';
                $dest_path      = $templates_dir . $clean_filename;
                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    // Hapus file lama jika ada
                    $old_file = dirname(__DIR__) . '/' . $template_db_path;
                    if (file_exists($old_file) && is_file($old_file)) {
                        @unlink($old_file);
                    }
                    $template_db_path = 'uploads/templates/' . $clean_filename;
                }
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE events SET name = ?, slug = ?, template = ?, hole_x = ?, hole_y = ?, hole_w = ?, hole_h = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssiiiii", $name, $slug, $template_db_path, $hole_x, $hole_y, $hole_w, $hole_h, $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Data Event '<b>" . htmlspecialchars($name) . "</b>' berhasil diperbarui!";
        } else {
            $error_msg = "Gagal memperbarui event: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// --------------------------------------------------------------------------
// Form Handler: Hapus Event
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_event'])) {
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($event_id > 0) {
        $res = mysqli_query($conn, "SELECT name, template FROM events WHERE id = $event_id");
        if ($ev = mysqli_fetch_assoc($res)) {
            $old_file = dirname(__DIR__) . '/' . $ev['template'];
            if (file_exists($old_file) && is_file($old_file)) {
                @unlink($old_file);
            }
            mysqli_query($conn, "DELETE FROM events WHERE id = $event_id");
            $success_msg = "Event '<b>" . htmlspecialchars($ev['name']) . "</b>' berhasil dihapus!";
        }
    }
}

// --------------------------------------------------------------------------
// Form Handler: Upload Media / Gambar Tambahan
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload_media'])) {
    if (isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'][0])) {
        $uploaded_count = 0;
        $total_files = count($_FILES['media_files']['name']);

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['media_files']['error'][$i] === UPLOAD_ERR_OK) {
                $orig_name = $_FILES['media_files']['name'][$i];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

                if (in_array($ext, $allowed)) {
                    $clean_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $orig_name);
                    $dest = $templates_dir . $clean_name;
                    if (move_uploaded_file($_FILES['media_files']['tmp_name'][$i], $dest)) {
                        $uploaded_count++;
                    }
                }
            }
        }
        if ($uploaded_count > 0) {
            $success_msg = "Berhasil mengunggah $uploaded_count file gambar!";
        } else {
            $error_msg = "Gagal mengunggah file. Pastikan format file PNG, JPG, WEBP, atau SVG.";
        }
    }
}

// --------------------------------------------------------------------------
// Form Handler: Hapus Media File
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_media'])) {
    $filename = basename($_POST['filename'] ?? '');
    if (!empty($filename)) {
        $file_path = $templates_dir . $filename;
        if (file_exists($file_path) && is_file($file_path)) {
            unlink($file_path);
            $success_msg = "File media '<b>" . htmlspecialchars($filename) . "</b>' berhasil dihapus!";
        }
    }
}

// --------------------------------------------------------------------------
// Form Handler: Simpan Pengaturan Sistem & Footer Medsos
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_settings'])) {
    $req_reg   = isset($_POST['require_registration']) && $_POST['require_registration'] === '1' ? '1' : '0';
    $fb_link   = trim($_POST['social_facebook'] ?? '');
    $ig_link   = trim($_POST['social_instagram'] ?? '');
    $yt_link   = trim($_POST['social_youtube'] ?? '');
    $tt_link   = trim($_POST['social_tiktok'] ?? '');
    $wa_link   = trim($_POST['social_whatsapp'] ?? '');

    set_setting($conn, 'require_registration', $req_reg);
    set_setting($conn, 'social_facebook', $fb_link);
    set_setting($conn, 'social_instagram', $ig_link);
    set_setting($conn, 'social_youtube', $yt_link);
    set_setting($conn, 'social_tiktok', $tt_link);
    set_setting($conn, 'social_whatsapp', $wa_link);

    $success_msg = 'Pengaturan sistem dan tautan media sosial footer berhasil diperbarui!';
}

// --------------------------------------------------------------------------
// Form Handler: Update Profil & Password Admin
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_admin'])) {
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_hp   = trim($_POST['admin_hp'] ?? '');
    $new_pass   = $_POST['new_password'] ?? '';
    $conf_pass  = $_POST['confirm_password'] ?? '';

    if (empty($admin_name) || empty($admin_hp)) {
        $error_msg = 'Nama admin dan Nomor HP tidak boleh kosong!';
    } else {
        if (!empty($new_pass)) {
            if ($new_pass !== $conf_pass) {
                $error_msg = 'Konfirmasi password baru tidak cocok!';
            } elseif (strlen($new_pass) < 6) {
                $error_msg = 'Password minimal harus 6 karakter!';
            } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, nomor_hp = ?, password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $admin_name, $admin_hp, $hash, $current_admin_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $_SESSION['user'] = $admin_name;
                $_SESSION['hp']   = $admin_hp;
                $success_msg = 'Profil dan password admin berhasil diperbarui!';
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, nomor_hp = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $admin_name, $admin_hp, $current_admin_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $_SESSION['user'] = $admin_name;
            $_SESSION['hp']   = $admin_hp;
            $success_msg = 'Profil admin berhasil diperbarui!';
        }
    }
}

// --------------------------------------------------------------------------
// Ambil Data untuk Tampilan Dashboard
// --------------------------------------------------------------------------
// 1. Total Event
$events_query = "SELECT e.*, COUNT(u.id) AS total_downloads FROM events e LEFT JOIN event_usage u ON e.id = u.event_id GROUP BY e.id ORDER BY e.created_at DESC";
$events_result = mysqli_query($conn, $events_query);
$events_list = [];
$total_events = 0;
if ($events_result) {
    while ($r = mysqli_fetch_assoc($events_result)) {
        $events_list[] = $r;
    }
    $total_events = count($events_list);
}

// 2. Total Alumni Terdaftar (Non-Admin)
$users_res = mysqli_query($conn, "SELECT id, nama_lengkap, tahun_alumni, nomor_hp, created_at FROM users WHERE is_admin = 0 ORDER BY created_at DESC");
$users_list = [];
if ($users_res) {
    while ($u = mysqli_fetch_assoc($users_res)) {
        $users_list[] = $u;
    }
}
$total_users = count($users_list);

// 3. Total Unduhan Twibbon
$usages_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM event_usage");
$total_usages = 0;
if ($row_u = mysqli_fetch_assoc($usages_res)) {
    $total_usages = intval($row_u['count']);
}

// 4. Daftar Media Files di uploads/templates/
$media_files = [];
if (is_dir($templates_dir)) {
    $scanned = scandir($templates_dir);
    foreach ($scanned as $f) {
        if ($f !== '.' && $f !== '..' && is_file($templates_dir . $f)) {
            $media_files[] = [
                'name' => $f,
                'path' => 'uploads/templates/' . $f,
                'size' => round(filesize($templates_dir . $f) / 1024, 1) . ' KB',
                'time' => date('d-m-Y H:i', filemtime($templates_dir . $f))
            ];
        }
    }
}

// 5. Data Pengaturan
$require_reg_val = get_setting($conn, 'require_registration', '0') === '1';
$soc_fb = get_setting($conn, 'social_facebook', '');
$soc_ig = get_setting($conn, 'social_instagram', '');
$soc_yt = get_setting($conn, 'social_youtube', '');
$soc_tt = get_setting($conn, 'social_tiktok', '');
$soc_wa = get_setting($conn, 'social_whatsapp', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrator - Twibbon IKAPMAWI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a5c2e">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="admin-body">

<div class="admin-shell">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI">
            <div>
                <div class="sidebar-brand-title">IKAPMAWI</div>
                <div class="sidebar-brand-sub">Admin Panel</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <button type="button" class="nav-link-btn active" data-tab="overview">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Ikhtisar & Statistik
            </button>

            <button type="button" class="nav-link-btn" data-tab="events">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Manajemen Event (<?php echo $total_events; ?>)
            </button>

            <button type="button" class="nav-link-btn" data-tab="add-event">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Tambah Event Baru
            </button>

            <button type="button" class="nav-link-btn" data-tab="media">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Galeri & Upload Media
            </button>

            <button type="button" class="nav-link-btn" data-tab="alumni">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Data Alumni (<?php echo $total_users; ?>)
            </button>

            <button type="button" class="nav-link-btn" data-tab="settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Pengaturan & Medsos
            </button>
        </nav>

        <div class="sidebar-footer">
            <a href="/" target="_blank" class="nav-link-btn" style="color: #a7f3d0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Lihat Situs Publik ↗
            </a>
            <a href="/admin/logout" class="nav-link-btn" style="color: #fca5a5; margin-top: 4px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- Main Content Body -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button type="button" class="mobile-menu-toggle" aria-label="Toggle Menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 style="font-size: 18px; font-weight: 700; color: var(--admin-primary); margin: 0;">Portal Administrator</h1>
            </div>

            <div class="topbar-actions">
                <a href="/" target="_blank" class="btn-sm-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Lihat Web
                </a>
                <div class="topbar-user">
                    <div class="user-avatar-circle">
                        <?php echo strtoupper(substr($_SESSION['user'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['user'] ?? 'Admin'); ?></span>
                        <span style="font-size: 11px; color: var(--admin-muted);">Administrator</span>
                    </div>
                </div>
                <a href="/admin/logout" class="btn-sm-danger" title="Keluar">Keluar</a>
            </div>
        </header>

        <!-- Admin Content Area -->
        <main class="admin-content">

            <?php if (!empty($success_msg)): ?>
                <div class="admin-alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <div><?php echo $success_msg; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="admin-alert alert-danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <!-- ========================================================== -->
            <!-- TAB 1: IKHTISAR & STATISTIK (OVERVIEW)                      -->
            <!-- ========================================================== -->
            <section id="overview" class="tab-panel active">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div>
                            <div class="stat-info-label">Total Event Twibbon</div>
                            <div class="stat-info-value"><?php echo $total_events; ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div>
                            <div class="stat-info-label">Total Alumni Terdaftar</div>
                            <div class="stat-info-value"><?php echo $total_users; ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-purple">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div>
                            <div class="stat-info-label">Total Twibbon Digunakan</div>
                            <div class="stat-info-value"><?php echo $total_usages; ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-gold">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </div>
                        <div>
                            <div class="stat-info-label">Mode Pendaftaran</div>
                            <div style="font-size: 15px; font-weight: 700; color: var(--admin-primary); margin-top: 4px;">
                                <?php echo $require_reg_val ? 'Wajib Registrasi' : 'Quick Join (Bebas)'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Terkini Quick View -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                            Event Twibbon Aktif
                        </h2>
                        <button type="button" class="btn-primary" style="padding: 8px 16px; font-size: 13px; width: auto;" onclick="document.querySelector('.nav-link-btn[data-tab=\'add-event\']').click();">
                            + Tambah Event Baru
                        </button>
                    </div>

                    <div class="admin-table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Preview</th>
                                    <th>Nama Event</th>
                                    <th>Slug URL</th>
                                    <th>Area Foto (Hole)</th>
                                    <th>Total Unduhan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($events_list)): ?>
                                    <?php foreach ($events_list as $ev): ?>
                                        <tr>
                                            <td style="width: 70px;">
                                                <img src="/<?php echo htmlspecialchars($ev['template']); ?>" alt="Template" style="width: 50px; height: 50px; object-fit: contain; background: #eee; border-radius: 6px; border: 1px solid var(--admin-border);">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ev['name']); ?></strong>
                                            </td>
                                            <td>
                                                <code>/<?php echo htmlspecialchars($ev['slug']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-blue">
                                                    X:<?php echo $ev['hole_x']; ?>, Y:<?php echo $ev['hole_y']; ?>, <?php echo $ev['hole_w']; ?>x<?php echo $ev['hole_h']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-green"><?php echo $ev['total_downloads']; ?> unduhan</span>
                                            </td>
                                            <td>
                                                <div class="btn-action-group">
                                                    <a href="/<?php echo htmlspecialchars($ev['slug']); ?>" target="_blank" class="btn-icon btn-icon-primary" title="Buka Halaman Twibbon">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                                    </a>
                                                    <button type="button" class="btn-icon" title="Edit Event" onclick="openEditEventModal(<?php echo htmlspecialchars(json_encode($ev)); ?>)">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--admin-muted); padding: 30px;">Belum ada event twibbon. Silahkan tambahkan event baru.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ========================================================== -->
            <!-- TAB 2: MANAJEMEN EVENT LENGKAP                              -->
            <!-- ========================================================== -->
            <section id="events" class="tab-panel">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">Daftar Semua Event Twibbon</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Kelola nama event, tautan, template bingkai, dan area foto alumni</p>
                        </div>
                        <button type="button" class="btn-primary" style="width: auto; padding: 10px 18px; font-size: 13.5px;" onclick="document.querySelector('.nav-link-btn[data-tab=\'add-event\']').click();">
                            + Tambah Event Baru
                        </button>
                    </div>

                    <div class="admin-table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Template</th>
                                    <th>Nama Event</th>
                                    <th>Slug / Tautan</th>
                                    <th>Koordinat Lubang</th>
                                    <th>Partisipan</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($events_list)): ?>
                                    <?php $no = 1; foreach ($events_list as $ev): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <a href="/<?php echo htmlspecialchars($ev['template']); ?>" target="_blank">
                                                    <img src="/<?php echo htmlspecialchars($ev['template']); ?>" alt="Template" style="width: 60px; height: 60px; object-fit: contain; background: #eee; border-radius: 8px; border: 1px solid var(--admin-border);">
                                                </a>
                                            </td>
                                            <td>
                                                <strong style="font-size: 14px; color: var(--admin-text);"><?php echo htmlspecialchars($ev['name']); ?></strong>
                                            </td>
                                            <td>
                                                <a href="/<?php echo htmlspecialchars($ev['slug']); ?>.php" target="_blank" style="color: var(--admin-primary); font-weight: 500; text-decoration: none;">
                                                    /<?php echo htmlspecialchars($ev['slug']); ?> ↗
                                                </a>
                                            </td>
                                            <td>
                                                <div style="font-size: 12px; color: var(--admin-muted);">
                                                    Posisi: <b>(<?php echo $ev['hole_x']; ?>, <?php echo $ev['hole_y']; ?>)</b><br>
                                                    Ukuran: <b><?php echo $ev['hole_w']; ?> &times; <?php echo $ev['hole_h']; ?> px</b>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-pill badge-green"><?php echo $ev['total_downloads']; ?> alumni</span>
                                            </td>
                                            <td style="font-size: 12px; color: var(--admin-muted);">
                                                <?php echo date('d M Y', strtotime($ev['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-action-group">
                                                    <a href="/<?php echo htmlspecialchars($ev['slug']); ?>" target="_blank" class="btn-icon btn-icon-primary" title="Buka Twibbon Publik">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                                    </a>
                                                    <button type="button" class="btn-icon" title="Edit Event" onclick="openEditEventModal(<?php echo htmlspecialchars(json_encode($ev)); ?>)">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus event \'<?php echo addslashes($ev['name']); ?>\'? File template dan data terkait akan ikut dihapus.');" style="display:inline;">
                                                        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                                        <button type="submit" name="action_delete_event" class="btn-icon btn-icon-danger" title="Hapus Event">
                                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--admin-muted); padding: 40px;">Belum ada event terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ========================================================== -->
            <!-- TAB 3: TAMBAH EVENT BARU (ADD EVENT)                        -->
            <!-- ========================================================== -->
            <section id="add-event" class="tab-panel">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">+ Tambah Event Twibbon Baru</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Lengkapi informasi event, upload file template PNG, dan sesuaikan posisi lubang foto</p>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" action="/admin/dashboard">
                        <div class="form-grid-2">
                            <div>
                                <div class="form-group">
                                    <label for="eventNameInput">Nama / Judul Event *</label>
                                    <input type="text" id="eventNameInput" name="event_name" class="form-control" placeholder="Contoh: Reuni Akbar IKAPMAWI 2026" required>
                                    <div class="form-help">Nama lengkap yang akan ditampilkan kepada alumni.</div>
                                </div>

                                <div class="form-group">
                                    <label for="eventSlugInput">Slug URL Event *</label>
                                    <input type="text" id="eventSlugInput" name="event_slug" class="form-control" placeholder="reuni-akbar-2026" required>
                                    <div class="form-help">URL akses publik twibbon, contoh: <code>https://twibbons.ikapmawi.com/reuni-akbar-2026</code></div>
                                </div>

                                <div class="form-group">
                                    <label>Upload File Template PNG (Transparan) *</label>
                                    <div class="dropzone-box" onclick="document.getElementById('templateFileInput').click();">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--admin-primary)" stroke-width="2" style="margin-bottom: 8px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        <div style="font-weight: 600; font-size: 14px;">Klik untuk memilih file template PNG</div>
                                        <div style="font-size: 12px; color: var(--admin-muted); margin-top: 4px;">Pastikan template berukuran 1080x1080 px dengan lubang transparan</div>
                                    </div>
                                    <input type="file" id="templateFileInput" name="template_file" accept="image/png" style="display:none;" required>
                                </div>

                                <!-- Preset Area Lubang -->
                                <div class="form-group">
                                    <label>Pilihan Cepat Posisi Lubang Foto:</label>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button type="button" class="btn-sm-outline" onclick="applyHolePreset('full')">Layar Penuh (1080x1080)</button>
                                        <button type="button" class="btn-sm-outline" onclick="applyHolePreset('kartini')">Model Kartini (Kiri Atas)</button>
                                        <button type="button" class="btn-sm-outline" onclick="applyHolePreset('center_square')">Kotak Tengah (800x800)</button>
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label for="holeXInput">Koordinat X (Posisi Horizontal)</label>
                                        <input type="number" id="holeXInput" name="hole_x" class="form-control" value="0" min="0" max="1080">
                                    </div>
                                    <div class="form-group">
                                        <label for="holeYInput">Koordinat Y (Posisi Vertikal)</label>
                                        <input type="number" id="holeYInput" name="hole_y" class="form-control" value="0" min="0" max="1080">
                                    </div>
                                    <div class="form-group">
                                        <label for="holeWInput">Lebar Lubang (Width px)</label>
                                        <input type="number" id="holeWInput" name="hole_w" class="form-control" value="1080" min="100" max="1080">
                                    </div>
                                    <div class="form-group">
                                        <label for="holeHInput">Tinggi Lubang (Height px)</label>
                                        <input type="number" id="holeHInput" name="hole_h" class="form-control" value="1080" min="100" max="1080">
                                    </div>
                                </div>
                            </div>

                            <!-- Live Visualizer Canvas -->
                            <div>
                                <label style="display:block; font-size: 13px; font-weight:600; margin-bottom: 6px;">Pratinjau Visual & Area Foto (1080 &times; 1080 px)</label>
                                <div class="hole-visualizer-container">
                                    <canvas id="holeVisualizerCanvas" class="visualizer-canvas" width="1080" height="1080" style="max-width: 320px;"></canvas>
                                    <p style="font-size: 12px; color: var(--admin-muted); margin-top: 10px;">
                                        Area biru menunjukkan lokasi foto alumni akan dipasang di bawah bingkai.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--admin-border); display: flex; gap: 12px;">
                            <button type="submit" name="action_add_event" class="btn-primary" style="width: auto; padding: 12px 28px; font-size: 14.5px;">
                                Simpan & Publikasikan Event →
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ========================================================== -->
            <!-- TAB 4: GALERI MEDIA & UPLOAD GAMBAR                         -->
            <!-- ========================================================== -->
            <section id="media" class="tab-panel">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">Galeri Media & Aset Template</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Upload file gambar template baru atau kelola aset yang sudah ada</p>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" action="/admin/dashboard" style="margin-bottom: 28px; background: #f8fafc; padding: 18px; border-radius: var(--admin-radius-sm); border: 1px solid var(--admin-border);">
                        <label style="display:block; font-weight:600; font-size:13.5px; margin-bottom:8px;">Unggah Gambar / Template Baru:</label>
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <input type="file" name="media_files[]" multiple accept="image/*" class="form-control" style="flex: 1; min-width: 240px;" required>
                            <button type="submit" name="action_upload_media" class="btn-primary" style="width: auto; padding: 11px 20px;">
                                Upload File
                            </button>
                        </div>
                    </form>

                    <!-- Media Grid -->
                    <div class="media-grid">
                        <?php if (!empty($media_files)): ?>
                            <?php foreach ($media_files as $m): ?>
                                <div class="media-item">
                                    <div class="media-preview-box">
                                        <img src="/<?php echo htmlspecialchars($m['path']); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" loading="lazy">
                                    </div>
                                    <div class="media-info">
                                        <div class="media-filename" title="<?php echo htmlspecialchars($m['name']); ?>">
                                            <?php echo htmlspecialchars($m['name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--admin-muted); margin-bottom: 8px;">
                                            <?php echo $m['size']; ?> • <?php echo $m['time']; ?>
                                        </div>
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" class="btn-sm-outline" style="flex: 1; justify-content: center; font-size: 11.5px; padding: 5px;" onclick="copyToClipboard('https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo htmlspecialchars($m['path']); ?>')">
                                                Salin Link
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Hapus file <?php echo addslashes($m['name']); ?>?');" style="margin: 0;">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($m['name']); ?>">
                                                <button type="submit" name="action_delete_media" class="btn-sm-danger" style="padding: 5px 8px;" title="Hapus File">
                                                    ✕
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--admin-muted);">
                                Belum ada file gambar yang diunggah.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ========================================================== -->
            <!-- TAB 5: DATA ALUMNI & EXPORT CSV                             -->
            <!-- ========================================================== -->
            <section id="alumni" class="tab-panel">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">Daftar Data Alumni Terdaftar</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Total alumni yang telah mengisi form atau registrasi akun</p>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <input type="text" id="searchAlumniInput" class="form-control" placeholder="Cari nama, tahun, atau no hp..." style="max-width: 260px;">
                            <button type="button" id="exportCsvBtn" class="btn-sm-outline" style="background: #166534; color: #ffffff; border-color: #166534;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Export ke CSV / Excel
                            </button>
                        </div>
                    </div>

                    <div class="admin-table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Tahun Alumni</th>
                                    <th>Nomor HP</th>
                                    <th>Waktu Terdaftar</th>
                                </tr>
                            </thead>
                            <tbody id="alumniTableBody">
                                <?php if (!empty($users_list)): ?>
                                    <?php $no = 1; foreach ($users_list as $user_row): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($user_row['nama_lengkap']); ?></strong></td>
                                            <td><span class="badge-pill badge-green">Tahun <?php echo htmlspecialchars($user_row['tahun_alumni']); ?></span></td>
                                            <td><code><?php echo htmlspecialchars($user_row['nomor_hp']); ?></code></td>
                                            <td style="font-size: 12.5px; color: var(--admin-muted);">
                                                <?php echo date('d-m-Y H:i', strtotime($user_row['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--admin-muted); padding: 30px;">Belum ada data alumni yang terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ========================================================== -->
            <!-- TAB 6: PENGATURAN SISTEM & MEDSOS FOOTER                   -->
            <!-- ========================================================== -->
            <section id="settings" class="tab-panel">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">Pengaturan Sistem & Media Sosial</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Atur mode registrasi alumni dan tautan media sosial footer situs</p>
                        </div>
                    </div>

                    <form method="POST" action="/admin/dashboard">
                        <!-- Mode Pendaftaran -->
                        <div class="form-group" style="background: #f8fafc; padding: 16px; border-radius: var(--admin-radius-sm); border: 1px solid var(--admin-border);">
                            <label style="font-size: 14px; font-weight: 700; color: var(--admin-primary); margin-bottom: 8px;">
                                Mode Pendaftaran Alumni
                            </label>
                            <div style="display: flex; gap: 18px; margin-top: 10px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="require_registration" value="0" <?php echo !$require_reg_val ? 'checked' : ''; ?>>
                                    <span><b>Nonaktif (Quick Join)</b> - Alumni cukup input Nama & Tahun</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="require_registration" value="1" <?php echo $require_reg_val ? 'checked' : ''; ?>>
                                    <span><b>Aktif (Wajib Daftar/Login)</b> - Harus menggunakan Nomor HP</span>
                                </label>
                            </div>
                        </div>

                        <!-- Footer Link Medsos -->
                        <h3 style="font-size: 15px; font-weight: 700; color: var(--admin-primary); margin: 24px 0 12px 0;">
                            Tautan Media Sosial Footer (Kosongkan jika tidak ditampilkan)
                        </h3>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="social_facebook">Facebook URL</label>
                                <input type="url" id="social_facebook" name="social_facebook" class="form-control" placeholder="https://facebook.com/ikapmawi..." value="<?php echo htmlspecialchars($soc_fb); ?>">
                                <div class="form-help">Link profil / fanpage Facebook IKAPMAWI</div>
                            </div>

                            <div class="form-group">
                                <label for="social_instagram">Instagram URL</label>
                                <input type="url" id="social_instagram" name="social_instagram" class="form-control" placeholder="https://instagram.com/ikapmawi..." value="<?php echo htmlspecialchars($soc_ig); ?>">
                                <div class="form-help">Link profil Instagram resmi IKAPMAWI</div>
                            </div>

                            <div class="form-group">
                                <label for="social_youtube">YouTube Channel URL</label>
                                <input type="url" id="social_youtube" name="social_youtube" class="form-control" placeholder="https://youtube.com/@ikapmawi..." value="<?php echo htmlspecialchars($soc_yt); ?>">
                                <div class="form-help">Link channel YouTube IKAPMAWI</div>
                            </div>

                            <div class="form-group">
                                <label for="social_tiktok">TikTok URL</label>
                                <input type="url" id="social_tiktok" name="social_tiktok" class="form-control" placeholder="https://tiktok.com/@ikapmawi..." value="<?php echo htmlspecialchars($soc_tt); ?>">
                                <div class="form-help">Link akun TikTok IKAPMAWI (opsional)</div>
                            </div>

                            <div class="form-group">
                                <label for="social_whatsapp">WhatsApp Group / Contact URL</label>
                                <input type="url" id="social_whatsapp" name="social_whatsapp" class="form-control" placeholder="https://chat.whatsapp.com/..." value="<?php echo htmlspecialchars($soc_wa); ?>">
                                <div class="form-help">Link grup atau narahubung WhatsApp (opsional)</div>
                            </div>
                        </div>

                        <div style="margin-top: 14px;">
                            <button type="submit" name="action_save_settings" class="btn-primary" style="width: auto; padding: 11px 24px;">
                                Simpan Pengaturan & Link Medsos
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Update Admin Account Info -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2 class="admin-card-title">Perbarui Akun & Password Administrator</h2>
                            <p style="font-size: 13px; color: var(--admin-muted); margin: 4px 0 0 0;">Ubah nama, nomor HP login, dan ganti password administrator</p>
                        </div>
                    </div>

                    <form method="POST" action="/admin/dashboard">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="admin_name">Nama Administrator</label>
                                <input type="text" id="admin_name" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user'] ?? 'Admin'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="admin_hp">Nomor HP (digunakan untuk login)</label>
                                <input type="text" id="admin_hp" name="admin_hp" class="form-control" value="<?php echo htmlspecialchars($_SESSION['hp'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">Password Baru (Kosongkan jika tidak diubah)</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Masukkan password baru">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Password Baru</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulangi password baru">
                            </div>
                        </div>

                        <div style="margin-top: 14px;">
                            <button type="submit" name="action_update_admin" class="btn-primary" style="width: auto; padding: 11px 24px;">
                                Perbarui Akun Admin
                            </button>
                        </div>
                    </form>
                </div>
            </section>

        </main>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL EDIT EVENT                                                       -->
<!-- ====================================================================== -->
<div id="editEventModal" class="admin-modal-overlay">
    <div class="admin-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--admin-border); padding-bottom: 12px;">
            <h3 style="margin: 0; color: var(--admin-primary); font-size: 17px; font-weight: 700;">Edit Event Twibbon</h3>
            <button type="button" onclick="closeEditEventModal()" style="background:none; border:none; font-size: 22px; cursor: pointer; color: var(--admin-muted);">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" action="/admin/dashboard">
            <input type="hidden" id="editEventId" name="event_id">

            <div class="form-group">
                <label for="editEventName">Nama Event</label>
                <input type="text" id="editEventName" name="event_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="editEventSlug">Slug URL</label>
                <input type="text" id="editEventSlug" name="event_slug" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Template Saat Ini:</label>
                <div style="text-align: center; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid var(--admin-border);">
                    <img id="editCurrentTemplateImg" src="" alt="Template" style="max-height: 120px; max-width: 100%; object-fit: contain;">
                </div>
            </div>

            <div class="form-group">
                <label>Ganti File Template PNG (Opsional):</label>
                <input type="file" name="template_file" accept="image/png" class="form-control">
                <div class="form-help">Biarkan kosong jika tidak ingin mengganti file template saat ini.</div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="editHoleX">Koordinat X</label>
                    <input type="number" id="editHoleX" name="hole_x" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="editHoleY">Koordinat Y</label>
                    <input type="number" id="editHoleY" name="hole_y" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="editHoleW">Lebar Lubang (Width)</label>
                    <input type="number" id="editHoleW" name="hole_w" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="editHoleH">Tinggi Lubang (Height)</label>
                    <input type="number" id="editHoleH" name="hole_h" class="form-control" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-sm-outline" onclick="closeEditEventModal()">Batal</button>
                <button type="submit" name="action_update_event" class="btn-primary" style="width: auto; padding: 10px 20px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="/admin/admin.js"></script>
</body>
</html>
