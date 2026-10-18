<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user = getCurrentUser($conn);
$favorites_count = countFavorites($conn, $_SESSION['user_id']);

// Ambil daftar favorit
$favorites_sql = "SELECT r.*, f.id as favorite_id FROM recipes r 
                  INNER JOIN favorites f ON r.id = f.recipe_id 
                  WHERE f.user_id = ? 
                  ORDER BY f.created_at DESC";
$stmt = $conn->prepare($favorites_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$favorites_result = $stmt->get_result();

$profile_picture = getUserProfilePictureUrl($conn, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profil Saya - YummyTaste</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="apple-touch-icon" href="assets/logo.png">
    
    <!-- Font Awesome untuk Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }
        
        /* Desktop Navbar */
        .navbar-desktop {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            color: #f97316;
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
            color: #6b7280;
            transition: color 0.2s;
        }
        
        .nav-links a:hover {
            color: #f97316;
        }
        
        /* Profile Nav Link dengan Avatar */
        .profile-nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #6b7280;
            transition: color 0.2s;
        }
        
        .profile-nav-link:hover {
            color: #f97316;
        }
        
        .nav-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fed7aa;
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
        
        /* Mobile Navbar */
        .navbar-mobile {
            display: none;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            color: #f97316;
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
            color: #f97316;
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
            gap: 0.5rem;
        }
        
        .mobile-nav-links a {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #4b5563;
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .mobile-nav-links a:hover {
            background-color: #fed7aa;
            color: #ea580c;
        }
        
        .mobile-user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background-color: #f9fafb;
            border-radius: 12px;
            margin-bottom: 0.5rem;
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
        
        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
        }
        
        .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f97316;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: white;
            border: 4px solid #fed7aa;
        }
        
        .user-info h1 {
            font-size: 1.8rem;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .username {
            color: #6b7280;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .email {
            color: #6b7280;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .member-badge {
            display: inline-block;
            background: #dcfce7;
            color: #16a34a;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-top: 0.5rem;
        }
        
        .stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .stat {
            text-align: center;
            background: #f9fafb;
            padding: 0.75rem 1.5rem;
            border-radius: 16px;
            min-width: 100px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #f97316;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .edit-profile-btn {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background-color: #f97316;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .edit-profile-btn:hover {
            background-color: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3);
        }
        
        .section-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #fed7aa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .section-title-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Recipe Grid - 3 kolom untuk Desktop, 2 kolom untuk Mobile */
        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        
        .recipe-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        
        .recipe-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        .recipe-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        
        .recipe-info {
            padding: 1rem;
            padding-right: 3rem;
        }
        
        .recipe-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.95rem;
        }
        
        .recipe-country {
            font-size: 0.75rem;
            color: #f97316;
        }
        
        /* Tombol Hapus Favorit dengan Icon Font Awesome */
        .delete-fav-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }
        
        .delete-fav-btn i {
            pointer-events: none;
        }
        
        .delete-fav-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
        
        /* Modal Konfirmasi Hapus */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            max-width: 320px;
            width: 90%;
            text-align: center;
            animation: modalPop 0.3s ease-out;
        }
        
        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .modal-content p {
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .modal-btn-confirm {
            background: #dc2626;
            color: white;
        }
        
        .modal-btn-confirm:hover {
            background: #b91c1c;
        }
        
        .modal-btn-cancel {
            background: #e5e7eb;
            color: #4b5563;
        }
        
        .modal-btn-cancel:hover {
            background: #d1d5db;
        }
        
        .empty-favorites {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 16px;
            color: #6b7280;
        }
        
        .empty-favorites a {
            color: #f97316;
            text-decoration: none;
        }
        
        .footer {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 0.8rem;
            margin-top: 2rem;
        }
        
        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: #f97316;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            
            .edit-profile-btn {
                position: static;
                margin-top: 1rem;
                align-self: center;
            }
            
            .stats {
                justify-content: center;
            }
            
            .stat {
                padding: 0.5rem 1rem;
                min-width: 80px;
            }
            
            .stat-number {
                font-size: 1.3rem;
            }
            
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .avatar-img, .avatar-placeholder {
                width: 90px;
                height: 90px;
                font-size: 2.5rem;
            }
            
            .user-info h1 {
                font-size: 1.3rem;
            }
            
            .recipe-info {
                padding-right: 2.5rem;
            }
            
            .recipe-image {
                height: 130px;
            }
            
            .recipe-title {
                font-size: 0.8rem;
            }
            
            .delete-fav-btn {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
                top: 0.5rem;
                right: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .recipe-image {
                height: 110px;
            }
            
            .recipe-title {
                font-size: 0.7rem;
            }
            
            .recipe-country {
                font-size: 0.65rem;
            }
            
            .delete-fav-btn {
                width: 24px;
                height: 24px;
                font-size: 0.65rem;
            }
            
            .recipe-info {
                padding: 0.5rem;
                padding-right: 2rem;
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
                <a href="logout.php" style="color: #dc2626;">Logout</a>
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
                <a href="edit_profile.php">Edit Profil</a>
                <a href="logout.php" style="color: #dc2626;">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="profile-header">
            <div class="avatar">
                <?php if ($profile_picture && file_exists($profile_picture)): ?>
                    <img src="<?php echo $profile_picture; ?>" class="avatar-img" alt="Profile Photo">
                <?php else: ?>
                    <div class="avatar-placeholder">👤</div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h1><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
                <div class="username">Username : <?php echo htmlspecialchars($user['username']); ?></div>
                <div class="email">Email : <?php echo htmlspecialchars($user['email']); ?></div>
                <span class="member-badge">Member sejak <?php echo date('d F Y', strtotime($user['created_at'])); ?></span>
                
                <div class="stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo $favorites_count; ?></div>
                        <div class="stat-label">Resep Favorit</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Bergabung</div>
                    </div>
                </div>
            </div>
            
            <a href="edit_profile.php" class="edit-profile-btn">
                 Edit Profil
            </a>
        </div>
        
        <div class="section-title">
            <div class="section-title-left">
                <i class="fa-solid fa-heart"></i> Resep Favorit Saya
                <?php if ($favorites_count > 0): ?>
                    <span style="font-size: 0.8rem; background: #fed7aa; padding: 0.2rem 0.6rem; border-radius: 20px;"><?php echo $favorites_count; ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="recipe-grid" id="favoritesGrid">
            <?php if ($favorites_result && $favorites_result->num_rows > 0): ?>
                <?php while($recipe = $favorites_result->fetch_assoc()): ?>
                    <div class="recipe-card" data-recipe-id="<?php echo $recipe['id']; ?>" data-favorite-id="<?php echo $recipe['favorite_id']; ?>">
                        <button class="delete-fav-btn" onclick="confirmDelete(<?php echo $recipe['id']; ?>, <?php echo $recipe['favorite_id']; ?>)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                 class="recipe-image"
                                 loading="lazy"
                                 onerror="this.src='https://placehold.co/400x300/e5e7eb/6b7280?text=YummyTaste'">
                            <div class="recipe-info">
                                <div class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                <div class="recipe-country"><?php echo htmlspecialchars($recipe['country']); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-favorites">
                    <p>Kamu belum memiliki resep favorit</p>
                    <p><a href="home.php">Jelajahi resep</a> dan klik ♡ untuk menambah ke favorit</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <p>YummyTaste - Koleksi Resep Makanan dari Seluruh Dunia</p>
    </div>
    
    <!-- Modal Konfirmasi Hapus -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p><i class="fas fa-trash-alt" style="color: #dc2626; margin-right: 0.5rem;"></i> Apakah Anda yakin ingin menghapus resep ini dari favorit?</p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-confirm" onclick="deleteFavorite()">Ya, Hapus</button>
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">Batal</button>
            </div>
        </div>
    </div>
    
    <script>
        let deleteRecipeId = null;
        let deleteFavoriteId = null;
        
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileNavContent');
            menu.classList.toggle('active');
        }
        
        function confirmDelete(recipeId, favoriteId) {
            deleteRecipeId = recipeId;
            deleteFavoriteId = favoriteId;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('show');
            deleteRecipeId = null;
            deleteFavoriteId = null;
        }
        
        function deleteFavorite() {
            if (!deleteFavoriteId) return;
            
            // Tampilkan loading di tombol
            const confirmBtn = document.querySelector('.modal-btn-confirm');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<span class="loading"></span>';
            confirmBtn.disabled = true;
            
            fetch('remove_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'favorite_id=' + deleteFavoriteId + '&recipe_id=' + deleteRecipeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hapus card dari grid
                    const card = document.querySelector(`.recipe-card[data-recipe-id="${deleteRecipeId}"]`);
                    if (card) {
                        card.remove();
                    }
                    
                    // Update jumlah favorit
                    const favoritesCountSpan = document.querySelector('.stat-number');
                    const badgeSpan = document.querySelector('.section-title-left span');
                    
                    if (favoritesCountSpan) {
                        let currentCount = parseInt(favoritesCountSpan.innerText);
                        if (!isNaN(currentCount) && currentCount > 0) {
                            favoritesCountSpan.innerText = currentCount - 1;
                        }
                    }
                    
                    if (badgeSpan) {
                        let currentBadge = parseInt(badgeSpan.innerText);
                        if (!isNaN(currentBadge) && currentBadge > 0) {
                            if (currentBadge - 1 === 0) {
                                badgeSpan.remove();
                            } else {
                                badgeSpan.innerText = currentBadge - 1;
                            }
                        }
                    }
                    
                    // Cek apakah grid kosong
                    const grid = document.getElementById('favoritesGrid');
                    if (grid && grid.children.length === 0) {
                        grid.innerHTML = `
                            <div class="empty-favorites">
                                <p>😢 Kamu belum memiliki resep favorit</p>
                                <p>🔍 <a href="home.php">Jelajahi resep</a> dan klik ♡ untuk menambah ke favorit</p>
                            </div>
                        `;
                    }
                    
                    closeModal();
                } else {
                    alert(data.message || 'Gagal menghapus favorit');
                    closeModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
                closeModal();
            })
            .finally(() => {
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }
        
        // Tutup modal jika klik di luar modal
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>