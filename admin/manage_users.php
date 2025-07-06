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
$current_admin_id = $_SESSION['admin_id'] ?? 0; // Lấy ID admin hiện tại để không tự xóa mình

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

// Xử lý xóa người dùng (nếu có yêu cầu)
// CẢNH BÁO: Xóa người dùng có thể gây mất dữ liệu liên quan (game_sessions, user_badges).
// Cân nhắc việc "vô hiệu hóa" tài khoản thay vì xóa vĩnh viễn.
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = (int)$_GET['id'];
    
    if ($user_id_to_delete > 0 && $user_id_to_delete != $current_admin_id) { // Không cho admin tự xóa mình
        
        // Trước khi xóa user, xóa các bản ghi phụ thuộc trong user_badges và game_sessions
        // (Hoặc bạn có thể cấu hình ON DELETE CASCADE/SET NULL trong CSDL)
        $conn->begin_transaction(); // Bắt đầu transaction để đảm bảo tính toàn vẹn

        try {
            // Xóa user_badges
            $sql_delete_user_badges = "DELETE FROM user_badges WHERE user_id = ?";
            $stmt_del_ub = $conn->prepare($sql_delete_user_badges);
            if ($stmt_del_ub) {
                $stmt_del_ub->bind_param("i", $user_id_to_delete);
                $stmt_del_ub->execute();
                $stmt_del_ub->close();
            } else {
                throw new Exception("Lỗi chuẩn bị xóa user_badges: " . $conn->error);
            }

            // Xóa game_sessions (hoặc set user_id = NULL nếu cột cho phép)
            // Giả sử chúng ta xóa luôn để đơn giản
            $sql_delete_game_sessions = "DELETE FROM game_sessions WHERE user_id = ?";
            $stmt_del_gs = $conn->prepare($sql_delete_game_sessions);
            if ($stmt_del_gs) {
                $stmt_del_gs->bind_param("i", $user_id_to_delete);
                $stmt_del_gs->execute();
                $stmt_del_gs->close();
            } else {
                throw new Exception("Lỗi chuẩn bị xóa game_sessions: " . $conn->error);
            }

            // Xóa người dùng
            $sql_delete_user = "DELETE FROM users WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete_user);
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $user_id_to_delete);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) {
                        $_SESSION['success_message_admin'] = "Đã xóa người dùng và các dữ liệu liên quan thành công!";
                        $conn->commit(); // Hoàn tất transaction
                    } else {
                        throw new Exception("Không tìm thấy người dùng để xóa.");
                    }
                } else {
                     throw new Exception("Lỗi khi xóa người dùng: " . $stmt_delete->error);
                }
                $stmt_delete->close();
            } else {
                throw new Exception("Lỗi chuẩn bị xóa người dùng: " . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback(); // Hoàn tác nếu có lỗi
            $_SESSION['error_message_admin'] = "Lỗi khi xóa người dùng: " . $e->getMessage();
            error_log("Lỗi xóa người dùng ID $user_id_to_delete: " . $e->getMessage());
        }

    } elseif ($user_id_to_delete == $current_admin_id) {
        $_SESSION['error_message_admin'] = "Bạn không thể tự xóa tài khoản admin của mình.";
    }
    header('Location: manage_users.php');
    exit;
}

// Lấy danh sách người dùng từ CSDL
$users_list = [];
$sql_users_list = "SELECT id, fullname, username, email, points, created_at, is_admin FROM users ORDER BY id ASC";

$result_users_list = $conn->query($sql_users_list);
if ($result_users_list) {
    while ($row = $result_users_list->fetch_assoc()) {
        $users_list[] = $row;
    }
    $result_users_list->free();
} else {
    error_log("Lỗi truy vấn danh sách người dùng: " . $conn->error);
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
    <title>Quản lý Người dùng - Admin Panel</title>
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
        .btn-delete[disabled] { background-color: #9CA3AF; cursor: not-allowed; opacity: 0.7;}
        .tag-admin { background-color: #FECACA; color: #991B1B; padding: 0.1rem 0.5rem; font-size: 0.75rem; border-radius: 0.25rem; font-weight: 500;}
        .tag-user { background-color: #DBEAFE; color: #1E40AF; padding: 0.1rem 0.5rem; font-size: 0.75rem; border-radius: 0.25rem; font-weight: 500;}

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
            <a href="manage_users.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-users mr-2"></i>Quản lý Người dùng</a>
            <a href="manage_characters.php" class="block py-2.5 px-4 rounded"><i class="fas fa-user-astronaut mr-2"></i>Quản lý Nhân vật</a>
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
            <a href="settings.php" class="block py-2.5 px-4 rounded"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div><h1 class="text-xl font-semibold">Quản lý Người dùng</h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-700">Danh sách Người dùng</h2>
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
                            <th>Họ Tên</th>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Điểm</th>
                            <th>Vai trò</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users_list)): ?>
                            <?php foreach ($users_list as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="font-medium text-gray-800"><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['points']); ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="tag-admin">Admin</span>
                                        <?php else: ?>
                                            <span class="tag-user">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($user['created_at']))); ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-sm btn-edit mr-1" title="Sửa thông tin người dùng"><i class="fas fa-edit"></i> Sửa</a>
                                        <?php if ($user['id'] != $current_admin_id): ?>
                                        <a href="manage_users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                           class="btn-sm btn-delete" 
                                           onclick="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa người dùng này không? Tất cả dữ liệu liên quan (lượt chơi, huy hiệu đã đạt) của người dùng này cũng sẽ bị XÓA VĨNH VIỄN. Hành động này không thể hoàn tác.');"
                                           title="Xóa người dùng">
                                           <i class="fas fa-trash"></i> Xóa
                                        </a>
                                        <?php else: ?>
                                            <button class="btn-sm btn-delete" disabled title="Không thể tự xóa tài khoản admin đang đăng nhập"><i class="fas fa-trash"></i> Xóa</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-gray-500 py-4">Chưa có người dùng nào.</td>
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
