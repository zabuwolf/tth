<?php
// File: process_login.php
// Nhiệm vụ: Xử lý thông tin đăng nhập, xác thực người dùng, tạo session và chuyển hướng.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db_config.php'; 

$conn = connect_db();

if ($conn === null) {
    error_log("CRITICAL: Lỗi kết nối CSDL trong process_login.php.");
    $_SESSION['login_error_message'] = 'Lỗi hệ thống nghiêm trọng. Không thể kết nối đến máy chủ.';
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email_input = trim($_POST['username'] ?? ''); 
    $password_input = $_POST['password'] ?? '';

    if (empty($username_or_email_input) || empty($password_input)) {
        $_SESSION['login_error_message'] = 'Vui lòng nhập đầy đủ tên đăng nhập/email và mật khẩu!';
        $_SESSION['old_login_input'] = ['username' => $username_or_email_input];
        close_db_connection($conn);
        header('Location: login.php');
        exit;
    }

    // Sử dụng tên cột `password_hash` hoặc `password` tùy theo CSDL của bạn
    // Ví dụ này dùng `password_hash` như code cũ của bạn
    $sql = "SELECT id, username, email, password_hash, fullname, avatar_url, points, is_admin 
            FROM users 
            WHERE username = ? OR email = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $username_or_email_input, $username_or_email_input);
        
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi SQL (SELECT user) trong process_login.php: " . $stmt->error);
            $_SESSION['login_error_message'] = 'Lỗi hệ thống khi truy vấn dữ liệu.';
            header('Location: login.php');
            $stmt->close();
            close_db_connection($conn);
            exit;
        }
        
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Thay 'password_hash' bằng 'password' nếu CSDL của bạn dùng tên cột đó
            if (password_verify($password_input, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['avatar_url'] = $user['avatar_url'];
                $_SESSION['points'] = (int)($user['points'] ?? 0); // Đảm bảo points được lấy và lưu vào session
                $_SESSION['is_admin'] = (bool)$user['is_admin'];

                unset($_SESSION['login_error_message']);
                unset($_SESSION['old_login_input']);

                // Cập nhật last_login_date (tùy chọn, nếu bạn có cột này và muốn cập nhật)
                // $update_last_login_sql = "UPDATE users SET last_login_date = NOW() WHERE id = ?";
                // $stmt_update_login = $conn->prepare($update_last_login_sql);
                // if($stmt_update_login) {
                //     $stmt_update_login->bind_param("i", $user['id']);
                //     $stmt_update_login->execute();
                //     $stmt_update_login->close();
                // }

                if ($_SESSION['is_admin']) {
                    header('Location: admin/index.php'); 
                } else {
                    // CHUYỂN HƯỚNG ĐẾN DASHBOARD
                    header('Location: dashboard.php'); 
                }
                exit;

            } else {
                $_SESSION['login_error_message'] = 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.';
            }
        } else {
            $_SESSION['login_error_message'] = 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.';
        }
        $stmt->close();
    } else {
        error_log("Lỗi chuẩn bị SQL (SELECT user) trong process_login.php: " . $conn->error);
        $_SESSION['login_error_message'] = 'Lỗi hệ thống khi chuẩn bị truy vấn.';
    }

    $_SESSION['old_login_input'] = ['username' => $username_or_email_input];
    close_db_connection($conn);
    header('Location: login.php');
    exit;
    
} else {
    header('Location: login.php');
    exit;
}
?>
