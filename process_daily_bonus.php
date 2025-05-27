<?php
// File: process_daily_bonus.php
// Nhiệm vụ: Xử lý logic nhận thưởng hàng ngày, cập nhật CSDL.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once 'config/db_config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: Bạn chưa đăng nhập.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$points_to_award = 1; // Số điểm thưởng

$conn = connect_db();
if ($conn === null) {
    error_log("Process Daily Bonus: Cannot connect to database for user_id: " . $user_id);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: Không thể kết nối đến cơ sở dữ liệu.']);
    exit;
}

$today_date_string = date("Y-m-d");
$can_claim_today = true; 
$message = '';
$new_total_points_for_response = null; // Sẽ chứa điểm mới để trả về client

// Bắt đầu transaction
if (!$conn->begin_transaction()) {
    error_log("Process Daily Bonus: Failed to begin transaction for user_id: " . $user_id . " - Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: Không thể bắt đầu giao dịch.']);
    close_db_connection($conn); // Đóng kết nối trước khi exit
    exit;
}

try {
    // 1. Kiểm tra xem người dùng đã nhận thưởng hôm nay chưa (với row lock)
    $sql_check = "SELECT points, last_daily_bonus_claimed_date FROM users WHERE id = ? FOR UPDATE";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception("Lỗi chuẩn bị câu lệnh kiểm tra: " . $conn->error);
    }
    $stmt_check->bind_param("i", $user_id);
    if (!$stmt_check->execute()) {
        throw new Exception("Lỗi thực thi câu lệnh kiểm tra: " . $stmt_check->error);
    }
    $result_check = $stmt_check->get_result();
    $user_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$user_data) {
        throw new Exception("Lỗi: Không tìm thấy thông tin người dùng (ID: {$user_id}).");
    }

    // Gán điểm hiện tại để tính toán
    $current_points_from_db = (int)($user_data['points'] ?? 0);

    if (!empty($user_data['last_daily_bonus_claimed_date']) && $user_data['last_daily_bonus_claimed_date'] == $today_date_string) {
        $can_claim_today = false;
        $message = 'Bạn đã nhận thưởng đăng nhập hôm nay rồi!';
        $new_total_points_for_response = $current_points_from_db; // Trả về điểm hiện tại nếu đã nhận
    }

    if ($can_claim_today) {
        // 2. Cộng điểm và cập nhật ngày nhận thưởng
        $new_total_points_for_response = $current_points_from_db + $points_to_award;
        
        $sql_update_user = "UPDATE users SET points = ?, last_daily_bonus_claimed_date = ? WHERE id = ?";
        $stmt_update_user = $conn->prepare($sql_update_user);
        if (!$stmt_update_user) {
            throw new Exception("Lỗi chuẩn bị câu lệnh cập nhật người dùng: " . $conn->error);
        }
        $stmt_update_user->bind_param("isi", $new_total_points_for_response, $today_date_string, $user_id);
        
        if (!$stmt_update_user->execute()) {
            throw new Exception("Lỗi thực thi cập nhật người dùng: " . $stmt_update_user->error);
        }
        
        if ($stmt_update_user->affected_rows > 0) {
            $message = "Chúc mừng! Bạn đã nhận được +{$points_to_award} điểm thưởng đăng nhập hàng ngày.";
            // Cập nhật điểm trong session
            $_SESSION['points'] = $new_total_points_for_response; 
        } else {
            // Nếu không có dòng nào được cập nhật, có thể là do race condition dù đã có FOR UPDATE
            // Hoặc ID người dùng không tồn tại (đã được kiểm tra ở trên)
            // Trong trường hợp này, coi như đã có lỗi và không cho nhận thưởng
            $can_claim_today = false; // Đặt lại để response success là false
            $message = "Lỗi: Không thể cập nhật điểm thưởng. Vui lòng thử lại.";
            // Không throw exception ở đây để vẫn commit transaction (nếu không có lỗi khác)
            // và trả về thông báo lỗi cho người dùng.
            // Điểm sẽ không bị thay đổi so với lúc SELECT FOR UPDATE.
            $new_total_points_for_response = $current_points_from_db;
        }
        $stmt_update_user->close();
    }

    // Hoàn tất transaction
    if (!$conn->commit()) {
         throw new Exception("Lỗi commit transaction: " . $conn->error);
    }
    
    echo json_encode([
        'success' => $can_claim_today, 
        'message' => $message,
        'new_points' => $new_total_points_for_response 
    ]);

} catch (Exception $e) {
    $conn->rollback(); 
    error_log("Daily Bonus Processing Error: " . $e->getMessage() . " for user_id: " . $user_id . " at line " . $e->getLine() . " in file " . $e->getFile());
    echo json_encode(['success' => false, 'message' => 'Đã có lỗi hệ thống xảy ra trong quá trình nhận thưởng. Vui lòng thử lại sau.']);
} finally {
    if ($conn) {
        close_db_connection($conn);
    }
}
exit;
?>
