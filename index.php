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

// Social media links for footer
$soc_fb = get_setting($conn, 'social_facebook', '');
$soc_ig = get_setting($conn, 'social_instagram', '');
$soc_yt = get_setting($conn, 'social_youtube', '');
$soc_tt = get_setting($conn, 'social_tiktok', '');
$soc_wa = get_setting($conn, 'social_whatsapp', '');
$has_socials = !empty($soc_fb) || !empty($soc_ig) || !empty($soc_yt) || !empty($soc_tt) || !empty($soc_wa);

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
                        
                        <!-- Modern Searchable Alumni Year Dropdown -->
                        <div class="custom-select-wrapper">
                            <input type="hidden" name="tahun" id="tahunQuickJoin" required>
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
                                    for ($i = date('Y')+1; $i >= 1950; $i--): 
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
        <?php if ($has_socials): ?>
            <div class="social-footer-links">
                <?php if (!empty($soc_fb)): ?>
                    <a href="<?php echo htmlspecialchars($soc_fb); ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-fb" title="Facebook IKAPMAWI" aria-label="Facebook">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($soc_ig)): ?>
                    <a href="<?php echo htmlspecialchars($soc_ig); ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-ig" title="Instagram IKAPMAWI" aria-label="Instagram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($soc_yt)): ?>
                    <a href="<?php echo htmlspecialchars($soc_yt); ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-yt" title="YouTube IKAPMAWI" aria-label="YouTube">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($soc_tt)): ?>
                    <a href="<?php echo htmlspecialchars($soc_tt); ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-tt" title="TikTok IKAPMAWI" aria-label="TikTok">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.1z"/></svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($soc_wa)): ?>
                    <a href="<?php echo htmlspecialchars($soc_wa); ?>" target="_blank" rel="noopener noreferrer" class="social-btn social-wa" title="WhatsApp IKAPMAWI" aria-label="WhatsApp">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p>IKAPMAWI &copy; <?php echo date('Y'); ?> • All Rights Reserved</p>
        <p style="margin-top: 6px;"><a href="/admin/login">Portal Admin</a></p>
    </footer>
</div>

<script src="/dropdown.js"></script>
<?php if (isset($_SESSION['user']) && $event): ?>
<script src="/script.js"></script>
<?php endif; ?>

</body>
</html>