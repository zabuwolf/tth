<?php
// process_register.php
session_start();

// Bao gồm các tệp cần thiết
require_once 'config/db_config.php'; 
require_once 'includes/validation_functions.php'; // Đã có hàm normalize_school_name

// 1. Khởi tạo kết nối CSDL
$conn = connect_db();

if ($conn === null) {
    $_SESSION['form_errors'] = ['general' => 'Lỗi hệ thống. Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.'];
    header('Location: register.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    close_db_connection($conn);
    header('Location: register.php');
    exit;
}

// --- HONEYPOT CHECK - START ---
if (isset($_POST['website_url_check']) && !empty($_POST['website_url_check'])) {
    error_log("Honeypot triggered by IP: " . $_SERVER['REMOTE_ADDR'] . " with value: " . $_POST['website_url_check']);
    close_db_connection($conn);
    header('Location: register.php');
    exit;
}
// --- HONEYPOT CHECK - END ---

// 2. Lấy và làm sạch dữ liệu đầu vào
$input = sanitize_registration_input($_POST); // Hàm này đã lấy school_name

// 3. Kiểm tra tính hợp lệ của dữ liệu (validation cơ bản)
$errors = validate_registration_data($input); // Hàm này đã validate school_name (ví dụ độ dài)

// 4. Nếu không có lỗi validate cơ bản, kiểm tra username và email đã tồn tại chưa
if (empty($errors)) {
    $username_taken = is_username_taken($input['username'], $conn);
    if ($username_taken === null) { 
        $errors['general'] = 'Lỗi hệ thống khi kiểm tra tên đăng nhập. Vui lòng thử lại sau.';
    } elseif ($username_taken === true) {
        $errors['username'] = "Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.";
    }

    if (!isset($errors['general'])) {
        $email_taken = is_email_taken($input['email'], $conn);
        if ($email_taken === null) { 
            $errors['general'] = 'Lỗi hệ thống khi kiểm tra email. Vui lòng thử lại sau.';
        } elseif ($email_taken === true) {
            $errors['email'] = "Địa chỉ email này đã được đăng ký. Vui lòng sử dụng email khác.";
        }
    }
}

// 5. Nếu không có lỗi nào, tiến hành tạo người dùng
if (empty($errors)) {
    
    // Chuẩn hóa tên trường học trước khi lưu
    $normalized_school_name = null;
    if (!empty($input['school_name'])) {
        $normalized_school_name = normalize_school_name($input['school_name']);
    }

    $user_data = [
        'fullname' => $input['fullname'],
        'username' => $input['username'],
        'email'    => $input['email'],
        'school_name' => $normalized_school_name, // Sử dụng tên trường đã chuẩn hóa
        'password' => $input['password']
    ];
    
    if (create_user($user_data, $conn)) { // Hàm create_user đã được cập nhật ở bước trước để nhận school_name
        $_SESSION['success_message'] = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.';
        close_db_connection($conn);
        header('Location: login.php');
        exit;
    } else {
        $errors['general'] = 'Đã có lỗi xảy ra trong quá trình đăng ký tài khoản. Vui lòng thử lại sau.';
    }
}

$_SESSION['form_errors'] = $errors;
$_SESSION['old_form_input'] = [ 
    'fullname' => $input['fullname'],
    'username' => $input['username'],
    'email'    => $input['email'],
    'school_name' => $input['school_name'] // Giữ lại giá trị gốc người dùng nhập cho old_input
];

close_db_connection($conn); 
header('Location: register.php');
exit;
?>