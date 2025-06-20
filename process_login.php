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
    $_SESSION['login_errors'] = ['general' => 'Lỗi hệ thống nghiêm trọng. Không thể kết nối đến máy chủ.']; // Sử dụng key login_errors
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email_input = trim($_POST['username'] ?? ''); 
    $password_input = $_POST['password'] ?? '';
    $errors = [];

    if (empty($username_or_email_input)) {
        $errors['username'] = 'Vui lòng nhập tên đăng nhập hoặc email!';
    }
    if (empty($password_input)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu!';
    }

    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['old_login_username'] = $username_or_email_input; // Giữ lại username
        close_db_connection($conn);
        header('Location: login.php');
        exit;
    }
    
    $sql = "SELECT id, username, email, password_hash, fullname, avatar_url, points, is_admin 
            FROM users 
            WHERE (username = ? OR email = ?)
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $username_or_email_input, $username_or_email_input);
        
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi SQL (SELECT user) trong process_login.php: " . $stmt->error);
            $_SESSION['login_errors'] = ['general' => 'Lỗi hệ thống khi truy vấn dữ liệu.'];
            header('Location: login.php');
            $stmt->close();
            close_db_connection($conn);
            exit;
        }
        
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password_input, $user['password_hash'])) {
                session_regenerate_id(true); // Tái tạo ID session để tăng bảo mật

                // Session chung cho người dùng đăng nhập
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['avatar_url'] = $user['avatar_url'];
                $_SESSION['points'] = (int)($user['points'] ?? 0);
                $_SESSION['is_admin'] = (bool)$user['is_admin'];

                unset($_SESSION['login_errors']);
                unset($_SESSION['old_login_username']);

                if ($_SESSION['is_admin']) {
                    // Đặt thêm các session cụ thể cho admin để tương thích với các file admin hiện tại
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = (int)$user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_fullname'] = $user['fullname'];
                    header('Location: admin/index.php'); 
                } else {
                    header('Location: dashboard.php'); 
                }
                exit;

            } else {
                $_SESSION['login_errors'] = ['general' => 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.'];
            }
        } else {
            $_SESSION['login_errors'] = ['general' => 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.'];
        }
        $stmt->close();
    } else {
        error_log("Lỗi chuẩn bị SQL (SELECT user) trong process_login.php: " . $conn->error);
        $_SESSION['login_errors'] = ['general' => 'Lỗi hệ thống khi chuẩn bị truy vấn.'];
    }

    $_SESSION['old_login_username'] = $username_or_email_input;
    close_db_connection($conn);
    header('Location: login.php');
    exit;
    
} else {
    header('Location: login.php');
    exit;
}
?>