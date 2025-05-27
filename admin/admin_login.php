<?php
session_start();
// Nếu admin đã đăng nhập, chuyển hướng đến trang dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
$error_message = $_SESSION['admin_login_error'] ?? null;
unset($_SESSION['admin_login_error']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Đăng Nhập - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F3F4F6; /* Gray 100 */
        }
        .font-baloo {
            font-family: 'Baloo 2', cursive;
        }
        .login-card {
            background-color: white;
            border-radius: 0.75rem; /* 12px */
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
        }
        .form-input {
            border-radius: 0.375rem; /* 6px */
            border: 1px solid #D1D5DB; /* Gray 300 */
            padding: 0.75rem 1rem;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-input:focus {
            border-color: #6366F1; /* Indigo 500 */
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .btn-submit {
            background-color: #6366F1; /* Indigo 500 */
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.3s ease;
            width: 100%;
        }
        .btn-submit:hover {
            background-color: #4F46E5; /* Indigo 600 */
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="login-card">
        <div class="text-center mb-8">
            <h1 class="font-baloo text-3xl font-bold text-indigo-600">Admin Panel</h1>
            <p class="text-gray-500">Đăng nhập để quản lý trò chơi</p>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                <strong class="font-semibold">Lỗi!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="process_admin_login.php" method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                <input type="text" id="username" name="username" required class="form-input" placeholder="admin_username">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                <input type="password" id="password" name="password" required class="form-input" placeholder="••••••••">
            </div>
            <div>
                <button type="submit" class="btn-submit">Đăng Nhập</button>
            </div>
        </form>
    </div>
</body>
</html>
