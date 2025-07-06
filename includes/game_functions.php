<?php
// includes/game_functions.php

/**
 * Cập nhật điểm cho người dùng đã đăng nhập.
 *
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int $user_id ID của người dùng.
 * @param int $points_to_add Số điểm cần cộng thêm.
 * @return bool True nếu cập nhật thành công, False nếu thất bại.
 */
function update_user_points(mysqli $conn, int $user_id, int $points_to_add): bool {
    if ($user_id <= 0 || $points_to_add < 0) { 
        return false; 
    }
    if ($points_to_add == 0) return true; 

    $sql = "UPDATE `users` SET `points` = `points` + ? WHERE `id` = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (update_user_points): " . $conn->error);
        return false;
    }

    $stmt->bind_param("ii", $points_to_add, $user_id);

    if (!$stmt->execute()) {
        error_log("Lỗi thực thi SQL (update_user_points): " . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

/**
 * Ghi lại thông tin của một lượt chơi (game session) vào cơ sở dữ liệu.
 *
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int|null $user_id ID của người dùng (NULL nếu là khách).
 * @param int $grade_id ID của khối lớp.
 * @param int $character_id ID của nhân vật.
 * @param int $score_achieved Điểm số đạt được.
 * @param bool $is_completed Trạng thái hoàn thành màn chơi (True nếu thắng, False nếu thua).
 * @param int|null $topic_id ID của chủ đề (NULL nếu không áp dụng).
 * @return bool True nếu ghi thành công, False nếu thất bại.
 */
function record_game_session(mysqli $conn, ?int $user_id, int $grade_id, int $character_id, int $score_achieved, bool $is_completed, ?int $topic_id): bool {
    $sql = "INSERT INTO `game_sessions` (`user_id`, `grade_id`, `topic_id`, `character_id`, `score_achieved`, `is_completed`, `start_time`, `end_time`) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"; 
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Lỗi chuẩn bị câu lệnh SQL (record_game_session): " . $conn->error);
        return false;
    }
    
    $completed_int = (int)$is_completed; 
    $stmt->bind_param("iiiiis", $user_id, $grade_id, $topic_id, $character_id, $score_achieved, $completed_int);

    if (!$stmt->execute()) {
        error_log("Lỗi thực thi SQL (record_game_session): " . $stmt->error . " | UserID: " . ($user_id ?? 'NULL') . ", GradeID: " . $grade_id . ", TopicID: " . ($topic_id ?? 'NULL'));
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}


/**
 * Kiểm tra và trao huy hiệu cho người dùng dựa trên tổng điểm.
 *
 * @param mysqli $conn Đối tượng kết nối CSDL.
 * @param int $user_id ID của người dùng.
 * @return array Danh sách các huy hiệu mới nhận được (mỗi phần tử là một mảng thông tin huy hiệu).
 */
function check_and_award_badges(mysqli $conn, int $user_id): array {
    if ($user_id <= 0) {
        return [];
    }

    $newly_awarded_badges = [];

    // 1. Lấy tổng điểm hiện tại của người dùng
    $current_points = 0;
    $sql_get_points = "SELECT points FROM users WHERE id = ?";
    $stmt_points = $conn->prepare($sql_get_points);
    if ($stmt_points) {
        $stmt_points->bind_param("i", $user_id);
        $stmt_points->execute();
        $result_points = $stmt_points->get_result();
        if ($user_data = $result_points->fetch_assoc()) {
            $current_points = (int)$user_data['points'];
        }
        $stmt_points->close();
    } else {
        error_log("Lỗi chuẩn bị SQL lấy điểm người dùng (check_and_award_badges): " . $conn->error);
        return []; // Không thể tiếp tục nếu không lấy được điểm
    }

    if ($current_points <= 0) { // Không có điểm thì không có huy hiệu theo điểm
        return [];
    }

    // 2. Lấy danh sách tất cả các huy hiệu có thể đạt được bằng điểm,
    //    và loại trừ những huy hiệu người dùng đã có.
    $sql_badges_to_check = "SELECT b.id, b.name, b.image_url, b.points_required 
                            FROM badges b
                            LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                            WHERE b.points_required > 0 AND ub.badge_id IS NULL"; // Chỉ lấy huy hiệu chưa có
    
    $stmt_badges = $conn->prepare($sql_badges_to_check);
    if (!$stmt_badges) {
        error_log("Lỗi chuẩn bị SQL lấy huy hiệu cần kiểm tra (check_and_award_badges): " . $conn->error);
        return [];
    }

    $stmt_badges->bind_param("i", $user_id);
    $stmt_badges->execute();
    $result_badges = $stmt_badges->get_result();

    while ($badge = $result_badges->fetch_assoc()) {
        if ($current_points >= (int)$badge['points_required']) {
            // Người dùng đủ điều kiện nhận huy hiệu này
            $sql_award_badge = "INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE earned_at = NOW()"; // Đề phòng trường hợp hi hữu
            $stmt_award = $conn->prepare($sql_award_badge);
            if ($stmt_award) {
                $stmt_award->bind_param("ii", $user_id, $badge['id']);
                if ($stmt_award->execute()) {
                    if ($stmt_award->affected_rows > 0) { // Chỉ thêm vào nếu insert mới thành công
                        $newly_awarded_badges[] = $badge; // Thêm huy hiệu vào danh sách mới nhận
                    }
                } else {
                    error_log("Lỗi thực thi SQL trao huy hiệu (ID: {$badge['id']}) cho user (ID: {$user_id}): " . $stmt_award->error);
                }
                $stmt_award->close();
            } else {
                 error_log("Lỗi chuẩn bị SQL trao huy hiệu: " . $conn->error);
            }
        }
    }
    $stmt_badges->close();

    return $newly_awarded_badges;
}

?>