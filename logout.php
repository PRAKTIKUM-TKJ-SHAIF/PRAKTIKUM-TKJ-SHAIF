<?php
require_once 'config.php';

// Hapus remember me token jika ada
if (isset($_SESSION['user_id'])) {
    clearRememberMe($_SESSION['user_id'], $conn);
}

// Hapus session
session_destroy();

// Redirect ke home
header("Location: home.php");
exit;
?>