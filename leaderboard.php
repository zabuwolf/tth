<?php
session_start(); 
require_once 'config/db_config.php';

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Xác định khoảng thời gian cho bảng xếp hạng (mặc định là 'all_time')
$period = $_GET['period'] ?? 'all_time'; // 'daily', 'weekly', 'monthly', 'all_time'
$page_title_suffix = "Toàn Thời Gian";
$sql_leaderboard_users = "";

// Xây dựng câu truy vấn dựa trên khoảng thời gian
// Lưu ý: Để có bảng xếp hạng chính xác theo ngày/tuần/tháng dựa trên ĐIỂM KIẾM ĐƯỢC
// trong khoảng thời gian đó, chúng ta cần một cách để theo dõi điểm kiếm được.
// Bảng `game_sessions` có `score_achieved` và `end_time` có thể được sử dụng.
// Nếu chỉ dựa trên `users.points` thì đó là tổng điểm mọi thời đại.

// Cách 1: Dựa trên TỔNG ĐIỂM hiện tại của người dùng (đơn giản nhất)
if ($period === 'all_time') {
    $sql_leaderboard_users = "SELECT u.id, u.fullname, u.username, u.points, u.avatar_url 
                              FROM users u
                              WHERE u.is_admin = FALSE AND u.points > 0
                              ORDER BY u.points DESC, u.fullname ASC
                              LIMIT 100";
} 
// Cách 2: Dựa trên TỔNG ĐIỂM KIẾM ĐƯỢC từ game_sessions trong khoảng thời gian (phức tạp hơn)
// Cần đảm bảo game_sessions được ghi lại chính xác.
else {
    $date_condition = "";
    $current_date = date("Y-m-d");
    if ($period === 'daily') {
        $page_title_suffix = "Trong Ngày";
        $date_condition = "DATE(gs.end_time) = CURDATE()";
    } elseif ($period === 'weekly') {
        $page_title_suffix = "Trong Tuần";
        // Lấy ngày đầu tuần (Thứ 2) và cuối tuần (Chủ Nhật)
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
        $sunday = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));
        $date_condition = "DATE(gs.end_time) BETWEEN '{$monday}' AND '{$sunday}'";
    } elseif ($period === 'monthly') {
        $page_title_suffix = "Trong Tháng";
        $first_day_month = date('Y-m-01', strtotime($current_date));
        $last_day_month = date('Y-m-t', strtotime($current_date));
        $date_condition = "DATE(gs.end_time) BETWEEN '{$first_day_month}' AND '{$last_day_month}'";
    }

    if (!empty($date_condition)) {
        // Tính tổng điểm từ game_sessions cho mỗi người dùng trong khoảng thời gian
        $sql_leaderboard_users = "SELECT u.id, u.fullname, u.username, SUM(gs.score_achieved) as period_points, u.avatar_url
                                  FROM users u
                                  JOIN game_sessions gs ON u.id = gs.user_id
                                  WHERE u.is_admin = FALSE AND gs.is_completed = TRUE AND {$date_condition}
                                  GROUP BY u.id, u.fullname, u.username, u.avatar_url
                                  HAVING period_points > 0
                                  ORDER BY period_points DESC, u.fullname ASC
                                  LIMIT 100";
    } else { // Fallback to all_time if period is invalid
        $period = 'all_time';
        $page_title_suffix = "Toàn Thời Gian";
        $sql_leaderboard_users = "SELECT u.id, u.fullname, u.username, u.points, u.avatar_url 
                                  FROM users u
                                  WHERE u.is_admin = FALSE AND u.points > 0
                                  ORDER BY u.points DESC, u.fullname ASC
                                  LIMIT 100";
    }
}


$leaderboard_users = [];
$stmt_leaderboard = $conn->prepare($sql_leaderboard_users);

if ($stmt_leaderboard) {
    // Không cần bind_param nếu LIMIT là cố định và date_condition đã được escape (nếu cần)
    // Tuy nhiên, nếu date_condition dùng placeholder thì cần bind.
    // Trong trường hợp này, date_condition được xây dựng trực tiếp, cần cẩn thận nếu có input từ user.
    // Hiện tại, $period là từ $_GET nhưng chỉ dùng để chọn logic, không trực tiếp vào SQL.
    $stmt_leaderboard->execute();
    $result_leaderboard = $stmt_leaderboard->get_result();
    while ($row_user = $result_leaderboard->fetch_assoc()) {
        // Gán 'points' hoặc 'period_points' vào một key thống nhất để hiển thị
        $row_user['display_points'] = $row_user['points'] ?? ($row_user['period_points'] ?? 0);
        $leaderboard_users[] = $row_user;
    }
    $stmt_leaderboard->close();
} else {
    error_log("Lỗi truy vấn bảng xếp hạng ($period): " . $conn->error);
}

$current_user_id_on_leaderboard = null;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $current_user_id_on_leaderboard = (int)$_SESSION['user_id'];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Xếp Hạng <?php echo $page_title_suffix; ?> - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .leaderboard-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .leaderboard-header {
            background: linear-gradient(135deg, #F97316 0%, #FBBF24 100%); 
            color: white; padding: 1.5rem; /* Adjusted padding */
            border-radius: 1rem 1rem 0 0; text-align: center;
        }
        .leaderboard-header h1 { font-family: 'Baloo 2', cursive; font-size: 2.2rem; font-weight: 800; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .leaderboard-header p { font-size: 0.9rem; opacity: 0.9; margin-top: 0.25rem;}
        
        .period-selector { margin: 1.5rem auto; text-align: center; }
        .period-selector a {
            display: inline-block; padding: 0.5rem 1rem; margin: 0 0.25rem;
            border-radius: 0.5rem; background-color: #FFF7ED; color: #D97706;
            font-weight: 600; text-decoration: none; transition: all 0.2s ease;
            border: 1px solid #FDE68A;
        }
        .period-selector a:hover { background-color: #FDE68A; color: #92400E; }
        .period-selector a.active { background-color: #F59E0B; color: white; border-color: #F59E0B; }

        .rank-item { display: flex; align-items: center; padding: 0.9rem 1.25rem; border-bottom: 1px solid #F3F4F6; transition: background-color 0.2s ease-in-out; }
        .rank-item:last-child { border-bottom: none; }
        .rank-item:hover { background-color: #FFF7ED; }
        .rank-item.current-user-highlight { background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding-left: calc(1.25rem - 4px); }
        
        .rank-position-wrapper { width: 60px; text-align: center; }
        .rank-position { font-family: 'Baloo 2', cursive; font-size: 1.4rem; font-weight: 700; color: #78350F; }
        .rank-item.current-user-highlight .rank-position { color: #B45309; }
        .rank-position .rank-icon { font-size: 1.2em; vertical-align: middle; } /* For trophy icons */

        .rank-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-left: 0.75rem; margin-right: 1rem; border: 2px solid #FDE68A; }
        .rank-info { flex-grow: 1; }
        .rank-info .name { font-weight: 700; color: #1F2937; font-size: 1rem;}
        .rank-info .username { font-size: 0.8rem; color: #6B7280; }
        .rank-points { font-family: 'Baloo 2', cursive; font-size: 1.3rem; font-weight: 700; color: #F59E0B; display: flex; align-items: center; }
        .rank-points i { margin-right: 0.3rem; color: #FACC15; }
        .btn-nav { background-color: #EC4899; color:white; padding: 0.6rem 1.2rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s; }
        .btn-nav:hover { background-color: #DB2777; }

    </style>
</head>
<body class="min-h-screen py-8">

    <div class="container mx-auto max-w-2xl px-4">
        <div class="leaderboard-card">
            <div class="leaderboard-header">
                <h1><i class="fas fa-trophy mr-2"></i>Bảng Xếp Hạng</h1>
                <p>Vinh danh những nhà toán học nhí xuất sắc <?php echo strtolower($page_title_suffix); ?>!</p>
            </div>

            <div class="period-selector">
                <a href="leaderboard.php?period=daily" class="<?php echo ($period === 'daily') ? 'active' : ''; ?>">Hôm Nay</a>
                <a href="leaderboard.php?period=weekly" class="<?php echo ($period === 'weekly') ? 'active' : ''; ?>">Tuần Này</a>
                <a href="leaderboard.php?period=monthly" class="<?php echo ($period === 'monthly') ? 'active' : ''; ?>">Tháng Này</a>
                <a href="leaderboard.php?period=all_time" class="<?php echo ($period === 'all_time') ? 'active' : ''; ?>">Tất Cả</a>
            </div>

            <div class="py-2">
                <?php if (!empty($leaderboard_users)): ?>
                    <?php foreach ($leaderboard_users as $index => $user_ranked): ?>
                        <?php
                            $rank = $index + 1;
                            $is_current_user_row = ($current_user_id_on_leaderboard !== null && $current_user_id_on_leaderboard === (int)$user_ranked['id']);
                            $avatar_ranked_url = $user_ranked['avatar_url'] 
                                                ?: 'https://placehold.co/50x50/cccccc/757575?text=' . strtoupper(substr($user_ranked['fullname'], 0, 1));
                        ?>
                        <div class="rank-item <?php echo $is_current_user_row ? 'current-user-highlight' : ''; ?>">
                            <div class="rank-position-wrapper">
                                <span class="rank-position">
                                    <?php if ($rank == 1): ?><i class="fas fa-crown text-yellow-400 rank-icon"></i>
                                    <?php elseif ($rank == 2): ?><i class="fas fa-medal text-gray-400 rank-icon"></i>
                                    <?php elseif ($rank == 3): ?><i class="fas fa-award text-yellow-600 rank-icon"></i>
                                    <?php else: echo $rank; endif; ?>
                                </span>
                            </div>
                            <img src="<?php echo htmlspecialchars($avatar_ranked_url); ?>" alt="Avatar của <?php echo htmlspecialchars($user_ranked['fullname']); ?>" class="rank-avatar" onerror="this.src='https://placehold.co/50x50/E0E0E0/757575?text=Lỗi'; this.onerror=null;">
                            <div class="rank-info">
                                <div class="name"><?php echo htmlspecialchars($user_ranked['fullname']); ?></div>
                                <div class="username">@<?php echo htmlspecialchars($user_ranked['username']); ?></div>
                            </div>
                            <div class="rank-points">
                                <i class="fas fa-star"></i> <?php echo htmlspecialchars($user_ranked['display_points']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-600 py-10">Chưa có ai trên bảng xếp hạng <?php echo strtolower($page_title_suffix); ?>. Hãy là người đầu tiên!</p>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center mt-8">
            <a href="index.php" class="btn-nav"><i class="fas fa-home mr-2"></i>Về Trang Chủ</a>
            <a href="game.php?stage=select_grade" class="btn-nav ml-4"><i class="fas fa-gamepad mr-2"></i>Chơi Game</a>
        </p>
    </div>

    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
</body>
</html>
