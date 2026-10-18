<?php
// get_recipe.php (sama seperti sebelumnya)
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "yummytaste";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM recipes WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'title' => $recipe['title'],
            'country' => $recipe['country'],
            'ingredients' => $recipe['ingredients'],
            'instructions' => $recipe['instructions'],
            'image_url' => $recipe['image_url']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Recipe not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
}

$conn->close();
?>