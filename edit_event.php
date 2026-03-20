<?php
session_start();
include 'config.php';

// Admin check
if (!isset($_SESSION['user']) || !$_SESSION['is_admin']) {
    header("Location: /login.php");
    exit();
}

$event_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$event_id) {
    header("Location: /admin.php");
    exit();
}

// Fetch current event data
$res = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
$event = mysqli_fetch_assoc($res);

if (!$event) {
    echo "Event tidak ditemukan.";
    exit();
}

// Handle template update
if (isset($_POST['update_event'])) {
    $file = $_FILES['template'];
    if ($file['size'] > 0) {
        $target_dir = "uploads/templates/";
        $filename = time() . "_" . basename($file["name"]);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            // Delete old template if it exists
            if (file_exists($event['template'])) {
                unlink($event['template']);
            }
            // Update database with new template path
            mysqli_query($conn, "UPDATE events SET template = '$target_file' WHERE id = $event_id");
            $success = "Template berhasil diperbarui!";
            // Refresh event data
            $res = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
            $event = mysqli_fetch_assoc($res);
        } else {
            $error = "Gagal mengunggah template baru.";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Event - <?php echo $event['name']; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/icon.png">
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="container" style="max-width: 600px;">
    <div class="header">
        <img src="/assets/ikapmawi-logo.png" alt="Logo IKAPMAWI">
        <h2>Edit Event</h2>
    </div>
    
    <h3><?php echo $event['name']; ?></h3>

    <div style="margin: 20px 0;">
        <p>Template saat ini:</p>
        <img src="/<?php echo $event['template']; ?>" alt="Template saat ini" style="max-width: 100%; border: 1px solid #ddd; border-radius: 8px;">
    </div>

    <form method="POST" enctype="multipart/form-data">
        <?php if(isset($success)) echo "<p style='color:green'>$success</p>"; ?>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        <label style="display:block; text-align:left; font-size:12px;">Pilih Template PNG Baru:</label>
        <input type="file" name="template" accept="image/png" required>
        <button type="submit" name="update_event" class="btn-primary">Update Template</button>
    </form>

    <a href="/admin.php" style="display:block; margin-top:20px;">Kembali ke Panel Admin</a>
</div>
</body>
</html>
