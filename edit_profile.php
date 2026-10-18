<?php
require_once 'config.php';

// Jika belum login, redirect ke login
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user = getCurrentUser($conn);
$error = '';
$success = '';

// Fungsi untuk mendapatkan foto profil

$profile_picture_url = getUserProfilePictureUrl($conn, $_SESSION['user_id']);

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Username
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['username']);
        
        if (strlen($new_username) < 3) {
            $error = 'Username minimal 3 karakter';
        } else {
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $new_username, $_SESSION['user_id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Username sudah digunakan';
            } else {
                $update_sql = "UPDATE users SET username = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_username, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $_SESSION['username'] = $new_username;
                    $success = 'Username berhasil diupdate';
                    $user = getCurrentUser($conn);
                } else {
                    $error = 'Gagal mengupdate username';
                }
            }
        }
    }
    
    // Update Nama Lengkap
    if (isset($_POST['update_fullname'])) {
        $new_fullname = trim($_POST['full_name']);
        
        $update_sql = "UPDATE users SET full_name = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_fullname, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['full_name'] = $new_fullname;
            $success = 'Nama lengkap berhasil diupdate';
            $user = getCurrentUser($conn);
        } else {
            $error = 'Gagal mengupdate nama lengkap';
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $pass_sql = "SELECT password FROM users WHERE id = ?";
        $pass_stmt = $conn->prepare($pass_sql);
        $pass_stmt->bind_param("i", $_SESSION['user_id']);
        $pass_stmt->execute();
        $pass_result = $pass_stmt->get_result();
        $pass_row = $pass_result->fetch_assoc();
        
        if (!password_verify($current_password, $pass_row['password'])) {
            $error = 'Password saat ini salah';
        } elseif (strlen($new_password) < 5) {
            $error = 'Password baru minimal 5 karakter';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password baru tidak cocok';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                $success = 'Password berhasil diupdate';
            } else {
                $error = 'Gagal mengupdate password';
            }
        }
    }
    
    // Update Foto Profil
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Format foto tidak support. Gunakan JPG, PNG, GIF, atau WEBP';
        } elseif ($file_size > $max_size) {
            $error = 'Ukuran foto maksimal 2MB';
        } else {
            // Buat folder uploads jika belum ada
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // Buat nama file unik
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = 'uploads/' . $new_filename;
            
            // Hapus foto lama jika bukan default
            $old_photo = $user['profile_picture'] ?? 'default-avatar.png';
            if ($old_photo != 'default-avatar.png' && file_exists('uploads/' . $old_photo)) {
                unlink('uploads/' . $old_photo);
            }
            
            // Upload foto baru
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_filename, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $success = 'Foto profil berhasil diupdate';
                    $user = getCurrentUser($conn);
                    $profile_picture_url = getUserProfilePictureUrl($conn, $_SESSION['user_id']);
                } else {
                    $error = 'Gagal menyimpan foto ke database';
                }
            } else {
                $error = 'Gagal mengupload foto';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit Profil - YummyTaste</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #fed7aa;
            flex-wrap: wrap;
        }
        
        .current-photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f97316;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            background: #f3f4f6;
        }
        
        .photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #ea580c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            border: 4px solid #fed7aa;
        }
        
        .profile-info h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .profile-info p {
            color: #6b7280;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #f97316;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        input:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: #f97316;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #ea580c;
            transform: translateY(-1px);
        }
        
        .error {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .success {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .file-info {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f3f4f6;
            color: #4b5563;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 1rem;
        }
        
        .back-link:hover {
            background: #e5e7eb;
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
            
            .profile-card {
                padding: 1.25rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .btn {
                width: 100%;
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
                <a href="profile.php" class="profile-nav-link">
                    <?php if ($profile_picture_url && file_exists($profile_picture_url)): ?>
                        <img src="<?php echo $profile_picture_url; ?>" class="nav-avatar" alt="Profile">
                    <?php else: ?>
                        <span class="nav-avatar-placeholder">👤</span>
                    <?php endif; ?>
                    Profil Saya
                </a>
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
                <div class="mobile-user-info">
                    <?php if ($profile_picture_url && file_exists($profile_picture_url)): ?>
                        <img src="<?php echo $profile_picture_url; ?>" class="mobile-user-avatar" alt="Profile">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f97316; display: flex; align-items: center; justify-content: center; color: white;">👤</div>
                    <?php endif; ?>
                    <div class="mobile-user-details">
                        <div class="mobile-user-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                        <a href="profile.php" style="font-size: 0.7rem; color: #f97316; text-decoration: none;">Lihat Profil →</a>
                    </div>
                </div>
                <a href="logout.php" style="color: #dc2626;">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <a href="profile.php" class="back-link">← Kembali ke Profil</a>
        
        <div class="profile-card">
            <div class="profile-header">
                <div>
                    <?php if ($profile_picture_url && file_exists($profile_picture_url)): ?>
                        <img src="<?php echo $profile_picture_url; ?>" class="current-photo-preview" alt="Profile Photo">
                    <?php else: ?>
                        <div class="photo-placeholder">👤</div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
                    <p>Username : <?php echo htmlspecialchars($user['username']); ?></p>
                    <p>Email :  <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Form Update Foto Profil -->
            <div class="form-section">
                <div class="form-title">Foto Profil</div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Ganti Foto Profil</label>
                        <input type="file" name="profile_picture" accept="image/*">
                        <div class="file-info">Format: JPG, PNG, GIF, WEBP. Maksimal 2MB</div>
                    </div>
                    <button type="submit" name="update_photo" class="btn btn-primary">Upload Foto</button>
                </form>
            </div>
            
            <!-- Form Update Username -->
            <div class="form-section">
                <div class="form-title">Username</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Username Baru</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <button type="submit" name="update_username" class="btn btn-primary">Update Username</button>
                </form>
            </div>
            
            <!-- Form Update Nama Lengkap -->
            <div class="form-section">
                <div class="form-title">Nama Lengkap</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="update_fullname" class="btn btn-primary">Update Nama</button>
                </form>
            </div>
            
            <!-- Form Update Password -->
            <div class="form-section">
                <div class="form-title">Password</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Password Saat Ini</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="new_password" required>
                        <div class="file-info">Minimal 5 karakter</div>
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileNavContent');
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>