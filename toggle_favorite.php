<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Harap login terlebih dahulu']);
    exit;
}

$user_id = $_SESSION['user_id'];
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($recipe_id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID resep tidak valid']);
    exit;
}

if ($action === 'add') {
    $sql = "INSERT IGNORE INTO favorites (user_id, recipe_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $recipe_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'action' => 'added']);
} elseif ($action === 'remove') {
    $sql = "DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $recipe_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'action' => 'removed']);
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
}
?>