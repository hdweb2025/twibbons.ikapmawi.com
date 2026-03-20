<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_FILES['photo'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$target_dir = "uploads/photos/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$file = $_FILES['photo'];
$filename = $_SESSION['hp'] . '_' . time() . '_' . basename($file["name"]);
$target_file = $target_dir . $filename;

if (move_uploaded_file($file["tmp_name"], $target_file)) {
    echo json_encode(['filePath' => $target_file]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload photo.']);
}
?>