<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Semua field wajib diisi';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid';
    } elseif (strlen($password) < 5) {
        $error = 'Password minimal 5 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Username atau email sudah terdaftar';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($insert_stmt->execute()) {
                $_SESSION['user_id'] = $insert_stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name ?: $username;
                
                header("Location: home.php");
                exit;
            } else {
                $error = 'Registrasi gagal, silakan coba lagi';
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
    <title>Registrasi - YummyTaste</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            
            /* Ganti URL gambar background di sini dengan gambar Anda */
            background-image: url('https://png.pngtree.com/thumb_back/fh260/background/20231008/pngtree-assorted-fast-food-varieties-displayed-on-a-textured-gray-table-image_13609478.png');
            
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }
        
        /* Overlay gelap agar teks lebih terbaca */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }
        
        .register-container {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
            backdrop-filter: blur(2px);
            animation: fadeInUp 0.5s ease-out;
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
        
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo-image {
            max-width: 80px;
            height: auto;
            margin-bottom: 0.75rem;
            transition: transform 0.3s ease;
        }
        
        .logo-image:hover {
            transform: scale(1.05);
        }
        
        .logo-text {
            font-size: 1.6rem;
            font-weight: bold;
            color: #f97316;
            letter-spacing: -0.5px;
        }
        
        .logo-text span {
            color: #333;
        }
        
        .logo-tagline {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 0.25rem;
            letter-spacing: 1px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
            font-size: 1.35rem;
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
        
        input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }
        
        input:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
        }
        
        button {
            width: 100%;
            background-color: #f97316;
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        
        button:hover {
            background-color: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }
        
        .error {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .success {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .info {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .info a {
            color: #f97316;
            text-decoration: none;
        }
        
        .info a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .back-link:hover {
            color: #f97316;
        }
        
        .password-requirement {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Responsive Mobile */
        @media (max-width: 480px) {
            body {
                padding: 0.75rem;
            }
            
            .register-container {
                padding: 1.5rem;
                max-width: 95%;
            }
            
            .logo-image {
                max-width: 60px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .logo-tagline {
                font-size: 0.6rem;
            }
            
            h2 {
                font-size: 1.15rem;
                margin-bottom: 1.25rem;
            }
            
            label {
                font-size: 0.85rem;
            }
            
            input {
                padding: 0.7rem;
                font-size: 0.9rem;
            }
            
            button {
                padding: 0.7rem;
                font-size: 0.9rem;
            }
            
            .info {
                font-size: 0.75rem;
            }
            
            .password-requirement {
                font-size: 0.65rem;
            }
        }
        
        /* Tablet */
        @media (min-width: 481px) and (max-width: 768px) {
            .register-container {
                max-width: 400px;
                padding: 1.75rem;
            }
            
            .logo-image {
                max-width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <!-- Logo dari folder assets -->
            <img src="assets/logo-png.png" alt="YummyTaste Logo" class="logo-image"
                 onerror="this.onerror=null; this.style.display='none'; document.getElementById('fallbackLogo').style.display='block';">
            <div class="logo-text">Yummy<span>Taste</span></div>
        </div>
        <h2>Daftar Akun Baru</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                <div class="password-requirement">Minimal 3 karakter</div>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                <div class="password-requirement">Opsional</div>
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
                <div class="password-requirement">Minimal 5 karakter</div>
            </div>
            
            <div class="form-group">
                <label>Konfirmasi Password *</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit">Daftar Sekarang</button>
        </form>
        
        <hr>
        
        <div class="info">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
        
        <a href="home.php" class="back-link">← Kembali ke Beranda</a>
    </div>
</body>
</html>