<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>
<div class="container">
    <h2>Twibbon IKAPMAWI</h2>
    <p>Halo, <b><?php echo $_SESSION['user']; ?></b> (Alumni <?php echo $_SESSION['tahun']; ?>)</p>
    
    <div class="canvas-wrapper">
        <canvas id="mainCanvas" width="1080" height="1080"></canvas>
    </div>

    <div class="controls">
        <input type="file" id="upload" accept="image/*" style="display:none">
        <label for="upload" class="btn-primary" style="display:block; margin-bottom:10px;">Pilih Foto</label>
        <button id="download" class="btn-primary" style="width:100%; background:#3498db" disabled>Unduh Hasil</button>
    </div>
    <a href="logout.php" style="display:block; margin-top:20px; color:red; font-size:12px;">Keluar</a>
</div>

<script src="script.js"></script>