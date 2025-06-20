<?php
session_start();
require_once '../config/db_config.php'; // Đi ra một cấp để vào config

// Kiểm tra xem admin đã đăng nhập chưa
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_errors'] = ['general' => "Vui lòng đăng nhập với quyền quản trị để truy cập."]; // Sử dụng key lỗi chung
    header('Location: ../login.php'); // Chuyển hướng ra trang login.php ở thư mục gốc
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin'; //
$page_title = "Dashboard Tổng Quan"; // Dành cho header.php

// Kết nối CSDL
$conn = connect_db(); //
if (!$conn) { //
    error_log("Lỗi kết nối CSDL trên trang admin/index.php: " . ($conn->connect_error ?? 'Unknown error')); //
    $dashboard_error = "Lỗi nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu để tải dữ liệu dashboard."; //
}

// Khởi tạo các biến thống kê
$total_users = 'N/A'; //
$total_questions = 'N/A'; //
$sessions_today = 'N/A'; //
$recent_activities = []; //
$top_players = []; //

if ($conn) { // Chỉ thực hiện truy vấn nếu kết nối CSDL thành công
    // 1. Lấy tổng số người dùng (không phải admin)
    $sql_total_users = "SELECT COUNT(*) as count FROM users WHERE is_admin = FALSE"; //
    $result_total_users = $conn->query($sql_total_users); //
    if ($result_total_users) { //
        $total_users = $result_total_users->fetch_assoc()['count']; //
        $result_total_users->free(); //
    } else {
        error_log("Lỗi truy vấn tổng số người dùng: " . $conn->error); //
    }

    // 2. Lấy tổng số câu hỏi
    $sql_total_questions = "SELECT COUNT(*) as count FROM questions"; //
    $result_total_questions = $conn->query($sql_total_questions); //
    if ($result_total_questions) { //
        $total_questions = $result_total_questions->fetch_assoc()['count']; //
        $result_total_questions->free(); //
    } else {
        error_log("Lỗi truy vấn tổng số câu hỏi: " . $conn->error); //
    }

    // 3. Lấy số lượt chơi hôm nay
    $today_date_sql = date("Y-m-d"); //
    $sql_sessions_today = "SELECT COUNT(*) as count FROM game_sessions WHERE DATE(start_time) = ?"; //
    $stmt_sessions = $conn->prepare($sql_sessions_today); //
    if ($stmt_sessions) { //
        $stmt_sessions->bind_param("s", $today_date_sql); //
        $stmt_sessions->execute(); //
        $result_sessions = $stmt_sessions->get_result(); //
        if ($result_sessions) { //
            $sessions_today = $result_sessions->fetch_assoc()['count']; //
        }
        $stmt_sessions->close(); //
    } else {
        error_log("Lỗi chuẩn bị truy vấn lượt chơi hôm nay: " . $conn->error); //
    }

    // 4. Lấy 10 hoạt động gần đây (game sessions)
    $sql_recent_activities = "SELECT gs.id, gs.score_achieved, gs.start_time, gs.is_completed,
                                     u.fullname as user_fullname, u.username as user_username,
                                     g.name as grade_name, t.name as topic_name, c.name as character_name
                              FROM game_sessions gs
                              LEFT JOIN users u ON gs.user_id = u.id
                              LEFT JOIN grades g ON gs.grade_id = g.id
                              LEFT JOIN topics t ON gs.topic_id = t.id
                              LEFT JOIN characters c ON gs.character_id = c.id
                              ORDER BY gs.start_time DESC
                              LIMIT 10"; // Giữ nguyên giới hạn 10 cho dashboard
    $result_activities = $conn->query($sql_recent_activities); //
    if ($result_activities) { //
        while ($row = $result_activities->fetch_assoc()) { //
            $recent_activities[] = $row; //
        }
        $result_activities->free(); //
    } else {
        error_log("Lỗi truy vấn hoạt động gần đây: " . $conn->error); //
    }

    // 5. Lấy top 3 người chơi có điểm cao nhất
    $sql_top_players = "SELECT fullname, username, points, avatar_url 
                        FROM users 
                        WHERE is_admin = FALSE AND points > 0
                        ORDER BY points DESC 
                        LIMIT 3"; //
    $result_top_players = $conn->query($sql_top_players); //
    if ($result_top_players) { //
        while ($row = $result_top_players->fetch_assoc()) { //
            $top_players[] = $row; //
        }
        $result_top_players->free(); //
    } else {
        error_log("Lỗi truy vấn top người chơi: " . $conn->error); //
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F9FAFB; /* Gray 50 */ }
        .admin-header { background-color: #4F46E5; /* Indigo 600 */ color: white; }
        .sidebar { background-color: #1F2937; /* Gray 800 */ color: #D1D5DB; /* Gray 300 */ }
        .sidebar a { color: #D1D5DB; transition: background-color 0.2s, color 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #374151; /* Gray 700 */ color: white; }
        .content-card { background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06); }
        .table th, .table td { padding: 0.75rem 1rem; border-bottom: 1px solid #E5E7EB; text-align: left; font-size: 0.875rem; }
        .table th { background-color: #F3F4F6; font-weight: 600; }
        .player-rank-item { display: flex; align-items: center; padding: 0.5rem 0; }
        .player-rank-item img { width: 32px; height: 32px; border-radius: 50%; margin-right: 0.75rem; object-fit: cover; }
    </style>
</head>
<body class="flex h-screen">

    <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col">
        <?php 
        // $page_title đã được đặt ở trên
        include_once __DIR__ . '/includes/header.php'; 
        ?>

        <main class="flex-1 p-6 overflow-y-auto">
            <?php if (isset($dashboard_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Lỗi!</p>
                    <p><?php echo htmlspecialchars($dashboard_error); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="content-card p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-500 text-white rounded-full mr-4">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Tổng Người Chơi</p>
                            <p class="text-3xl font-semibold text-gray-700">
                                <?php echo htmlspecialchars($total_users); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="content-card p-6">
                     <div class="flex items-center">
                        <div class="p-3 bg-green-500 text-white rounded-full mr-4">
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Tổng Số Câu Hỏi</p>
                            <p class="text-3xl font-semibold text-gray-700">
                                <?php echo htmlspecialchars($total_questions); ?>
                            </p>
                        </div>
                    </div>
                </div>
                 <div class="content-card p-6">
                     <div class="flex items-center">
                        <div class="p-3 bg-yellow-500 text-white rounded-full mr-4">
                            <i class="fas fa-gamepad fa-2x"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Lượt Chơi Hôm Nay</p>
                            <p class="text-3xl font-semibold text-gray-700">
                                 <?php echo htmlspecialchars($sessions_today); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="content-card p-6 lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-700">Hoạt động gần đây (10 lượt chơi mới nhất)</h3>
                        <a href="view_activity_logs.php" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Xem tất cả log <i class="fas fa-arrow-right fa-xs ml-1"></i>
                        </a>
                    </div>
                    <?php if (!empty($recent_activities)): ?>
                        <div class="overflow-x-auto">
                            <table class="table w-full min-w-[700px]">
                                <thead>
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Người chơi</th>
                                        <th>Chủ đề (Khối)</th>
                                        <th>Nhân vật</th>
                                        <th>Điểm</th>
                                        <th>Kết quả</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_activities as $activity): ?>
                                        <tr>
                                            <td class="text-gray-500"><?php echo htmlspecialchars(date("d/m H:i", strtotime($activity['start_time']))); ?></td>
                                            <td>
                                                <strong class="text-gray-800"><?php echo htmlspecialchars($activity['user_fullname'] ?? ($activity['user_username'] ?? 'Khách')); ?></strong>
                                                <?php if($activity['user_username']): ?>
                                                    <span class="text-xs text-gray-400 block">@<?php echo htmlspecialchars($activity['user_username']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($activity['topic_name'] ?? 'N/A'); ?>
                                                <span class="text-xs text-gray-400 block">(<?php echo htmlspecialchars($activity['grade_name'] ?? 'N/A'); ?>)</span>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['character_name'] ?? 'N/A'); ?></td>
                                            <td class="font-semibold text-green-600"><?php echo htmlspecialchars($activity['score_achieved']); ?></td>
                                            <td>
                                                <?php if ($activity['is_completed']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Thắng</span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Thua/Chưa xong</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 py-4">Chưa có hoạt động nào gần đây.</p>
                    <?php endif; ?>
                </div>

                 <div class="content-card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-700">Top 3 Người Chơi</h3>
                        <a href="../leaderboard.php" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Xem Bảng Xếp Hạng Đầy Đủ <i class="fas fa-external-link-alt fa-xs ml-1"></i>
                        </a>
                    </div>
                     <?php if (!empty($top_players)): ?>
                        <ul class="space-y-3">
                            <?php foreach($top_players as $index => $player): 
                                $player_avatar = $player['avatar_url'] ? '../' . ltrim($player['avatar_url'], '/') : 'https://placehold.co/40x40/cccccc/757575?text=' . strtoupper(substr($player['fullname'],0,1));
                                // Chỉnh sửa đường dẫn avatar cho đúng nếu nó là tương đối
                                if ($player['avatar_url'] && strpos($player['avatar_url'], 'http') !== 0 && strpos($player['avatar_url'], 'assets/') === 0) {
                                    $player_avatar = '../' . $player['avatar_url'];
                                } elseif ($player['avatar_url'] && strpos($player['avatar_url'], 'http') === 0) {
                                    $player_avatar = $player['avatar_url'];
                                }
                            ?>
                            <li class="player-rank-item border-b border-gray-100 pb-2 last:border-b-0 last:pb-0">
                                <span class="font-bold text-indigo-600 w-10 text-lg text-center"><?php echo $index + 1; ?>.</span>
                                <img src="<?php echo htmlspecialchars($player_avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-full mr-3 object-cover">
                                <div class="flex-1">
                                    <span class="font-semibold text-gray-800 block"><?php echo htmlspecialchars($player['fullname']); ?></span>
                                    <span class="text-xs text-gray-500">@<?php echo htmlspecialchars($player['username']); ?></span>
                                </div>
                                <span class="font-bold text-xl text-yellow-500"><?php echo htmlspecialchars($player['points']); ?> <i class="fas fa-star text-xs"></i></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500 py-4">Chưa có người chơi nào trong bảng xếp hạng.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <?php
    if ($conn) { //
        close_db_connection($conn); //
    }
    ?>
</body>
</html>