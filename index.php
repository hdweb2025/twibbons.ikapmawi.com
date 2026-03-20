<?php
session_start();
include 'config.php';

// If user is logged in, redirect to event selection
if (isset($_SESSION['user'])) {
    $event_id = isset($_GET['event']) ? $_GET['event'] : null;
    $event = null;

    if ($event_id) {
        $res = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
        $event = mysqli_fetch_assoc($res);
    }

    $events = mysqli_query($conn, "SELECT * FROM events ORDER BY created_at DESC");
} // For non-logged-in users, the rest of the page will show the landing content.

?>
<!DOCTYPE html>
<html>
<head>
    <title>Twibbon IKAPMAWI</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Twibbon IKAPMAWI</h2>

    <?php if (isset($_SESSION['user'])): ?>
        <p>Halo, <b><?php echo $_SESSION['user']; ?></b> (Alumni <?php echo $_SESSION['tahun']; ?>)</p>

        <?php if (!$event): ?>
            <h3>Pilih Event / Ucapan:</h3>
            <div class="event-list" style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                <?php if (mysqli_num_rows($events) == 0): ?>
                    <p>Belum ada event tersedia.</p>
                <?php else: ?>
                    <?php while($row = mysqli_fetch_assoc($events)): ?>
                        <a href="index.php?event=<?php echo $row['id']; ?>" class="btn-primary" style="text-decoration: none;">
                            <?php echo $row['name']; ?>
                        </a>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h3>Event: <?php echo $event['name']; ?></h3>
            <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Gunakan mouse wheel untuk zoom, drag untuk geser foto.</p>
            <div class="canvas-wrapper">
                <canvas id="mainCanvas" width="1080" height="1080" data-template="<?php echo $event['template']; ?>"></canvas>
            </div>

            <div class="controls">
                <input type="file" id="upload" accept="image/*" style="display:none">
                <label for="upload" class="btn-primary" style="display:block; margin-bottom:10px;">Pilih Foto</label>
                <button id="download" class="btn-primary" style="width:100%; background:#3498db" disabled>Unduh Hasil</button>
                <a href="index.php" style="display:block; margin-top:10px; color: #666; font-size: 12px; text-decoration: none;">Kembali ke Pilih Event</a>
            </div>
        <?php endif; ?>

        <a href="logout.php" style="display:block; margin-top:20px; color:red; font-size:12px;">Keluar</a>

    <?php else: ?>
        <div class="landing-content" style="text-align: center;">
            <p style="font-size: 18px; margin-bottom: 30px;">Selamat datang di portal Twibbon resmi IKAPMAWI. Mari meriahkan setiap momen kebersamaan kita!</p>
            <a href="login.php" class="btn-primary" style="text-decoration: none; margin-bottom: 10px; display: block;">Mulai Buat Twibbon</a>
            <a href="register.php" class="btn-secondary" style="text-decoration: none; display: block;">Daftar / Gabung Sekarang</a>
        </div>
    <?php endif; ?>

</div>

<?php if (isset($_SESSION['user'])): ?>
<script src="script.js"></script>
<?php endif; ?>

</body>
</html>