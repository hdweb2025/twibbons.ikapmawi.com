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

// Fetch all events for the gallery view for everyone
$events = mysqli_query($conn, "SELECT * FROM events ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo ($event) ? $event['name'] . ' - Twibbon IKAPMAWI' : 'Galeri Twibbon IKAPMAWI'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <?php if (!isset($_SESSION['user'])): ?>
            <img src="/assets/logo_ikapmawi.webp" alt="Logo IKAPMAWI">
        <?php endif; ?>
        <h3><?php echo ($event) ? $event['name'] : 'Galeri Twibbon'; ?></h3>
    </div>

    <?php if ($event): // If a specific event is being viewed ?>
        <?php if (isset($_SESSION['user'])): // If user is logged in, show editor ?>
            <p>Halo, <b><?php echo $_SESSION['user']; ?></b> (Alumni <?php echo $_SESSION['tahun']; ?>)</p>
            <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Gunakan mouse wheel untuk zoom, drag untuk geser foto.</p>
            <div class="canvas-wrapper">
            <canvas id="mainCanvas" width="1080" height="1080" data-template="/<?php echo $event['template']; ?>" data-event-id="<?php echo $event['id']; ?>"></canvas>
        </div>

        <div class="zoom-control">
            <label>Resize Foto:</label>
            <input type="range" id="zoomSlider" min="0.01" max="5" step="0.001" value="1">
            <button id="resetBtn" class="btn-reset">Reset Posisi</button>
        </div>

        <div class="controls">
                <input type="file" id="upload" accept="image/*" style="display:none">
                <label for="upload" class="btn-primary" style="display:block; margin-bottom:10px;">Pilih Foto</label>
                <button id="download" class="btn-primary" style="width:100%; background:#3498db" disabled>Unduh Hasil</button>
                <a href="/" style="display:block; margin-top:10px; color: #666; font-size: 12px; text-decoration: none;">Kembali ke Galeri</a>
            </div>

            <!-- Event Usage Statistics -->
            <div class="usage-stats">
                <?php
                $current_event_id = $event['id'];
                $usage_res = mysqli_query($conn, "SELECT users.nama_lengkap FROM event_usage JOIN users ON event_usage.user_id = users.id WHERE event_usage.event_id = $current_event_id ORDER BY event_usage.created_at DESC");
                $total_usage = mysqli_num_rows($usage_res);
                ?>
                <h5 style="text-align: center; color: #555; margin-top: 10px;">Sebanyak <?php echo $total_usage; ?> Alumni MWI</h5>
                <h5 style="text-align: center; color: #555; margin-top: 10px;">Sudah Berpartisipasi dengan Twibbon ini</h5>
            </div>
            <a href="/logout.php" style="display:block; margin-top:20px; text-align:center; color:red; font-size:12px;">Keluar</a>
        <?php else: // If user is not logged in, show login prompt ?>
            <div class="login-prompt">
                <div class="prompt-icon" style="text-align: center;">✨</div>
                <h3 style="margin-bottom: 20px; text-align: center;">Gunakan Desain Eksklusif dari <b>ikapmawi.</b></h3>
                
                <?php 
                    $current_slug = $event['slug'];
                    $login_url = "/login.php?redirect=" . urlencode($current_slug);
                    $register_url = "/register.php?redirect=" . urlencode($current_slug);
                ?>

                <div class="prompt-actions">
                    <a href="<?php echo $register_url; ?>" class="btn-primary">Buat Twibbon</a>
                    <div class="divider"><span>atau</span></div>
                    <a href="<?php echo $login_url; ?>" class="btn-link">Sudah punya akun? <b>Masuk</b></a>
                </div>
            </div>
        <?php endif; ?>
    <?php else: // If no specific event, show the gallery ?>
        <p style="text-align:center; margin-bottom: 25px; color: #555;">Pilih twibbon favoritmu untuk meriahkan setiap momen kebersamaan kita!</p>
        <div class="twibbon-gallery">
            <?php if (mysqli_num_rows($events) == 0): ?>
                <p>Belum ada event twibbon yang tersedia. Silakan cek kembali nanti.</p>
            <?php else: ?>
                <?php while($row = mysqli_fetch_assoc($events)): ?>
                    <a href="/<?php echo $row['slug']; ?>.php" class="twibbon-card">
                        <img src="/<?php echo $row['template']; ?>" alt="<?php echo $row['name']; ?>" class="twibbon-card-img">
                        <div class="twibbon-card-title"><?php echo $row['name']; ?></div>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>



<footer class="site-footer">
    <p>ikapmawi &copy; <?php echo date('Y'); ?> | All Rights Reserved.</p>
    <?php if(!isset($_SESSION['user'])): ?>
        <p><a href="/login.php">Admin Login</a></p>
    <?php endif; ?>
</footer>

<?php if (isset($_SESSION['user']) && $event): ?>
<script src="/script.js"></script>
<?php endif; ?>

</body>
</html>