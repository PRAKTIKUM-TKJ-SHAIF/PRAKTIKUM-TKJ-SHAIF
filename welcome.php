<?php
require_once 'config.php';


// Ambil resep random untuk carousel (ambil 12 resep untuk loop yang lebih panjang)
$random_sql = "SELECT id, title, country, image_url FROM recipes ORDER BY RAND() LIMIT 12";
$random_result = $conn->query($random_sql);
$carousel_recipes = [];
while ($row = $random_result->fetch_assoc()) {
    $carousel_recipes[] = $row;
}

// Jika kurang dari 12, duplikat atau ambil lagi
if (count($carousel_recipes) < 12) {
    $random_sql2 = "SELECT id, title, country, image_url FROM recipes ORDER BY RAND() LIMIT " . (12 - count($carousel_recipes));
    $random_result2 = $conn->query($random_sql2);
    while ($row = $random_result2->fetch_assoc()) {
        $carousel_recipes[] = $row;
    }
}

// Ambil resep random untuk tombol "Coba Resep Random"
$random_recipe_sql = "SELECT id FROM recipes ORDER BY RAND() LIMIT 1";
$random_recipe_result = $conn->query($random_recipe_sql);
$random_recipe = $random_recipe_result->fetch_assoc();
$random_recipe_id = $random_recipe['id'] ?? 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>YummyTaste - Selamat Datang</title>
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
            min-height: 100vh;
            /* Ganti URL background di sini */
            background-image: url('https://st4.depositphotos.com/3664757/27559/i/450/depositphotos_275596086-stock-photo-ingredients-making-traditional-italian-pesto.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }
        
        /* Overlay semi-transparan */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 0;
        }
        
        .welcome-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInDown 0.8s ease-out;
        }
        
        /* Logo dari folder assets */
        .logo-assets {
            max-width: 100px;
            height: auto;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .logo-assets:hover {
            transform: scale(1.05);
        }
        
        .logo-text {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .logo-text span {
            color: #f97316;
        }
        
        .tagline {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.9);
            letter-spacing: 2px;
            margin-top: 0.5rem;
        }
        
        .subtagline {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
            margin-top: 0.25rem;
        }
        
        /* Random Button */
        .random-btn-container {
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        
        .random-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            padding: 1rem 2rem;
            border-radius: 60px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(249, 115, 22, 0.4);
            border: none;
            cursor: pointer;
        }
        
        .random-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(249, 115, 22, 0.5);
        }
        
        /* Infinite Carousel Section */
        .carousel-section {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .carousel-title {
            text-align: center;
            color: white;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .carousel-title span {
            background: rgba(0,0,0,0.5);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            display: inline-block;
        }
        
        /* Infinite Carousel Container */
        .carousel-container {
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .carousel-track {
            display: flex;
            gap: 1.5rem;
            animation: scroll 30s linear infinite;
            width: fit-content;
        }
        
        .carousel-track:hover {
            animation-play-state: paused;
        }
        
        .carousel-slide {
            flex-shrink: 0;
            width: 250px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .carousel-slide:hover {
            transform: translateY(-5px);
        }
        
        .slide-image {
            width: 100%;
            height: 170px;
            object-fit: cover;
        }
        
        .slide-info {
            padding: 1rem;
        }
        
        .slide-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            color: #333;
        }
        
        .slide-country {
            font-size: 0.7rem;
            color: #f97316;
        }
        
        /* Animasi infinite scroll - kanan ke kiri */
        @keyframes scroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        /* Enter Button */
        .enter-btn-container {
            margin-top: 2.5rem;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            text-align: center;
        }
        
        .enter-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .enter-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        .skip-link {
            display: block;
            margin-top: 0.75rem;
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .skip-link:hover {
            color: rgba(255,255,255,0.7);
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .welcome-container {
                padding: 1.5rem;
            }
            
            .logo-assets {
                max-width: 70px;
            }
            
            .logo-text {
                font-size: 1.8rem;
            }
            
            .tagline {
                font-size: 0.7rem;
            }
            
            .subtagline {
                font-size: 0.65rem;
            }
            
            .random-btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.85rem;
            }
            
            .carousel-title {
                font-size: 0.9rem;
            }
            
            .carousel-slide {
                width: 200px;
            }
            
            .slide-image {
                height: 140px;
            }
            
            .slide-title {
                font-size: 0.75rem;
            }
            
            .enter-btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            .carousel-slide {
                width: 170px;
            }
            
            .slide-image {
                height: 120px;
            }
            
            .carousel-track {
                gap: 1rem;
            }
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <!-- Hero Section dengan Logo dari Assets -->
        <div class="hero-section">
            <!-- Logo dari folder assets -->
            <img src="assets/logo-png.png" alt="YummyTaste Logo" class="logo-assets" 
                 onerror="this.onerror=null; this.style.display='none';">
            <div class="logo-text">Yummy<span>Taste</span></div>
        </div>
        
       
        
        <!-- Infinite Carousel Section - Setiap slide langsung ke detail resep -->
        <div class="carousel-section">
            <div class="carousel-title">
                <span>Temukan inspirasi masakan dari berbagai belahan dunia</span>
            </div>
            
            <div class="carousel-container">
                <div class="carousel-track" id="carouselTrack">
                    <!-- Slide asli - langsung ke recipe_detail.php -->
                    <?php foreach ($carousel_recipes as $recipe): ?>
                        <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="carousel-slide">
                            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
                                 class="slide-image"
                                 alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                 loading="lazy"
                                 onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="slide-info">
                                <div class="slide-title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                <div class="slide-country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <!-- Duplikat untuk seamless loop -->
                    <?php foreach ($carousel_recipes as $recipe): ?>
                        <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" class="carousel-slide">
                            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
                                 class="slide-image"
                                 alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                 loading="lazy"
                                 onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="slide-info">
                                <div class="slide-title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                <div class="slide-country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tombol Masuk ke Website -->
        <div class="enter-btn-container">
            <a href="home.php" class="enter-btn">
                Masuk ke Website →
            </a>
            <a href="home.php?skip=1" class="skip-link">
                Lewati & langsung masuk
            </a>
        </div>
    </div>
    
    <script>
        // Loading effect untuk random button
        document.getElementById('randomBtn')?.addEventListener('click', function(e) {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading-spinner"></span> Memuat...';
            btn.style.pointerEvents = 'none';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.pointerEvents = 'auto';
            }, 1000);
        });
        
        // Pause animation on hover
        const track = document.getElementById('carouselTrack');
        if (track) {
            track.addEventListener('mouseenter', () => {
                track.style.animationPlayState = 'paused';
            });
            track.addEventListener('mouseleave', () => {
                track.style.animationPlayState = 'running';
            });
        }
    </script>
</body>
</html>