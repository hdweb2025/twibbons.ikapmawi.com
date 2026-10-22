<?php
session_start();
session_destroy();

// Hapus cookie remembered participant (6 bulan)
if (isset($_COOKIE['ikapmawi_participant'])) {
    setcookie('ikapmawi_participant', '', time() - 3600, '/');
}

if (isset($_GET['action']) && $_GET['action'] === 'ajax_reset') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit();
}

// Redirect ke referer atau galeri utama jika bukan AJAX
$redirect = $_SERVER['HTTP_REFERER'] ?? '/';
header("Location: " . $redirect);
exit();
?>
