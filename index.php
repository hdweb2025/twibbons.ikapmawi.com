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
                Assalamualaikum, <b><?php echo htmlspecialchars($_SESSION['user']); ?></b> (<?php echo htmlspecialchars($_SESSION['keterangan'] ?? $_SESSION['peran'] ?? ('Alumni ' . ($_SESSION['tahun'] ?? ''))); ?>)
            </div>
        <?php endif; ?>
    </header>

    <?php if ($event): // If a specific event is being viewed, render editor directly ?>
        <div class="editor-layout">
            <!-- Sisi Kiri: Preview Canvas Twibbon Responsif -->
            <div class="editor-left">
                <div class="canvas-wrapper">
                    <canvas id="mainCanvas" width="1080" height="1080" 
                        data-template="/<?php echo $event['template']; ?>" 
                        data-event-id="<?php echo $event['id']; ?>"
                        data-event-slug="<?php echo htmlspecialchars($event['slug']); ?>"
                        data-logged-in="<?php echo isset($_SESSION['user']) ? '1' : '0'; ?>"
                        data-hole-x="<?php echo $event['hole_x']; ?>"
                        data-hole-y="<?php echo $event['hole_y']; ?>"
                        data-hole-w="<?php echo $event['hole_w']; ?>"
                        data-hole-h="<?php echo $event['hole_h']; ?>">
                    </canvas>
                </div>
                <div class="canvas-hint">
                    <span>💡</span> Geser / Drag untuk atur posisi • Cubit (pinch) atau slider untuk zoom
                </div>
            </div>

            <!-- Sisi Kanan: Kontrol & Tombol Aksi -->
            <div class="editor-right">
                <div>
                    <span class="event-badge">Twibbon Resmi IKAPMAWI</span>
                    <h3 style="color: var(--primary-color); margin-bottom: 4px; font-size: 20px;"><?php echo htmlspecialchars($event['name']); ?></h3>
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

                <!-- Statistik Partisipasi Sahabat & Alumni MWI -->
                <?php
                $current_event_id = $event['id'];
                $usage_res = mysqli_query($conn, "SELECT users.nama_lengkap FROM event_usage JOIN users ON event_usage.user_id = users.id WHERE event_usage.event_id = $current_event_id ORDER BY event_usage.created_at DESC");
                $total_usage = mysqli_num_rows($usage_res);
                ?>
                <div class="usage-stats-card">
                    <div class="usage-count-number" id="usageCountDisplay"><?php echo $total_usage; ?></div>
                    <div class="usage-count-label">Sahabat Santri, Asatidz & Alumni telah berpartisipasi dengan Twibbon ini</div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                    <a href="/" class="btn-link">← Kembali ke Galeri</a>
                    <?php if (isset($_SESSION['user'])): ?>
                        <a href="/logout.php" style="color: var(--danger-color); font-size: 13px; text-decoration: none;">Keluar / Ganti Akun</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal Identitas Partisipan Sebelum Download (Santri, Alumni, Asatidz, Umum) -->
        <div id="participantModal" class="twibbon-modal-overlay" style="display: none;" aria-hidden="true">
            <div class="twibbon-modal-dialog">
                <div class="twibbon-modal-header">
                    <span class="modal-badge-sanad">Keluarga Besar MWI Kebarongan</span>
                    <h3 class="twibbon-modal-title">Satu Langkah Menuju Twibbon Anda</h3>
                    <p class="twibbon-modal-subtitle">Silakan isi identitas singkat untuk pencatatan partisipasi keluarga besar MWI.</p>
                    <button type="button" class="btn-modal-close" onclick="closeParticipantModal()" aria-label="Tutup">&times;</button>
                </div>

                <form id="participantForm" class="twibbon-modal-form" onsubmit="handleParticipantSubmit(event)">
                    <input type="hidden" id="participantEventId" name="event_id" value="<?php echo $event['id']; ?>">
                    
                    <label class="form-label">Status Anda di MWI:</label>
                    <div class="role-selector-grid">
                        <button type="button" class="role-pill-btn active" data-role="Santri Aktif" onclick="selectRole('Santri Aktif')">
                            <span class="role-icon">🎓</span>
                            <span class="role-title">Santri Aktif</span>
                        </button>
                        <button type="button" class="role-pill-btn" data-role="Alumni" onclick="selectRole('Alumni')">
                            <span class="role-icon">🤝</span>
                            <span class="role-title">Alumni</span>
                        </button>
                        <button type="button" class="role-pill-btn" data-role="Asatidz / Guru" onclick="selectRole('Asatidz / Guru')">
                            <span class="role-icon">📖</span>
                            <span class="role-title">Asatidz / Guru</span>
                        </button>
                        <button type="button" class="role-pill-btn" data-role="Simpatisan / Umum" onclick="selectRole('Simpatisan / Umum')">
                            <span class="role-icon">🌟</span>
                            <span class="role-title">Wali / Umum</span>
                        </button>
                    </div>
                    <input type="hidden" id="selectedRole" name="peran" value="Santri Aktif">

                    <!-- Input Nama Lengkap -->
                    <div class="form-group-modal">
                        <label for="modalNama" class="form-label">Nama Lengkap <span class="required-star">*</span></label>
                        <input type="text" id="modalNama" name="nama" class="modal-input" placeholder="Masukkan nama lengkap Anda..." required autocomplete="name">
                    </div>

                    <!-- Dynamic Role-Specific Fields -->
                    <!-- 1. Santri Aktif -->
                    <div id="fieldSantri" class="role-dynamic-field">
                        <label for="selectJenjangSantri" class="form-label">Jenjang & Kelas Santri <span class="required-star">*</span></label>
                        <select id="selectJenjangSantri" name="keterangan_santri" class="modal-select">
                            <option value="Santri MA WI - Kelas 10">Santri MA WI Kebarongan - Kelas 10</option>
                            <option value="Santri MA WI - Kelas 11" selected>Santri MA WI Kebarongan - Kelas 11</option>
                            <option value="Santri MA WI - Kelas 12">Santri MA WI Kebarongan - Kelas 12</option>
                            <option value="Santri MTs WI - Kelas 7">Santri MTs WI Kebarongan - Kelas 7</option>
                            <option value="Santri MTs WI - Kelas 8">Santri MTs WI Kebarongan - Kelas 8</option>
                            <option value="Santri MTs WI - Kelas 9">Santri MTs WI Kebarongan - Kelas 9</option>
                            <option value="Santri Pondok Pesantren MWI">Santri Pondok Pesantren MWI</option>
                        </select>
                    </div>

                    <!-- 2. Alumni -->
                    <div id="fieldAlumni" class="role-dynamic-field" style="display: none;">
                        <label for="selectTahunAlumni" class="form-label">Tahun Kelulusan Alumni <span class="required-star">*</span></label>
                        <select id="selectTahunAlumni" name="tahun_alumni" class="modal-select">
                            <?php for ($y = date('Y'); $y >= 1950; $y--): ?>
                                <option value="<?php echo $y; ?>">Alumni Tahun <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- 3. Asatidz -->
                    <div id="fieldAsatidz" class="role-dynamic-field" style="display: none;">
                        <label for="selectKeteranganAsatidz" class="form-label">Unit Pengabdian / Lembaga <span class="required-star">*</span></label>
                        <select id="selectKeteranganAsatidz" name="keterangan_asatidz" class="modal-select">
                            <option value="Dewan Asatidz MA WI Kebarongan" selected>Dewan Asatidz MA WI Kebarongan</option>
                            <option value="Dewan Asatidz MTs WI Kebarongan">Dewan Asatidz MTs WI Kebarongan</option>
                            <option value="Pengasuh / Pembina Pondok Pesantren MWI">Pengasuh / Pembina Pondok Pesantren MWI</option>
                            <option value="Dewan Guru & Yayasan MWI">Dewan Guru & Yayasan MWI</option>
                        </select>
                    </div>

                    <!-- 4. Simpatisan / Umum -->
                    <div id="fieldSimpatisan" class="role-dynamic-field" style="display: none;">
                        <label for="selectKeteranganSimpatisan" class="form-label">Kategori <span class="required-star">*</span></label>
                        <select id="selectKeteranganSimpatisan" name="keterangan_simpatisan" class="modal-select">
                            <option value="Wali Santri MWI Kebarongan" selected>Wali Santri MWI Kebarongan</option>
                            <option value="Simpatisan / Sahabat IKAPMAWI">Simpatisan / Sahabat IKAPMAWI</option>
                            <option value="Tamu Undangan Talkshow / Umum">Tamu Undangan Talkshow / Umum</option>
                        </select>
                    </div>

                    <!-- Nomor WhatsApp (Opsional) -->
                    <div class="form-group-modal">
                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <label for="modalHp" class="form-label">Nomor WhatsApp</label>
                            <span class="label-optional">Boleh dikosongkan</span>
                        </div>
                        <input type="tel" id="modalHp" name="hp" class="modal-input" placeholder="08xxxxxxxxxx (Opsional)" autocomplete="tel">
                        <div class="modal-help-text">Jika diisi, nomor hanya digunakan untuk silaturahmi & info agenda madrasah.</div>
                    </div>

                    <div id="modalErrorMsg" class="modal-alert-error" style="display: none;"></div>

                    <div class="modal-actions">
                        <button type="submit" id="btnSubmitModal" class="btn-modal-submit">
                            <span class="btn-text">Simpan & Unduh Twibbon ⬇</span>
                            <span class="btn-spinner" style="display: none;">Menyiapkan Twibbon...</span>
                        </button>
                    </div>

                    <div class="modal-privacy-notice">
                        🔒 Data Anda terjaga dengan amanah sesuai UU PDP No. 27/2022.
                    </div>
                </form>
            </div>
        </div>

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
<?php if ($event): ?>
<script src="/script.js"></script>
<?php endif; ?>

</body>
</html>