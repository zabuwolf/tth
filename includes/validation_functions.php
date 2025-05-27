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
        'password' => $post_data['password'] ?? '', // Mật khẩu không trim() để giữ nguyên nếu người dùng cố ý nhập khoảng trắng
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

    // Mật khẩu
    if (empty($input['password'])) {
        $errors['password'] = "Mật khẩu không được để trống.";
    } elseif (strlen($input['password']) < 6) {
        $errors['password'] = "Mật khẩu phải có ít nhất 6 ký tự.";
    }
    // (Bạn có thể thêm các quy tắc phức tạp hơn cho mật khẩu ở đây nếu muốn)
    // Ví dụ: yêu cầu chữ hoa, chữ thường, số, ký tự đặc biệt.
    // elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $input['password'])) {
    //     $errors['password'] = "Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt.";
    // }


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
        return null; // Lỗi truy vấn
    }

    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        error_log("Lỗi thực thi câu lệnh SQL (is_username_taken): " . $stmt->error);
        $stmt->close();
        return null; // Lỗi truy vấn
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
        return null; // Lỗi truy vấn
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        error_log("Lỗi thực thi câu lệnh SQL (is_email_taken): " . $stmt->error);
        $stmt->close();
        return null; // Lỗi truy vấn
    }

    $stmt->store_result();
    $is_taken = $stmt->num_rows > 0;
    $stmt->close();

    return $is_taken;
}

/**
 * Tạo người dùng mới trong cơ sở dữ liệu.
 * @param array $data Dữ liệu người dùng bao gồm 'fullname', 'username', 'email', 'password'.
 * @param mysqli $conn Đối tượng kết nối CSDL mysqli.
 * @return bool True nếu tạo thành công, False nếu thất bại.
 */
function create_user(array $data, mysqli $conn): bool {
    // Mã hóa mật khẩu trước khi lưu
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    if ($hashed_password === false) {
        error_log("Lỗi mã hóa mật khẩu.");
        return false; // Lỗi khi hash mật khẩu
    }

    $sql = "INSERT INTO `users` (`fullname`, `username`, `email`, `password_hash`, `created_at`, `updated_at`) 
            VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (create_user): " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssss", $data['fullname'], $data['username'], $data['email'], $hashed_password);

    if (!$stmt->execute()) {
        // Kiểm tra lỗi trùng lặp (ví dụ: username hoặc email đã tồn tại do race condition dù đã kiểm tra trước)
        if ($conn->errno == 1062) { // Mã lỗi cho duplicate entry
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

?>
