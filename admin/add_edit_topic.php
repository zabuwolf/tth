<?php
session_start();
require_once '../config/db_config.php'; // Đi ra một cấp để vào config

// Kiểm tra xem admin đã đăng nhập chưa
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_errors'] = ['general' => "Vui lòng đăng nhập với quyền quản trị để truy cập."]; // Sử dụng key lỗi chung
    header('Location: ../login.php'); // Chuyển hướng ra trang login.php ở thư mục gốc
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

// Khởi tạo các biến
$page_title = "Thêm Chủ Đề Mới";
$form_action = "add_edit_topic.php"; // Mặc định là thêm mới
$topic_id = null;
$current_topic = [
    'grade_id' => '',
    'name' => '',
    'description' => '',
    'sort_order' => 0 // Mặc định thứ tự sắp xếp
];
$errors = $_SESSION['form_errors_admin'] ?? [];
$old_input = $_SESSION['old_form_input_admin'] ?? [];
unset($_SESSION['form_errors_admin'], $_SESSION['old_form_input_admin']);


// Kiểm tra xem có phải là chế độ sửa không (có 'id' trong URL)
if (isset($_GET['id'])) {
    $topic_id = (int)$_GET['id'];
    $page_title = "Sửa Chủ Đề (ID: $topic_id)";
    $form_action = "add_edit_topic.php?id=" . $topic_id; 

    $sql_fetch_topic = "SELECT * FROM topics WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_topic);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $topic_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_topic = $result_fetch->fetch_assoc();
        } else {
            $_SESSION['error_message_admin'] = "Không tìm thấy chủ đề với ID: $topic_id";
            header('Location: manage_topics.php');
            exit;
        }
        $stmt_fetch->close();
    } else {
        $_SESSION['error_message_admin'] = "Lỗi chuẩn bị truy vấn chủ đề: " . $conn->error;
        header('Location: manage_topics.php');
        exit;
    }
}

// Xử lý khi form được submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $current_topic['grade_id'] = trim($_POST['grade_id'] ?? '');
    $current_topic['name'] = trim($_POST['name'] ?? '');
    $current_topic['description'] = trim($_POST['description'] ?? '');
    $current_topic['sort_order'] = (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') ? (int)$_POST['sort_order'] : 0;


    // Validate dữ liệu
    if (empty($current_topic['grade_id'])) $errors['grade_id'] = "Vui lòng chọn khối lớp.";
    if (empty($current_topic['name'])) $errors['name'] = "Tên chủ đề không được để trống.";
    if (strlen($current_topic['name']) > 255) $errors['name'] = "Tên chủ đề không được vượt quá 255 ký tự.";
    // sort_order có thể là 0 hoặc số nguyên dương
    if (!is_numeric($current_topic['sort_order']) || $current_topic['sort_order'] < 0) {
        $errors['sort_order'] = "Thứ tự sắp xếp phải là một số không âm.";
    }


    if (empty($errors)) {
        if ($topic_id) { // Chế độ Sửa
            $sql_update = "UPDATE topics SET grade_id=?, name=?, description=?, sort_order=? WHERE id=?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("issii", 
                    $current_topic['grade_id'], $current_topic['name'], $current_topic['description'],
                    $current_topic['sort_order'], $topic_id
                );
                if ($stmt_update->execute()) {
                    $_SESSION['success_message_admin'] = "Đã cập nhật chủ đề (ID: $topic_id) thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi cập nhật chủ đề: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị cập nhật chủ đề: " . $conn->error;
            }
        } else { // Chế độ Thêm mới
            $sql_insert = "INSERT INTO topics (grade_id, name, description, sort_order, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                 $stmt_insert->bind_param("issi", 
                    $current_topic['grade_id'], $current_topic['name'], $current_topic['description'],
                    $current_topic['sort_order']
                );
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message_admin'] = "Đã thêm chủ đề mới thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi thêm chủ đề mới: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị thêm mới chủ đề: " . $conn->error;
            }
        }
        header('Location: manage_topics.php');
        exit;
    } else {
        $_SESSION['form_errors_admin'] = $errors;
        $_SESSION['old_form_input_admin'] = $_POST; 
        header('Location: ' . $form_action); 
        exit;
    }
}

// Lấy danh sách Khối lớp để điền vào dropdown
$grades_options = [];
$sql_grades = "SELECT id, name FROM grades ORDER BY id ASC";
$result_grades = $conn->query($sql_grades);
if ($result_grades) {
    while ($row = $result_grades->fetch_assoc()) {
        $grades_options[] = $row;
    }
    $result_grades->free();
}

// Điền lại giá trị từ old_input nếu có lỗi validate
if (!empty($old_input)) {
    $current_topic['grade_id'] = htmlspecialchars($old_input['grade_id'] ?? $current_topic['grade_id']);
    $current_topic['name'] = htmlspecialchars($old_input['name'] ?? $current_topic['name']);
    $current_topic['description'] = htmlspecialchars($old_input['description'] ?? $current_topic['description']);
    $current_topic['sort_order'] = htmlspecialchars($old_input['sort_order'] ?? $current_topic['sort_order']);
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
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
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
            <div class="content-card p-6 max-w-xl mx-auto">
                <form action="<?php echo htmlspecialchars($form_action); ?>" method="POST" class="space-y-6">
                    
                    <div>
                        <label for="grade_id" class="form-label">Thuộc Khối lớp <span class="text-red-500">*</span></label>
                        <select id="grade_id" name="grade_id" class="form-select" required>
                            <option value="">-- Chọn Khối lớp --</option>
                            <?php foreach ($grades_options as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo ($current_topic['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['grade_id'])): ?><p class="error-text"><?php echo $errors['grade_id']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="name" class="form-label">Tên Chủ đề <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required 
                               value="<?php echo htmlspecialchars($current_topic['name']); ?>" placeholder="Ví dụ: Các số từ 1 đến 10">
                        <?php if (isset($errors['name'])): ?><p class="error-text"><?php echo $errors['name']; ?></p><?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="description" class="form-label">Mô tả (tùy chọn)</label>
                        <textarea id="description" name="description" rows="3" class="form-textarea" placeholder="Mô tả ngắn về chủ đề..."><?php echo htmlspecialchars($current_topic['description']); ?></textarea>
                        <?php if (isset($errors['description'])): ?><p class="error-text"><?php echo $errors['description']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="sort_order" class="form-label">Thứ tự sắp xếp (số nhỏ hơn hiển thị trước)</label>
                        <input type="number" id="sort_order" name="sort_order" class="form-input w-32" 
                               value="<?php echo htmlspecialchars($current_topic['sort_order']); ?>" min="0">
                        <?php if (isset($errors['sort_order'])): ?><p class="error-text"><?php echo $errors['sort_order']; ?></p><?php endif; ?>
                    </div>


                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="manage_topics.php" class="px-4 py-2 rounded-md text-sm font-medium btn-cancel">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium btn-save">
                            <i class="fas fa-save mr-1"></i> <?php echo $topic_id ? 'Lưu Thay Đổi' : 'Thêm Chủ Đề'; ?>
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
