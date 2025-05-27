<?php
session_start();
require_once 'config/db_config.php';

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error_message'] = "Vui lòng đăng nhập để chỉnh sửa hồ sơ của bạn.";
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Lấy thông tin người dùng hiện tại từ CSDL để điền vào form
$current_user_data = null;
$sql_user = "SELECT fullname, username, email, avatar_url FROM users WHERE id = ? LIMIT 1";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $current_user_data = $result_user->fetch_assoc();
    }
    $stmt_user->close();
} else {
    error_log("Lỗi chuẩn bị SQL lấy thông tin user (edit_profile): " . $conn->error);
    // Hiển thị lỗi hoặc chuyển hướng nếu cần
}

if (!$current_user_data) {
    // Không tìm thấy người dùng, xử lý lỗi
    $_SESSION['message_profile'] = ['type' => 'error', 'text' => 'Không tìm thấy thông tin tài khoản của bạn.'];
    header('Location: profile.php'); // Chuyển về trang profile
    exit;
}

// Lấy lỗi và input cũ từ session (nếu có sau khi submit lỗi)
$errors = $_SESSION['form_errors_profile_edit'] ?? [];
$old_input = $_SESSION['old_form_input_profile_edit'] ?? [];
unset($_SESSION['form_errors_profile_edit'], $_SESSION['old_form_input_profile_edit']);

// Ưu tiên old_input nếu có, nếu không thì dùng current_user_data
$display_fullname = htmlspecialchars($old_input['fullname'] ?? ($current_user_data['fullname'] ?? ''));
$display_avatar_url = htmlspecialchars($old_input['avatar_url'] ?? ($current_user_data['avatar_url'] ?? ''));

// Lấy thông báo thành công/lỗi từ session (nếu có từ process_edit_profile.php)
$form_message = $_SESSION['message_profile_edit'] ?? null;
unset($_SESSION['message_profile_edit']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Hồ Sơ - <?php echo htmlspecialchars($current_user_data['fullname']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .form-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .form-input, .form-textarea {
            border-radius: 0.5rem; border: 1px solid #D1D5DB; /* Gray 300 */
            padding: 0.75rem 1rem; width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.95rem;
        }
        .form-input:focus, .form-textarea:focus {
            border-color: #EC4899; /* Pink 500 */ outline: none;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2); /* Pink focus ring */
        }
        .form-label { display: block; text-sm font-medium text-gray-700 mb-1.5; }
        .btn-save { background-color: #10B981; /* Emerald 500 */ color: white; }
        .btn-save:hover { background-color: #059669; /* Emerald 600 */ }
        .btn-cancel { background-color: #6B7280; /* Gray 500 */ color: white; }
        .btn-cancel:hover { background-color: #4B5563; /* Gray 600 */ }
        .error-text { color: #EF4444; /* Red 500 */ font-size: 0.875rem; margin-top: 0.25rem; }
        .success-message { background-color: #D1FAE5; border-left-width: 4px; border-color: #10B981; color: #065F46; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;}
        .error-message-form { background-color: #FEE2E2; border-left-width: 4px; border-color: #EF4444; color: #B91C1C; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;}
        .avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #FBCFE8; margin-top: 0.5rem; }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="container mx-auto max-w-xl px-4">
        <div class="form-card p-6 md:p-8">
            <div class="text-center mb-8">
                <h1 class="font-baloo text-3xl font-bold text-pink-600">Chỉnh Sửa Hồ Sơ</h1>
                <p class="text-gray-600">Cập nhật thông tin cá nhân của bạn.</p>
            </div>

            <?php if (isset($form_message['type']) && $form_message['type'] === 'success'): ?>
                <div class="success-message" role="alert">
                    <p class="font-bold">Thành công!</p>
                    <p><?php echo htmlspecialchars($form_message['text']); ?></p>
                </div>
            <?php elseif (isset($form_message['type']) && $form_message['type'] === 'error'): ?>
                 <div class="error-message-form" role="alert">
                    <p class="font-bold">Lỗi!</p>
                    <p><?php echo htmlspecialchars($form_message['text']); ?></p>
                </div>
            <?php endif; ?>


            <form action="process_edit_profile.php" method="POST" class="space-y-6">
                <div>
                    <label for="username_display" class="form-label">Tên đăng nhập</label>
                    <input type="text" id="username_display" name="username_display" class="form-input bg-gray-100 cursor-not-allowed" 
                           value="<?php echo htmlspecialchars($current_user_data['username']); ?>" readonly>
                    <p class="text-xs text-gray-500 mt-1">Tên đăng nhập không thể thay đổi.</p>
                </div>

                <div>
                    <label for="email_display" class="form-label">Email</label>
                    <input type="email" id="email_display" name="email_display" class="form-input bg-gray-100 cursor-not-allowed"
                           value="<?php echo htmlspecialchars($current_user_data['email']); ?>" readonly>
                     <p class="text-xs text-gray-500 mt-1">Email không thể thay đổi qua form này.</p>
                </div>
                
                <div>
                    <label for="fullname" class="form-label">Họ và Tên <span class="text-red-500">*</span></label>
                    <input type="text" id="fullname" name="fullname" class="form-input" required 
                           value="<?php echo $display_fullname; ?>">
                    <?php if (isset($errors['fullname'])): ?><p class="error-text"><?php echo $errors['fullname']; ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="avatar_url" class="form-label">URL Ảnh Đại Diện</label>
                    <input type="text" id="avatar_url" name="avatar_url" class="form-input" 
                           value="<?php echo $display_avatar_url; ?>" placeholder="https://example.com/avatar.png hoặc assets/images/avatar.png">
                    <?php if (isset($errors['avatar_url'])): ?><p class="error-text"><?php echo $errors['avatar_url']; ?></p><?php endif; ?>
                    <?php if (!empty($display_avatar_url)): ?>
                        <img src="<?php echo $display_avatar_url; ?>" alt="Xem trước avatar" class="avatar-preview" 
                             onerror="this.src='https://placehold.co/100x100/E0E0E0/757575?text=Ảnh+Lỗi'; this.onerror=null;">
                    <?php else: ?>
                         <img src="https://placehold.co/100x100/E0E0E0/757575?text=Avatar" alt="Avatar mặc định" class="avatar-preview">
                    <?php endif; ?>
                     <p class="text-xs text-gray-500 mt-1">Để trống nếu muốn dùng avatar mặc định. Đường dẫn có thể là URL đầy đủ hoặc đường dẫn tương đối từ thư mục gốc của web (ví dụ: assets/images/ten_anh.png).</p>
                </div>
                
                <div class="flex items-center justify-end space-x-4 pt-4">
                    <a href="profile.php" class="px-4 py-2.5 rounded-md text-sm font-medium btn-cancel">
                        Hủy
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-md text-sm font-medium btn-save">
                        <i class="fas fa-save mr-2"></i>Lưu Thay Đổi
                    </button>
                </div>
            </form>
        </div>
         <p class="text-center text-sm text-gray-500 mt-6">
            <a href="index.php" class="hover:text-pink-600 hover:underline">&larr; Quay lại trang chủ</a>
        </p>
    </div>

    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
</body>
</html>
