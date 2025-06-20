<?php
session_start(); 
require_once 'config/db_config.php';

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Xác định loại bảng xếp hạng (user hoặc school)
$leaderboard_type = $_GET['type'] ?? 'user'; 
if (!in_array($leaderboard_type, ['user', 'school'])) {
    $leaderboard_type = 'user'; 
}

// Xác định khoảng thời gian cho bảng xếp hạng
$period = $_GET['period'] ?? 'all_time'; 
if (!in_array($period, ['daily', 'weekly', 'monthly', 'all_time'])) {
    $period = 'all_time'; 
}

// --- Xác định tiêu đề trang ---
$page_title_main = ($leaderboard_type === 'school') ? "Xếp Hạng Trường Học" : "Bảng Xếp Hạng Cá Nhân";
$page_title_suffix = "";
switch ($period) {
    case 'daily':
        $page_title_suffix = "Trong Ngày";
        break;
    case 'weekly':
        $page_title_suffix = "Trong Tuần";
        break;
    case 'monthly':
        $page_title_suffix = "Trong Tháng";
        break;
    case 'all_time':
    default:
        $page_title_suffix = "Toàn Thời Gian";
        break;
}

// --- Xây dựng câu truy vấn SQL dựa trên loại bảng xếp hạng ---
$leaderboard_data = [];
$sql_leaderboard = "";

if ($leaderboard_type === 'school') {
    $sql_leaderboard = "SELECT u.school_name, SUM(u.points) as display_points
                        FROM users u
                        WHERE u.school_name IS NOT NULL AND u.school_name != '' AND u.is_admin = FALSE AND u.points > 0
                        GROUP BY u.school_name
                        HAVING SUM(u.points) > 0
                        ORDER BY display_points DESC, u.school_name ASC
                        LIMIT 100";
} else { // 'user'
    $sql_leaderboard = "SELECT u.id, u.fullname, u.username, u.school_name, u.points as display_points, u.avatar_url 
                        FROM users u
                        WHERE u.is_admin = FALSE AND u.points > 0
                        ORDER BY u.points DESC, u.fullname ASC
                        LIMIT 100";
}

$stmt_leaderboard = $conn->prepare($sql_leaderboard);
if ($stmt_leaderboard) {
    $stmt_leaderboard->execute();
    $result_leaderboard = $stmt_leaderboard->get_result();
    while ($row = $result_leaderboard->fetch_assoc()) {
        $leaderboard_data[] = $row;
    }
    $stmt_leaderboard->close();
} else {
    error_log("Lỗi truy vấn bảng xếp hạng (Loại: {$leaderboard_type}, Kỳ: {$period}): " . $conn->error);
}

$current_user_id_on_leaderboard = null;
if ($leaderboard_type === 'user' && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $current_user_id_on_leaderboard = (int)$_SESSION['user_id'];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_main . " " . $page_title_suffix); ?> - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .leaderboard-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .leaderboard-header {
            background: linear-gradient(135deg, #F97316 0%, #FBBF24 100%); 
            color: white; padding: 1.5rem;
            border-radius: 1rem 1rem 0 0; text-align: center;
        }
        .leaderboard-header h1 { font-family: 'Baloo 2', cursive; font-size: 2.2rem; font-weight: 800; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .leaderboard-header p { font-size: 0.9rem; opacity: 0.9; margin-top: 0.25rem;}
        
        .type-selector, .period-selector { margin: 1rem auto; text-align: center; }
        .type-selector a, .period-selector a {
            display: inline-block; padding: 0.5rem 1rem; margin: 0.25rem 0.25rem;
            border-radius: 0.5rem; background-color: #FFF7ED; color: #D97706;
            font-weight: 600; text-decoration: none; transition: all 0.2s ease;
            border: 1px solid #FDE68A; font-size: 0.9rem;
        }
        .type-selector a:hover, .period-selector a:hover { background-color: #FDE68A; color: #92400E; }
        .type-selector a.active, .period-selector a.active { background-color: #F59E0B; color: white; border-color: #F59E0B; }

        .rank-item { display: flex; align-items: center; padding: 0.9rem 1.25rem; border-bottom: 1px solid #F3F4F6; transition: background-color 0.2s ease-in-out; }
        .rank-item:last-child { border-bottom: none; }
        .rank-item:hover { background-color: #FFF7ED; }
        .rank-item.current-user-highlight { background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding-left: calc(1.25rem - 4px); }
        
        .rank-position-wrapper { width: 60px; text-align: center; }
        .rank-position { font-family: 'Baloo 2', cursive; font-size: 1.4rem; font-weight: 700; color: #78350F; }
        .rank-item.current-user-highlight .rank-position { color: #B45309; }
        .rank-position .rank-icon { font-size: 1.2em; vertical-align: middle; }

        .rank-avatar, .rank-school-icon-wrapper { 
            width: 45px; height: 45px; border-radius: 50%; 
            margin-left: 0.75rem; margin-right: 1rem; border: 2px solid #FDE68A;
            display: flex; align-items: center; justify-content: center;
        }
        .rank-avatar { object-fit: cover; }
        .rank-school-icon-wrapper { background-color: #FEF3C7; }
        .rank-school-icon-wrapper i { font-size: 1.5rem; color: #F59E0B; }

        .rank-info { flex-grow: 1; }
        .rank-info .name { font-weight: 700; color: #1F2937; font-size: 1rem;}
        .rank-info .sub-name { font-size: 0.8rem; color: #6B7280; } 
        
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
                <h1><i class="fas fa-trophy mr-2"></i><?php echo htmlspecialchars($page_title_main); ?></h1>
                <p>Vinh danh những <?php echo ($leaderboard_type === 'school') ? 'trường học' : 'nhà toán học nhí'; ?> xuất sắc <?php echo strtolower($page_title_suffix); ?>!</p>
                <?php if ($leaderboard_type === 'school'): ?>
                    <p class="text-xs opacity-70 mt-1">(Điểm trường được tính bằng tổng điểm của tất cả học sinh)</p>
                <?php elseif ($period !== 'all_time'): ?>
                     <p class="text-xs opacity-70 mt-1">(Hiển thị tổng điểm tích lũy của người chơi)</p>
                <?php endif; ?>
            </div>

            <div class="type-selector">
                <a href="leaderboard.php?type=user&period=<?php echo $period; ?>" class="<?php echo ($leaderboard_type === 'user') ? 'active' : ''; ?>">
                    <i class="fas fa-user mr-1"></i>Cá Nhân
                </a>
                <a href="leaderboard.php?type=school&period=<?php echo $period; ?>" class="<?php echo ($leaderboard_type === 'school') ? 'active' : ''; ?>">
                    <i class="fas fa-school mr-1"></i>Trường Học
                </a>
            </div>

            <div class="period-selector">
                <a href="leaderboard.php?type=<?php echo $leaderboard_type; ?>&period=daily" class="<?php echo ($period === 'daily') ? 'active' : ''; ?>">Hôm Nay</a>
                <a href="leaderboard.php?type=<?php echo $leaderboard_type; ?>&period=weekly" class="<?php echo ($period === 'weekly') ? 'active' : ''; ?>">Tuần Này</a>
                <a href="leaderboard.php?type=<?php echo $leaderboard_type; ?>&period=monthly" class="<?php echo ($period === 'monthly') ? 'active' : ''; ?>">Tháng Này</a>
                <a href="leaderboard.php?type=<?php echo $leaderboard_type; ?>&period=all_time" class="<?php echo ($period === 'all_time') ? 'active' : ''; ?>">Tất Cả</a>
            </div>

            <div class="py-2">
                <?php if (!empty($leaderboard_data)): ?>
                    <?php foreach ($leaderboard_data as $index => $item): ?>
                        <?php
                            $rank = $index + 1;
                            $is_current_user_row = false;
                            if ($leaderboard_type === 'user') {
                                $is_current_user_row = ($current_user_id_on_leaderboard !== null && $current_user_id_on_leaderboard === (int)($item['id'] ?? 0));
                                $avatar_display_url = $item['avatar_url'] 
                                    ?: 'https://placehold.co/50x50/cccccc/757575?text=' . strtoupper(substr($item['fullname'], 0, 1));
                            }
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

                            <?php if ($leaderboard_type === 'user'): ?>
                                <img src="<?php echo htmlspecialchars($avatar_display_url); ?>" alt="Avatar của <?php echo htmlspecialchars($item['fullname']); ?>" class="rank-avatar" onerror="this.src='https://placehold.co/50x50/E0E0E0/757575?text=Lỗi'; this.onerror=null;">
                                <div class="rank-info">
                                    <div class="name"><?php echo htmlspecialchars($item['fullname']); ?></div>
                                    <div class="sub-name">
                                        <?php if (!empty($item['school_name'])): ?>
                                            <i class="fas fa-school fa-xs mr-1 text-sky-500"></i><?php echo htmlspecialchars($item['school_name']); ?>
                                        <?php else: ?>
                                            <span class="italic">Chưa có tên trường</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($leaderboard_type === 'school'): ?>
                                <div class="rank-school-icon-wrapper">
                                    <i class="fas fa-school"></i>
                                </div>
                                <div class="rank-info">
                                    <div class="name"><?php echo htmlspecialchars($item['school_name']); ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="rank-points">
                                <i class="fas fa-star"></i> <?php echo htmlspecialchars($item['display_points']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-600 py-10">
                        Chưa có dữ liệu trên bảng xếp hạng 
                        <?php echo ($leaderboard_type === 'school') ? 'trường học' : 'cá nhân'; ?>
                        <?php echo strtolower($page_title_suffix); ?>.
                    </p>
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