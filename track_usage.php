<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$user_id = 0;

if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
    $user_id = (int)$_SESSION['user_id'];
} elseif (isset($_COOKIE['ikapmawi_participant'])) {
    $cdata = json_decode($_COOKIE['ikapmawi_participant'], true);
    if ($cdata && !empty($cdata['user_id'])) {
        $user_id = intval($cdata['user_id']);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user'] = $cdata['nama'] ?? '';
        $_SESSION['peran'] = $cdata['peran'] ?? 'Alumni';
        $_SESSION['keterangan'] = $cdata['keterangan'] ?? '';
        $_SESSION['tahun'] = $cdata['tahun'] ?? date('Y');
    }
} elseif (isset($_POST['user_id']) && intval($_POST['user_id']) > 0) {
    $user_id = intval($_POST['user_id']);
    $_SESSION['user_id'] = $user_id;
}

if ($user_id > 0 && $event_id > 0) {
    $stmt = mysqli_prepare($conn, "INSERT INTO event_usage (user_id, event_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['status' => 'success', 'user_id' => $user_id, 'event_id' => $event_id]);
    exit();
}

echo json_encode(['status' => 'ignored']);
?>
