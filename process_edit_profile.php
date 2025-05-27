<?php
session_start();
require_once 'config/db_config.php';

// Kiểm tra xem người dùng đã đăng nhập và có phải là POST request không
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    // Không nên xảy ra nếu truy cập từ form hợp lệ
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: edit_profile.php'); // Chuyển hướng nếu không phải POST
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    $_SESSION['message_profile_edit'] = ['type' => 'error', 'text' => 'Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu.'];
    header('Location: edit_profile.php');
    exit;
}

// Lấy dữ liệu từ form
$new_fullname = trim($_POST['fullname'] ?? '');
$new_avatar_url = trim($_POST['avatar_url'] ?? ''); // Admin tự quản lý đường dẫn này

$errors = [];

// Validate dữ liệu
if (empty($new_fullname)) {
    $errors['fullname'] = "Họ và tên không được để trống.";
} elseif (strlen($new_fullname) > 100) {
    $errors['fullname'] = "Họ và tên không được vượt quá 100 ký tự.";
}

// Validate avatar_url (ví dụ đơn giản: nếu không rỗng thì phải là URL hợp lệ hoặc đường dẫn tương đối)
if (!empty($new_avatar_url)) {
    // Kiểm tra nếu là URL đầy đủ
    if (filter_var($new_avatar_url, FILTER_VALIDATE_URL)) {
        // URL hợp lệ
    } 
    // Kiểm tra nếu là đường dẫn tương đối (ví dụ: assets/images/...)
    // Bạn có thể thêm các quy tắc phức tạp hơn ở đây, ví dụ kiểm tra định dạng file ảnh
    elseif (!preg_match('/^([a-zA-Z0-9_\-\/\.]+)\.(jpg|jpeg|png|gif|svg)$/i', $new_avatar_url)) {
         // $errors['avatar_url'] = "Đường dẫn ảnh đại diện không hợp lệ. Phải là URL hoặc đường dẫn tương đối (ví dụ: assets/images/anh.png).";
         // Tạm thời chấp nhận chuỗi, admin tự quản lý
    }
}


if (empty($errors)) {
    // Cập nhật thông tin người dùng vào CSDL
    $sql_update = "UPDATE users SET fullname = ?, avatar_url = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("ssi", $new_fullname, $new_avatar_url, $user_id);
        if ($stmt_update->execute()) {
            $_SESSION['success_message_profile'] = "Hồ sơ của bạn đã được cập nhật thành công!"; // Dùng key khác cho profile page
            
            // Cập nhật session nếu cần
            $_SESSION['fullname'] = $new_fullname;
            if(!empty($new_avatar_url)) {
                $_SESSION['avatar_url'] = $new_avatar_url;
            } else {
                // Nếu avatar bị xóa, có thể đặt lại avatar mặc định trong session
                unset($_SESSION['avatar_url']); 
            }
            
            header('Location: profile.php'); // Chuyển về trang profile
            exit;
        } else {
            $_SESSION['message_profile_edit'] = ['type' => 'error', 'text' => 'Lỗi khi cập nhật hồ sơ: ' . $stmt_update->error];
            error_log("Lỗi cập nhật profile user ID {$user_id}: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        $_SESSION['message_profile_edit'] = ['type' => 'error', 'text' => 'Lỗi chuẩn bị cập nhật hồ sơ: ' . $conn->error];
        error_log("Lỗi chuẩn bị SQL cập nhật profile user ID {$user_id}: " . $conn->error);
    }
} else {
    // Nếu có lỗi validate, lưu lỗi và input cũ vào session
    $_SESSION['form_errors_profile_edit'] = $errors;
    $_SESSION['old_form_input_profile_edit'] = $_POST;
}

// Đóng kết nối và chuyển hướng lại trang edit_profile nếu có lỗi
if ($conn) {
    close_db_connection($conn);
}
header('Location: edit_profile.php');
exit;
?>
