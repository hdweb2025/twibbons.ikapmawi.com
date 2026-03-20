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
        <img src="/assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
        <h2><?php echo ($event) ? $event['name'] : 'Galeri Twibbon'; ?></h2>
    </div>

    <?php if ($event): // If a specific event is being viewed ?>
        <?php if (isset($_SESSION['user'])): // If user is logged in, show editor ?>
            <p>Halo, <b><?php echo $_SESSION['user']; ?></b> (Alumni <?php echo $_SESSION['tahun']; ?>)</p>
            <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Gunakan mouse wheel untuk zoom, drag untuk geser foto.</p>
            <div class="canvas-wrapper">
                <canvas id="mainCanvas" width="1080" height="1080" data-template="/<?php echo $event['template']; ?>" data-event-id="<?php echo $event['id']; ?>"></canvas>
            </div>

            <div class="controls">
                <input type="file" id="upload" accept="image/*" style="display:none">
                <label for="upload" class="btn-primary" style="display:block; margin-bottom:10px;">Pilih Foto</label>
                <button id="download" class="btn-primary" style="width:100%; background:#3498db" disabled>Unduh Hasil</button>
                <a href="/" style="display:block; margin-top:10px; color: #666; font-size: 12px; text-decoration: none;">Kembali ke Galeri</a>
            </div>

            <!-- Event Usage Statistics -->
            <div class="usage-stats" style="margin-top: 30px; text-align: left; background: #f9f9f9; padding: 15px; border-radius: 8px; font-size: 14px;">
                <?php
                $current_event_id = $event['id'];
                $usage_res = mysqli_query($conn, "SELECT users.nama_lengkap FROM event_usage JOIN users ON event_usage.user_id = users.id WHERE event_usage.event_id = $current_event_id ORDER BY event_usage.created_at DESC");
                $total_usage = mysqli_num_rows($usage_res);
                ?>
                <h4 style="color: #1a5c2e; margin-bottom: 10px;">Telah Digunakan oleh (<?php echo $total_usage; ?>):</h4>
                <div class="user-list" style="max-height: 150px; overflow-y: auto; color: #555;">
                    <?php if ($total_usage == 0): ?>
                        <p style="font-style: italic; font-size: 12px;">Jadilah yang pertama menggunakan twibbon ini!</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php while($user_row = mysqli_fetch_assoc($usage_res)): ?>
                                <li style="padding: 5px 0; border-bottom: 1px solid #eee;"><?php echo $user_row['nama_lengkap']; ?></li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <a href="/logout.php" style="display:block; margin-top:20px; color:red; font-size:12px;">Keluar</a>
        <?php else: // If user is not logged in, show login prompt ?>
            <div class="landing-content" style="text-align: center;">
                <p style="font-size: 18px; margin-bottom: 30px;">
                    Anda ingin membuat Twibbon untuk <b><?php echo $event['name']; ?></b>?<br>
                    Silakan masuk atau daftar terlebih dahulu.
                </p>
                <?php 
                    $login_url = "/login.php?redirect=" . urlencode($event['slug'] . ".php");
                    $register_url = "/register.php?redirect=" . urlencode($event['slug'] . ".php");
                ?>
                <a href="<?php echo $login_url; ?>" class="btn-primary" style="text-decoration: none; margin-bottom: 10px; display: block;">Masuk Sekarang</a>
                <a href="<?php echo $register_url; ?>" class="btn-secondary" style="text-decoration: none; display: block;">Daftar Akun</a>
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
        <?php if(!isset($_SESSION['user'])): ?>
            <div style="text-align: center; margin-top: 30px;">
                <p>Sudah punya akun? <a href="/login.php">Masuk di sini</a></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php if (isset($_SESSION['user']) && $event): ?>
<script src="/script.js"></script>
<?php endif; ?>

</body>
</html>