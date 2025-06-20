<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/validation_functions.php'; // Đảm bảo file này được include để có hàm normalize_school_name

// Kiểm tra xem người dùng đã đăng nhập và có phải là POST request không
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: edit_profile.php'); 
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$conn = connect_db();
if (!$conn) {
    $_SESSION['message_profile_edit'] = ['type' => 'error', 'text' => 'Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu.'];
    header('Location: edit_profile.php');
    exit;
}

// Lấy dữ liệu từ form
$new_fullname = trim($_POST['fullname'] ?? '');
$new_avatar_url = trim($_POST['avatar_url'] ?? '');
$new_school_name_input = trim($_POST['school_name'] ?? ''); // Lấy school_name người dùng nhập

$errors = [];

// Validate dữ liệu
if (empty($new_fullname)) {
    $errors['fullname'] = "Họ và tên không được để trống.";
} elseif (strlen($new_fullname) > 100) {
    $errors['fullname'] = "Họ và tên không được vượt quá 100 ký tự.";
}

// Validate school_name (ví dụ: giới hạn độ dài)
if (!empty($new_school_name_input) && strlen($new_school_name_input) > 255) {
    $errors['school_name'] = "Tên trường học không được vượt quá 255 ký tự.";
}

// Validate avatar_url (giữ nguyên logic cũ)
if (!empty($new_avatar_url)) {
    if (filter_var($new_avatar_url, FILTER_VALIDATE_URL)) {
        // URL hợp lệ
    } 
    elseif (!preg_match('/^([a-zA-Z0-9_\-\/\.]+)\.(jpg|jpeg|png|gif|svg)$/i', $new_avatar_url)) {
         // $errors['avatar_url'] = "Đường dẫn ảnh đại diện không hợp lệ...";
    }
}


if (empty($errors)) {
    // Chuẩn hóa tên trường học trước khi lưu
    $normalized_school_name_for_edit = null;
    if (!empty($new_school_name_input)) {
        $normalized_school_name_for_edit = normalize_school_name($new_school_name_input);
    } else {
        // Nếu người dùng xóa trắng trường tên trường, thì lưu NULL
        $normalized_school_name_for_edit = null;
    }


    $sql_update = "UPDATE users SET fullname = ?, avatar_url = ?, school_name = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("sssi", $new_fullname, $new_avatar_url, $normalized_school_name_for_edit, $user_id);
        if ($stmt_update->execute()) {
            $_SESSION['success_message_profile'] = "Hồ sơ của bạn đã được cập nhật thành công!";
            
            $_SESSION['fullname'] = $new_fullname;
            $_SESSION['school_name'] = $normalized_school_name_for_edit; // Cập nhật school_name trong session
            
            if(!empty($new_avatar_url)) {
                $_SESSION['avatar_url'] = $new_avatar_url;
            } else {
                // Nếu avatar_url bị xóa, có thể đặt lại avatar mặc định hoặc xóa khỏi session
                 $_SESSION['avatar_url'] = null; // hoặc unset($_SESSION['avatar_url']);
            }
            
            header('Location: profile.php'); 
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
    $_SESSION['form_errors_profile_edit'] = $errors;
    $_SESSION['old_form_input_profile_edit'] = $_POST; 
}

if ($conn) {
    close_db_connection($conn);
}
header('Location: edit_profile.php');
exit;
?>