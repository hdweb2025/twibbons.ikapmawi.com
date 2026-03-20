<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id']) && isset($_POST['event_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $event_id = (int)$_POST['event_id'];

    // Gunakan INSERT IGNORE agar jika alumni yang sama mengunduh berkali-kali, 
    // datanya tidak ganda (karena sudah ada jaminan UNIQUE KEY di tabel event_usage)
    $query = "INSERT IGNORE INTO event_usage (user_id, event_id) VALUES ($user_id, $event_id)";
    mysqli_query($conn, $query);
}
?>