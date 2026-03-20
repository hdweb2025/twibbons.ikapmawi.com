<?php
$host = "localhost";
$user = "u573188607_twibbonikap";
$pass = "Jt0N*CD~1G!k";
$db   = "u573188607_twibbonikap";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>