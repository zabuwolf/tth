<?php
/**
 * File: admin/edit_user.php
 * Chức năng: Cho phép admin chỉnh sửa thông tin người dùng (bao gồm cả mật khẩu và quyền admin).
 * Cải tiến: Tăng cường bảo mật với CSRF token, cấu trúc lại logic, cải thiện xử lý lỗi và trải nghiệm người dùng.
 */

// 1. KHỞI TẠO & KIỂM TRA BẢO MẬT
// ==========================================

// Bắt đầu session nếu chưa tồn tại
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Tải file cấu hình CSDL
// Đường dẫn này giả định `edit_user.php` nằm trong thư mục `admin` và `config` nằm ở thư mục gốc.
require_once __DIR__ . '/../config/db_config.php';

// Kiểm tra quyền truy cập: người dùng phải đăng nhập và là admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['login_errors'] = ['general' => "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản admin."];
    header('Location: ../login.php');
    exit;
}

// Kết nối CSDL
$conn = connect_db();
if ($conn === null) {
    // Nếu không kết nối được CSDL, nên có một trang lỗi riêng thay vì chuyển hướng lung tung.
    // Ở đây, tạm thời hiển thị thông báo và dừng lại.
    die("Lỗi nghiêm trọng: Không thể kết nối đến Cơ sở dữ liệu.");
}

// 2. XÁC ĐỊNH NGƯỜI DÙNG & BIẾN LOGIC
// ========================================

$current_admin_id = (int)$_SESSION['user_id'];
$user_id_to_edit = 0;
$is_editing_self = false;

// Lấy ID người dùng cần sửa từ URL.
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_edit = (int)$_GET['id'];
} else {
    // Nếu không có ID, mặc định admin đang sửa hồ sơ của chính mình
    $user_id_to_edit = $current_admin_id;
}

if ($user_id_to_edit === $current_admin_id) {
    $is_editing_self = true;
}

// Nếu không xác định được ID để sửa, chuyển hướng về trang quản lý
if ($user_id_to_edit <= 0) {
    $_SESSION['error_message_admin'] = "ID người dùng không hợp lệ.";
    header('Location: manage_users.php');
    exit;
}


// 3. XỬ LÝ KHI FORM ĐƯỢC GỬI (HTTP POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- 3.1. Xác thực CSRF Token ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['form_errors_admin_edit'] = ['general' => 'Lỗi xác thực form không hợp lệ. Vui lòng thử lại.'];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $user_id_to_edit);
        exit;
    }
    // Xóa token sau khi dùng
    unset($_SESSION['csrf_token']);


    // --- 3.2. Lấy dữ liệu từ Form ---
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // Chỉ admin mới có thể thay đổi quyền, và không thể tự thay đổi cho chính mình
    $is_admin_from_form = (!$is_editing_self && isset($_POST['is_admin'])) ? 1 : 0;
    
    $form_errors = [];

    // Lấy thông tin hiện tại của người dùng để so sánh
    $stmt_get_user_for_validation = $conn->prepare("SELECT email, password_hash, is_admin FROM users WHERE id = ?");
    $stmt_get_user_for_validation->bind_param("i", $user_id_to_edit);
    $stmt_get_user_for_validation->execute();
    $user_current_data = $stmt_get_user_for_validation->get_result()->fetch_assoc();
    $stmt_get_user_for_validation->close();
    
    // --- 3.3. Validate dữ liệu ---
    if (empty($fullname)) {
        $form_errors['fullname'] = "Họ tên không được để trống.";
    }

    if (empty($email)) {
        $form_errors['email'] = "Email không được để trống.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_errors['email'] = "Địa chỉ email không hợp lệ.";
    } elseif ($email !== $user_current_data['email']) {
        // Kiểm tra email đã tồn tại chưa nếu người dùng thay đổi email
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check_email->bind_param("si", $email, $user_id_to_edit);
        $stmt_check_email->execute();
        if ($stmt_check_email->get_result()->num_rows > 0) {
            $form_errors['email'] = "Địa chỉ email này đã được sử dụng.";
        }
        $stmt_check_email->close();
    }
    
    // --- 3.4. Validate mật khẩu (nếu có thay đổi) ---
    $new_password_hashed = null;
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $form_errors['new_password'] = "Mật khẩu mới phải có ít nhất 6 ký tự.";
        }
        if ($new_password !== $confirm_password) {
            $form_errors['confirm_password'] = "Xác nhận mật khẩu mới không khớp.";
        }

        // Nếu admin đang tự sửa mật khẩu, yêu cầu mật khẩu hiện tại
        if ($is_editing_self) {
            $current_password = $_POST['current_password'] ?? '';
            if (empty($current_password)) {
                $form_errors['current_password'] = "Vui lòng nhập mật khẩu hiện tại để thay đổi.";
            } elseif (!password_verify($current_password, $user_current_data['password_hash'])) {
                $form_errors['current_password'] = "Mật khẩu hiện tại không chính xác.";
            }
        }
        
        // Nếu không có lỗi mật khẩu, hash mật khẩu mới
        if (!isset($form_errors['new_password']) && !isset($form_errors['confirm_password']) && !isset($form_errors['current_password'])) {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // --- 3.5. Validate quyền Admin ---
    // Ngăn admin cuối cùng tự bỏ quyền của mình
    if ($is_editing_self && $user_current_data['is_admin'] == 1 && $is_admin_from_form == 0) {
         $stmt_count_admins = $conn->query("SELECT COUNT(id) as admin_count FROM users WHERE is_admin = 1");
         $admin_count = $stmt_count_admins->fetch_assoc()['admin_count'];
         if ($admin_count <= 1) {
             $form_errors['is_admin'] = "Không thể tự bỏ quyền của quản trị viên cuối cùng.";
         }
    }

    // --- 3.6. Xử lý sau khi validate ---
    if (empty($form_errors)) {
        // Bắt đầu transaction
        $conn->begin_transaction();
        try {
            // Xây dựng câu lệnh UPDATE động
            $sql_parts = [];
            $param_types = "";
            $param_values = [];

            $sql_parts[] = "fullname = ?"; $param_types .= "s"; $param_values[] = $fullname;
            $sql_parts[] = "email = ?"; $param_types .= "s"; $param_values[] = $email;
            $sql_parts[] = "avatar_url = ?"; $param_types .= "s"; $param_values[] = $avatar_url === '' ? null : $avatar_url;

            if ($new_password_hashed !== null) {
                $sql_parts[] = "password_hash = ?"; $param_types .= "s"; $param_values[] = $new_password_hashed;
            }

            // Chỉ cho phép cập nhật quyền admin nếu không phải đang tự sửa
            if (!$is_editing_self) {
                $sql_parts[] = "is_admin = ?"; $param_types .= "i"; $param_values[] = $is_admin_from_form;
            }
            
            // Thêm ID người dùng vào cuối
            $param_values[] = $user_id_to_edit;
            $param_types .= "i";

            $sql_query = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_query);
            $stmt_update->bind_param($param_types, ...$param_values);
            
            if ($stmt_update->execute()) {
                $conn->commit();
                $_SESSION['success_message_admin'] = "Thông tin người dùng đã được cập nhật thành công!";
                header('Location: manage_users.php'); // Chuyển về trang quản lý
                exit;
            } else {
                throw new Exception("Lỗi khi thực thi câu lệnh cập nhật.");
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Lỗi cập nhật user admin: " . $e->getMessage());
            $_SESSION['form_errors_admin_edit'] = ['general' => "Đã có lỗi xảy ra trong quá trình cập nhật. Vui lòng thử lại."];
        }
    }

    // Nếu có lỗi, lưu lỗi và dữ liệu đã nhập vào session, sau đó chuyển hướng lại form
    if (!empty($form_errors)) {
        $_SESSION['form_errors_admin_edit'] = $form_errors;
        $_SESSION['old_form_input_admin_edit'] = $_POST; // Lưu lại dữ liệu người dùng đã nhập
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $user_id_to_edit);
        exit;
    }
}


// 4. LẤY DỮ LIỆU ĐỂ HIỂN THỊ (HTTP GET hoặc sau khi xử lý POST bị lỗi)
// =====================================================================

// Lấy thông tin chi tiết của người dùng để hiển thị trên form
$stmt_get_user = $conn->prepare("SELECT id, username, email, fullname, avatar_url, is_admin FROM users WHERE id = ?");
$stmt_get_user->bind_param("i", $user_id_to_edit);
$stmt_get_user->execute();
$result = $stmt_get_user->get_result();
$user_to_edit = $result->fetch_assoc();
$stmt_get_user->close();

// Nếu không tìm thấy người dùng, chuyển hướng
if (!$user_to_edit) {
    $_SESSION['error_message_admin'] = "Không tìm thấy người dùng với ID được cung cấp.";
    header('Location: manage_users.php');
    exit;
}

// Lấy lỗi và dữ liệu cũ từ session (nếu có, từ lần submit trước bị lỗi)
$form_errors = $_SESSION['form_errors_admin_edit'] ?? [];
$old_input = $_SESSION['old_form_input_admin_edit'] ?? [];
unset($_SESSION['form_errors_admin_edit'], $_SESSION['old_form_input_admin_edit']);

// Điền lại form bằng dữ liệu cũ nếu có, nếu không thì dùng dữ liệu từ CSDL
$display_fullname = $old_input['fullname'] ?? $user_to_edit['fullname'];
$display_email = $old_input['email'] ?? $user_to_edit['email'];
$display_avatar_url = $old_input['avatar_url'] ?? $user_to_edit['avatar_url'];
$display_is_admin = isset($old_input['is_admin']) ? 1 : ($user_to_edit['is_admin'] ?? 0);

// Tạo CSRF token mới cho form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Đóng kết nối CSDL
close_db_connection($conn);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa: <?php echo htmlspecialchars($user_to_edit['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #f3f4f6; }
        .form-container { max-width: 700px; margin: 2rem auto; background-color: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .form-input { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; transition: border-color 0.2s; font-size: 0.9rem; }
        .form-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .form-input.is-invalid { border-color: #ef4444; }
        .form-input[readonly] { background-color: #f3f4f6; cursor: not-allowed; }
        .form-label { display: block; margin-bottom: 0.375rem; font-weight: 600; color: #374151; font-size: 0.875rem; }
        .error-text { color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; }
        .alert-error { background-color: #fef2f2; color: #991b1b; padding: 0.75rem; border-radius: 0.375rem; margin-bottom: 1.5rem; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1 class="text-2xl sm:text-3xl font-bold text-indigo-600 mb-6 text-center">
            Chỉnh Sửa Thông Tin: <?php echo htmlspecialchars($user_to_edit['username']); ?>
        </h1>

        <?php if (isset($form_errors['general'])): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($form_errors['general']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $user_id_to_edit); ?>" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div class="mb-4">
                    <label for="username" class="form-label">Tên đăng nhập</label>
                    <input type="text" id="username" class="form-input" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" readonly>
                    <p class="text-xs text-gray-500 mt-1">Tên đăng nhập không thể thay đổi.</p>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input <?php echo isset($form_errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($display_email); ?>" required>
                    <?php if (isset($form_errors['email'])): ?><p class="error-text"><?php echo $form_errors['email']; ?></p><?php endif; ?>
                </div>
            
                <div class="mb-4 md:col-span-2">
                    <label for="fullname" class="form-label">Họ và Tên</label>
                    <input type="text" id="fullname" name="fullname" class="form-input <?php echo isset($form_errors['fullname']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($display_fullname); ?>" required>
                    <?php if (isset($form_errors['fullname'])): ?><p class="error-text"><?php echo $form_errors['fullname']; ?></p><?php endif; ?>
                </div>

                <div class="mb-4 md:col-span-2">
                    <label for="avatar_url" class="form-label">URL Ảnh đại diện (tùy chọn)</label>
                    <input type="url" id="avatar_url" name="avatar_url" class="form-input" placeholder="https://example.com/avatar.png" value="<?php echo htmlspecialchars($display_avatar_url); ?>">
                    <?php if(!empty($display_avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($display_avatar_url); ?>" alt="Avatar hiện tại" class="mt-2 h-16 w-16 rounded-full object-cover border border-gray-300" onerror="this.style.display='none';">
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 border-t pt-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-3">Thay Đổi Mật Khẩu</h2>
                <p class="text-sm text-gray-500 mb-4">Để trống các trường mật khẩu nếu bạn không muốn thay đổi.</p>
                
                <?php if ($is_editing_self): ?>
                <div class="mb-4">
                    <label for="current_password" class="form-label">Mật khẩu hiện tại (bắt buộc nếu đổi mật khẩu)</label>
                    <input type="password" id="current_password" name="current_password" class="form-input <?php echo isset($form_errors['current_password']) ? 'is-invalid' : ''; ?>" autocomplete="current-password">
                    <?php if (isset($form_errors['current_password'])): ?><p class="error-text"><?php echo $form_errors['current_password']; ?></p><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="mb-4">
                        <label for="new_password" class="form-label">Mật khẩu mới</label>
                        <input type="password" id="new_password" name="new_password" class="form-input <?php echo isset($form_errors['new_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password" placeholder="Ít nhất 6 ký tự">
                        <?php if (isset($form_errors['new_password'])): ?><p class="error-text"><?php echo $form_errors['new_password']; ?></p><?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input <?php echo isset($form_errors['confirm_password']) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
                        <?php if (isset($form_errors['confirm_password'])): ?><p class="error-text"><?php echo $form_errors['confirm_password']; ?></p><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t pt-6">
                <label for="is_admin" class="flex items-center <?php echo $is_editing_self ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'; ?>">
                    <input type="checkbox" id="is_admin" name="is_admin" value="1" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        <?php echo ($display_is_admin) ? 'checked' : ''; ?>
                        <?php if ($is_editing_self) { echo 'disabled title="Bạn không thể tự thay đổi quyền của chính mình."'; } ?>>
                    <span class="ml-2 text-gray-700 font-semibold">Là Quản Trị Viên (Admin)</span>
                </label>
                <?php if ($is_editing_self): ?>
                    <p class="text-xs text-gray-500 mt-1">Để đảm bảo an toàn, bạn không thể tự thay đổi quyền quản trị của chính mình tại đây.</p>
                <?php endif; ?>
                 <?php if (isset($form_errors['is_admin'])): ?><p class="error-text mt-1"><?php echo $form_errors['is_admin']; ?></p><?php endif; ?>
            </div>


            <div class="mt-8 flex flex-col sm:flex-row justify-end sm:space-x-3 space-y-3 sm:space-y-0">
                <a href="manage_users.php" class="text-center py-2 px-4 rounded-md font-semibold bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                    <i class="fas fa-times mr-2"></i>Hủy Bỏ
                </a>
                <button type="submit" class="text-center py-2 px-4 rounded-md font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i>Lưu Thay Đổi
                </button>
            </div>
        </form>
    </div>
</body>
</html>