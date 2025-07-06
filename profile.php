<?php
session_start();
require_once 'config/db_config.php';
// require_once 'includes/game_functions.php'; // Có thể không cần trực tiếp ở đây nếu chỉ hiển thị

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    $_SESSION['login_error_message'] = "Vui lòng đăng nhập để xem hồ sơ của bạn.";
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    // Xử lý lỗi kết nối CSDL một cách thân thiện
    // Có thể chuyển hướng đến trang lỗi hoặc hiển thị thông báo
    die("Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
}

// Lấy thông tin người dùng hiện tại từ CSDL
$user_profile_data = null;
$sql_user = "SELECT id, fullname, username, email, points, avatar_url, created_at FROM users WHERE id = ? LIMIT 1";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_profile_data = $result_user->fetch_assoc();
    }
    $stmt_user->close();
} else {
    error_log("Lỗi chuẩn bị SQL lấy thông tin user profile: " . $conn->error);
}

if (!$user_profile_data) {
    // Không tìm thấy người dùng, có thể session bị lỗi hoặc user bị xóa
    // Hủy session và chuyển về trang đăng nhập
    session_unset();
    session_destroy();
    header('Location: login.php?error=Không tìm thấy thông tin tài khoản của bạn.');
    exit;
}

// Lấy danh sách huy hiệu người dùng đã đạt được
$user_badges = [];
$sql_user_badges = "SELECT b.id, b.name, b.description, b.image_url 
                    FROM user_badges ub
                    JOIN badges b ON ub.badge_id = b.id
                    WHERE ub.user_id = ?
                    ORDER BY ub.earned_at DESC"; // Sắp xếp theo thời gian nhận gần nhất
$stmt_badges = $conn->prepare($sql_user_badges);
if ($stmt_badges) {
    $stmt_badges->bind_param("i", $user_id);
    $stmt_badges->execute();
    $result_badges = $stmt_badges->get_result();
    while ($row_badge = $result_badges->fetch_assoc()) {
        $user_badges[] = $row_badge;
    }
    $stmt_badges->close();
} else {
    error_log("Lỗi truy vấn huy hiệu của người dùng (ID: {$user_id}): " . $conn->error);
}

// Lấy avatar (ưu tiên từ CSDL, sau đó session, cuối cùng là placeholder)
$user_avatar_url = $user_profile_data['avatar_url'] 
                    ?: ($_SESSION['avatar_url'] 
                        ?? 'https://placehold.co/150x150/3B82F6/FFFFFF?text=' . strtoupper(substr($user_profile_data['fullname'], 0, 1)));
if (empty($user_profile_data['avatar_url']) && isset($_SESSION['avatar_url']) && strpos($_SESSION['avatar_url'], 'placehold.co') === false) {
    $user_avatar_url = $_SESSION['avatar_url']; // Dùng session nếu DB rỗng và session có ảnh thật
}


// Lấy và xóa thông báo thành công từ session (nếu có từ process_edit_profile.php)
$success_profile_message = $_SESSION['success_message_profile'] ?? null;
unset($_SESSION['success_message_profile']);

// Thông báo đổi mật khẩu (nếu có từ change_password.php -> process_change_password.php)
$password_change_success_on_profile = $_SESSION['success_message_change_password'] ?? null;
unset($_SESSION['success_message_change_password']); // Xóa sau khi lấy để không hiển thị lại

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Của Bạn - <?php echo htmlspecialchars($user_profile_data['fullname']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .profile-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #FBCFE8; /* Pink-200 */ margin: -60px auto 0 auto; display: block; position: relative; z-index: 10;}
        .profile-header-bg {
            background: linear-gradient(135deg, #EC4899 0%, #D946EF 50%, #8B5CF6 100%); /* Pink to Purple gradient */
            height: 150px;
            border-radius: 1rem 1rem 0 0;
        }
        .stat-box { background-color: #FFF7ED; border: 1px solid #FDE68A; padding: 1rem; border-radius: 0.75rem; text-align: center; }
        .stat-box .value { font-family: 'Baloo 2', cursive; font-size: 2rem; color: #D97706; /* Amber-600 */ }
        .stat-box .label { font-size: 0.9rem; color: #78350F; /* Amber-800 */ }
        
        .badge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; }
        .badge-item { background-color: #FFF0F5; border: 1px solid #FBCFE8; border-radius: 0.5rem; padding: 0.75rem; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .badge-item:hover { transform: translateY(-3px); }
        .badge-item img { width: 60px; height: 60px; object-fit: contain; margin: 0 auto 0.5rem auto; background-color: #fff; padding: 3px; border-radius: 50%;}
        .badge-item .badge-name { font-size: 0.8rem; font-weight: 600; color: #7C2D12; }
        
        .btn-profile-action {
            display: inline-block; padding: 0.65rem 1.25rem; /* Adjusted padding */
            border-radius: 0.5rem; /* Tailwind rounded-lg */
            font-weight: 600; /* Tailwind font-semibold */
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            font-size: 0.9rem; /* Adjusted font size */
        }
        .btn-profile-action:hover { transform: translateY(-1px); }

        .btn-edit-profile { background-color: #8B5CF6; color: white; } /* Purple */
        .btn-edit-profile:hover { background-color: #7C3AED; }
        .btn-change-password { background-color: #F59E0B; color: white; } /* Amber */
        .btn-change-password:hover { background-color: #D97706; }
        .btn-nav { background-color: #EC4899; color:white; } /* Pink */
        .btn-nav:hover { background-color: #DB2777; }
        .btn-home { background-color: #6B7280; color: white; } /* Gray */
        .btn-home:hover { background-color: #4B5563; }

        .alert-success-profile { background-color: #D1FAE5; border-left-width: 4px; border-color: #10B981; color: #065F46; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;}
    </style>
</head>
<body class="min-h-screen pt-8 pb-8">

    <div class="container mx-auto max-w-3xl px-4">
        <div class="profile-card">
            <div class="profile-header-bg"></div>
            <div class="p-6 md:p-8 relative">
                <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="Avatar của <?php echo htmlspecialchars($user_profile_data['fullname']); ?>" class="profile-avatar" onerror="this.src='https://placehold.co/150x150/E0E0E0/757575?text=Ảnh+Lỗi'; this.onerror=null;">
                
                <?php if ($success_profile_message): ?>
                    <div class="alert-success-profile mt-4" role="alert">
                        <p class="font-bold">Tuyệt vời!</p>
                        <p><?php echo htmlspecialchars($success_profile_message); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($password_change_success_on_profile): ?>
                    <div class="alert-success-profile mt-4" role="alert">
                        <p class="font-bold">Thành công!</p>
                        <p><?php echo htmlspecialchars($password_change_success_on_profile); ?></p>
                    </div>
                <?php endif; ?>


                <div class="text-center mt-4">
                    <h1 class="font-baloo text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($user_profile_data['fullname']); ?></h1>
                    <p class="text-md text-gray-500">@<?php echo htmlspecialchars($user_profile_data['username']); ?></p>
                    <p class="text-sm text-gray-500 mt-1"><i class="fas fa-envelope mr-1 text-pink-500"></i> 
                        <?php 
                        $email_parts = explode('@', $user_profile_data['email']);
                        if (count($email_parts) === 2) {
                            $local_part = $email_parts[0];
                            $domain_part = $email_parts[1];
                            if (strlen($local_part) > 3) {
                                echo htmlspecialchars(substr($local_part, 0, 3) . str_repeat('*', strlen($local_part) - 3) . '@' . $domain_part);
                            } else {
                                echo htmlspecialchars(str_repeat('*', strlen($local_part)) . '@' . $domain_part);
                            }
                        } else {
                            echo htmlspecialchars($user_profile_data['email']); 
                        }
                        ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Tham gia ngày: <?php echo htmlspecialchars(date("d/m/Y", strtotime($user_profile_data['created_at']))); ?></p>
                </div>

                <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="stat-box">
                        <div class="value"><?php echo htmlspecialchars($user_profile_data['points']); ?></div>
                        <div class="label">Tổng Điểm</div>
                    </div>
                    <div class="stat-box">
                        <div class="value"><?php echo count($user_badges); ?></div>
                        <div class="label">Huy Hiệu Đã Đạt</div>
                    </div>
                </div>

                <div class="mt-8">
                    <h2 class="font-baloo text-2xl font-semibold text-pink-600 mb-4">Huy Hiệu Của Bạn</h2>
                    <?php if (!empty($user_badges)): ?>
                        <div class="badge-grid">
                            <?php foreach ($user_badges as $badge): ?>
                                <div class="badge-item" title="<?php echo htmlspecialchars($badge['name'] . ($badge['description'] ? ' - ' . $badge['description'] : '')); ?>">
                                    <img src="<?php echo htmlspecialchars(!empty($badge['image_url']) ? $badge['image_url'] : 'https://placehold.co/80x80/FFD700/78350F?text=Badge'); ?>" 
                                         alt="<?php echo htmlspecialchars($badge['name']); ?>"
                                         onerror="this.src='https://placehold.co/80x80/cccccc/333333?text=Ảnh+Lỗi'; this.onerror=null;">
                                    <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 text-center py-4">Bạn chưa đạt được huy hiệu nào. Cố gắng chơi game để sưu tầm nhé!</p>
                    <?php endif; ?>
                </div>

                <div class="mt-10 text-center space-y-3 sm:space-y-0 sm:flex sm:flex-wrap sm:justify-center sm:gap-4">
                    <a href="edit_profile.php" class="btn-profile-action btn-edit-profile">
                        <i class="fas fa-user-edit mr-2"></i>Chỉnh Sửa Hồ Sơ
                    </a>
                    <a href="change_password.php" class="btn-profile-action btn-change-password">
                        <i class="fas fa-key mr-2"></i>Đổi Mật Khẩu
                    </a>
                     <a href="game.php?stage=select_grade" class="btn-profile-action btn-nav">
                        <i class="fas fa-gamepad mr-2"></i>Vào Game
                    </a>
                    <a href="index.php" class="btn-profile-action btn-home">
                        <i class="fas fa-home mr-2"></i>Trang Chủ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
</body>
</html>
