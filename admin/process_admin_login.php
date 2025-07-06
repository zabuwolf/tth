<?php
session_start();
require_once '../config/db_config.php'; // Đi ra một cấp để vào config
// require_once '../includes/user_functions.php'; // Nếu có hàm check admin

// --- THÔNG TIN ADMIN CỐ ĐỊNH (NÊN THAY BẰNG LOGIC CSDL) ---
// Ví dụ: bạn có thể tạo một tài khoản admin trong bảng users với is_admin = 1
// Và kiểm tra username, password_hash, và is_admin từ CSDL.
define('ADMIN_USERNAME', 'admin'); // Thay đổi nếu cần
define('ADMIN_PASSWORD_HASH', password_hash('secureAdminPassword123!', PASSWORD_DEFAULT)); // Thay 'secureAdminPassword123!' bằng mật khẩu mạnh của bạn
// Để tạo hash này, bạn có thể chạy password_hash('your_password', PASSWORD_DEFAULT) một lần và copy kết quả.
// -------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_attempt = $_POST['username'] ?? '';
    $password_attempt = $_POST['password'] ?? '';

    // Kết nối CSDL để kiểm tra admin từ bảng users (khuyến nghị)
    $conn = connect_db();
    $login_successful = false;

    if ($conn) {
        $sql = "SELECT id, username, password_hash, fullname, is_admin FROM users WHERE username = ? AND is_admin = TRUE LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $username_attempt);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin_user = $result->fetch_assoc();
            $stmt->close();

            if ($admin_user && password_verify($password_attempt, $admin_user['password_hash'])) {
                $login_successful = true;
                $_SESSION['admin_id'] = $admin_user['id'];
                $_SESSION['admin_username'] = $admin_user['username'];
                $_SESSION['admin_fullname'] = $admin_user['fullname'];
            }
        } else {
            error_log("Admin login SQL prepare error: " . $conn->error);
        }
        close_db_connection($conn);
    } else {
         // Xử lý lỗi kết nối CSDL nếu cần, ví dụ:
         $_SESSION['admin_login_error'] = "Lỗi hệ thống, không thể kết nối CSDL.";
         header('Location: admin_login.php');
         exit;
    }


    // // Kiểm tra với thông tin admin cố định (cách cũ, ít bảo mật hơn là dùng CSDL)
    // if ($username_attempt === ADMIN_USERNAME && password_verify($password_attempt, ADMIN_PASSWORD_HASH)) {
    //     $login_successful = true;
    //     $_SESSION['admin_username'] = ADMIN_USERNAME; // Lưu thông tin admin nếu cần
    // }

    if ($login_successful) {
        $_SESSION['admin_logged_in'] = true;
        unset($_SESSION['admin_login_error']); // Xóa lỗi nếu đăng nhập thành công
        header('Location: index.php'); // Chuyển hướng đến trang dashboard admin
        exit;
    } else {
        $_SESSION['admin_login_error'] = "Tên đăng nhập hoặc mật khẩu không chính xác.";
        header('Location: admin_login.php');
        exit;
    }

} else {
    // Không phải POST request
    header('Location: admin_login.php');
    exit;
}
?>
