<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['event_id']) || $_SESSION['is_admin']) {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'];

// Insert or ignore if already recorded
$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO event_usage (user_id, event_id) VALUES (?, ?)");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode(['status' => 'success']);
?>