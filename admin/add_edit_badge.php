<?php
session_start();
require_once '../config/db_config.php'; 

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_errors'] = ['general' => "Vui lòng đăng nhập với quyền quản trị để truy cập."]; // Sử dụng key lỗi chung
    header('Location: ../login.php'); // Chuyển hướng ra trang login.php ở thư mục gốc
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';

$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

$page_title = "Thêm Huy Hiệu Mới";
$form_action = "add_edit_badge.php";
$badge_id = null;
$current_badge = [
    'name' => '',
    'description' => '',
    'image_url' => '',
    'points_required' => 0
];
$errors = $_SESSION['form_errors_admin'] ?? [];
$old_input = $_SESSION['old_form_input_admin'] ?? [];
unset($_SESSION['form_errors_admin'], $_SESSION['old_form_input_admin']);

if (isset($_GET['id'])) {
    $badge_id = (int)$_GET['id'];
    $page_title = "Sửa Huy Hiệu (ID: $badge_id)";
    $form_action = "add_edit_badge.php?id=" . $badge_id; 

    $sql_fetch_badge = "SELECT * FROM badges WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_badge);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $badge_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_badge = $result_fetch->fetch_assoc();
        } else {
            $_SESSION['error_message_admin'] = "Không tìm thấy huy hiệu với ID: $badge_id";
            header('Location: manage_badges.php');
            exit;
        }
        $stmt_fetch->close();
    } else {
        $_SESSION['error_message_admin'] = "Lỗi chuẩn bị truy vấn huy hiệu: " . $conn->error;
        header('Location: manage_badges.php');
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_badge['name'] = trim($_POST['name'] ?? '');
    $current_badge['description'] = trim($_POST['description'] ?? '');
    $current_badge['image_url'] = trim($_POST['image_url'] ?? '');
    $current_badge['points_required'] = (isset($_POST['points_required']) && $_POST['points_required'] !== '') ? (int)$_POST['points_required'] : 0;

    if (empty($current_badge['name'])) $errors['name'] = "Tên huy hiệu không được để trống.";
    if (strlen($current_badge['name']) > 100) $errors['name'] = "Tên huy hiệu không được vượt quá 100 ký tự.";
    if (!is_numeric($current_badge['points_required']) || $current_badge['points_required'] < 0) {
        $errors['points_required'] = "Điểm yêu cầu phải là một số không âm.";
    }
    // Thêm validate cho image_url nếu cần

    if (empty($errors)) {
        if ($badge_id) { // Sửa
            $sql_update = "UPDATE badges SET name=?, description=?, image_url=?, points_required=? WHERE id=?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("sssii", 
                    $current_badge['name'], $current_badge['description'], $current_badge['image_url'],
                    $current_badge['points_required'], $badge_id
                );
                if ($stmt_update->execute()) {
                    $_SESSION['success_message_admin'] = "Đã cập nhật huy hiệu (ID: $badge_id) thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi cập nhật huy hiệu: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị cập nhật huy hiệu: " . $conn->error;
            }
        } else { // Thêm mới
            $sql_insert = "INSERT INTO badges (name, description, image_url, points_required) 
                           VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                 $stmt_insert->bind_param("sssi", 
                    $current_badge['name'], $current_badge['description'], $current_badge['image_url'],
                    $current_badge['points_required']
                );
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message_admin'] = "Đã thêm huy hiệu mới thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi thêm huy hiệu mới: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị thêm mới huy hiệu: " . $conn->error;
            }
        }
        header('Location: manage_badges.php');
        exit;
    } else {
        $_SESSION['form_errors_admin'] = $errors;
        $_SESSION['old_form_input_admin'] = $_POST; 
        header('Location: ' . $form_action); 
        exit;
    }
}

if (!empty($old_input)) {
    $current_badge['name'] = htmlspecialchars($old_input['name'] ?? $current_badge['name']);
    $current_badge['description'] = htmlspecialchars($old_input['description'] ?? $current_badge['description']);
    $current_badge['image_url'] = htmlspecialchars($old_input['image_url'] ?? $current_badge['image_url']);
    $current_badge['points_required'] = htmlspecialchars($old_input['points_required'] ?? $current_badge['points_required']);
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
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
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
                        <label for="name" class="form-label">Tên Huy hiệu <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required 
                               value="<?php echo htmlspecialchars($current_badge['name']); ?>" placeholder="Ví dụ: Nhà Toán Học Nhí">
                        <?php if (isset($errors['name'])): ?><p class="error-text"><?php echo $errors['name']; ?></p><?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" rows="3" class="form-textarea" placeholder="Mô tả về huy hiệu và cách đạt được..."><?php echo htmlspecialchars($current_badge['description']); ?></textarea>
                        <?php if (isset($errors['description'])): ?><p class="error-text"><?php echo $errors['description']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="image_url" class="form-label">Đường dẫn Hình ảnh Huy hiệu</label>
                        <input type="text" id="image_url" name="image_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_badge['image_url']); ?>" placeholder="Ví dụ: assets/images/badges/nha_toan_hoc.png">
                        <?php if (isset($errors['image_url'])): ?><p class="error-text"><?php echo $errors['image_url']; ?></p><?php endif; ?>
                        <?php if (!empty($current_badge['image_url'])): ?>
                            <img src="<?php echo '../' . htmlspecialchars($current_badge['image_url']); ?>" alt="Xem trước" class="mt-2 h-20 w-20 object-contain rounded border bg-gray-100 p-1">
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="points_required" class="form-label">Điểm Yêu Cầu <span class="text-red-500">*</span></label>
                        <input type="number" id="points_required" name="points_required" class="form-input w-40" required
                               value="<?php echo htmlspecialchars($current_badge['points_required']); ?>" min="0">
                        <?php if (isset($errors['points_required'])): ?><p class="error-text"><?php echo $errors['points_required']; ?></p><?php endif; ?>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="manage_badges.php" class="px-4 py-2 rounded-md text-sm font-medium btn-cancel">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium btn-save">
                            <i class="fas fa-save mr-1"></i> <?php echo $badge_id ? 'Lưu Thay Đổi' : 'Thêm Huy Hiệu'; ?>
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
