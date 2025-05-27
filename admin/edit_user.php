<?php
// File: admin/edit_user.php (Giả sử nằm trong thư mục admin)
// Nhiệm vụ: Cho phép người dùng chỉnh sửa thông tin cá nhân (bao gồm mật khẩu)
// và cho phép admin chỉnh sửa thông tin của người dùng khác.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Điều chỉnh đường dẫn cho require_once nếu file này nằm trong thư mục con 'admin'
// và thư mục 'config' nằm ở thư mục gốc (ngang hàng với 'admin')
require_once '../config/db_config.php'; // ĐÃ THAY ĐỔI ĐƯỜNG DẪN

// Kiểm tra đăng nhập (logic này có thể cần điều chỉnh nếu session admin của bạn khác)
// Mã hiện tại dùng $_SESSION['logged_in'] và $_SESSION['is_admin']
// Nếu trang admin của bạn dùng session riêng như $_SESSION['admin_logged_in'],
// bạn cần thay thế logic kiểm tra đăng nhập cho phù hợp.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    // Nếu không phải admin hoặc không đăng nhập, có thể chuyển hướng về trang login của admin hoặc trang chính
    $_SESSION['error_message'] = "Vui lòng đăng nhập với quyền quản trị để truy cập.";
    header('Location: ../login.php'); // Hoặc admin_login.php nếu có
    exit;
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này.";
    header('Location: ../dashboard.php'); // Hoặc trang chính của người dùng
    exit;
}


$conn = connect_db();
if ($conn === null) {
    $_SESSION['error_message'] = "Lỗi kết nối cơ sở dữ liệu.";
    // Chuyển hướng đến trang dashboard của admin nếu có, hoặc trang lỗi
    header('Location: index.php'); // Giả sử admin có trang index.php riêng
    exit;
}

$current_logged_in_user_id = (int)$_SESSION['user_id']; // ID của admin đang đăng nhập
$is_current_user_admin = true; // Đã xác thực là admin ở trên

// Xác định user_id cần chỉnh sửa
$user_id_to_edit = null; 
$is_editing_self = false;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_edit = (int)$_GET['id'];
    if ($user_id_to_edit === $current_logged_in_user_id) {
        $is_editing_self = true;
    }
} else if (strpos($_SERVER['REQUEST_URI'], 'edit_user.php') !== false && !isset($_GET['id'])) {
    // Nếu admin truy cập edit_user.php mà không có id, mặc định là sửa chính mình
    // Điều này hữu ích nếu admin có link "Sửa hồ sơ của tôi" trỏ đến edit_user.php không có tham số id
    $user_id_to_edit = $current_logged_in_user_id;
    $is_editing_self = true;
}


if ($user_id_to_edit === null) {
    $_SESSION['error_message_admin'] = "Không có ID người dùng được cung cấp để sửa."; // Sử dụng session error của admin
    header('Location: manage_users.php'); // Trang quản lý người dùng của admin
    exit;
}


// Lấy thông tin người dùng cần chỉnh sửa
// Đảm bảo chọn cột password_hash (hoặc tên cột mật khẩu đúng trong CSDL của bạn)
$sql_get_user = "SELECT id, username, email, fullname, avatar_url, is_admin, password_hash 
                 FROM users 
                 WHERE id = ? LIMIT 1";
$stmt_get_user = $conn->prepare($sql_get_user);
$user_to_edit = null; 

if ($stmt_get_user) {
    $stmt_get_user->bind_param("i", $user_id_to_edit);
    $stmt_get_user->execute();
    $result_user = $stmt_get_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_to_edit = $result_user->fetch_assoc();
    } else {
        $_SESSION['error_message_admin'] = "Không tìm thấy người dùng với ID: {$user_id_to_edit}";
        header('Location: manage_users.php');
        exit;
    }
    $stmt_get_user->close();
} else {
    error_log("Lỗi chuẩn bị SQL (get_user_to_edit) trong admin/edit_user.php: " . $conn->error);
    $_SESSION['error_message_admin'] = "Lỗi hệ thống khi truy vấn thông tin người dùng.";
    header('Location: manage_users.php');
    exit;
}

// Xử lý khi form được gửi đi (POST request)
$success_message = '';
$form_errors = []; // Sử dụng mảng này để chứa lỗi cụ thể cho từng trường

// Kiểm tra xem có lỗi từ lần submit trước không (nếu dùng redirect để hiển thị lỗi)
if(isset($_SESSION['form_errors_admin_edit'])) {
    $form_errors = $_SESSION['form_errors_admin_edit'];
    unset($_SESSION['form_errors_admin_edit']);
}
if(isset($_SESSION['old_form_input_admin_edit'])) {
    // Điền lại form với dữ liệu cũ nếu có lỗi
    $user_to_edit['fullname'] = $_SESSION['old_form_input_admin_edit']['fullname'] ?? $user_to_edit['fullname'];
    $user_to_edit['email'] = $_SESSION['old_form_input_admin_edit']['email'] ?? $user_to_edit['email'];
    $user_to_edit['avatar_url'] = $_SESSION['old_form_input_admin_edit']['avatar_url'] ?? $user_to_edit['avatar_url'];
    // is_admin sẽ được xử lý riêng
    unset($_SESSION['old_form_input_admin_edit']);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_user_id = isset($_POST['user_id_to_edit_field']) ? (int)$_POST['user_id_to_edit_field'] : 0;
    if ($posted_user_id !== $user_id_to_edit) {
        $form_errors['general'] = "Lỗi xác thực form. ID không khớp.";
    } else {
        $fullname_input = trim($_POST['fullname'] ?? '');
        $email_input = trim($_POST['email'] ?? '');
        $avatar_url_input = trim($_POST['avatar_url'] ?? '');
        $current_password_input = $_POST['current_password'] ?? ''; // Chỉ cần nếu admin tự đổi pass và có yêu cầu pass cũ
        $new_password_input = $_POST['new_password'] ?? '';
        $confirm_password_input = $_POST['confirm_password'] ?? '';
        $is_admin_from_form = isset($_POST['is_admin']) ? 1 : 0;

        if (empty($fullname_input)) {
            $form_errors['fullname'] = "Họ tên không được để trống.";
        }
        if (empty($email_input)) {
             $form_errors['email'] = "Email không được để trống.";
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = "Địa chỉ email không hợp lệ.";
        }
        
        if ($email_input !== $user_to_edit['email']) {
            $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1";
            $stmt_check_email = $conn->prepare($sql_check_email);
            if ($stmt_check_email) {
                $stmt_check_email->bind_param("si", $email_input, $user_id_to_edit);
                $stmt_check_email->execute();
                if ($stmt_check_email->get_result()->num_rows > 0) {
                    $form_errors['email'] = "Địa chỉ email này đã được sử dụng bởi người dùng khác.";
                }
                $stmt_check_email->close();
            } else {
                 $form_errors['general'] = "Lỗi kiểm tra email.";
            }
        }
        
        $password_changed_successfully = false;
        $password_update_sql_part = "";
        $new_password_hashed_for_update = null;

        if (!empty($new_password_input) || !empty($confirm_password_input)) {
            // Nếu admin đang tự sửa mật khẩu của mình, có thể yêu cầu mật khẩu hiện tại
            if ($is_editing_self && $is_current_user_admin) { 
                if (empty($current_password_input)) {
                    $form_errors['current_password'] = "Vui lòng nhập mật khẩu hiện tại của bạn để thay đổi.";
                } elseif (!password_verify($current_password_input, $user_to_edit['password_hash'])) { 
                    $form_errors['current_password'] = "Mật khẩu hiện tại không chính xác.";
                }
            }
            // Admin có thể đổi mật khẩu người khác mà không cần mật khẩu hiện tại của người đó

            if (empty($new_password_input)) {
                $form_errors['new_password'] = "Mật khẩu mới không được để trống nếu bạn muốn thay đổi.";
            } elseif (strlen($new_password_input) < 6) {
                $form_errors['new_password'] = "Mật khẩu mới phải có ít nhất 6 ký tự.";
            }
            if ($new_password_input !== $confirm_password_input) {
                $form_errors['confirm_password'] = "Xác nhận mật khẩu mới không khớp.";
            }
            
            if (empty($form_errors['current_password']) && empty($form_errors['new_password']) && empty($form_errors['confirm_password'])) {
                $new_password_hashed_for_update = password_hash($new_password_input, PASSWORD_DEFAULT);
                $password_update_sql_part = ", password_hash = ?"; // Sử dụng password_hash
                $password_changed_successfully = true;
            }
        }

        $final_is_admin_value = $user_to_edit['is_admin']; 
        $can_try_update_admin_status = false;

        if ($is_current_user_admin) {
            if (!$is_editing_self) { 
                $final_is_admin_value = $is_admin_from_form; 
                $can_try_update_admin_status = true; 
            } else { 
                if ($is_admin_from_form == 0 && $user_to_edit['is_admin'] == 1) {
                    $sql_count_admins = "SELECT COUNT(id) as admin_count FROM users WHERE is_admin = 1";
                    $res_count = $conn->query($sql_count_admins);
                    $admin_count_row = $res_count ? $res_count->fetch_assoc() : null; // Kiểm tra $res_count
                    if ($admin_count_row && $admin_count_row['admin_count'] <= 1) {
                        $form_errors['is_admin'] = "Bạn không thể tự bỏ quyền của admin cuối cùng.";
                    } else {
                        // Nếu admin cố tình bỏ quyền (ví dụ qua dev tools) và không phải admin cuối cùng
                        // thì cho phép (hoặc chặn tùy logic của bạn).
                        // Hiện tại, checkbox bị disabled nên giá trị này không nên được gửi.
                        // Nếu vẫn gửi, giữ nguyên quyền admin hiện tại để an toàn.
                        $final_is_admin_value = $user_to_edit['is_admin']; 
                    }
                }
                // $can_try_update_admin_status vẫn là false khi admin tự sửa
            }
        }

        if (empty($form_errors)) {
            $conn->begin_transaction();
            try {
                $sql_update_parts = [];
                $params_types = "";
                $params_values = [];

                // Chỉ thêm vào update nếu giá trị thay đổi so với CSDL hoặc là mật khẩu mới
                if ($fullname_input !== $user_to_edit['fullname']) {
                    $sql_update_parts[] = "fullname = ?"; $params_types .= "s"; $params_values[] = $fullname_input;
                }
                if ($email_input !== $user_to_edit['email']) {
                    $sql_update_parts[] = "email = ?"; $params_types .= "s"; $params_values[] = $email_input;
                }
                if ($avatar_url_input !== $user_to_edit['avatar_url']) {
                     $sql_update_parts[] = "avatar_url = ?"; $params_types .= "s"; $params_values[] = $avatar_url_input === '' ? null : $avatar_url_input;
                }
                if ($password_changed_successfully && $new_password_hashed_for_update) {
                    $sql_update_parts[] = "password_hash = ?"; $params_types .= "s"; $params_values[] = $new_password_hashed_for_update;
                }
                if ($can_try_update_admin_status && (int)$final_is_admin_value !== (int)$user_to_edit['is_admin']) {
                    $sql_update_parts[] = "is_admin = ?"; $params_types .= "i"; $params_values[] = $final_is_admin_value;
                }
                
                if (!empty($sql_update_parts)) {
                    $params_values[] = $user_id_to_edit; 
                    $params_types .= "i";
                    $sql_update_query = "UPDATE users SET " . implode(", ", $sql_update_parts) . " WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update_query);

                    if ($stmt_update) {
                        $stmt_update->bind_param($params_types, ...$params_values); 
                        if ($stmt_update->execute()) {
                            $conn->commit();
                            $_SESSION['success_message_admin'] = "Thông tin người dùng đã được cập nhật thành công!";
                            // Chuyển hướng về trang quản lý người dùng để thấy thay đổi
                            header('Location: manage_users.php?update_success=1');
                            exit;
                        } else {
                            $conn->rollback();
                            $form_errors['general'] = "Lỗi khi cập nhật thông tin: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    } else {
                        $conn->rollback();
                        $form_errors['general'] = "Lỗi chuẩn bị câu lệnh cập nhật: " . $conn->error;
                    }
                } else {
                    $success_message = "Không có thông tin nào được thay đổi."; 
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Lỗi Exception khi cập nhật user (admin): " . $e->getMessage());
                $form_errors['general'] = "Đã có lỗi không mong muốn xảy ra trong quá trình cập nhật.";
            }
        }
        // Nếu có lỗi validate, lưu vào session và redirect lại để hiển thị lỗi
        if (!empty($form_errors)) {
            $_SESSION['form_errors_admin_edit'] = $form_errors;
            $_SESSION['old_form_input_admin_edit'] = $_POST; // Lưu lại toàn bộ POST data
            header('Location: edit_user.php?id=' . $user_id_to_edit);
            exit;
        }
    } 
}

// Lấy lại thông tin người dùng một lần nữa nếu có cập nhật thành công (để form hiển thị đúng)
// Hoặc nếu không phải POST request (lần đầu tải trang)
if ($success_message || $_SERVER["REQUEST_METHOD"] != "POST") {
    $stmt_reload_user = $conn->prepare("SELECT id, username, email, fullname, avatar_url, is_admin, password_hash FROM users WHERE id = ? LIMIT 1");
    if($stmt_reload_user){
        $stmt_reload_user->bind_param("i", $user_id_to_edit);
        $stmt_reload_user->execute();
        $reloaded_result = $stmt_reload_user->get_result();
        if($reloaded_result->num_rows === 1){
            $user_to_edit = $reloaded_result->fetch_assoc();
        }
        $stmt_reload_user->close();
    }
}


close_db_connection($conn);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Thông Tin: <?php echo htmlspecialchars($user_to_edit['username'] ?? 'Không rõ'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #f3f4f6; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .form-container { max-width: 700px; margin: 2rem auto; background-color: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
        .form-input { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; transition: border-color 0.2s; font-size: 0.9rem; }
        .form-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .form-input.bg-gray-100[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
        .form-label { display: block; margin-bottom: 0.375rem; font-weight: 500; color: #374151; font-size: 0.875rem; }
        .form-group { margin-bottom: 1.25rem; }
        .btn { padding: 0.65rem 1.25rem; border-radius: 0.375rem; font-weight: 600; transition: background-color 0.2s; font-size: 0.9rem; }
        .btn-primary { background-color: #4f46e5; color: white; }
        .btn-primary:hover { background-color: #4338ca; }
        .btn-secondary { background-color: #6b7280; color: white; }
        .btn-secondary:hover { background-color: #4b5563; }
        .error-text { color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; }
        .success-message { background-color: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 0.375rem; margin-bottom: 1.5rem; border-left: 4px solid #22c55e; }
        .password-section { border-top: 1px solid #e5e7eb; margin-top: 1.75rem; padding-top: 1.75rem; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1 class="font-baloo text-2xl sm:text-3xl font-bold text-indigo-600 mb-6 text-center">
            Chỉnh Sửa Thông Tin: <?php echo htmlspecialchars($user_to_edit['username'] ?? 'N/A'); ?>
        </h1>

        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($form_errors['general'])): ?>
            <div class="p-3 bg-red-100 border border-red-300 rounded-md mb-4 text-red-700">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($form_errors['general']); ?>
            </div>
        <?php endif; ?>

        <?php if ($user_to_edit): // Chỉ hiển thị form nếu có dữ liệu người dùng ?>
        <form action="edit_user.php?id=<?php echo $user_id_to_edit; ?>" method="POST">
            <input type="hidden" name="user_id_to_edit_field" value="<?php echo $user_id_to_edit; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div class="form-group">
                    <label for="username" class="form-label">Tên đăng nhập:</label>
                    <input type="text" id="username" name="username_display" class="form-input bg-gray-100" 
                           value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" readonly>
                    <p class="text-xs text-gray-500 mt-1">Tên đăng nhập không thể thay đổi.</p>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user_to_edit['email'] ?? ''); ?>" required>
                    <?php if (isset($form_errors['email'])): ?><p class="error-text"><?php echo $form_errors['email']; ?></p><?php endif; ?>
                </div>
            
                <div class="form-group md:col-span-2">
                    <label for="fullname" class="form-label">Họ và Tên:</label>
                    <input type="text" id="fullname" name="fullname" class="form-input" 
                           value="<?php echo htmlspecialchars($user_to_edit['fullname'] ?? ''); ?>" required>
                    <?php if (isset($form_errors['fullname'])): ?><p class="error-text"><?php echo $form_errors['fullname']; ?></p><?php endif; ?>
                </div>

                <div class="form-group md:col-span-2">
                    <label for="avatar_url" class="form-label">URL Ảnh đại diện (tùy chọn):</label>
                    <input type="url" id="avatar_url" name="avatar_url" class="form-input" 
                           placeholder="https://example.com/avatar.png"
                           value="<?php echo htmlspecialchars($user_to_edit['avatar_url'] ?? ''); ?>">
                    <?php if(!empty($user_to_edit['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($user_to_edit['avatar_url']); ?>" 
                             alt="Avatar hiện tại" 
                             class="mt-2 h-16 w-16 rounded-full object-cover border border-gray-300"
                             onerror="this.style.display='none';">
                    <?php endif; ?>
                </div>
            </div>

            <div class="password-section">
                <h2 class="font-baloo text-xl font-semibold text-gray-700 mb-3">Thay Đổi Mật Khẩu</h2>
                <p class="text-sm text-gray-500 mb-4">Để trống các trường mật khẩu nếu bạn không muốn thay đổi.</p>
                
                <?php if ($is_editing_self && $is_current_user_admin): // Admin tự sửa, có thể yêu cầu mật khẩu hiện tại ?>
                <div class="form-group">
                    <label for="current_password" class="form-label">Mật khẩu hiện tại (nếu đổi mật khẩu):</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" autocomplete="current-password">
                    <?php if (isset($form_errors['current_password'])): ?><p class="error-text"><?php echo $form_errors['current_password']; ?></p><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="form-group">
                        <label for="new_password" class="form-label">Mật khẩu mới:</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" autocomplete="new-password" placeholder="Ít nhất 6 ký tự">
                        <?php if (isset($form_errors['new_password'])): ?><p class="error-text"><?php echo $form_errors['new_password']; ?></p><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" autocomplete="new-password">
                        <?php if (isset($form_errors['confirm_password'])): ?><p class="error-text"><?php echo $form_errors['confirm_password']; ?></p><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($is_current_user_admin): // Chỉ admin mới thấy và có thể thay đổi quyền (trừ khi tự sửa) ?>
            <div class="form-group mt-6 border-t pt-6">
                <label for="is_admin" class="flex items-center <?php echo $is_editing_self ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'; ?>">
                    <input type="checkbox" id="is_admin" name="is_admin" value="1" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                           <?php echo ($user_to_edit['is_admin'] ?? 0) ? 'checked' : ''; ?>
                           <?php if ($is_editing_self) { echo 'disabled title="Bạn không thể tự thay đổi quyền admin của chính mình."'; } ?>>
                    <span class="ml-2 text-gray-700">Là Quản Trị Viên (Admin)</span>
                </label>
                <?php if (isset($form_errors['is_admin'])): ?>
                    <p class="error-text"><?php echo $form_errors['is_admin']; ?></p>
                <?php endif; ?>
                <?php if ($is_editing_self && $is_current_user_admin): ?>
                    <p class="text-xs text-gray-500 mt-1">Quyền admin của bạn không thể thay đổi qua form này.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>


            <div class="mt-8 flex flex-col sm:flex-row justify-end sm:space-x-3 space-y-3 sm:space-y-0">
                <a href="<?php echo $is_current_user_admin ? 'manage_users.php' : '../dashboard.php'; ?>" class="btn btn-secondary text-center">
                    <i class="fas fa-times mr-2"></i>Hủy Bỏ
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i>Lưu Thay Đổi
                </button>
            </div>
        </form>
        <?php else: ?>
            <p class="text-red-600 text-center">Không thể tải thông tin người dùng. Vui lòng thử lại hoặc liên hệ quản trị viên.</p>
        <?php endif; ?>
    </div>
</body>
</html>

