<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
    header("Location: /admin/dashboard");
    exit();
} else {
    header("Location: /admin/login");
    exit();
}
