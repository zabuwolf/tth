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
    error_log("Lỗi kết nối CSDL trên trang admin/view_activity_logs.php: " . ($conn->connect_error ?? 'Unknown error'));
    $page_error = "Lỗi nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.";
}

// Phân trang
$results_per_page = 20; // Số lượng log mỗi trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$start_from = ($current_page - 1) * $results_per_page;

$activity_logs = [];
$total_logs = 0;

if ($conn) {
    // Đếm tổng số log để phân trang
    $sql_count = "SELECT COUNT(*) as total FROM game_sessions";
    $result_count = $conn->query($sql_count);
    if ($result_count) {
        $total_logs = $result_count->fetch_assoc()['total'];
        $result_count->free();
    } else {
        error_log("Lỗi đếm tổng số game_sessions: " . $conn->error);
    }
    $total_pages = ceil($total_logs / $results_per_page);
    if ($current_page > $total_pages && $total_logs > 0) { // Nếu trang hiện tại vượt quá tổng số trang
        $current_page = $total_pages;
        $start_from = ($current_page - 1) * $results_per_page;
    }


    // Lấy danh sách hoạt động (game sessions) với phân trang
    $sql_logs = "SELECT gs.id, gs.score_achieved, gs.start_time, gs.end_time, gs.is_completed,
                        u.fullname as user_fullname, u.username as user_username,
                        g.name as grade_name, t.name as topic_name, c.name as character_name
                 FROM game_sessions gs
                 LEFT JOIN users u ON gs.user_id = u.id
                 LEFT JOIN grades g ON gs.grade_id = g.id
                 LEFT JOIN topics t ON gs.topic_id = t.id
                 LEFT JOIN characters c ON gs.character_id = c.id
                 ORDER BY gs.start_time DESC
                 LIMIT ?, ?";
    $stmt_logs = $conn->prepare($sql_logs);
    if ($stmt_logs) {
        $stmt_logs->bind_param("ii", $start_from, $results_per_page);
        $stmt_logs->execute();
        $result_logs = $stmt_logs->get_result();
        while ($row = $result_logs->fetch_assoc()) {
            $activity_logs[] = $row;
        }
        $stmt_logs->close();
    } else {
        error_log("Lỗi truy vấn log hoạt động: " . $conn->error);
        if(!isset($page_error)) $page_error = "Không thể tải log hoạt động.";
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toàn Bộ Log Hoạt Động - Admin Panel</title>
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
        .table th, .table td { padding: 0.75rem 1rem; border-bottom: 1px solid #E5E7EB; text-align: left; font-size: 0.875rem; }
        .table th { background-color: #F3F4F6; font-weight: 600; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5rem 0.75rem; margin: 0 0.125rem; border: 1px solid #D1D5DB; border-radius: 0.25rem; color: #374151; text-decoration: none; }
        .pagination a:hover { background-color: #E5E7EB; }
        .pagination .active { background-color: #4F46E5; color: white; border-color: #4F46E5; }
        .pagination .disabled { color: #9CA3AF; cursor: not-allowed; }
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
            <a href="settings.php" class="block py-2.5 px-4 rounded"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
            <a href="view_activity_logs.php" class="block py-2.5 px-4 rounded bg-gray-700 text-white"> <i class="fas fa-clipboard-list mr-2"></i>Xem Log Hoạt Động
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div><h1 class="text-xl font-semibold">Toàn Bộ Log Hoạt Động (Lượt Chơi)</h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <h2 class="text-2xl font-semibold text-gray-700 mb-6">Lịch sử các lượt chơi game</h2>
            
            <?php if (isset($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Lỗi!</p>
                    <p><?php echo htmlspecialchars($page_error); ?></p>
                </div>
            <?php endif; ?>

            <div class="content-card overflow-x-auto">
                <table class="table w-full min-w-[1000px]">
                    <thead>
                        <tr>
                            <th>ID Log</th>
                            <th>Người chơi</th>
                            <th>Khối</th>
                            <th>Chủ đề</th>
                            <th>Nhân vật</th>
                            <th>Điểm</th>
                            <th>Hoàn thành</th>
                            <th>Thời gian bắt đầu</th>
                            <th>Thời gian kết thúc</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activity_logs)): ?>
                            <?php foreach ($activity_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($log['user_fullname'] ?? ($log['user_username'] ?? 'Khách')); ?>
                                        <?php if($log['user_username']): ?>
                                            <span class="text-xs text-gray-500 block">@<?php echo htmlspecialchars($log['user_username']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['grade_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['topic_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['character_name'] ?? 'N/A'); ?></td>
                                    <td class="font-semibold text-green-600"><?php echo htmlspecialchars($log['score_achieved']); ?></td>
                                    <td>
                                        <?php if ($log['is_completed']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Thắng</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Thua/Chưa xong</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($log['start_time']))); ?></td>
                                    <td><?php echo $log['end_time'] ? htmlspecialchars(date("d/m/Y H:i:s", strtotime($log['end_time']))) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-gray-500 py-10">Chưa có log hoạt động nào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_logs > 0 && $total_pages > 1): ?>
            <div class="mt-6 flex justify-center pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>">&laquo; Trước</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Trước</span>
                <?php endif; ?>

                <?php 
                // Logic hiển thị số trang (ví dụ: 1 ... 3 4 5 ... 10)
                $num_links_around_current = 2; // Số link trước và sau trang hiện tại
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $num_links_around_current && $i <= $current_page + $num_links_around_current)):
                ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php 
                    elseif ($i == $current_page - $num_links_around_current - 1 || $i == $current_page + $num_links_around_current + 1):
                ?>
                    <span class="disabled">...</span>
                <?php 
                    endif;
                endfor; 
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>">Tiếp &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Tiếp &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
</body>
</html>
