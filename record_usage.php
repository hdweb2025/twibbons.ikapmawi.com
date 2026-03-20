<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['event_id'])) {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = mysqli_real_escape_string($conn, $_POST['event_id']);

// Insert or ignore if already recorded
$query = "INSERT IGNORE INTO event_usage (user_id, event_id) VALUES ('$user_id', '$event_id')";
mysqli_query($conn, $query);

echo json_encode(['status' => 'success']);
?>