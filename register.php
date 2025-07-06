<?php
session_start(); // Bắt đầu session để truy cập các biến session

// Lấy lỗi từ session (nếu có) và xóa khỏi session
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// Lấy dữ liệu cũ từ session (nếu có) và xóa khỏi session
$old_input = $_SESSION['old_form_input'] ?? [];
unset($_SESSION['old_form_input']);

// Lấy thông báo thành công chung (nếu có, mặc dù ít khi dùng ở trang register sau khi có lỗi)
// $success_message = $_SESSION['success_message'] ?? '';
// unset($_SESSION['success_message']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;700;800&display=swap" rel="stylesheet">
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
        .register-card {
            background-color: white;
            border-radius: 1rem; /* 16px */
            padding: 2rem 2.5rem; /* Adjusted padding */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px; /* Slightly wider for more fields */
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
        .btn-submit-register {
            background-color: #F97316; /* Orange 500 */
            color: white;
            font-weight: 600;
            padding: 0.85rem 1.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, transform 0.1s ease;
            width: 100%;
            font-size: 1rem;
            box-shadow: 0 3px 6px rgba(249,115,22,0.2);
        }
        .btn-submit-register:hover {
            background-color: #EA580C; /* Orange 600 */
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
        .logo-text-register {
            font-family: 'Baloo 2', cursive;
            font-weight: 800;
            font-size: 2.5rem;
            color: #EC4899;
        }
        .alert-error {
            background-color: #FEE2E2; /* Red 100 */
            border: 1px solid #FECACA; /* Red 200 */
            color: #B91C1C; /* Red 700 */
            padding: 0.75rem 1rem;
            border-radius: 0.375rem; /* 6px */
            margin-bottom: 1rem; /* 16px */
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="logo-container">
            <a href="index.php" class="logo-text-register">Toán Vui</a>
        </div>

        <h1 class="font-baloo text-2xl font-bold text-gray-800 text-center mb-1">
            Tạo Tài Khoản Mới
        </h1>
        <p class="text-center text-gray-500 text-sm mb-6">
            Tham gia cộng đồng học toán vui nhộn!
        </p>

        <?php
        // Hiển thị lỗi chung (nếu có)
        if (isset($errors['general'])): ?>
            <div class="alert-error" role="alert">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <form action="process_register.php" method="POST" class="space-y-4">
            <div>
                <label for="fullname" class="block text-sm font-medium text-gray-700 mb-1.5">Họ và Tên</label>
                <input type="text" id="fullname" name="fullname" required
                       class="form-input <?php echo isset($errors['fullname']) ? 'is-invalid' : ''; ?>"
                       placeholder="Ví dụ: Nguyễn Văn An"
                       value="<?php echo htmlspecialchars($old_input['fullname'] ?? ''); ?>">
                <?php if (isset($errors['fullname'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['fullname']); ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label for="username_register" class="block text-sm font-medium text-gray-700 mb-1.5">Tên đăng nhập</label>
                <input type="text" id="username_register" name="username" required
                       class="form-input <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                       placeholder="Chọn tên đăng nhập của bạn"
                       value="<?php echo htmlspecialchars($old_input['username'] ?? ''); ?>">
                <?php if (isset($errors['username'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                <input type="email" id="email" name="email" required
                       class="form-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                       placeholder="Địa chỉ email của bạn"
                       value="<?php echo htmlspecialchars($old_input['email'] ?? ''); ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label for="password_register" class="block text-sm font-medium text-gray-700 mb-1.5">Mật khẩu</label>
                <input type="password" id="password_register" name="password" required
                       class="form-input <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                       placeholder="Tạo mật khẩu (ít nhất 6 ký tự)">
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1.5">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="form-input <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                       placeholder="Nhập lại mật khẩu">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-submit-register">
                    Đăng Ký
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-600 mt-8">
            Đã có tài khoản? <a href="login.php" class="link-style">Đăng nhập tại đây</a>
        </p>
         <p class="text-center text-xs text-gray-400 mt-2">
            <a href="index.php" class="hover:underline">&larr; Quay lại trang chủ</a>
        </p>
    </div>

</body>
</html>
