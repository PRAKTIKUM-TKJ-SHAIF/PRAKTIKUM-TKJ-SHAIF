<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Harap login terlebih dahulu']);
    exit;
}

$user_id = $_SESSION['user_id'];
$favorite_id = isset($_POST['favorite_id']) ? (int)$_POST['favorite_id'] : 0;
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : 0;

if ($favorite_id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID favorit tidak valid']);
    exit;
}

// Pastikan favorit milik user yang login
$check_sql = "SELECT id FROM favorites WHERE id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $favorite_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke favorit ini']);
    exit;
}

// Hapus favorit
$delete_sql = "DELETE FROM favorites WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $favorite_id);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Resep berhasil dihapus dari favorit']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus favorit']);
}

$conn->close();
?>