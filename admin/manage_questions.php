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
    // Lỗi kết nối CSDL đã được log trong connect_db()
    // Hiển thị thông báo lỗi chung và dừng script.
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau hoặc liên hệ quản trị viên.");
}

// Xử lý xóa câu hỏi (nếu có yêu cầu)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $question_id_to_delete = (int)$_GET['id'];
    if ($question_id_to_delete > 0) {
        $sql_delete = "DELETE FROM questions WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $question_id_to_delete);
            if ($stmt_delete->execute()) {
                $_SESSION['success_message_admin'] = "Đã xóa câu hỏi thành công!";
            } else {
                $_SESSION['error_message_admin'] = "Lỗi khi xóa câu hỏi: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        } else {
            $_SESSION['error_message_admin'] = "Lỗi chuẩn bị xóa câu hỏi: " . $conn->error;
        }
        // Chuyển hướng lại trang này để làm mới danh sách và xóa tham số GET
        header('Location: manage_questions.php');
        exit;
    }
}


// Lấy danh sách câu hỏi từ CSDL
// Sử dụng JOIN để lấy tên Khối (grade_name) và tên Chủ đề (topic_name)
$questions = [];
$sql = "SELECT q.id, q.question_text, q.option_1, q.option_2, q.option_3, q.option_4, q.correct_option, q.difficulty, 
               g.name as grade_name, t.name as topic_name
        FROM questions q
        LEFT JOIN grades g ON q.grade_id = g.id
        LEFT JOIN topics t ON q.topic_id = t.id
        ORDER BY q.grade_id ASC, q.topic_id ASC, q.id DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $result->free();
} else {
    // Xử lý lỗi truy vấn (ví dụ: ghi log, hiển thị thông báo)
    error_log("Lỗi truy vấn danh sách câu hỏi: " . $conn->error);
    // $page_error = "Không thể tải danh sách câu hỏi.";
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
    <title>Quản lý Câu hỏi - Admin Panel</title>
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
            <a href="index.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </a>
            <a href="manage_questions.php" class="block py-2.5 px-4 rounded active">
                <i class="fas fa-question-circle mr-2"></i>Quản lý Câu hỏi
            </a>
            <a href="manage_users.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-users mr-2"></i>Quản lý Người dùng
            </a>
            <a href="manage_characters.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-user-astronaut mr-2"></i>Quản lý Nhân vật
            </a>
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề
            </a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp
            </a>
             <a href="manage_badges.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu
            </a>
            <a href="settings.php" class="block py-2.5 px-4 rounded">
                <i class="fas fa-cog mr-2"></i>Cài đặt chung
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div>
                <h1 class="text-xl font-semibold">Quản lý Câu hỏi</h1>
            </div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200">
                    <i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất
                </a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-700">Danh sách Câu hỏi</h2>
                <a href="add_edit_question.php" class="btn-add rounded font-semibold">
                    <i class="fas fa-plus mr-1"></i> Thêm Câu Hỏi Mới
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                    <strong class="font-semibold">Thành công!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                    <strong class="font-semibold">Lỗi!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            <?php // if (isset($page_error)): echo "<p class='text-red-500'>{$page_error}</p>"; endif; ?>


            <div class="content-card overflow-x-auto">
                <table class="table w-full min-w-max">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nội dung Câu hỏi</th>
                            <th>Khối</th>
                            <th>Chủ đề</th>
                            <th>Đáp án đúng</th>
                            <th>Độ khó</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($questions)): ?>
                            <?php foreach ($questions as $q): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($q['id']); ?></td>
                                    <td class="max-w-xs truncate" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($q['question_text'], 0, 70, "...")); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($q['grade_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($q['topic_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($q['option_' . $q['correct_option']]); ?> (Lựa chọn <?php echo $q['correct_option']; ?>)</td>
                                    <td class="capitalize"><?php echo htmlspecialchars($q['difficulty']); ?></td>
                                    <td>
                                        <a href="add_edit_question.php?id=<?php echo $q['id']; ?>" class="btn-sm btn-edit mr-1"><i class="fas fa-edit"></i> Sửa</a>
                                        <a href="manage_questions.php?action=delete&id=<?php echo $q['id']; ?>" 
                                           class="btn-sm btn-delete" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa câu hỏi này không? Hành động này không thể hoàn tác.');">
                                           <i class="fas fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-gray-500 py-4">Chưa có câu hỏi nào.</td>
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
