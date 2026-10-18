<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? true : false; // Tambahan: cek checkbox
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi';
    } else {
        $sql = "SELECT id, username, password, full_name FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                
                // ========== TAMBAHAN: REMEMBER ME ==========
                if ($remember_me) {
                    setRememberMe($row['id'], $conn);
                }
                // ========== END REMEMBER ME ==========
                
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $row['id']);
                $update_stmt->execute();
                
                header("Location: home.php");
                exit;
            } else {
                $error = 'Password salah';
            }
        } else {
            $error = 'Username tidak ditemukan';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - YummyTaste</title>
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
            background-image: url('https://static.vecteezy.com/system/resources/thumbnails/037/236/579/small/ai-generated-beautuful-fast-food-background-with-copy-space-free-photo.jpg');
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
        
        .login-container {
            max-width: 420px;
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
        
        input[type="text"],
        input[type="password"] {
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
        
        /* Remember Me Checkbox Style */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #f97316;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: normal;
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
        
        .guest-btn {
            background-color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .guest-btn:hover {
            background-color: #4b5563;
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
        
        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }
        
        .register-prompt {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.85rem;
        }
        
        /* Responsive Mobile */
        @media (max-width: 480px) {
            body {
                padding: 0.75rem;
            }
            
            .login-container {
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
            
            .info, .register-prompt {
                font-size: 0.75rem;
            }
            
            .checkbox-group label {
                font-size: 0.75rem;
            }
        }
        
        /* Tablet */
        @media (min-width: 481px) and (max-width: 768px) {
            .login-container {
                max-width: 380px;
                padding: 1.75rem;
            }
            
            .logo-image {
                max-width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <!-- Logo dari folder assets -->
            <img src="assets/logo-png.png" alt="YummyTaste Logo" class="logo-image" 
                 onerror="this.onerror=null; this.style.display='none';">
            <div class="logo-text">Yummy<span>Taste</span></div>
        </div>
        <h2>Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username atau Email</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <!-- ========== TAMBAHAN: REMEMBER ME CHECKBOX ========== -->
            <div class="checkbox-group">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Ingat Saya</label>
            </div>
            <!-- ========== END REMEMBER ME ========== -->
            
            <button type="submit">Login</button>
        </form>
        
        <button class="guest-btn" onclick="window.location.href='home.php'">Lanjut sebagai Tamu</button>
        
        <hr>

        
        <div class="register-prompt">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </div>
        
        <a href="home.php" class="back-link">← Kembali ke Beranda</a>
    </div>
</body>
</html>