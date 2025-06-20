<?php
// includes/validation_functions.php

/**
 * Kiểm tra và làm sạch dữ liệu đầu vào từ form đăng ký.
 * @param array $post_data Dữ liệu từ $_POST.
 * @return array Mảng chứa dữ liệu đã được làm sạch.
 */
function sanitize_registration_input(array $post_data): array {
    return [
        'fullname' => trim($post_data['fullname'] ?? ''),
        'username' => trim($post_data['username'] ?? ''),
        'email' => trim($post_data['email'] ?? ''),
        'school_name' => trim($post_data['school_name'] ?? ''), // Giữ nguyên, việc chuẩn hóa sẽ ở process
        'password' => $post_data['password'] ?? '', 
        'confirm_password' => $post_data['confirm_password'] ?? ''
    ];
}

/**
 * Kiểm tra tính hợp lệ của dữ liệu đăng ký.
 * @param array $input Dữ liệu đã được làm sạch.
 * @return array Mảng chứa các thông báo lỗi. Rỗng nếu không có lỗi.
 */
function validate_registration_data(array $input): array {
    $errors = [];

    // Họ và tên
    if (empty($input['fullname'])) {
        $errors['fullname'] = "Họ và tên không được để trống.";
    } elseif (strlen($input['fullname']) > 100) {
        $errors['fullname'] = "Họ và tên không được vượt quá 100 ký tự.";
    }

    // Tên đăng nhập
    if (empty($input['username'])) {
        $errors['username'] = "Tên đăng nhập không được để trống.";
    } elseif (strlen($input['username']) < 4) {
        $errors['username'] = "Tên đăng nhập phải có ít nhất 4 ký tự.";
    } elseif (strlen($input['username']) > 50) {
        $errors['username'] = "Tên đăng nhập không được vượt quá 50 ký tự.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $input['username'])) {
        $errors['username'] = "Tên đăng nhập chỉ được chứa chữ cái (a-z, A-Z), số (0-9) và dấu gạch dưới (_).";
    }

    // Email
    if (empty($input['email'])) {
        $errors['email'] = "Email không được để trống.";
    } elseif (strlen($input['email']) > 100) {
        $errors['email'] = "Email không được vượt quá 100 ký tự.";
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Định dạng email không hợp lệ.";
    }

    // Tên trường học (Tùy chọn, ví dụ: kiểm tra độ dài nếu có nhập)
    if (!empty($input['school_name']) && strlen($input['school_name']) > 255) {
        $errors['school_name'] = "Tên trường học không được vượt quá 255 ký tự.";
    }

    // Mật khẩu
    if (empty($input['password'])) {
        $errors['password'] = "Mật khẩu không được để trống.";
    } elseif (strlen($input['password']) < 6) {
        $errors['password'] = "Mật khẩu phải có ít nhất 6 ký tự.";
    }

    // Xác nhận mật khẩu
    if (empty($input['confirm_password'])) {
        $errors['confirm_password'] = "Xác nhận mật khẩu không được để trống.";
    } elseif ($input['password'] !== $input['confirm_password']) {
        $errors['confirm_password'] = "Mật khẩu và xác nhận mật khẩu không khớp.";
    }

    return $errors;
}

/**
 * Kiểm tra tên đăng nhập đã tồn tại trong cơ sở dữ liệu hay chưa.
 * @param string $username Tên đăng nhập cần kiểm tra.
 * @param mysqli $conn Đối tượng kết nối CSDL mysqli.
 * @return bool True nếu tồn tại, False nếu không. Trả về null nếu có lỗi CSDL.
 */
function is_username_taken(string $username, mysqli $conn): ?bool {
    $sql = "SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (is_username_taken): " . $conn->error);
        return null; 
    }

    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        error_log("Lỗi thực thi câu lệnh SQL (is_username_taken): " . $stmt->error);
        $stmt->close();
        return null; 
    }
    
    $stmt->store_result();
    $is_taken = $stmt->num_rows > 0;
    $stmt->close();
    
    return $is_taken;
}

/**
 * Kiểm tra email đã tồn tại trong cơ sở dữ liệu hay chưa.
 * @param string $email Email cần kiểm tra.
 * @param mysqli $conn Đối tượng kết nối CSDL mysqli.
 * @return bool True nếu tồn tại, False nếu không. Trả về null nếu có lỗi CSDL.
 */
function is_email_taken(string $email, mysqli $conn): ?bool {
    $sql = "SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (is_email_taken): " . $conn->error);
        return null; 
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        error_log("Lỗi thực thi câu lệnh SQL (is_email_taken): " . $stmt->error);
        $stmt->close();
        return null; 
    }

    $stmt->store_result();
    $is_taken = $stmt->num_rows > 0;
    $stmt->close();

    return $is_taken;
}

/**
 * Tạo người dùng mới trong cơ sở dữ liệu.
 * @param array $data Dữ liệu người dùng bao gồm 'fullname', 'username', 'email', 'school_name', 'password'.
 * @param mysqli $conn Đối tượng kết nối CSDL mysqli.
 * @return bool True nếu tạo thành công, False nếu thất bại.
 */
function create_user(array $data, mysqli $conn): bool {
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    if ($hashed_password === false) {
        error_log("Lỗi mã hóa mật khẩu.");
        return false; 
    }
    
    $school_name_to_db = $data['school_name']; // Tên trường đã được chuẩn hóa từ process_register.php

    $sql = "INSERT INTO `users` (`fullname`, `username`, `email`, `school_name`, `password_hash`, `created_at`, `updated_at`) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (create_user): " . $conn->error);
        return false;
    }

    $stmt->bind_param("sssss", $data['fullname'], $data['username'], $data['email'], $school_name_to_db, $hashed_password);

    if (!$stmt->execute()) {
        if ($conn->errno == 1062) { 
            error_log("Lỗi thực thi SQL (create_user - Duplicate entry): " . $stmt->error);
        } else {
            error_log("Lỗi thực thi SQL (create_user): " . $stmt->error);
        }
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

/**
 * Chuẩn hóa tên trường học.
 * Viết hoa chữ cái đầu của mỗi từ và thêm tiền tố "Tiểu học " nếu chưa có.
 * @param string $school_name Tên trường người dùng nhập.
 * @return string|null Tên trường đã được chuẩn hóa, hoặc null nếu input rỗng.
 */
function normalize_school_name(string $school_name): ?string {
    $trimmed_school_name = trim($school_name);
    if (empty($trimmed_school_name)) {
        return null;
    }

    // Viết hoa chữ cái đầu mỗi từ
    $capitalized_school_name = mb_convert_case($trimmed_school_name, MB_CASE_TITLE, "UTF-8");

    $prefix = "Tiểu học ";
    $prefix_lower = mb_strtolower($prefix);
    $name_prefix_lower = mb_strtolower(mb_substr($capitalized_school_name, 0, mb_strlen($prefix)));

    if ($name_prefix_lower === $prefix_lower) {
        // Đã có tiền tố (hoặc biến thể của nó), chuẩn hóa lại phần tên chính
        $main_part = mb_convert_case(trim(mb_substr($capitalized_school_name, mb_strlen($prefix))), MB_CASE_TITLE, "UTF-8");
        if(empty($main_part)) { // Trường hợp người dùng chỉ nhập "Tiểu học" hoặc "tiểu học "
            return trim($prefix); // Trả về "Tiểu học "
        }
        $normalized_name = $prefix . $main_part;
    } else {
        // Chưa có tiền tố, thêm vào
        $normalized_name = $prefix . $capitalized_school_name;
    }
    
    // Loại bỏ khoảng trắng thừa
    return trim(preg_replace('/\s+/', ' ', $normalized_name));
}
?>