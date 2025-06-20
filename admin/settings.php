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
    error_log("Lỗi kết nối CSDL trên trang settings.php: " . ($conn->connect_error ?? 'Unknown error'));
    // Hiển thị lỗi thân thiện hơn cho admin thay vì die()
    $page_error = "Lỗi nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình và thử lại.";
}

/**
 * Lấy tất cả cài đặt từ CSDL.
 * @param mysqli $db_conn Đối tượng kết nối mysqli.
 * @return array Mảng các cài đặt (key => value).
 */
function get_all_settings(mysqli $db_conn): array {
    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM site_settings";
    $result = $db_conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $result->free();
    } else {
        error_log("Lỗi truy vấn cài đặt: " . $db_conn->error);
        // Gán lỗi vào biến để có thể hiển thị nếu cần
        // global $page_error; // Hoặc truyền $page_error vào hàm
        // $page_error = "Không thể tải cài đặt từ cơ sở dữ liệu.";
    }
    return $settings;
}

/**
 * Cập nhật một cài đặt trong CSDL.
 * Sử dụng INSERT ... ON DUPLICATE KEY UPDATE.
 * @param mysqli $db_conn Đối tượng kết nối mysqli.
 * @param string $key Khóa của cài đặt.
 * @param string $value Giá trị của cài đặt.
 * @return bool True nếu thành công, False nếu thất bại.
 */
function update_setting(mysqli $db_conn, string $key, string $value): bool {
    // Thêm mô tả mặc định nếu không có, để tránh lỗi khi INSERT key mới mà không có description
    $default_description = "Mô tả cho " . $key; 
    $sql = "INSERT INTO site_settings (setting_key, setting_value, description) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $db_conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $default_description);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Lỗi thực thi cập nhật cài đặt ($key): " . $stmt->error);
            $stmt->close();
            return false;
        }
    } else {
        error_log("Lỗi chuẩn bị cập nhật cài đặt ($key): " . $db_conn->error);
        return false;
    }
}

$current_settings_from_db = [];
if ($conn) { // Chỉ lấy settings nếu kết nối CSDL thành công
    $current_settings_from_db = get_all_settings($conn);
}

// Thiết lập giá trị mặc định nếu key không tồn tại trong CSDL hoặc kết nối lỗi
$current_settings = [
    'website_name' => $current_settings_from_db['website_name'] ?? 'Toán Vui Tiểu Học',
    'default_questions_per_map' => isset($current_settings_from_db['default_questions_per_map']) ? (int)$current_settings_from_db['default_questions_per_map'] : 10,
    'points_per_correct_answer' => isset($current_settings_from_db['points_per_correct_answer']) ? (int)$current_settings_from_db['points_per_correct_answer'] : 1,
    'maintenance_mode' => isset($current_settings_from_db['maintenance_mode']) ? (bool)(int)$current_settings_from_db['maintenance_mode'] : false, // Chuyển '0'/'1' thành boolean
    'daily_login_bonus' => isset($current_settings_from_db['daily_login_bonus']) ? (int)$current_settings_from_db['daily_login_bonus'] : 1,
    'daily_point_limit_from_game' => isset($current_settings_from_db['daily_point_limit_from_game']) ? (int)$current_settings_from_db['daily_point_limit_from_game'] : 30,
    'total_time_per_map_seconds' => isset($current_settings_from_db['total_time_per_map_seconds']) ? (int)$current_settings_from_db['total_time_per_map_seconds'] : 600,
];

// Xử lý lưu cài đặt
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    if (!$conn) { // Kiểm tra lại kết nối trước khi lưu
        $_SESSION['error_message_admin'] = "Lỗi kết nối cơ sở dữ liệu. Không thể lưu cài đặt.";
        header("Location: settings.php");
        exit;
    }
    $new_settings_values = [
        'website_name' => trim($_POST['website_name'] ?? $current_settings['website_name']),
        'default_questions_per_map' => (int)($_POST['default_questions_per_map'] ?? $current_settings['default_questions_per_map']),
        'points_per_correct_answer' => (int)($_POST['points_per_correct_answer'] ?? $current_settings['points_per_correct_answer']),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'daily_login_bonus' => (int)($_POST['daily_login_bonus'] ?? $current_settings['daily_login_bonus']),
        'daily_point_limit_from_game' => (int)($_POST['daily_point_limit_from_game'] ?? $current_settings['daily_point_limit_from_game']),
        'total_time_per_map_seconds' => (int)($_POST['total_time_per_map_seconds'] ?? $current_settings['total_time_per_map_seconds']),
    ];

    $form_errors = [];
    if (empty($new_settings_values['website_name'])) {
        $form_errors['website_name'] = "Tên website không được để trống.";
    }
    if ($new_settings_values['default_questions_per_map'] < 1 || $new_settings_values['default_questions_per_map'] > 50) {
        $form_errors['default_questions_per_map'] = "Số câu hỏi mỗi màn phải từ 1 đến 50.";
    }
    if ($new_settings_values['points_per_correct_answer'] < 0) {
        $form_errors['points_per_correct_answer'] = "Điểm cho câu đúng không thể là số âm.";
    }
    if ($new_settings_values['daily_login_bonus'] < 0) {
        $form_errors['daily_login_bonus'] = "Điểm thưởng đăng nhập không thể là số âm.";
    }
    if ($new_settings_values['daily_point_limit_from_game'] < 0) {
        $form_errors['daily_point_limit_from_game'] = "Giới hạn điểm mỗi ngày không thể là số âm.";
    }
    if ($new_settings_values['total_time_per_map_seconds'] < 30 || $new_settings_values['total_time_per_map_seconds'] > 3600) { 
        $form_errors['total_time_per_map_seconds'] = "Tổng thời gian mỗi màn phải từ 30 đến 3600 giây.";
    }

    if (empty($form_errors)) {
        $all_saved_successfully = true;
        foreach ($new_settings_values as $key => $value) {
            if (!update_setting($conn, $key, (string)$value)) { 
                $all_saved_successfully = false;
                break; 
            }
        }
        if ($all_saved_successfully) {
            $_SESSION['success_message_admin'] = "Đã lưu cài đặt thành công!";
        } else {
            $_SESSION['error_message_admin'] = "Có lỗi xảy ra khi lưu một hoặc nhiều cài đặt. Vui lòng kiểm tra log server.";
        }
    } else {
        $_SESSION['form_errors_admin_settings'] = $form_errors; 
        $_SESSION['old_form_input_admin_settings'] = $_POST; 
    }
    
    header("Location: settings.php"); 
    exit;
}

$success_message = $_SESSION['success_message_admin'] ?? null;
unset($_SESSION['success_message_admin']);
$error_message_general = $_SESSION['error_message_admin'] ?? ($page_error ?? null); 
unset($_SESSION['error_message_admin']);

$form_validation_errors = $_SESSION['form_errors_admin_settings'] ?? [];
$old_form_data = $_SESSION['old_form_input_admin_settings'] ?? [];
unset($_SESSION['form_errors_admin_settings'], $_SESSION['old_form_input_admin_settings']);

$display_settings = !empty($old_form_data) ? [
    'website_name' => htmlspecialchars($old_form_data['website_name'] ?? $current_settings['website_name']),
    'default_questions_per_map' => (int)($old_form_data['default_questions_per_map'] ?? $current_settings['default_questions_per_map']),
    'points_per_correct_answer' => (int)($old_form_data['points_per_correct_answer'] ?? $current_settings['points_per_correct_answer']),
    'maintenance_mode' => isset($old_form_data['maintenance_mode']),
    'daily_login_bonus' => (int)($old_form_data['daily_login_bonus'] ?? $current_settings['daily_login_bonus']),
    'daily_point_limit_from_game' => (int)($old_form_data['daily_point_limit_from_game'] ?? $current_settings['daily_point_limit_from_game']),
    'total_time_per_map_seconds' => (int)($old_form_data['total_time_per_map_seconds'] ?? $current_settings['total_time_per_map_seconds']),
] : $current_settings;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt chung - Admin Panel</title>
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
        .form-input, .form-select, .form-checkbox-input { /* Renamed .form-checkbox to .form-checkbox-input for clarity */
            border-radius: 0.375rem; border: 1px solid #D1D5DB; padding: 0.5rem 0.75rem; width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9rem;
        }
        .form-checkbox-input { width: auto; height: 1.25rem; margin-right: 0.5rem; }
        .form-input:focus, .form-select:focus, .form-checkbox-input:focus {
            border-color: #6366F1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .form-label { display: block; text-sm font-medium text-gray-700 mb-1; }
        .btn-save { background-color: #10B981; color: white; padding: 0.6rem 1.2rem; }
        .btn-save:hover { background-color: #059669; }
        .error-text { color: #EF4444; font-size: 0.875rem; margin-top: 0.25rem; }
        .setting-section { border-bottom: 1px solid #E5E7EB; padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
        .setting-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .setting-section-title { font-size: 1.125rem; font-weight: 600; color: #374151; margin-bottom: 1rem; border-bottom: 1px solid #E5E7EB; padding-bottom: 0.5rem;}
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
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
            <a href="settings.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div><h1 class="text-xl font-semibold">Cài đặt chung</h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="content-card p-6 max-w-3xl mx-auto">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-3">Thiết lập Thông số Website & Game</h2>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Thành công!</p>
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($error_message_general): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p class="font-bold">Lỗi!</p>
                        <p><?php echo htmlspecialchars($error_message_general); ?></p>
                    </div>
                <?php endif; ?>

                <form action="settings.php" method="POST" class="space-y-8">
                    <div class="setting-section">
                        <h3 class="setting-section-title">Cài đặt Website</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="website_name" class="form-label">Tên Website / Trò chơi</label>
                                <input type="text" id="website_name" name="website_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['website_name']); ?>">
                                <?php if (isset($form_validation_errors['website_name'])): ?><p class="error-text"><?php echo $form_validation_errors['website_name']; ?></p><?php endif; ?>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" class="form-checkbox-input text-indigo-600 focus:ring-indigo-500" 
                                       <?php echo $display_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <label for="maintenance_mode" class="ml-2 block text-sm text-gray-900">Bật chế độ bảo trì?</label>
                            </div>
                            <p class="text-xs text-gray-500">Khi bật, chỉ admin mới có thể truy cập website. Người dùng thường sẽ thấy thông báo bảo trì.</p>
                            <?php if (isset($form_validation_errors['maintenance_mode'])): ?><p class="error-text"><?php echo $form_validation_errors['maintenance_mode']; ?></p><?php endif; ?>
                        </div>
                    </div>

                    <div class="setting-section">
                        <h3 class="setting-section-title">Cài đặt Game</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="default_questions_per_map" class="form-label">Số câu hỏi mỗi màn</label>
                                <input type="number" id="default_questions_per_map" name="default_questions_per_map" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['default_questions_per_map']); ?>" min="1" max="50">
                                <?php if (isset($form_validation_errors['default_questions_per_map'])): ?><p class="error-text"><?php echo $form_validation_errors['default_questions_per_map']; ?></p><?php endif; ?>
                            </div>
                             <div>
                                <label for="total_time_per_map_seconds" class="form-label">Tổng thời gian mỗi màn (giây)</label>
                                <input type="number" id="total_time_per_map_seconds" name="total_time_per_map_seconds" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['total_time_per_map_seconds']); ?>" min="30" max="3600">
                                <?php if (isset($form_validation_errors['total_time_per_map_seconds'])): ?><p class="error-text"><?php echo $form_validation_errors['total_time_per_map_seconds']; ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="setting-section">
                        <h3 class="setting-section-title">Cài đặt Điểm Thưởng & Giới Hạn</h3>
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="points_per_correct_answer" class="form-label">Điểm cho mỗi câu đúng</label>
                                <input type="number" id="points_per_correct_answer" name="points_per_correct_answer" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['points_per_correct_answer']); ?>" min="0">
                                <?php if (isset($form_validation_errors['points_per_correct_answer'])): ?><p class="error-text"><?php echo $form_validation_errors['points_per_correct_answer']; ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label for="daily_login_bonus" class="form-label">Điểm thưởng đăng nhập ngày</label>
                                <input type="number" id="daily_login_bonus" name="daily_login_bonus" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['daily_login_bonus']); ?>" min="0">
                                <?php if (isset($form_validation_errors['daily_login_bonus'])): ?><p class="error-text"><?php echo $form_validation_errors['daily_login_bonus']; ?></p><?php endif; ?>
                            </div>
                             <div>
                                <label for="daily_point_limit_from_game" class="form-label">Giới hạn điểm game/ngày</label>
                                <input type="number" id="daily_point_limit_from_game" name="daily_point_limit_from_game" class="form-input" 
                                       value="<?php echo htmlspecialchars($display_settings['daily_point_limit_from_game']); ?>" min="0">
                                <?php if (isset($form_validation_errors['daily_point_limit_from_game'])): ?><p class="error-text"><?php echo $form_validation_errors['daily_point_limit_from_game']; ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 text-right">
                        <button type="submit" name="save_settings" class="btn-save rounded font-semibold">
                            <i class="fas fa-save mr-1"></i> Lưu Tất Cả Cài Đặt
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