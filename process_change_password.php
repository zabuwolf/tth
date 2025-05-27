<?php
session_start();
require_once 'config/db_config.php'; // Kết nối CSDL

// Kiểm tra xem người dùng đã đăng nhập và có phải là POST request không
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Chưa đăng nhập, về trang login
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: change_password.php'); // Không phải POST, về trang đổi mật khẩu
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];

// Lấy dữ liệu từ form
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';

// Validate dữ liệu
if (empty($current_password)) {
    $errors['current_password'] = "Mật khẩu hiện tại không được để trống.";
}
if (empty($new_password)) {
    $errors['new_password'] = "Mật khẩu mới không được để trống.";
} elseif (strlen($new_password) < 6) {
    $errors['new_password'] = "Mật khẩu mới phải có ít nhất 6 ký tự.";
}
if (empty($confirm_new_password)) {
    $errors['confirm_new_password'] = "Xác nhận mật khẩu mới không được để trống.";
} elseif ($new_password !== $confirm_new_password) {
    $errors['confirm_new_password'] = "Mật khẩu mới và xác nhận mật khẩu không khớp.";
}

// Nếu có lỗi validate cơ bản, quay lại form
if (!empty($errors)) {
    $_SESSION['form_errors_change_password'] = $errors;
    header('Location: change_password.php');
    exit;
}

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    $_SESSION['error_message_change_password'] = "Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu.";
    header('Location: change_password.php');
    exit;
}

// 1. Lấy mật khẩu đã hash hiện tại của người dùng từ CSDL
$current_password_hash_from_db = null;
$sql_get_current_pass = "SELECT password_hash FROM users WHERE id = ?";
$stmt_get_pass = $conn->prepare($sql_get_current_pass);

if ($stmt_get_pass) {
    $stmt_get_pass->bind_param("i", $user_id);
    $stmt_get_pass->execute();
    $result_get_pass = $stmt_get_pass->get_result();
    if ($user_data = $result_get_pass->fetch_assoc()) {
        $current_password_hash_from_db = $user_data['password_hash'];
    }
    $stmt_get_pass->close();
} else {
    error_log("Lỗi chuẩn bị SQL lấy mật khẩu hiện tại (user ID: {$user_id}): " . $conn->error);
    $_SESSION['error_message_change_password'] = "Đã có lỗi xảy ra. Vui lòng thử lại sau.";
    close_db_connection($conn);
    header('Location: change_password.php');
    exit;
}

if ($current_password_hash_from_db === null) {
    // Không tìm thấy người dùng, hoặc lỗi không mong muốn
    $_SESSION['error_message_change_password'] = "Không thể xác thực tài khoản của bạn.";
    close_db_connection($conn);
    header('Location: change_password.php');
    exit;
}

// 2. Xác minh mật khẩu hiện tại người dùng nhập vào
if (!password_verify($current_password, $current_password_hash_from_db)) {
    $errors['current_password'] = "Mật khẩu hiện tại không chính xác.";
    $_SESSION['form_errors_change_password'] = $errors;
    close_db_connection($conn);
    header('Location: change_password.php');
    exit;
}

// 3. Nếu mật khẩu hiện tại đúng và không có lỗi nào khác, hash mật khẩu mới và cập nhật CSDL
$new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
if ($new_password_hashed === false) {
    error_log("Lỗi hash mật khẩu mới cho user ID: {$user_id}.");
    $_SESSION['error_message_change_password'] = "Đã có lỗi xảy ra khi xử lý mật khẩu mới. Vui lòng thử lại.";
    close_db_connection($conn);
    header('Location: change_password.php');
    exit;
}

$sql_update_password = "UPDATE users SET password_hash = ? WHERE id = ?";
$stmt_update_pass = $conn->prepare($sql_update_password);

if ($stmt_update_pass) {
    $stmt_update_pass->bind_param("si", $new_password_hashed, $user_id);
    if ($stmt_update_pass->execute()) {
        $_SESSION['success_message_change_password'] = "Đổi mật khẩu thành công!";
        // Optional: Đăng xuất người dùng khỏi các session khác nếu cần (phức tạp hơn)
        // session_regenerate_id(true); // Tái tạo ID session để tăng bảo mật
    } else {
        error_log("Lỗi thực thi SQL cập nhật mật khẩu (user ID: {$user_id}): " . $stmt_update_pass->error);
        $_SESSION['error_message_change_password'] = "Đã có lỗi xảy ra khi cập nhật mật khẩu. Vui lòng thử lại.";
    }
    $stmt_update_pass->close();
} else {
    error_log("Lỗi chuẩn bị SQL cập nhật mật khẩu (user ID: {$user_id}): " . $conn->error);
    $_SESSION['error_message_change_password'] = "Đã có lỗi xảy ra. Vui lòng thử lại sau.";
}

close_db_connection($conn);
header('Location: change_password.php'); // Chuyển hướng lại trang đổi mật khẩu để hiển thị thông báo
exit;
?>
