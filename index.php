<?php
session_start();
include 'config.php';

// Get event slug regardless of login status
$event_slug = isset($_GET['event_slug']) ? $_GET['event_slug'] : null;
$event = null;

if ($event_slug) {
    $res = mysqli_query($conn, "SELECT * FROM events WHERE slug = '$event_slug'");
    $event = mysqli_fetch_assoc($res);
}

// Check require_registration setting
$require_registration = get_setting($conn, 'require_registration', '0') === '1';

// Handle quick join submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_join'])) {
    $nama = $_POST['nama'];
    $tahun = $_POST['tahun'];
    $hp = 'guest_' . time() . rand(100, 999);
    $pass = '';

    $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, tahun_alumni, nomor_hp, password) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_insert, "ssss", $nama, $tahun, $hp, $pass);
    mysqli_stmt_execute($stmt_insert);
    $user_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_insert);

    $_SESSION['user_id'] = $user_id;
    $_SESSION['user'] = $nama;
    $_SESSION['tahun'] = $tahun;
    $_SESSION['hp'] = $hp;
    $_SESSION['is_admin'] = 0;
    
    // Redirect to prevent form resubmission
    header("Location: /" . $event['slug'] . ".php");
    exit();
}

// Fetch all events for the gallery view for everyone
$events = mysqli_query($conn, "SELECT * FROM events ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo ($event) ? htmlspecialchars($event['name']) . ' - Twibbon IKAPMAWI' : 'Galeri Twibbon IKAPMAWI'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#1a5c2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container wide-container">
    <header class="header">
        <a href="/" style="display:inline-block; text-decoration:none;">
            <img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI">
        </a>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="user-badge">
                Assalamualaikum, <b><?php echo htmlspecialchars($_SESSION['user']); ?></b> (Alumni <?php echo htmlspecialchars($_SESSION['tahun']); ?>)
            </div>
        <?php endif; ?>
    </header>

    <?php if ($event): // If a specific event is being viewed ?>
        <?php if (isset($_SESSION['user'])): // If user is logged in, show editor ?>
            
            <div class="editor-layout">
                <!-- Sisi Kiri: Preview Canvas Twibbon Responsif -->
                <div class="editor-left">
                    <div class="canvas-wrapper">
                        <canvas id="mainCanvas" width="1080" height="1080" 
                            data-template="/<?php echo $event['template']; ?>" 
                            data-event-id="<?php echo $event['id']; ?>"
                            data-hole-x="<?php echo $event['hole_x']; ?>"
                            data-hole-y="<?php echo $event['hole_y']; ?>"
                            data-hole-w="<?php echo $event['hole_w']; ?>"
                            data-hole-h="<?php echo $event['hole_h']; ?>">
                        </canvas>
                    </div>
                    <div class="canvas-hint">
                        <span>💡</span> Geser / Drag untuk atur posisi • Cubit (pinch) atau scroll untuk zoom
                    </div>
                </div>

                <!-- Sisi Kanan: Kontrol & Tombol Aksi -->
                <div class="editor-right">
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 4px;"><?php echo htmlspecialchars($event['name']); ?></h3>
                        <p style="font-size: 13.5px; color: var(--text-muted);">Silahkan pilih foto terbaik Anda untuk dipasang pada bingkai Twibbon.</p>
                    </div>

                    <!-- Input Upload File -->
                    <div>
                        <input type="file" id="upload" accept="image/*" style="display:none">
                        <label for="upload" class="btn-primary" style="cursor: pointer;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Pilih Foto Anda
                        </label>
                    </div>

                    <!-- Kotak Pengaturan Zoom & Rotasi -->
                    <div class="zoom-control-box">
                        <div class="zoom-header">
                            <span>Pengaturan Foto</span>
                            <span id="zoomValBadge" style="font-weight: 500; color: var(--text-muted); font-size: 12px;">100%</span>
                        </div>
                        
                        <div class="slider-container">
                            <input type="range" id="zoomSlider" min="0.01" max="5" step="0.001" value="1" title="Atur Ukuran Foto">
                        </div>

                        <!-- Tombol Cepat: Zoom In, Zoom Out, Putar, Reset -->
                        <div class="tool-btn-group">
                            <button type="button" id="zoomOutBtn" class="btn-tool" title="Perkecil Foto">
                                🔍- Perkecil
                            </button>
                            <button type="button" id="zoomInBtn" class="btn-tool" title="Perbesar Foto">
                                🔍+ Perbesar
                            </button>
                            <button type="button" id="rotateBtn" class="btn-tool" title="Putar Foto 90 Derajat">
                                ↻ Putar
                            </button>
                            <button type="button" id="resetBtn" class="btn-tool btn-tool-reset" title="Kembalikan Posisi Semula">
                                ↺ Reset
                            </button>
                        </div>
                    </div>

                    <!-- Tombol Download -->
                    <div>
                        <button id="download" class="btn-download" disabled>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download Twibbon Anda
                        </button>
                    </div>

                    <!-- Statistik Partisipasi -->
                    <?php
                    $current_event_id = $event['id'];
                    $usage_res = mysqli_query($conn, "SELECT users.nama_lengkap FROM event_usage JOIN users ON event_usage.user_id = users.id WHERE event_usage.event_id = $current_event_id ORDER BY event_usage.created_at DESC");
                    $total_usage = mysqli_num_rows($usage_res);
                    ?>
                    <div class="usage-stats-card">
                        <div class="usage-count-number"><?php echo $total_usage; ?></div>
                        <div class="usage-count-label">Alumni MWI telah berpartisipasi dengan Twibbon ini</div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                        <a href="/" class="btn-link">← Kembali ke Galeri</a>
                        <a href="/logout.php" style="color: var(--danger-color); font-size: 13px; text-decoration: none;">Keluar Akun</a>
                    </div>
                </div>
            </div>

        <?php else: // If user is not logged in, show login/join prompt ?>
            <div class="login-prompt">
                <div class="prompt-icon">✨</div>
                <h2 style="color: var(--primary-color); margin-bottom: 8px;"><?php echo htmlspecialchars($event['name']); ?></h2>
                <p style="color: var(--text-muted); font-size: 14.5px; max-width: 460px; margin: 0 auto 20px auto;">
                    Gunakan bingkai Twibbon eksklusif dari <b>IKAPMAWI</b> untuk merayakan momen spesial bersama rekan alumni.
                </p>
                
                <?php if ($require_registration): ?>
                    <?php 
                        $current_slug = $event['slug'];
                        $login_url = "/login.php?redirect=" . urlencode($current_slug);
                        $register_url = "/register.php?redirect=" . urlencode($current_slug);
                    ?>
                    <div class="prompt-actions" style="max-width: 360px; margin: 0 auto;">
                        <a href="<?php echo $register_url; ?>" class="btn-primary">Buat Twibbon Sekarang</a>
                        <div class="divider"><span>atau</span></div>
                        <a href="<?php echo $login_url; ?>" class="btn-link">Sudah punya akun? <b>Masuk</b></a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="quick-join-form" style="max-width: 380px; margin: 20px auto 0 auto;">
                        <input type="text" name="nama" placeholder="Nama Lengkap Anda" required>
                        <select name="tahun" required>
                            <option value="" disabled selected>Alumni Tahun Berapa?</option>
                            <?php for ($i = date('Y')+1; $i >= 1950; $i--): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" name="quick_join" class="btn-primary">Lanjut ke Twibbon →</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: // If no specific event, show the gallery ?>
        <div style="text-align: center; margin-bottom: 24px;">
            <h2 style="color: var(--primary-color); margin-bottom: 6px;">Pilih Tema Twibbon</h2>
            <p style="color: var(--text-muted); font-size: 14.5px;">Pilih twibbon favoritmu untuk meriahkan momen kebersamaan alumni!</p>
        </div>

        <div class="twibbon-gallery">
            <?php if (mysqli_num_rows($events) == 0): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted);">
                    Belum ada event twibbon yang tersedia saat ini.
                </div>
            <?php else: ?>
                <?php while($row = mysqli_fetch_assoc($events)): ?>
                    <a href="/<?php echo htmlspecialchars($row['slug']); ?>.php" class="twibbon-card">
                        <div class="twibbon-card-img-wrapper">
                            <img src="/<?php echo htmlspecialchars($row['template']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="twibbon-card-img" loading="lazy">
                        </div>
                        <div class="twibbon-card-body">
                            <div class="twibbon-card-title"><?php echo htmlspecialchars($row['name']); ?></div>
                            <div class="twibbon-card-btn">Pasang Foto →</div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <footer class="site-footer">
        <p>IKAPMAWI &copy; <?php echo date('Y'); ?> • All Rights Reserved</p>
        <?php if(!isset($_SESSION['user'])): ?>
            <p style="margin-top: 6px;"><a href="/login.php">Login Admin</a></p>
        <?php endif; ?>
    </footer>
</div>

<?php if (isset($_SESSION['user']) && $event): ?>
<script src="/script.js"></script>
<?php endif; ?>

</body>
</html>