<?php
// fetch_all_recipes.php - Versi lengkap tanpa error
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "yummytaste";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Cek dan tambahkan kolom youtube_url jika belum ada
$check_column = $conn->query("SHOW COLUMNS FROM recipes LIKE 'youtube_url'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE recipes ADD COLUMN youtube_url TEXT");
    echo "Kolom youtube_url telah ditambahkan ke database<br>\n";
}

echo "Mulai mengambil data resep...<br>\n";
ob_flush();
flush();

$processed_ids = [];

function fetchAPI($url, $conn, &$processed_ids) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['meals']) && is_array($data['meals'])) {
            $count = 0;
            foreach ($data['meals'] as $meal) {
                if ($meal && isset($meal['idMeal']) && !in_array($meal['idMeal'], $processed_ids)) {
                    if (saveRecipe($meal, $conn)) {
                        $processed_ids[] = $meal['idMeal'];
                        $count++;
                    }
                }
            }
            return $count;
        }
    }
    return 0;
}

function saveRecipe($meal, $conn) {
    $idMeal = $conn->real_escape_string($meal['idMeal']);
    $title = $conn->real_escape_string($meal['strMeal']);
    $country = $conn->real_escape_string($meal['strArea'] ?? 'Unknown');
    
    // Kumpulkan ingredients
    $ingredients = [];
    for ($i = 1; $i <= 20; $i++) {
        $ingredient = $meal['strIngredient' . $i] ?? '';
        $measure = $meal['strMeasure' . $i] ?? '';
        if (!empty(trim($ingredient))) {
            if (!empty(trim($measure))) {
                $ingredients[] = trim($measure) . " " . trim($ingredient);
            } else {
                $ingredients[] = trim($ingredient);
            }
        }
    }
    $ingredients_text = $conn->real_escape_string(implode("\n", $ingredients));
    
    $instructions = $conn->real_escape_string($meal['strInstructions'] ?? '');
    $image_url = $conn->real_escape_string($meal['strMealThumb'] ?? '');
    $source_url = $conn->real_escape_string($meal['strSource'] ?? '');
    $youtube_url = $conn->real_escape_string($meal['strYoutube'] ?? '');
    
    // Cek apakah sudah ada berdasarkan id_meal
    $check_sql = "SELECT id FROM recipes WHERE id_meal = '$idMeal'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows == 0) {
        $sql = "INSERT INTO recipes (id_meal, title, country, ingredients, instructions, image_url, source_url, youtube_url) 
                VALUES ('$idMeal', '$title', '$country', '$ingredients_text', '$instructions', '$image_url', '$source_url', '$youtube_url')";
        
        if ($conn->query($sql)) {
            if (!empty($country) && $country != 'Unknown') {
                $country_sql = "INSERT IGNORE INTO countries (name) VALUES ('$country')";
                $conn->query($country_sql);
            }
            return true;
        } else {
            echo "Error: " . $conn->error . "<br>";
            return false;
        }
    }
    return false;
}

// ============ MULAI FETCH DATA ============

echo "1. Mengambil resep berdasarkan huruf A-Z...<br>\n";
for ($char = 'a'; $char <= 'z'; $char++) {
    $url = "https://www.themealdb.com/api/json/v1/1/search.php?f=" . $char;
    $count = fetchAPI($url, $conn, $processed_ids);
    if ($count > 0) {
        echo "   - Huruf '$char': $count resep ditemukan<br>\n";
    }
    ob_flush();
    flush();
    usleep(100000);
}

echo "<br>2. Mengambil resep berdasarkan kategori...<br>\n";
$categories = ['Beef', 'Chicken', 'Dessert', 'Lamb', 'Miscellaneous', 'Pasta', 'Pork', 
               'Seafood', 'Side', 'Starter', 'Vegan', 'Vegetarian', 'Breakfast', 'Goat'];

foreach ($categories as $category) {
    $url = "https://www.themealdb.com/api/json/v1/1/filter.php?c=" . urlencode($category);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['meals']) && is_array($data['meals'])) {
            $category_count = 0;
            foreach ($data['meals'] as $meal_item) {
                if (!in_array($meal_item['idMeal'], $processed_ids)) {
                    $detail_url = "https://www.themealdb.com/api/json/v1/1/lookup.php?i=" . $meal_item['idMeal'];
                    $detail_ch = curl_init();
                    curl_setopt($detail_ch, CURLOPT_URL, $detail_url);
                    curl_setopt($detail_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($detail_ch, CURLOPT_SSL_VERIFYPEER, false);
                    $detail_response = curl_exec($detail_ch);
                    curl_close($detail_ch);
                    
                    if ($detail_response) {
                        $detail_data = json_decode($detail_response, true);
                        if ($detail_data && isset($detail_data['meals'][0])) {
                            if (saveRecipe($detail_data['meals'][0], $conn)) {
                                $processed_ids[] = $meal_item['idMeal'];
                                $category_count++;
                            }
                        }
                    }
                    usleep(50000);
                }
            }
            if ($category_count > 0) {
                echo "   - Kategori '$category': $category_count resep baru<br>\n";
            }
        }
    }
    ob_flush();
    flush();
}

echo "<br>3. Mengambil resep berdasarkan area/negara...<br>\n";
$areas = ['American', 'British', 'Canadian', 'Chinese', 'Croatian', 'Dutch', 'Egyptian', 
          'French', 'Greek', 'Indian', 'Irish', 'Italian', 'Jamaican', 'Japanese', 
          'Kenyan', 'Malaysian', 'Mexican', 'Moroccan', 'Polish', 'Portuguese', 
          'Russian', 'Spanish', 'Thai', 'Tunisian', 'Turkish', 'Ukrainian', 'Vietnamese'];

foreach ($areas as $area) {
    $url = "https://www.themealdb.com/api/json/v1/1/filter.php?a=" . urlencode($area);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['meals']) && is_array($data['meals'])) {
            $area_count = 0;
            foreach ($data['meals'] as $meal_item) {
                if (!in_array($meal_item['idMeal'], $processed_ids)) {
                    $detail_url = "https://www.themealdb.com/api/json/v1/1/lookup.php?i=" . $meal_item['idMeal'];
                    $detail_ch = curl_init();
                    curl_setopt($detail_ch, CURLOPT_URL, $detail_url);
                    curl_setopt($detail_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($detail_ch, CURLOPT_SSL_VERIFYPEER, false);
                    $detail_response = curl_exec($detail_ch);
                    curl_close($detail_ch);
                    
                    if ($detail_response) {
                        $detail_data = json_decode($detail_response, true);
                        if ($detail_data && isset($detail_data['meals'][0])) {
                            if (saveRecipe($detail_data['meals'][0], $conn)) {
                                $processed_ids[] = $meal_item['idMeal'];
                                $area_count++;
                            }
                        }
                    }
                    usleep(50000);
                }
            }
            if ($area_count > 0) {
                echo "   - Area '$area': $area_count resep baru<br>\n";
            }
        }
    }
    ob_flush();
    flush();
}

echo "<br>4. Mengambil resep random untuk melengkapi...<br>\n";
for ($i = 0; $i < 20; $i++) {
    $url = "https://www.themealdb.com/api/json/v1/1/random.php";
    $count = fetchAPI($url, $conn, $processed_ids);
    if ($count > 0) {
        echo "   - Random ke-" . ($i+1) . ": $count resep baru<br>\n";
    }
    ob_flush();
    flush();
    usleep(200000);
}

echo "<br><br>========== PROSES SELESAI ==========<br>\n";
echo "Total resep unik yang berhasil disimpan: " . count($processed_ids) . "<br>\n";

$total_sql = "SELECT COUNT(*) as total FROM recipes";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
echo "Total resep di database: " . $total_row['total'] . "<br>\n";

$country_sql = "SELECT COUNT(*) as total FROM countries";
$country_result = $conn->query($country_sql);
$country_row = $country_result->fetch_assoc();
echo "Total negara di database: " . $country_row['total'] . "<br>\n";

$conn->close();
echo "<br><a href='index.php'>Klik disini untuk melihat website</a>";
?>