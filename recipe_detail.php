<?php
require_once 'config.php';

// Ambil ID dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: home.php");
    exit;
}

// Ambil detail resep
$sql = "SELECT * FROM recipes WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: home.php");
    exit;
}

$recipe = $result->fetch_assoc();

// Cek apakah sudah difavoritkan
$is_favorited = false;
if (isLoggedIn()) {
    $is_favorited = isFavorite($conn, $_SESSION['user_id'], $id);
}

// Ambil 4 resep rekomendasi (dari negara yang sama)
$recommend_sql = "SELECT id, title, image_url FROM recipes 
                  WHERE country = '{$conn->real_escape_string($recipe['country'])}' AND id != $id 
                  LIMIT 4";
$recommend_result = $conn->query($recommend_sql);

// Parse ingredients
$ingredients = explode("\n", $recipe['ingredients']);
$ingredients = array_filter($ingredients, function($item) {
    return trim($item) != '';
});

// Parse instructions
$instructions = explode("\n", $recipe['instructions']);
$instructions = array_filter($instructions, function($item) {
    return trim($item) != '';
});

// Extract YouTube ID
$youtube_url = $recipe['youtube_url'] ?? '';
$youtube_id = '';
if (!empty($youtube_url)) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches);
    if (isset($matches[1])) {
        $youtube_id = $matches[1];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - YummyTaste</title>
    
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f9fafb;
            color: #333;
            line-height: 1.6;
        }

        :root {
            --orange: #f97316;
            --orange-dark: #ea580c;
            --orange-light: #fed7aa;
            --white: #ffffff;
            --gray-light: #f9fafb;
            --gray: #6b7280;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1);
        }

        /* Desktop Navbar */
        .navbar-desktop {
            background: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            display: block;
        }

        .navbar-desktop .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--orange);
            text-decoration: none;
        }

        .logo span {
            color: #333;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--gray);
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--orange);
        }

        /* Profile Nav Link dengan Avatar */
        .profile-nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--gray);
            transition: color 0.2s;
        }

        .profile-nav-link:hover {
            color: var(--orange);
        }

        .nav-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--orange-light);
        }

        .nav-avatar-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
        }

        /* Login Link */
        .login-link {
            background-color: var(--orange);
            color: white !important;
            padding: 0.4rem 1rem;
            border-radius: 30px;
        }

        /* Mobile Navbar */
        .navbar-mobile {
            display: none;
            background: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
        }

        .mobile-logo-text {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--orange);
            text-decoration: none;
        }

        .mobile-logo-text span {
            color: #333;
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
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .mobile-nav-content.active {
            display: block;
        }

        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .mobile-nav-links a {
            display: block;
            padding: 0.75rem;
            text-decoration: none;
            color: var(--gray);
            border-radius: 12px;
            transition: all 0.2s;
        }

        .mobile-nav-links a:hover {
            background-color: var(--orange-light);
            color: var(--orange-dark);
        }

        .mobile-user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background-color: var(--gray-light);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .mobile-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .mobile-user-details {
            flex: 1;
        }

        .mobile-user-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--gray-light);
            border: 1px solid #e5e7eb;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--gray);
            transition: all 0.2s;
            margin-bottom: 1rem;
        }

        .back-btn:hover {
            background: var(--orange-light);
            color: var(--orange-dark);
        }

        /* Recipe Detail */
        .recipe-detail {
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .recipe-header {
            position: relative;
            height: 400px;
            overflow: hidden;
        }

        .recipe-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .recipe-header-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            padding: 2rem;
        }

        .recipe-header-overlay h1 {
            color: white;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .recipe-country {
            color: var(--orange-light);
            font-size: 1rem;
        }

        /* Favorite Button */
        .favorite-section {
            padding: 0 2rem;
            margin-top: -1.5rem;
            position: relative;
            z-index: 5;
        }
        
        .favorite-btn {
            background: white;
            border: 2px solid var(--orange);
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            box-shadow: var(--shadow);
        }
        
        .favorite-btn.active {
            background-color: #fee2e2;
            border-color: #dc2626;
            color: #dc2626;
        }
        
        .favorite-btn:hover {
            transform: scale(1.02);
        }
        
        .login-required {
            background: #f3f4f6;
            color: var(--gray);
            border-color: #d1d5db;
        }

        .recipe-content {
            padding: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--gray-light);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .info-card h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--orange);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card ul {
            list-style: none;
            padding-left: 0;
        }

        .info-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .info-card li:before {
            content: "•";
            color: var(--orange);
            font-weight: bold;
        }

        .instructions-list {
            list-style: none;
            padding-left: 0;
        }

        .instructions-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
        }

        .step-number {
            background: var(--orange);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .youtube-section {
            margin-top: 2rem;
            text-align: center;
        }

        .youtube-video {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 16px;
            margin-top: 1rem;
        }

        .youtube-video iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .btn-youtube {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #ff0000;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 1rem;
        }

        /* Recommendations */
        .recommendations {
            margin-top: 2rem;
        }

        .recommendations h2 {
            margin-bottom: 1rem;
        }

        .recommend-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .recommend-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .recommend-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .recommend-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }

        .recommend-card .info {
            padding: 0.75rem;
        }

        .recommend-card .title {
            font-size: 0.875rem;
            font-weight: 600;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .recommend-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .navbar-desktop {
                display: none;
            }
            
            .navbar-mobile {
                display: block;
            }
            
            .container {
                padding: 1rem;
            }
            
            .recipe-header {
                height: 250px;
            }
            
            .recipe-header-overlay h1 {
                font-size: 1.25rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .recipe-content {
                padding: 1rem;
            }
            
            .favorite-section {
                padding: 0 1rem;
            }
            
            .recommend-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .recommend-card img {
                height: 100px;
            }
            
            .recommend-card .title {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .recommend-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            .recommend-card img {
                height: 90px;
            }
            .favorite-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Desktop Navbar -->
    <div class="navbar-desktop">
        <div class="container">
            <a href="home.php" class="logo">Yummy<span>Taste</span></a>
            <div class="nav-links">
                <a href="home.php">Beranda</a>
                <?php if (isLoggedIn()): 
                    $profile_pic = getUserProfilePictureUrl($conn, $_SESSION['user_id']);
                ?>
                    <a href="profile.php" class="profile-nav-link">
                        <?php if ($profile_pic && file_exists($profile_pic)): ?>
                            <img src="<?php echo $profile_pic; ?>" class="nav-avatar" alt="Profile">
                        <?php else: ?>
                            <span class="nav-avatar-placeholder">👤</span>
                        <?php endif; ?>
                        Profil Saya
                    </a>
                <?php else: ?>
                    <a href="login.php" class="login-link">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navbar dengan Icon Burger -->
    <div class="navbar-mobile">
        <div class="mobile-header">
            <a href="home.php" class="mobile-logo-text">Yummy<span>Taste</span></a>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
        </div>
        <div class="mobile-nav-content" id="mobileNavContent">
            <div class="mobile-nav-links">
                <a href="home.php">Beranda</a>
                <?php if (isLoggedIn()): 
                    $profile_pic = getUserProfilePictureUrl($conn, $_SESSION['user_id']);
                ?>
                    <div class="mobile-user-info">
                        <?php if ($profile_pic && file_exists($profile_pic)): ?>
                            <img src="<?php echo $profile_pic; ?>" class="mobile-user-avatar" alt="Profile">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #f97316; display: flex; align-items: center; justify-content: center; color: white;">👤</div>
                        <?php endif; ?>
                        <div class="mobile-user-details">
                            <div class="mobile-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                            <a href="profile.php" style="font-size: 0.7rem; color: #f97316; text-decoration: none;">Lihat Profil →</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="javascript:history.back()" class="back-btn">← Kembali</a>

        <div class="recipe-detail">
            <div class="recipe-header">
                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                     onerror="this.src='https://placehold.co/800x400/e5e7eb/6b7280?text=YummyTaste'">
                <div class="recipe-header-overlay">
                    <h1><?php echo htmlspecialchars($recipe['title']); ?></h1>
                    <div class="recipe-country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                </div>
            </div>

            <!-- Favorite Button -->
            <div class="favorite-section">
                <?php if (isLoggedIn()): ?>
                    <button onclick="toggleFavorite(<?php echo $id; ?>)" id="favoriteBtn" class="favorite-btn <?php echo $is_favorited ? 'active' : ''; ?>">
                        <span id="favoriteIcon"><?php echo $is_favorited ? '❤️' : '♡'; ?></span>
                        <span id="favoriteText"><?php echo $is_favorited ? 'Hapus dari Favorit' : 'Tambah ke Favorit'; ?></span>
                    </button>
                <?php else: ?>
                    <a href="login.php" class="favorite-btn login-required">
                        <span>♡</span> Login untuk menambah favorit
                    </a>
                <?php endif; ?>
            </div>

            <div class="recipe-content">
                <div class="info-grid">
                    <!-- Bahan-bahan -->
                    <div class="info-card">
                        <h2>Bahan-bahan</h2>
                        <ul>
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li><?php echo htmlspecialchars(trim($ingredient)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Cara Memasak -->
                    <div class="info-card">
                        <h2>Cara Memasak</h2>
                        <ol class="instructions-list">
                            <?php 
                            $step_num = 1;
                            foreach ($instructions as $instruction): 
                                if (trim($instruction) != ''):
                            ?>
                                <li>
                                    <span class="step-number"><?php echo $step_num++; ?></span>
                                    <span><?php echo htmlspecialchars(trim($instruction)); ?></span>
                                </li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ol>
                    </div>
                </div>

                <!-- YouTube Video -->
                <?php if (!empty($youtube_id)): ?>
                    <div class="youtube-section">
                        <h2>Video Tutorial</h2>
                        <div class="youtube-video">
                            <iframe 
                                src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" 
                                title="<?php echo htmlspecialchars($recipe['title']); ?>"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                        <a href="<?php echo htmlspecialchars($youtube_url); ?>" target="_blank" class="btn-youtube">
                            Tonton di YouTube
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Sumber -->
                <?php if (!empty($recipe['source_url'])): ?>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="<?php echo htmlspecialchars($recipe['source_url']); ?>" target="_blank" style="color: var(--gray); font-size: 0.875rem;">
                            Sumber resep
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rekomendasi Resep Lainnya -->
        <?php if ($recommend_result && $recommend_result->num_rows > 0): ?>
            <div class="recommendations">
                <h2>Resep Lainnya dari <?php echo htmlspecialchars($recipe['country']); ?></h2>
                <div class="recommend-grid">
                    <?php while($rec = $recommend_result->fetch_assoc()): ?>
                        <a href="recipe_detail.php?id=<?php echo $rec['id']; ?>" class="recommend-card">
                            <img src="<?php echo htmlspecialchars($rec['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($rec['title']); ?>"
                                 onerror="this.src='https://placehold.co/300x200/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="info">
                                <div class="title"><?php echo htmlspecialchars($rec['title']); ?></div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>YummyTaste - Koleksi Resep Makanan dari Seluruh Dunia </p>
    </div>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileNavContent');
            menu.classList.toggle('active');
        }
        
        function toggleFavorite(recipeId) {
            const btn = document.getElementById('favoriteBtn');
            const icon = document.getElementById('favoriteIcon');
            const text = document.getElementById('favoriteText');
            const isActive = btn.classList.contains('active');
            
            const action = isActive ? 'remove' : 'add';
            
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'recipe_id=' + recipeId + '&action=' + action
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        btn.classList.add('active');
                        icon.textContent = '❤️';
                        text.textContent = 'Hapus dari Favorit';
                    } else {
                        btn.classList.remove('active');
                        icon.textContent = '♡';
                        text.textContent = 'Tambah ke Favorit';
                    }
                } else {
                    alert(data.message || 'Terjadi kesalahan');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            });
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>