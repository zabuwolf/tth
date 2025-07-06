<?php
// process_register.php
session_start();

// Bao gồm các tệp cần thiết
require_once 'config/db_config.php'; // Cấu hình CSDL và hàm connect_db()
require_once 'includes/validation_functions.php'; // Các hàm kiểm tra

// 1. Khởi tạo kết nối CSDL
$conn = connect_db();

// Kiểm tra nếu kết nối CSDL thất bại
if ($conn === null) {
    // Lỗi kết nối CSDL đã được ghi vào error_log trong hàm connect_db()
    // Hiển thị một thông báo lỗi chung cho người dùng và dừng script.
    $_SESSION['form_errors'] = ['general' => 'Lỗi hệ thống. Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.'];
    header('Location: register.php');
    exit;
}

// Kiểm tra xem form đã được submit hay chưa
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Nếu không phải là POST request, đóng kết nối và chuyển hướng
    close_db_connection($conn);
    header('Location: register.php');
    exit;
}

// 2. Lấy và làm sạch dữ liệu đầu vào
$input = sanitize_registration_input($_POST);

// 3. Kiểm tra tính hợp lệ của dữ liệu (validation cơ bản)
$errors = validate_registration_data($input);

// 4. Nếu không có lỗi validate cơ bản, kiểm tra username và email đã tồn tại chưa (sử dụng CSDL)
if (empty($errors)) {
    $username_taken = is_username_taken($input['username'], $conn);
    if ($username_taken === null) { // Lỗi truy vấn CSDL
        $errors['general'] = 'Lỗi hệ thống khi kiểm tra tên đăng nhập. Vui lòng thử lại sau.';
    } elseif ($username_taken === true) {
        $errors['username'] = "Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.";
    }

    // Chỉ kiểm tra email nếu chưa có lỗi nghiêm trọng
    if (!isset($errors['general'])) {
        $email_taken = is_email_taken($input['email'], $conn);
        if ($email_taken === null) { // Lỗi truy vấn CSDL
            $errors['general'] = 'Lỗi hệ thống khi kiểm tra email. Vui lòng thử lại sau.';
        } elseif ($email_taken === true) {
            $errors['email'] = "Địa chỉ email này đã được đăng ký. Vui lòng sử dụng email khác.";
        }
    }
}

// 5. Nếu không có lỗi nào (bao gồm cả lỗi CSDL), tiến hành tạo người dùng
if (empty($errors)) {
    $user_data = [
        'fullname' => $input['fullname'],
        'username' => $input['username'],
        'email'    => $input['email'],
        'password' => $input['password'] // Mật khẩu gốc, hàm create_user sẽ hash
    ];

    if (create_user($user_data, $conn)) {
        // Đăng ký thành công
        $_SESSION['success_message'] = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.';
        close_db_connection($conn);
        header('Location: login.php');
        exit;
    } else {
        // Lỗi khi tạo người dùng (có thể do lỗi CSDL không lường trước hoặc lỗi hash password)
        // Hàm create_user đã log lỗi cụ thể.
        $errors['general'] = 'Đã có lỗi xảy ra trong quá trình đăng ký tài khoản. Vui lòng thử lại sau.';
    }
}

// Nếu có lỗi (từ validation hoặc xử lý CSDL), lưu lỗi và dữ liệu cũ vào session
// rồi chuyển hướng về trang đăng ký
$_SESSION['form_errors'] = $errors;
$_SESSION['old_form_input'] = [ // Giữ lại dữ liệu người dùng đã nhập (trừ mật khẩu)
    'fullname' => $input['fullname'],
    'username' => $input['username'],
    'email'    => $input['email'],
];

close_db_connection($conn); // Đảm bảo đóng kết nối CSDL
header('Location: register.php');
exit;
?>
