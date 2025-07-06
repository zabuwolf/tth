<?php
session_start();
require_once '../config/db_config.php'; // Đi ra một cấp để vào config

// Kiểm tra xem admin đã đăng nhập chưa
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_login_error'] = "Vui lòng đăng nhập để truy cập trang quản trị.";
    header('Location: admin_login.php');
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

// Xử lý xóa khối lớp (nếu có yêu cầu)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $grade_id_to_delete = (int)$_GET['id'];
    if ($grade_id_to_delete > 0) {
        // Trước khi xóa khối lớp, cần cân nhắc việc xử lý các chủ đề và câu hỏi thuộc khối này.
        // Nếu khóa ngoại có ON DELETE CASCADE, các chủ đề và câu hỏi liên quan sẽ tự động bị xóa.
        // Nếu không, bạn sẽ gặp lỗi ràng buộc khóa ngoại.
        // Cần có cơ chế kiểm tra hoặc thông báo cho admin về các dữ liệu phụ thuộc.

        // Kiểm tra xem có chủ đề nào thuộc khối này không
        $sql_check_topics = "SELECT COUNT(*) as topic_count FROM topics WHERE grade_id = ?";
        $stmt_check = $conn->prepare($sql_check_topics);
        $can_delete = true;
        if($stmt_check){
            $stmt_check->bind_param("i", $grade_id_to_delete);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $topic_count_data = $result_check->fetch_assoc();
            $stmt_check->close();
            if($topic_count_data && $topic_count_data['topic_count'] > 0){
                $can_delete = false;
                $_SESSION['error_message_admin'] = "Không thể xóa khối lớp này vì vẫn còn chủ đề thuộc về nó. Vui lòng xóa hoặc di chuyển các chủ đề trước.";
            }
        } else {
            $can_delete = false; // Không kiểm tra được thì không cho xóa
            $_SESSION['error_message_admin'] = "Lỗi khi kiểm tra các chủ đề phụ thuộc.";
        }


        if ($can_delete) {
            $sql_delete_grade = "DELETE FROM grades WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete_grade);
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $grade_id_to_delete);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) {
                        $_SESSION['success_message_admin'] = "Đã xóa khối lớp thành công!";
                    } else {
                        $_SESSION['error_message_admin'] = "Không tìm thấy khối lớp để xóa.";
                    }
                } else {
                     $_SESSION['error_message_admin'] = "Lỗi khi xóa khối lớp: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $_SESSION['error_message_admin'] = "Lỗi chuẩn bị xóa khối lớp: " . $conn->error;
            }
        }
        header('Location: manage_grades.php');
        exit;
    }
}

// Lấy danh sách khối lớp từ CSDL
$grades_list = [];
$sql_grades_list = "SELECT g.id, g.name, g.description, COUNT(t.id) as topic_count 
                    FROM grades g
                    LEFT JOIN topics t ON g.id = t.grade_id
                    GROUP BY g.id, g.name, g.description
                    ORDER BY g.id ASC";
$result_grades_list = $conn->query($sql_grades_list);
if ($result_grades_list) {
    while ($row = $result_grades_list->fetch_assoc()) {
        $grades_list[] = $row;
    }
    $result_grades_list->free();
} else {
    error_log("Lỗi truy vấn danh sách khối lớp: " . $conn->error);
}

// Lấy và xóa thông báo (nếu có)
$success_message = $_SESSION['success_message_admin'] ?? null;
unset($_SESSION['success_message_admin']);
$error_message = $_SESSION['error_message_admin'] ?? null;
unset($_SESSION['error_message_admin']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khối lớp - Admin Panel</title>
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
        .table th, .table td { padding: 0.75rem 1rem; border-bottom: 1px solid #E5E7EB; text-align: left; }
        .table th { background-color: #F3F4F6; font-weight: 600; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 0.25rem; }
        .btn-edit { background-color: #3B82F6; color: white; }
        .btn-edit:hover { background-color: #2563EB; }
        .btn-delete { background-color: #EF4444; color: white; }
        .btn-delete:hover { background-color: #DC2626; }
        .btn-add { background-color: #10B981; color: white; padding: 0.5rem 1rem; }
        .btn-add:hover { background-color: #059669; }
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
            <div><h1 class="text-xl font-semibold">Quản lý Khối lớp</h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-700">Danh sách Khối lớp</h2>
                <a href="add_edit_grade.php" class="btn-add rounded font-semibold">
                    <i class="fas fa-plus mr-1"></i> Thêm Khối Lớp Mới
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="content-card overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên Khối lớp</th>
                            <th>Mô tả</th>
                            <th>Số Chủ đề</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($grades_list)): ?>
                            <?php foreach ($grades_list as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['id']); ?></td>
                                    <td class="font-medium text-gray-800"><?php echo htmlspecialchars($grade['name']); ?></td>
                                    <td class="max-w-md truncate" title="<?php echo htmlspecialchars($grade['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($grade['description'] ?? '', 0, 80, "...")); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($grade['topic_count']); ?></td>
                                    <td>
                                        <a href="add_edit_grade.php?id=<?php echo $grade['id']; ?>" class="btn-sm btn-edit mr-1"><i class="fas fa-edit"></i> Sửa</a>
                                        <a href="manage_grades.php?action=delete&id=<?php echo $grade['id']; ?>" 
                                           class="btn-sm btn-delete" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa khối lớp này không? Hành động này có thể ảnh hưởng đến các chủ đề và câu hỏi liên quan.');">
                                           <i class="fas fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-4">Chưa có khối lớp nào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
