<?php
session_start(); // Bắt đầu session để truy cập các biến session

// Lấy thông báo thành công từ session (nếu có, ví dụ sau khi đăng ký)
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']); // Xóa sau khi lấy

// Lấy lỗi đăng nhập từ session (nếu có, từ process_login.php)
$login_errors = $_SESSION['login_errors'] ?? [];
unset($_SESSION['login_errors']);

// Lấy dữ liệu username cũ (nếu có lỗi đăng nhập)
$old_username = $_SESSION['old_login_username'] ?? '';
unset($_SESSION['old_login_username']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FCE4EC; /* Consistent light Pink background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .font-baloo {
            font-family: 'Baloo 2', cursive;
        }
        .login-card {
            background-color: white;
            border-radius: 1rem; /* 16px */
            padding: 2rem 2.5rem; /* Adjusted padding */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px; /* Slightly wider for better spacing */
        }
        .form-input {
            border-radius: 0.5rem; /* 8px */
            border: 1px solid #D1D5DB; /* Gray 300 */
            padding: 0.75rem 1rem; /* Increased padding */
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-size: 0.95rem;
        }
        .form-input:focus {
            border-color: #EC4899; /* Pink 500 */
            outline: none;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2); /* Pink focus ring */
        }
        .form-input.is-invalid { /* Style for invalid input */
            border-color: #EF4444; /* Red 500 */
        }
        .form-input.is-invalid:focus {
            border-color: #EF4444; /* Red 500 */
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); /* Red focus ring */
        }
        .invalid-feedback { /* Style for error message */
            color: #EF4444; /* Red 500 */
            font-size: 0.875rem; /* 14px */
            margin-top: 0.25rem; /* 4px */
        }
        .btn-submit-login {
            background-color: #3B82F6; /* Blue 500 */
            color: white;
            font-weight: 600;
            padding: 0.85rem 1.5rem; /* Adjusted padding */
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, transform 0.1s ease;
            width: 100%;
            font-size: 1rem;
            box-shadow: 0 3px 6px rgba(59,130,246,0.2);
        }
        .btn-submit-login:hover {
            background-color: #2563EB; /* Blue 600 */
            transform: translateY(-1px);
        }
        .btn-guest-play {
            background-color: #10B981; /* Emerald 500 */
            color: white;
            font-weight: 600;
            padding: 0.85rem 1.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, transform 0.1s ease;
            width: 100%;
            font-size: 1rem;
            margin-top: 0.75rem; /* 12px */
            box-shadow: 0 3px 6px rgba(16,185,129,0.2);
        }
        .btn-guest-play:hover {
            background-color: #059669; /* Emerald 600 */
            transform: translateY(-1px);
        }
        .btn-google-login {
            background-color: #FFFFFF;
            color: #4A5568; /* Gray-700 */
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: 1px solid #D1D5DB; /* Gray-300 */
            transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.1s ease;
            width: 100%;
            font-size: 1rem;
            margin-top: 1rem; /* Space above Google button */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-google-login img { /* For Google icon */
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
        }
        .btn-google-login:hover {
            background-color: #F9FAFB; /* Gray-50 */
            border-color: #9CA3AF; /* Gray-400 */
            transform: translateY(-1px);
        }
        .link-style {
            font-weight: 500;
            color: #EC4899; /* Pink 500 */
            transition: color 0.3s ease;
        }
        .link-style:hover {
            color: #DB2777; /* Pink 600 */
            text-decoration: underline;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem; /* 24px */
        }
        .logo-text-login {
            font-family: 'Baloo 2', cursive;
            font-weight: 800; /* Extra bold */
            font-size: 2.5rem; /* Larger logo */
            color: #EC4899; /* Pink 500 */
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #9CA3AF; /* Gray 400 */
            font-size: 0.875rem; /* 14px */
            margin: 1.25rem 0; /* Adjusted margin */
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E5E7EB; /* Gray 200 */
        }
        .divider:not(:empty)::before {
            margin-right: .5em;
        }
        .divider:not(:empty)::after {
            margin-left: .5em;
        }
        .alert-success {
            background-color: #D1FAE5; /* Green 100 */
            border: 1px solid #A7F3D0; /* Green 200 */
            color: #065F46; /* Green 700 */
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background-color: #FEE2E2; /* Red 100 */
            border: 1px solid #FECACA; /* Red 200 */
            color: #B91C1C; /* Red 700 */
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <a href="index.php" class="logo-text-login">Toán Vui</a>
        </div>

        <h1 class="font-baloo text-2xl font-bold text-gray-800 text-center mb-1">
            Đăng Nhập Tài Khoản
        </h1>
        <p class="text-center text-gray-500 text-sm mb-6">
            Chào mừng bạn trở lại!
        </p>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($login_errors['general'])): ?>
            <div class="alert-error" role="alert">
                <?php echo htmlspecialchars($login_errors['general']); ?>
            </div>
        <?php endif; ?>


        <form action="process_login.php" method="POST" class="space-y-5">
            <div>
                <label for="username_login" class="block text-sm font-medium text-gray-700 mb-1.5">Tên đăng nhập hoặc Email</label>
                <input type="text" id="username_login" name="username" required
                       class="form-input <?php echo isset($login_errors['username']) ? 'is-invalid' : ''; ?>"
                       placeholder="Nhập tên đăng nhập hoặc email"
                       value="<?php echo htmlspecialchars($old_username); ?>">
                <?php if (isset($login_errors['username'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($login_errors['username']); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label for="password_login" class="block text-sm font-medium text-gray-700">Mật khẩu</label>
                    <a href="#" class="text-xs link-style">Quên mật khẩu?</a>
                </div>
                <input type="password" id="password_login" name="password" required
                       class="form-input <?php echo isset($login_errors['password']) ? 'is-invalid' : ''; ?>"
                       placeholder="Nhập mật khẩu">
                <?php if (isset($login_errors['password'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($login_errors['password']); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <button type="submit" class="btn-submit-login">
                    Đăng Nhập
                </button>
            </div>
        </form>

        <div class="divider">hoặc</div>

        <a href="google_auth_redirect.php" class="btn-google-login">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Google_%22G%22_logo.svg/768px-Google_%22G%22_logo.svg.png" alt="Google icon">
            Đăng nhập với Google
        </a>

        <a href="game.php?action=guest_play" class="btn-guest-play block text-center">
            Chơi Với Tư Cách Khách
        </a>

        <p class="text-center text-sm text-gray-600 mt-8">
            Chưa có tài khoản? <a href="register.php" class="link-style">Đăng ký ngay</a>
        </p>
        <p class="text-center text-xs text-gray-400 mt-2">
            <a href="index.php" class="hover:underline">&larr; Quay lại trang chủ</a>
        </p>
    </div>

</body>
</html>
