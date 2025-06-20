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

$page_title = "Thêm Nhân Vật Mới";
$form_action = "add_edit_character.php";
$character_id = null;
$current_character = [
    'name' => '',
    'description' => '',
    'image_url' => '',
    'base_lives' => 3, // Mặc định
    'special_ability_code' => ''
];
$errors = $_SESSION['form_errors_admin'] ?? [];
$old_input = $_SESSION['old_form_input_admin'] ?? [];
unset($_SESSION['form_errors_admin'], $_SESSION['old_form_input_admin']);

if (isset($_GET['id'])) {
    $character_id = (int)$_GET['id'];
    $page_title = "Sửa Nhân Vật (ID: $character_id)";
    $form_action = "add_edit_character.php?id=" . $character_id; 

    $sql_fetch_char = "SELECT * FROM characters WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_char);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $character_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_character = $result_fetch->fetch_assoc();
        } else {
            $_SESSION['error_message_admin'] = "Không tìm thấy nhân vật với ID: $character_id";
            header('Location: manage_characters.php');
            exit;
        }
        $stmt_fetch->close();
    } else {
        $_SESSION['error_message_admin'] = "Lỗi chuẩn bị truy vấn nhân vật: " . $conn->error;
        header('Location: manage_characters.php');
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_character['name'] = trim($_POST['name'] ?? '');
    $current_character['description'] = trim($_POST['description'] ?? '');
    $current_character['image_url'] = trim($_POST['image_url'] ?? ''); // Đường dẫn tương đối từ thư mục gốc
    $current_character['base_lives'] = (isset($_POST['base_lives']) && $_POST['base_lives'] !== '') ? (int)$_POST['base_lives'] : 3;
    $current_character['special_ability_code'] = trim($_POST['special_ability_code'] ?? '');

    if (empty($current_character['name'])) $errors['name'] = "Tên nhân vật không được để trống.";
    if (strlen($current_character['name']) > 100) $errors['name'] = "Tên nhân vật không được vượt quá 100 ký tự.";
    if ($current_character['base_lives'] < 1 || $current_character['base_lives'] > 10) $errors['base_lives'] = "Số mạng cơ bản phải từ 1 đến 10.";
    if (!empty($current_character['image_url']) && !filter_var($current_character['image_url'], FILTER_VALIDATE_URL) && !file_exists('../' . $current_character['image_url']) && !preg_match('/^assets\/images\/.+\.(jpg|jpeg|png|gif|svg)$/i', $current_character['image_url'])) {
        // $errors['image_url'] = "URL hình ảnh không hợp lệ hoặc đường dẫn file không tồn tại (ví dụ: assets/images/char.png).";
        // Tạm thời chấp nhận mọi chuỗi cho image_url, admin tự quản lý
    }
     if (!empty($current_character['special_ability_code']) && !preg_match('/^[A-Z0-9_]+$/', $current_character['special_ability_code'])) {
        $errors['special_ability_code'] = "Mã kỹ năng chỉ được chứa chữ hoa, số và dấu gạch dưới.";
    }


    if (empty($errors)) {
        if ($character_id) { // Sửa
            $sql_update = "UPDATE characters SET name=?, description=?, image_url=?, base_lives=?, special_ability_code=? WHERE id=?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("sssisi", 
                    $current_character['name'], $current_character['description'], $current_character['image_url'],
                    $current_character['base_lives'], $current_character['special_ability_code'], $character_id
                );
                if ($stmt_update->execute()) {
                    $_SESSION['success_message_admin'] = "Đã cập nhật nhân vật (ID: $character_id) thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi cập nhật nhân vật: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị cập nhật nhân vật: " . $conn->error;
            }
        } else { // Thêm mới
            $sql_insert = "INSERT INTO characters (name, description, image_url, base_lives, special_ability_code) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                 $stmt_insert->bind_param("sssis", 
                    $current_character['name'], $current_character['description'], $current_character['image_url'],
                    $current_character['base_lives'], $current_character['special_ability_code']
                );
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message_admin'] = "Đã thêm nhân vật mới thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi thêm nhân vật mới: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị thêm mới nhân vật: " . $conn->error;
            }
        }
        header('Location: manage_characters.php');
        exit;
    } else {
        $_SESSION['form_errors_admin'] = $errors;
        $_SESSION['old_form_input_admin'] = $_POST; 
        header('Location: ' . $form_action); 
        exit;
    }
}

if (!empty($old_input)) {
    $current_character['name'] = htmlspecialchars($old_input['name'] ?? $current_character['name']);
    $current_character['description'] = htmlspecialchars($old_input['description'] ?? $current_character['description']);
    $current_character['image_url'] = htmlspecialchars($old_input['image_url'] ?? $current_character['image_url']);
    $current_character['base_lives'] = htmlspecialchars($old_input['base_lives'] ?? $current_character['base_lives']);
    $current_character['special_ability_code'] = htmlspecialchars($old_input['special_ability_code'] ?? $current_character['special_ability_code']);
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
            <a href="manage_characters.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-user-astronaut mr-2"></i>Quản lý Nhân vật</a>
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
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
                        <label for="name" class="form-label">Tên Nhân vật <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" required 
                               value="<?php echo htmlspecialchars($current_character['name']); ?>" placeholder="Ví dụ: Thỏ Thông Thái">
                        <?php if (isset($errors['name'])): ?><p class="error-text"><?php echo $errors['name']; ?></p><?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="description" class="form-label">Mô tả Kỹ năng</label>
                        <textarea id="description" name="description" rows="3" class="form-textarea" placeholder="Mô tả ngắn về kỹ năng đặc biệt của nhân vật..."><?php echo htmlspecialchars($current_character['description']); ?></textarea>
                        <?php if (isset($errors['description'])): ?><p class="error-text"><?php echo $errors['description']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="image_url" class="form-label">Đường dẫn Hình ảnh</label>
                        <input type="text" id="image_url" name="image_url" class="form-input" 
                               value="<?php echo htmlspecialchars($current_character['image_url']); ?>" placeholder="Ví dụ: assets/images/tho_thong_thai.png hoặc URL đầy đủ">
                        <?php if (isset($errors['image_url'])): ?><p class="error-text"><?php echo $errors['image_url']; ?></p><?php endif; ?>
                        <?php if (!empty($current_character['image_url'])): ?>
                            <img src="<?php echo '../' . htmlspecialchars($current_character['image_url']); ?>" alt="Xem trước" class="mt-2 h-24 w-24 object-cover rounded border">
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="base_lives" class="form-label">Số Mạng Cơ Bản <span class="text-red-500">*</span></label>
                        <input type="number" id="base_lives" name="base_lives" class="form-input w-32" required
                               value="<?php echo htmlspecialchars($current_character['base_lives']); ?>" min="1" max="10">
                        <?php if (isset($errors['base_lives'])): ?><p class="error-text"><?php echo $errors['base_lives']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="special_ability_code" class="form-label">Mã Kỹ Năng Đặc Biệt (tùy chọn)</label>
                        <input type="text" id="special_ability_code" name="special_ability_code" class="form-input" 
                               value="<?php echo htmlspecialchars($current_character['special_ability_code']); ?>" placeholder="Ví dụ: EXTRA_LIFE, SKIP_CHANCE (chữ hoa, số, _)">
                        <p class="text-xs text-gray-500 mt-1">Mã này sẽ được dùng trong code để kích hoạt kỹ năng. Để trống nếu không có.</p>
                        <?php if (isset($errors['special_ability_code'])): ?><p class="error-text"><?php echo $errors['special_ability_code']; ?></p><?php endif; ?>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="manage_characters.php" class="px-4 py-2 rounded-md text-sm font-medium btn-cancel">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium btn-save">
                            <i class="fas fa-save mr-1"></i> <?php echo $character_id ? 'Lưu Thay Đổi' : 'Thêm Nhân Vật'; ?>
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
