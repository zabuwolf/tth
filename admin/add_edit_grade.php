<?php
session_start();
require_once '../config/db_config.php'; 

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_login_error'] = "Vui lòng đăng nhập để truy cập trang quản trị.";
    header('Location: admin_login.php');
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';

$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

$page_title = "Thêm Khối Lớp Mới";
$form_action = "add_edit_grade.php";
$grade_id = null;
$current_grade = [
    'name' => '',
    'description' => ''
];
$errors = $_SESSION['form_errors_admin'] ?? [];
$old_input = $_SESSION['old_form_input_admin'] ?? [];
unset($_SESSION['form_errors_admin'], $_SESSION['old_form_input_admin']);

if (isset($_GET['id'])) {
    $grade_id = (int)$_GET['id'];
    $page_title = "Sửa Khối Lớp (ID: $grade_id)";
    $form_action = "add_edit_grade.php?id=" . $grade_id; 

    $sql_fetch_grade = "SELECT * FROM grades WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_grade);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $grade_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_grade = $result_fetch->fetch_assoc();
        } else {
            $_SESSION['error_message_admin'] = "Không tìm thấy khối lớp với ID: $grade_id";
            header('Location: manage_grades.php');
            exit;
        }
        $stmt_fetch->close();
    } else {
        $_SESSION['error_message_admin'] = "Lỗi chuẩn bị truy vấn khối lớp: " . $conn->error;
        header('Location: manage_grades.php');
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_grade['name'] = trim($_POST['name'] ?? '');
    $current_grade['description'] = trim($_POST['description'] ?? '');

    if (empty($current_grade['name'])) $errors['name'] = "Tên khối lớp không được để trống.";
    if (strlen($current_grade['name']) > 50) $errors['name'] = "Tên khối lớp không được vượt quá 50 ký tự.";

    // Kiểm tra tên khối lớp đã tồn tại chưa (trừ trường hợp đang sửa chính nó)
    $sql_check_name = "SELECT id FROM grades WHERE name = ? AND (? IS NULL OR id != ?)";
    $stmt_check_name = $conn->prepare($sql_check_name);
    if($stmt_check_name){
        $stmt_check_name->bind_param("sii", $current_grade['name'], $grade_id, $grade_id);
        $stmt_check_name->execute();
        $stmt_check_name->store_result();
        if($stmt_check_name->num_rows > 0){
            $errors['name'] = "Tên khối lớp này đã tồn tại.";
        }
        $stmt_check_name->close();
    }


    if (empty($errors)) {
        if ($grade_id) { // Sửa
            $sql_update = "UPDATE grades SET name=?, description=? WHERE id=?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("ssi", $current_grade['name'], $current_grade['description'], $grade_id);
                if ($stmt_update->execute()) {
                    $_SESSION['success_message_admin'] = "Đã cập nhật khối lớp (ID: $grade_id) thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi cập nhật khối lớp: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị cập nhật khối lớp: " . $conn->error;
            }
        } else { // Thêm mới
            $sql_insert = "INSERT INTO grades (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                 $stmt_insert->bind_param("ss", $current_grade['name'], $current_grade['description']);
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message_admin'] = "Đã thêm khối lớp mới thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi thêm khối lớp mới: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị thêm mới khối lớp: " . $conn->error;
            }
        }
        header('Location: manage_grades.php');
        exit;
    } else {
        $_SESSION['form_errors_admin'] = $errors;
        $_SESSION['old_form_input_admin'] = $_POST; 
        header('Location: ' . $form_action); 
        exit;
    }
}

if (!empty($old_input)) {
    $current_grade['name'] = htmlspecialchars($old_input['name'] ?? $current_grade['name']);
    $current_grade['description'] = htmlspecialchars($old_input['description'] ?? $current_grade['description']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F9FAFB; }
        .admin-header { background-color: #4F46E5; color: white; }
        .sidebar { background-color: #1F2937; color: #D1D5DB; }
        .sidebar a { color: #D1D5DB; transition: background-color 0.2s, color 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #374151; color: white; }
        .content-card { background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06); }
        .form-input, .form-select, .form-textarea {
            border-radius: 0.375rem; border: 1px solid #D1D5DB; padding: 0.5rem 0.75rem; width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9rem;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #6366F1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .form-label { display: block; text-sm font-medium text-gray-700 mb-1; }
        .btn-save { background-color: #10B981; color: white; }
        .btn-save:hover { background-color: #059669; }
        .btn-cancel { background-color: #6B7280; color: white; }
        .btn-cancel:hover { background-color: #4B5563; }
        .error-text { color: #EF4444; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
</head>
<body class="flex h-screen">
    <aside class="sidebar w-64 min-h-screen p-4 space-y-2">
        <div class="text-center py-4">
            <a href="index.php" class="font-baloo text-2xl font-bold text-white">Admin Panel</a>
        </div>
        <nav>
            <a href="index.php" class="block py-2.5 px-4 rounded"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
            <a href="manage_questions.php" class="block py-2.5 px-4 rounded"><i class="fas fa-question-circle mr-2"></i>Quản lý Câu hỏi</a>
            <a href="manage_users.php" class="block py-2.5 px-4 rounded"><i class="fas fa-users mr-2"></i>Quản lý Người dùng</a>
            <a href="manage_characters.php" class="block py-2.5 px-4 rounded"><i class="fas fa-user-astronaut mr-2"></i>Quản lý Nhân vật</a>
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
            <a href="settings.php" class="block py-2.5 px-4 rounded"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div><h1 class="text-xl font-semibold"><?php echo htmlspecialchars($page_title); ?></h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="content-card p-6 max-w-lg mx-auto">
                <form action="<?php echo htmlspecialchars($form_action); ?>" method="POST" class="space-y-6">
                    <div>
                        <label for="name" class="form-label">Tên Khối lớp <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required 
                               value="<?php echo htmlspecialchars($current_grade['name']); ?>" placeholder="Ví dụ: Khối 1, Lớp Chồi">
                        <?php if (isset($errors['name'])): ?><p class="error-text"><?php echo $errors['name']; ?></p><?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="description" class="form-label">Mô tả (tùy chọn)</label>
                        <textarea id="description" name="description" rows="3" class="form-textarea" placeholder="Mô tả ngắn về khối lớp..."><?php echo htmlspecialchars($current_grade['description']); ?></textarea>
                        <?php if (isset($errors['description'])): ?><p class="error-text"><?php echo $errors['description']; ?></p><?php endif; ?>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="manage_grades.php" class="px-4 py-2 rounded-md text-sm font-medium btn-cancel">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium btn-save">
                            <i class="fas fa-save mr-1"></i> <?php echo $grade_id ? 'Lưu Thay Đổi' : 'Thêm Khối Lớp'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
</body>
</html>
