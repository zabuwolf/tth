<?php
session_start();
require_once 'config/db_config.php'; // For potential future use, though not strictly needed for the form itself

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error_message'] = "Vui lòng đăng nhập để đổi mật khẩu.";
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_fullname = $_SESSION['fullname'] ?? 'Người dùng'; // Get fullname for display

// Lấy thông báo lỗi/thành công từ session (nếu có từ process_change_password.php)
$form_errors = $_SESSION['form_errors_change_password'] ?? [];
$success_message = $_SESSION['success_message_change_password'] ?? null;
$error_message = $_SESSION['error_message_change_password'] ?? null; // General error from processing
unset($_SESSION['form_errors_change_password'], $_SESSION['success_message_change_password'], $_SESSION['error_message_change_password']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi Mật Khẩu - <?php echo htmlspecialchars($user_fullname); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .form-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .form-input {
            border-radius: 0.5rem; border: 1px solid #D1D5DB; /* Gray 300 */
            padding: 0.75rem 1rem; width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.95rem;
        }
        .form-input:focus {
            border-color: #EC4899; /* Pink 500 */ outline: none;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2); /* Pink focus ring */
        }
        .form-label { display: block; text-sm font-medium text-gray-700 mb-1.5; }
        .btn-submit { background-color: #EC4899; /* Pink 500 */ color: white; }
        .btn-submit:hover { background-color: #DB2777; /* Pink 600 */ }
        .btn-cancel { background-color: #6B7280; /* Gray 500 */ color: white; }
        .btn-cancel:hover { background-color: #4B5563; /* Gray 600 */ }
        .error-text { color: #EF4444; /* Red 500 */ font-size: 0.875rem; margin-top: 0.25rem; }
        .success-message-box { background-color: #D1FAE5; border-left-width: 4px; border-color: #10B981; color: #065F46; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;}
        .error-message-box { background-color: #FEE2E2; border-left-width: 4px; border-color: #EF4444; color: #B91C1C; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;}
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="container mx-auto max-w-lg px-4">
        <div class="form-card p-6 md:p-8">
            <div class="text-center mb-8">
                <h1 class="font-baloo text-3xl font-bold text-pink-600">Đổi Mật Khẩu</h1>
                <p class="text-gray-600">Bảo mật tài khoản của bạn bằng một mật khẩu mới.</p>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message-box" role="alert">
                    <p class="font-bold">Thành công!</p>
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                 <div class="error-message-box" role="alert">
                    <p class="font-bold">Lỗi!</p>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form action="process_change_password.php" method="POST" class="space-y-6">
                <div>
                    <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-red-500">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="form-input" required>
                    <?php if (isset($form_errors['current_password'])): ?><p class="error-text"><?php echo $form_errors['current_password']; ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="new_password" class="form-label">Mật khẩu mới <span class="text-red-500">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required placeholder="Ít nhất 6 ký tự">
                    <?php if (isset($form_errors['new_password'])): ?><p class="error-text"><?php echo $form_errors['new_password']; ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="confirm_new_password" class="form-label">Xác nhận mật khẩu mới <span class="text-red-500">*</span></label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-input" required>
                    <?php if (isset($form_errors['confirm_new_password'])): ?><p class="error-text"><?php echo $form_errors['confirm_new_password']; ?></p><?php endif; ?>
                </div>
                
                <div class="flex items-center justify-end space-x-4 pt-4">
                    <a href="profile.php" class="px-4 py-2.5 rounded-md text-sm font-medium btn-cancel">
                        Hủy
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-md text-sm font-medium btn-submit">
                        <i class="fas fa-key mr-2"></i>Đổi Mật Khẩu
                    </button>
                </div>
            </form>
        </div>
         <p class="text-center text-sm text-gray-500 mt-6">
            <a href="profile.php" class="hover:text-pink-600 hover:underline">&larr; Quay lại Hồ sơ</a>
        </p>
    </div>

    <?php
    // Không cần đóng kết nối CSDL ở đây vì chúng ta không mở nó cho form này (trừ khi bạn muốn lấy thêm thông tin user)
    // if ($conn) {
    //     close_db_connection($conn);
    // }
    ?>
</body>
</html>
