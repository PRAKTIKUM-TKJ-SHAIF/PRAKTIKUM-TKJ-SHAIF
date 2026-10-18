<?php
require_once 'config.php';

// Fungsi untuk mendapatkan foto profil user
function getUserProfilePicture($conn, $user_id)
{
    $sql = "SELECT profile_picture FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $photo = $row['profile_picture'] ?? 'default-avatar.png';

    if ($photo != 'default-avatar.png' && file_exists('uploads/' . $photo)) {
        return 'uploads/' . $photo;
    }
    return null;
}

// ========== REKOMENDASI RESEP HARIAN DENGAN DATABASE CACHE ==========
function getMealRecommendationId($conn, $meal_type)
{
    $meal_keywords = [
        'breakfast' => ['breakfast', 'pancake', 'oatmeal', 'cereal', 'egg', 'toast', 'smoothie', 'bakery', 'nasi goreng', 'bubur', 'telur'],
        'lunch' => ['salad', 'sandwich', 'soup', 'rice', 'noodle', 'pasta', 'bowl', 'wrap', 'ayam', 'sapi', 'ikan'],
        'afternoon' => ['snack', 'cake', 'cookie', 'pastry', 'tea', 'coffee', 'juice', 'smoothie', 'pisang', 'kolak'],
        'dinner' => ['steak', 'roast', 'casserole', 'curry', 'stew', 'pasta', 'grill', 'bake', 'soup', 'rendang', 'sate']
    ];

    $keywords = $meal_keywords[$meal_type] ?? ['recipe'];
    $keyword = $keywords[array_rand($keywords)];

    $sql = "SELECT id FROM recipes 
            WHERE LOWER(title) LIKE ? 
            ORDER BY RAND() 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $like = "%$keyword%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }

    $fallback_sql = "SELECT id FROM recipes ORDER BY RAND() LIMIT 1";
    $fallback_result = $conn->query($fallback_sql);
    return $fallback_result->fetch_assoc()['id'];
}

function getOrCreateDailyRecommendations($conn)
{
    $today = date('Y-m-d');

    // Cek apakah sudah ada rekomendasi untuk hari ini
    $check_sql = "SELECT * FROM daily_recommendations WHERE date = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $today);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Ambil dari database (sudah ada)
        $row = $check_result->fetch_assoc();
        return [
            'breakfast' => $row['breakfast_id'],
            'lunch' => $row['lunch_id'],
            'afternoon' => $row['afternoon_id'],
            'dinner' => $row['dinner_id']
        ];
    }

    // Generate rekomendasi baru untuk hari ini (pertama kali)
    // Set seed untuk konsistensi dalam satu hari
    $today_seed = date('Y-m-d');
    srand(hexdec(substr(md5($today_seed), 0, 8)));

    $breakfast_id = getMealRecommendationId($conn, 'breakfast');
    $lunch_id = getMealRecommendationId($conn, 'lunch');
    $afternoon_id = getMealRecommendationId($conn, 'afternoon');
    $dinner_id = getMealRecommendationId($conn, 'dinner');

    srand();

    // Simpan ke database
    $insert_sql = "INSERT INTO daily_recommendations (date, breakfast_id, lunch_id, afternoon_id, dinner_id) 
                   VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("siiii", $today, $breakfast_id, $lunch_id, $afternoon_id, $dinner_id);
    $insert_stmt->execute();

    return [
        'breakfast' => $breakfast_id,
        'lunch' => $lunch_id,
        'afternoon' => $afternoon_id,
        'dinner' => $dinner_id
    ];
}

// Ambil rekomendasi harian dari database cache
$daily_recs = getOrCreateDailyRecommendations($conn);

// Ambil detail resep berdasarkan ID
$breakfast = $conn->query("SELECT id, title, country, image_url FROM recipes WHERE id = {$daily_recs['breakfast']}")->fetch_assoc();
$lunch = $conn->query("SELECT id, title, country, image_url FROM recipes WHERE id = {$daily_recs['lunch']}")->fetch_assoc();
$afternoon = $conn->query("SELECT id, title, country, image_url FROM recipes WHERE id = {$daily_recs['afternoon']}")->fetch_assoc();
$dinner = $conn->query("SELECT id, title, country, image_url FROM recipes WHERE id = {$daily_recs['dinner']}")->fetch_assoc();

// Ambil resep untuk carousel rekomendasi lainnya (12 resep)
$carousel_sql = "SELECT id, title, country, image_url FROM recipes ORDER BY RAND() LIMIT 12";
$carousel_result = $conn->query($carousel_sql);
$carousel_recipes = [];
while ($row = $carousel_result->fetch_assoc()) {
    $carousel_recipes[] = $row;
}
// ========== AKHIR REKOMENDASI ==========

// Get filter country
$selected_country = isset($_GET['country']) ? $_GET['country'] : '';

// Get search keyword
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination variables
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = "";

if ($selected_country != '') {
    $where_clauses[] = "country = ?";
    $params[] = $selected_country;
    $types .= "s";
}

if ($search_keyword != '') {
    $where_clauses[] = "(title LIKE ? OR ingredients LIKE ? OR country LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM recipes $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get data with LIMIT
$sql = "SELECT * FROM recipes $where_sql ORDER BY title ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get countries for filter dropdown
$country_sql = "SELECT DISTINCT country FROM recipes WHERE country != '' ORDER BY country";
$country_result = $conn->query($country_sql);
$countries = [];
while ($row = $country_result->fetch_assoc()) {
    $countries[] = $row['country'];
}

// Hitung total resep
$total_resep = $conn->query("SELECT COUNT(*) as total FROM recipes")->fetch_assoc();
$total_negara = $conn->query("SELECT COUNT(*) as total FROM countries")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>YummyTaste - Koleksi Resep Makanan</title>

    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #fefefe;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
        }

        :root {
            --orange: #f97316;
            --orange-dark: #ea580c;
            --orange-light: #fed7aa;
            --white: #ffffff;
            --gray-light: #f9fafb;
            --gray: #6b7280;
            --gray-dark: #4b5563;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Desktop Layout */
        .desktop-layout {
            display: flex;
            min-height: 100vh;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Navbar Desktop (Kiri) */
        .navbar-desktop {
            width: 280px;
            background-color: var(--white);
            border-right: 1px solid #e5e7eb;
            padding: 2rem 1.5rem;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: var(--shadow);
            z-index: 10;
            display: flex;
            flex-direction: column;
        }

        /* Logo Container Desktop */
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--orange-light);
        }

        .logo-container img {
            max-width: 100%;
            height: auto;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background-color: var(--gray-light);
            min-height: 100vh;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            overflow-x: hidden;
        }

        /* Mobile Navbar */
        .navbar-mobile {
            display: none;
            background-color: var(--white);
            padding: 1rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-logo img {
            height: 40px;
            width: auto;
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--orange);
        }

        .mobile-nav-content {
            display: none;
            padding-top: 1rem;
        }

        .mobile-nav-content.active {
            display: block;
        }

        /* Menu Navigasi */
        .nav-menu {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--orange-light);
        }

        .nav-link {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--gray);
            border-radius: 12px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: var(--orange-light);
            color: var(--orange-dark);
        }

        .nav-link.active {
            background-color: var(--orange);
            color: white;
        }

        .filter-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.1rem;
        }

        /* Dropdown Filter */
        .filter-dropdown {
            margin-bottom: 1.5rem;
        }

        .country-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background-color: var(--white);
            color: var(--gray);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
        }

        .country-select:hover {
            border-color: var(--orange);
        }

        .country-select:focus {
            outline: none;
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-light);
        }

        /* User Menu */
        .user-menu {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background-color: var(--gray-light);
            border-radius: 12px;
        }

        .user-avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            font-size: 1.5rem;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: #333;
        }

        .user-link {
            font-size: 0.7rem;
            color: var(--orange);
            text-decoration: none;
        }

        .login-btn {
            display: block;
            text-align: center;
            background: var(--orange);
            color: white;
            padding: 0.6rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .guest-text {
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray);
        }

        .guest-text a {
            color: var(--orange);
            text-decoration: none;
        }

        .logout-link {
            display: block;
            text-align: center;
            background: #fee2e2;
            color: #dc2626;
            padding: 0.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
        }

        /* ========== CAROUSEL STYLES ========== */
        .carousel-section {
            margin-bottom: 2rem;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .carousel-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }

        .carousel-title i {
            color: var(--orange);
        }

        .carousel-container {
            position: relative;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 0.5rem;
            width: 100%;
        }

        .carousel-container::-webkit-scrollbar {
            height: 6px;
        }

        .carousel-container::-webkit-scrollbar-track {
            background: #e5e7eb;
            border-radius: 10px;
        }

        .carousel-container::-webkit-scrollbar-thumb {
            background: var(--orange);
            border-radius: 10px;
        }

        .carousel-track {
            display: flex;
            gap: 1rem;
            width: fit-content;
        }

        /* Daily Meal Card */
        .daily-card {
            flex: 0 0 calc(25% - 0.75rem);
            min-width: 200px;
            scroll-snap-align: start;
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .daily-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .daily-card-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .daily-card-info {
            padding: 0.75rem;
            text-align: center;
        }

        .daily-card-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .daily-card-country {
            font-size: 0.7rem;
            color: var(--orange);
        }

        .daily-time-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .daily-time-label {
            font-size: 0.7rem;
            color: var(--gray);
            font-weight: 500;
        }

        .daily-time-range {
            font-size: 0.6rem;
            margin-top: 0.2rem;
        }

        .breakfast .daily-time-icon {
            color: #f59e0b;
        }

        .lunch .daily-time-icon {
            color: #10b981;
        }

        .afternoon .daily-time-icon {
            color: #ef4444;
        }

        .dinner .daily-time-icon {
            color: #8b5cf6;
        }

        /* Recommendation Card */
        .rec-card {
            flex: 0 0 200px;
            scroll-snap-align: start;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .rec-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .rec-card img {
            width: 100%;
            height: 130px;
            object-fit: cover;
        }

        .rec-card .info {
            padding: 0.75rem;
        }

        .rec-card .title {
            font-weight: 600;
            font-size: 0.8rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rec-card .country {
            font-size: 0.65rem;
            color: var(--orange);
            margin-top: 0.25rem;
        }

        /* Search Box */
        .search-section {
            margin-bottom: 1.5rem;
            background: var(--white);
            padding: 1rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .search-form {
            display: flex;
            gap: 0.75rem;
            align-items: stretch;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            height: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-light);
        }

        .search-btn {
            background-color: var(--orange);
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-height: 52px;
        }

        .search-btn:hover {
            background-color: var(--orange-dark);
            transform: translateY(-1px);
        }

        .clear-search {
            background-color: var(--gray-light);
            color: var(--gray-dark);
            border: 2px solid #e5e7eb;
            padding: 0.875rem 1.5rem;
            border-radius: 14px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-height: 52px;
        }

        .clear-search:hover {
            background-color: #e5e7eb;
        }

        /* Recipe Grid - 3 kolom desktop */
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            width: 100%;
            max-width: 100%;
        }

        .recipe-card {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .recipe-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .recipe-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .recipe-info {
            padding: 1rem;
        }

        .recipe-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recipe-country {
            color: var(--orange);
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Filter Info */
        .filter-info {
            margin-bottom: 1.5rem;
            padding: 0.875rem 1.25rem;
            background-color: var(--white);
            border-radius: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow);
        }

        .result-count {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .result-count strong {
            color: var(--orange);
        }

        .clear-filter {
            color: var(--orange);
            text-decoration: none;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .clear-filter:hover {
            background-color: var(--orange-light);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            padding: 0 1rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a {
            background-color: var(--white);
            color: var(--gray-dark);
            border: 1px solid #e5e7eb;
        }

        .pagination a:hover {
            background-color: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange-dark);
            transform: translateY(-2px);
        }

        .pagination .active {
            background-color: var(--orange);
            color: white;
            border-color: var(--orange);
            box-shadow: var(--shadow);
        }

        .pagination .disabled {
            background-color: var(--gray-light);
            color: var(--gray);
            border: 1px solid #e5e7eb;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .pagination .dots {
            background: none;
            border: none;
            color: var(--gray);
            min-width: auto;
            padding: 0 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
            background: var(--white);
            border-radius: 16px;
        }

        .stats {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Responsive Tablet */
        @media (max-width: 1024px) {
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .daily-card {
                flex: 0 0 calc(50% - 0.5rem);
                min-width: 180px;
            }
        }

        /* Responsive Mobile */
        @media (max-width: 768px) {
            .desktop-layout {
                display: block;
            }

            .navbar-desktop {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
                max-width: 100%;
            }

            .navbar-mobile {
                display: block;
            }

            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .recipe-image {
                height: 130px;
            }

            .recipe-info {
                padding: 0.65rem;
            }

            .recipe-title {
                font-size: 0.8rem;
            }

            .recipe-country {
                font-size: 0.65rem;
            }

            .search-section {
                padding: 0.875rem;
            }

            .search-form {
                gap: 0.5rem;
            }

            .search-input {
                padding: 0.7rem 0.875rem;
                font-size: 0.85rem;
            }

            .search-btn,
            .clear-search {
                padding: 0.7rem 1rem;
                font-size: 0.85rem;
                min-height: 44px;
            }

            .filter-info {
                flex-direction: column;
                text-align: center;
                padding: 0.75rem;
                gap: 0.5rem;
            }

            .result-count {
                font-size: 0.75rem;
            }

            .pagination {
                gap: 0.35rem;
                margin-top: 1.5rem;
            }

            .pagination a,
            .pagination span {
                min-width: 36px;
                height: 36px;
                padding: 0 0.6rem;
                font-size: 0.8rem;
                border-radius: 10px;
            }

            .mobile-user-menu {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #e5e7eb;
            }

            .daily-card {
                flex: 0 0 calc(50% - 0.5rem);
                min-width: 160px;
            }

            .rec-card {
                flex: 0 0 160px;
            }

            .rec-card img {
                height: 110px;
            }

            .daily-card-image {
                height: 130px;
            }
        }

        @media (max-width: 550px) {

            /* Search tetap horizontal seperti desktop */
            .search-form {
                flex-direction: row;
                gap: 0.4rem;
            }

            .search-input-wrapper {
                flex: 1;
            }

            .search-input {
                padding: 0.6rem 0.75rem;
                font-size: 0.8rem;
            }

            .search-btn,
            .clear-search {
                padding: 0.6rem 0.9rem;
                font-size: 0.75rem;
                min-height: 40px;
                width: auto;
                white-space: nowrap;
            }

            /* Sembunyikan teks tombol, tampilkan icon saja */
            .search-btn span,
            .clear-search span {
                display: none;
            }

            .search-btn:before {
                content: "Cari";
                margin-right: 0;
            }

            .clear-search:before {
                content: "Hapus";
                margin-right: 0;
            }

            /* Hapus atau sesuaikan style lain jika ada konflik */
            .recipe-grid {
                gap: 0.5rem;
            }

            .recipe-image {
                height: 110px;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
                padding: 0 0.5rem;
                font-size: 0.7rem;
                border-radius: 8px;
            }
        }

        @media (max-width: 450px) {
            .recipe-grid {
                gap: 0.5rem;
            }

            .recipe-image {
                height: 110px;
            }

            .recipe-title {
                font-size: 0.7rem;
            }

            .recipe-country {
                font-size: 0.6rem;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
                padding: 0 0.5rem;
                font-size: 0.7rem;
                border-radius: 8px;
            }

            .rec-card {
                flex: 0 0 130px;
            }

            .daily-card-image {
                height: 110px;
            }

            .daily-card-title {
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <div class="navbar-mobile">
        <div class="mobile-header">
            <div class="mobile-logo">
                <img src="assets/logo-removebg-preview.png" alt="YummyTaste">
            </div>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
        </div>
        <div class="mobile-nav-content" id="mobileNavContent">
            <div class="filter-section">
                <div class="nav-menu">
                    <a href="home.php" class="nav-link <?php echo !$selected_country && !$search_keyword ? 'active' : ''; ?>">
                        Semua Resep
                    </a>
                </div>

                <div class="filter-title">Filter Negara</div>
                <div class="filter-dropdown">
                    <select id="mobileCountrySelect" class="country-select" onchange="applyFilterMobile()">
                        <option value="">-- Semua Negara --</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $selected_country == $country ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- User Menu Mobile -->
            <div class="mobile-user-menu">
                <?php if (isLoggedIn()):
                    $profile_pic = getUserProfilePicture($conn, $_SESSION['user_id']);
                ?>
                    <div class="user-info" style="margin-bottom: 0.75rem;">
                        <?php if ($profile_pic && file_exists($profile_pic)): ?>
                            <img src="<?php echo $profile_pic; ?>" class="user-avatar-img">
                        <?php else: ?>
                            <span class="user-avatar-placeholder">👤</span>
                        <?php endif; ?>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                            <a href="profile.php" class="user-link">Profil Saya →</a>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Login / Daftar</a>
                    <div class="guest-text">
                        atau <a href="home.php">lanjut sebagai tamu</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desktop Layout -->
    <div class="desktop-layout">
        <!-- Navbar Desktop -->
        <div class="navbar-desktop">
            <div class="logo-container">
                <img src="assets/logo-removebg-preview.png" alt="YummyTaste" style="width: 100%; max-width: 200px;">
            </div>

            <div class="filter-section">
                <div class="nav-menu">
                    <a href="home.php" class="nav-link <?php echo !$selected_country && !$search_keyword ? 'active' : ''; ?>">
                        Semua Resep
                    </a>
                </div>

                <div class="filter-title">Filter Negara</div>
                <div class="filter-dropdown">
                    <select id="desktopCountrySelect" class="country-select" onchange="applyFilterDesktop()">
                        <option value="">-- Pilih Negara --</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $selected_country == $country ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- User Menu Desktop -->
            <div class="user-menu">
                <?php if (isLoggedIn()):
                    $profile_pic = getUserProfilePicture($conn, $_SESSION['user_id']);
                ?>
                    <div class="user-info">
                        <?php if ($profile_pic && file_exists($profile_pic)): ?>
                            <img src="<?php echo $profile_pic; ?>" class="user-avatar-img">
                        <?php else: ?>
                            <span class="user-avatar-placeholder">👤</span>
                        <?php endif; ?>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                            <a href="profile.php" class="user-link">Profil Saya →</a>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Login / Daftar</a>
                    <div class="guest-text">
                        atau <a href="home.php">lanjut sebagai tamu</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- ========== CAROUSEL REKOMENDASI HARIAN ========== -->
            <div class="carousel-section">
                <div class="carousel-title">
                    <i class="fas fa-calendar-day"></i> Rekomendasi Menu Hari Ini
                    <span style="font-size: 0.8rem; color: var(--gray); font-weight: normal;">
                        <?php echo date('l, d F Y'); ?>
                    </span>
                </div>
                <div class="carousel-container">
                    <div class="carousel-track">
                        <!-- Breakfast -->
                        <a href="recipe_detail.php?id=<?php echo $breakfast['id']; ?>" class="daily-card breakfast">
                            <div class="daily-card-info">
                                <div class="daily-time-icon"><i class="fa-solid fa-sun"></i></div>
                                <div class="daily-time-label">Sarapan</div>
                                <div class="daily-time-range" style="color: #f59e0b;">06:00 - 10:00</div>
                            </div>
                            <img src="<?php echo htmlspecialchars($breakfast['image_url']); ?>"
                                class="daily-card-image"
                                alt="<?php echo htmlspecialchars($breakfast['title']); ?>"
                                onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="daily-card-info">
                                <div class="daily-card-title"><?php echo htmlspecialchars($breakfast['title']); ?></div>
                                <div class="daily-card-country"><?php echo htmlspecialchars($breakfast['country']); ?></div>
                            </div>
                        </a>

                        <!-- Lunch -->
                        <a href="recipe_detail.php?id=<?php echo $lunch['id']; ?>" class="daily-card lunch">
                            <div class="daily-card-info">
                                <div class="daily-time-icon"><i class="fa-solid fa-bowl-food"></i></div>
                                <div class="daily-time-label">Makan Siang</div>
                                <div class="daily-time-range" style="color: #10b981;">12:00 - 14:00</div>
                            </div>
                            <img src="<?php echo htmlspecialchars($lunch['image_url']); ?>"
                                class="daily-card-image"
                                alt="<?php echo htmlspecialchars($lunch['title']); ?>"
                                onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="daily-card-info">
                                <div class="daily-card-title"><?php echo htmlspecialchars($lunch['title']); ?></div>
                                <div class="daily-card-country"><?php echo htmlspecialchars($lunch['country']); ?></div>
                            </div>
                        </a>

                        <!-- Afternoon Snack -->
                        <a href="recipe_detail.php?id=<?php echo $afternoon['id']; ?>" class="daily-card afternoon">
                            <div class="daily-card-info">
                                <div class="daily-time-icon"><i class="fa-solid fa-cheese"></i></div>
                                <div class="daily-time-label">Camilan Sore</div>
                                <div class="daily-time-range" style="color: #ef4444;">15:00 - 17:00</div>
                            </div>
                            <img src="<?php echo htmlspecialchars($afternoon['image_url']); ?>"
                                class="daily-card-image"
                                alt="<?php echo htmlspecialchars($afternoon['title']); ?>"
                                onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="daily-card-info">
                                <div class="daily-card-title"><?php echo htmlspecialchars($afternoon['title']); ?></div>
                                <div class="daily-card-country"><?php echo htmlspecialchars($afternoon['country']); ?></div>
                            </div>
                        </a>

                        <!-- Dinner -->
                        <a href="recipe_detail.php?id=<?php echo $dinner['id']; ?>" class="daily-card dinner">
                            <div class="daily-card-info">
                                <div class="daily-time-icon"><i class="fa-solid fa-moon"></i></div>
                                <div class="daily-time-label">Makan Malam</div>
                                <div class="daily-time-range" style="color: #8b5cf6;">18:00 - 21:00</div>
                            </div>
                            <img src="<?php echo htmlspecialchars($dinner['image_url']); ?>"
                                class="daily-card-image"
                                alt="<?php echo htmlspecialchars($dinner['title']); ?>"
                                onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="daily-card-info">
                                <div class="daily-card-title"><?php echo htmlspecialchars($dinner['title']); ?></div>
                                <div class="daily-card-country"><?php echo htmlspecialchars($dinner['country']); ?></div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ========== CAROUSEL REKOMENDASI LAINNYA ========== -->
            <div class="carousel-section">
                <div class="carousel-title">
                    <i class="fas fa-fire" style="color: var(--orange);"></i> Rekomendasi Lainnya
                    <i class="fas fa-arrow-right" style="font-size: 0.7rem; color: var(--gray);"></i>
                </div>
                <div class="carousel-container">
                    <div class="carousel-track">
                        <?php foreach ($carousel_recipes as $recipe): ?>
                            <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="rec-card">
                                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                    alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                    onerror="this.src='https://placehold.co/300x200/e5e7eb/6b7280?text=YummyTaste'">
                                <div class="info">
                                    <div class="title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                    <div class="country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Koleksi Resep -->
            <h1 style="margin-bottom: 1.25rem; color: #333; font-size: 1.75rem;">Koleksi Resep</h1>

            <!-- Search Form -->
            <div class="search-section">
                <form method="GET" action="home.php" class="search-form" id="searchForm">
                    <div class="search-input-wrapper">
                        <input type="text" name="search" class="search-input"
                            placeholder="Cari resep..."
                            value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                    <button type="submit" class="search-btn">
                        <span>Cari</span>
                    </button>
                    <?php if ($search_keyword != ''): ?>
                        <a href="<?php echo buildResetUrl($selected_country); ?>" class="clear-search">
                            <span>Hapus</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter & Result Info -->
            <div class="filter-info">
                <div class="result-count">
                    Menampilkan <strong><?php echo $result->num_rows; ?></strong> dari <strong><?php echo $total_records; ?></strong> resep
                    <?php if ($search_keyword): ?>
                        <span>"<strong><?php echo htmlspecialchars($search_keyword); ?></strong>"</span>
                    <?php endif; ?>
                    <?php if ($selected_country): ?>
                        <span><strong><?php echo htmlspecialchars($selected_country); ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php if ($selected_country || $search_keyword): ?>
                    <a href="home.php" class="clear-filter">✖ Hapus semua filter</a>
                <?php endif; ?>
            </div>

            <!-- Recipe Grid - 3 kolom desktop -->
            <div class="recipe-grid">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($recipe = $result->fetch_assoc()): ?>
                        <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="recipe-card">
                            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                class="recipe-image"
                                loading="lazy"
                                onerror="this.src='https://placehold.co/600x400/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="recipe-info">
                                <div class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                <div class="recipe-country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">😢</p>
                        <p>Tidak ada resep ditemukan.</p>
                        <?php if ($search_keyword): ?>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">Coba dengan kata kunci lain</p>
                        <?php endif; ?>
                        <a href="home.php" style="color: var(--orange);">Lihat semua resep</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($page - 1, $selected_country, $search_keyword); ?>">
                            « Sebelumnya
                        </a>
                    <?php else: ?>
                        <span class="disabled">« Sebelumnya</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="' . buildPaginationUrl(1, $selected_country, $search_keyword) . '">1</a>';
                        if ($start_page > 2) echo '<span class="dots">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="' . buildPaginationUrl($i, $selected_country, $search_keyword) . '">' . $i . '</a>';
                        }
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="dots">...</span>';
                        echo '<a href="' . buildPaginationUrl($total_pages, $selected_country, $search_keyword) . '">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($page + 1, $selected_country, $search_keyword); ?>">
                            Selanjutnya »
                        </a>
                    <?php else: ?>
                        <span class="disabled">Selanjutnya »</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mobileNavContent').classList.toggle('active');
        }

        function applyFilterDesktop() {
            var country = document.getElementById('desktopCountrySelect').value;
            var currentUrl = new URL(window.location.href);
            var searchValue = document.querySelector('.search-input')?.value || '';

            if (country) {
                currentUrl.searchParams.set('country', country);
            } else {
                currentUrl.searchParams.delete('country');
            }

            if (searchValue) {
                currentUrl.searchParams.set('search', searchValue);
            } else {
                currentUrl.searchParams.delete('search');
            }

            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }

        function applyFilterMobile() {
            var country = document.getElementById('mobileCountrySelect').value;
            var currentUrl = new URL(window.location.href);
            var searchValue = document.querySelector('.search-input')?.value || '';

            if (country) {
                currentUrl.searchParams.set('country', country);
            } else {
                currentUrl.searchParams.delete('country');
            }

            if (searchValue) {
                currentUrl.searchParams.set('search', searchValue);
            } else {
                currentUrl.searchParams.delete('search');
            }

            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }
    </script>
</body>

</html>

<?php
function buildPaginationUrl($page, $country, $search)
{
    $params = [];
    if ($page > 1) $params['page'] = $page;
    if ($country) $params['country'] = $country;
    if ($search) $params['search'] = $search;

    if (empty($params)) {
        return 'home.php';
    }
    return 'home.php?' . http_build_query($params);
}

function buildResetUrl($country)
{
    if ($country) {
        return 'home.php?country=' . urlencode($country);
    }
    return 'home.php';
}

$conn->close();
?>