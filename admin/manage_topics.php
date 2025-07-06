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

// Xử lý xóa chủ đề (nếu có yêu cầu)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $topic_id_to_delete = (int)$_GET['id'];
    if ($topic_id_to_delete > 0) {
        // Trước khi xóa chủ đề, cần cân nhắc việc xử lý các câu hỏi thuộc chủ đề này.
        // Tùy chọn 1: Xóa luôn các câu hỏi (ON DELETE CASCADE đã được thiết lập cho fk_question_topic nếu bạn đã chạy lệnh đó).
        // Tùy chọn 2: Không cho xóa nếu chủ đề còn câu hỏi.
        // Tùy chọn 3: Gán các câu hỏi đó cho một chủ đề "Chung" hoặc để topic_id thành NULL (nếu cột cho phép).

        // Hiện tại, giả sử ON DELETE CASCADE đã được thiết lập hoặc bạn chấp nhận xóa câu hỏi liên quan.
        $sql_delete_topic = "DELETE FROM topics WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete_topic);
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $topic_id_to_delete);
            if ($stmt_delete->execute()) {
                // Kiểm tra xem có dòng nào bị ảnh hưởng không
                if ($stmt_delete->affected_rows > 0) {
                    $_SESSION['success_message_admin'] = "Đã xóa chủ đề thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Không tìm thấy chủ đề để xóa hoặc chủ đề không thể xóa.";
                }
            } else {
                // Kiểm tra lỗi ràng buộc khóa ngoại (nếu câu hỏi vẫn còn trỏ đến topic này và không có ON DELETE CASCADE)
                if ($conn->errno == 1451) { // Mã lỗi cho vi phạm ràng buộc khóa ngoại
                     $_SESSION['error_message_admin'] = "Không thể xóa chủ đề này vì vẫn còn câu hỏi thuộc về nó. Vui lòng xóa hoặc di chuyển các câu hỏi trước.";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi xóa chủ đề: " . $stmt_delete->error;
                }
            }
            $stmt_delete->close();
        } else {
            $_SESSION['error_message_admin'] = "Lỗi chuẩn bị xóa chủ đề: " . $conn->error;
        }
        header('Location: manage_topics.php');
        exit;
    }
}

// Lấy danh sách chủ đề từ CSDL, JOIN với bảng grades để lấy tên khối lớp
$topics = [];
$sql_topics_list = "SELECT t.id, t.name as topic_name, t.description, t.sort_order, g.name as grade_name 
                    FROM topics t
                    LEFT JOIN grades g ON t.grade_id = g.id
                    ORDER BY g.id ASC, t.sort_order ASC, t.name ASC";
$result_topics_list = $conn->query($sql_topics_list);
if ($result_topics_list) {
    while ($row = $result_topics_list->fetch_assoc()) {
        $topics[] = $row;
    }
    $result_topics_list->free();
} else {
    error_log("Lỗi truy vấn danh sách chủ đề: " . $conn->error);
    // $page_error_topics = "Không thể tải danh sách chủ đề.";
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
    <title>Quản lý Chủ đề - Admin Panel</title>
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
        .table th, .table td { padding: 0.75rem 1rem; border-bottom: 1px solid #E5E7EB; }
        .table th { background-color: #F3F4F6; font-weight: 600; text-align: left; }
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
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
            <a href="settings.php" class="block py-2.5 px-4 rounded"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div>
                <h1 class="text-xl font-semibold">Quản lý Chủ đề Học tập</h1>
            </div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-700">Danh sách Chủ đề</h2>
                <a href="add_edit_topic.php" class="btn-add rounded font-semibold">
                    <i class="fas fa-plus mr-1"></i> Thêm Chủ Đề Mới
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
                <table class="table w-full min-w-max">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên Chủ đề</th>
                            <th>Khối lớp</th>
                            <th>Mô tả</th>
                            <th>Thứ tự</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topics)): ?>
                            <?php foreach ($topics as $topic): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($topic['id']); ?></td>
                                    <td class="font-medium text-gray-800"><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                    <td><?php echo htmlspecialchars($topic['grade_name'] ?? 'N/A'); ?></td>
                                    <td class="max-w-md truncate" title="<?php echo htmlspecialchars($topic['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($topic['description'] ?? '', 0, 80, "...")); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($topic['sort_order']); ?></td>
                                    <td>
                                        <a href="add_edit_topic.php?id=<?php echo $topic['id']; ?>" class="btn-sm btn-edit mr-1"><i class="fas fa-edit"></i> Sửa</a>
                                        <a href="manage_topics.php?action=delete&id=<?php echo $topic['id']; ?>" 
                                           class="btn-sm btn-delete" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa chủ đề này không? Các câu hỏi thuộc chủ đề này cũng có thể bị ảnh hưởng hoặc xóa tùy theo cài đặt CSDL.');">
                                           <i class="fas fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-gray-500 py-4">Chưa có chủ đề nào.</td>
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
