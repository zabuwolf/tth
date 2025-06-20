<?php
if (session_status() == PHP_SESSION_NONE) { 
    session_start();
}
require_once 'config/db_config.php';
require_once 'includes/game_functions.php'; 

$conn = connect_db();
if ($conn === null) {
    error_log("CRITICAL ERROR: game_process_answer.php - Không thể kết nối đến cơ sở dữ liệu.");
    $_SESSION['game_error_message_critical'] = "Hệ thống đang gặp sự cố, vui lòng thử lại sau ít phút.";
    $fallback_guest_param = (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest']) ? '&action=guest_play' : '';
    header('Location: game.php?stage=select_grade&error=system' . $fallback_guest_param);
    exit;
}

$site_settings_raw = [];
$sql_settings = "SELECT setting_key, setting_value FROM site_settings";
$result_settings = $conn->query($sql_settings);
if ($result_settings) {
    while ($row_setting = $result_settings->fetch_assoc()) {
        $site_settings_raw[$row_setting['setting_key']] = $row_setting['setting_value'];
    }
    $result_settings->free();
} else {
    error_log("Lỗi truy vấn site_settings trong game_process_answer.php: " . $conn->error);
}

$site_settings = [
    'points_per_correct_answer' => isset($site_settings_raw['points_per_correct_answer']) ? (int)$site_settings_raw['points_per_correct_answer'] : 1,
    'default_questions_per_map' => isset($site_settings_raw['default_questions_per_map']) ? (int)$site_settings_raw['default_questions_per_map'] : 10,
    'daily_point_limit_from_game' => isset($site_settings_raw['daily_point_limit_from_game']) ? (int)$site_settings_raw['daily_point_limit_from_game'] : 30,
    'total_time_per_map_seconds' => isset($site_settings_raw['total_time_per_map_seconds']) ? (int)$site_settings_raw['total_time_per_map_seconds'] : 600, 
    'child_prodigy_streak_threshold' => isset($site_settings_raw['child_prodigy_streak_threshold']) ? (int)$site_settings_raw['child_prodigy_streak_threshold'] : 5, 
    'child_prodigy_bonus_points' => isset($site_settings_raw['child_prodigy_bonus_points']) ? (int)$site_settings_raw['child_prodigy_bonus_points'] : 1, 
];

if ($_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_SESSION['game_state']) ||
    !isset($_SESSION['game_state']['game_active']) ||
    !$_SESSION['game_state']['game_active']) {

    close_db_connection($conn);
    $fallback_guest_param_invalid = (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest']) ? '&action=guest_play' : '';
    header('Location: game.php?stage=select_grade&error=invalid_access_or_game_ended' . $fallback_guest_param_invalid);
    exit;
}

$game_state = &$_SESSION['game_state'];

// Chuẩn bị map_start_time để truyền vào record_game_session
$map_start_timestamp = $game_state['map_start_time'] ?? time(); // Unix timestamp
$map_start_time_iso_for_db = date("Y-m-d H:i:s", $map_start_timestamp);

if (isset($game_state['map_end_timestamp']) &&
    $site_settings['total_time_per_map_seconds'] > 0 && 
    time() > $game_state['map_end_timestamp']) {

    $game_state['game_active'] = false; 
    $user_id_for_db_timeout = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : null;

    record_game_session(
        $conn, $user_id_for_db_timeout, $game_state['grade_id'], $game_state['character_id'],
        $game_state['current_score'], false, $game_state['topic_id'], $map_start_time_iso_for_db
    );
    close_db_connection($conn);
    $timeout_redirect_params = '&grade_id=' . urlencode($game_state['grade_id']) .
                               '&topic_id=' . urlencode($game_state['topic_id']) .
                               '&character_id=' . urlencode($game_state['character_id']);
    if (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest'] === true) {
        $timeout_redirect_params .= "&action=guest_play";
    }
    header('Location: game.php?stage=game_over' . $timeout_redirect_params . '&final_score=' . $game_state['current_score'] . '&reason=time_out');
    exit;
}

$submitted_answer_original_key = isset($_POST['answer']) ? (int)$_POST['answer'] : null;
$question_id_from_form = isset($_POST['question_id']) ? (int)$_POST['question_id'] : null;
$topic_id_from_form = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;

$total_map_steps = $site_settings['default_questions_per_map'];
$user_id_for_db = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : null;

if ($submitted_answer_original_key === null ||
    $question_id_from_form === null ||
    $topic_id_from_form === null ||
    $question_id_from_form != ($game_state['current_question_db_id'] ?? null) || 
    $topic_id_from_form != ($game_state['topic_id'] ?? null) ) {                 

    close_db_connection($conn);
    $gs_grade_id_err = $game_state['grade_id'] ?? $_POST['grade_id'] ?? ''; 
    $gs_topic_id_err = $game_state['topic_id'] ?? $topic_id_from_form ?? '';
    $gs_char_id_err = $game_state['character_id'] ?? $_POST['character_id'] ?? '';
    $redirect_guest_param_err = (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest']) ? "&action=guest_play" : "";
    header('Location: game.php?stage=play_game&grade_id=' . urlencode($gs_grade_id_err) .
           '&topic_id=' . urlencode($gs_topic_id_err) .
           '&character_id=' . urlencode($gs_char_id_err) .
           $redirect_guest_param_err . '&error=invalid_submission');
    exit;
}

$correct_option_key = $game_state['current_correct_option_key_original'] ?? null;
$answered_correctly = ($submitted_answer_original_key !== null && $correct_option_key !== null && $submitted_answer_original_key == $correct_option_key);

$redirect_params = '&grade_id=' . urlencode($game_state['grade_id']) .
                   '&topic_id=' . urlencode($game_state['topic_id']) .
                   '&character_id=' . urlencode($game_state['character_id']);
if (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest']) {
    $redirect_params .= "&action=guest_play";
}

$game_state['wizard_skill_used_this_question'] = false;
$game_state['removed_option_keys_for_current_q'] = [];

if ($answered_correctly) {
    $points_for_this_question_raw = $site_settings['points_per_correct_answer'];
    $game_state['current_score'] += $points_for_this_question_raw; 

    $points_earned_today_after_this_answer = 0; 
    $today_date_for_limit = date("Y-m-d"); 
    $daily_limit = $site_settings['daily_point_limit_from_game']; 

    if ($user_id_for_db !== null) { 
        $current_points_earned_today = 0;

        $sql_get_daily_points = "SELECT points_earned_today FROM daily_earned_points WHERE user_id = ? AND earned_date = ?";
        $stmt_get_daily = $conn->prepare($sql_get_daily_points);
        if ($stmt_get_daily) {
            $stmt_get_daily->bind_param("is", $user_id_for_db, $today_date_for_limit);
            $stmt_get_daily->execute();
            $result_daily = $stmt_get_daily->get_result();
            if ($daily_row = $result_daily->fetch_assoc()) {
                $current_points_earned_today = (int)$daily_row['points_earned_today'];
            }
            $stmt_get_daily->close();
        }
        
        $points_credited_for_answer = 0;
        if ($current_points_earned_today < $daily_limit) {
            $points_can_still_earn = $daily_limit - $current_points_earned_today;
            $points_credited_for_answer = min($points_for_this_question_raw, $points_can_still_earn);

            if ($points_credited_for_answer > 0) {
                update_user_points($conn, $user_id_for_db, $points_credited_for_answer); 
                $points_earned_today_after_this_answer = $current_points_earned_today + $points_credited_for_answer;
                $sql_upsert_daily = "INSERT INTO daily_earned_points (user_id, earned_date, points_earned_today) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE points_earned_today = ?";
                $stmt_upsert_daily = $conn->prepare($sql_upsert_daily);
                if ($stmt_upsert_daily) {
                    $stmt_upsert_daily->bind_param("isii", $user_id_for_db, $today_date_for_limit, $points_earned_today_after_this_answer, $points_earned_today_after_this_answer);
                    $stmt_upsert_daily->execute();
                    $stmt_upsert_daily->close();
                }
            } else {
                 $points_earned_today_after_this_answer = $current_points_earned_today;
            }
        } else {
             $_SESSION['daily_limit_reached_message'] = "Bạn đã đạt giới hạn " . $daily_limit . " điểm kiếm được từ game hôm nay!";
             $points_earned_today_after_this_answer = $current_points_earned_today; 
        }
    } else { 
        $points_earned_today_after_this_answer = 0; 
    }

    if (isset($game_state['special_ability_code']) && $game_state['special_ability_code'] === 'CHILD_PRODIGY_STREAK') {
        $game_state['consecutive_correct_answers'] = ($game_state['consecutive_correct_answers'] ?? 0) + 1;
        error_log("Thần Đồng Nhí: Trả lời đúng, consecutive_correct_answers = " . $game_state['consecutive_correct_answers']); 
    }

    $game_state['current_step']++; 
    $game_state['current_question_index_in_map']++; 
    unset($_SESSION['show_motivation_popup']); 

    if ($game_state['current_step'] > $total_map_steps) { 
        $game_state['game_active'] = false; 
        error_log("Thắng game! Current step: " . $game_state['current_step'] . ", Total map steps: " . $total_map_steps); 

        if (isset($game_state['special_ability_code']) && 
            $game_state['special_ability_code'] === 'CHILD_PRODIGY_STREAK' && 
            ($game_state['consecutive_correct_answers'] ?? 0) >= $site_settings['child_prodigy_streak_threshold']) {
            
            $prodigy_bonus_amount = $site_settings['child_prodigy_bonus_points'];
            error_log("Thần Đồng Nhí: Đạt ngưỡng streak! Bonus: " . $prodigy_bonus_amount); 
            $game_state['current_score'] += $prodigy_bonus_amount; 
            $_SESSION['prodigy_bonus_applied'] = true; 
            error_log("Thần Đồng Nhí: Đã áp dụng bonus, current_score = " . $game_state['current_score']); 


            if ($user_id_for_db !== null) {
                $current_total_daily_points = $points_earned_today_after_this_answer; 

                if ($current_total_daily_points < $daily_limit) {
                    $points_can_still_earn_for_bonus = $daily_limit - $current_total_daily_points;
                    $prodigy_bonus_points_credited_to_db = min($prodigy_bonus_amount, $points_can_still_earn_for_bonus);

                    if ($prodigy_bonus_points_credited_to_db > 0) {
                        update_user_points($conn, $user_id_for_db, $prodigy_bonus_points_credited_to_db);
                        $final_daily_points_after_all_bonus = $current_total_daily_points + $prodigy_bonus_points_credited_to_db;
                        
                        $sql_upsert_daily_bonus_win = "INSERT INTO daily_earned_points (user_id, earned_date, points_earned_today) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE points_earned_today = ?";
                        $stmt_upsert_daily_bonus_win = $conn->prepare($sql_upsert_daily_bonus_win);
                        if ($stmt_upsert_daily_bonus_win) {
                            $stmt_upsert_daily_bonus_win->bind_param("isii", $user_id_for_db, $today_date_for_limit, $final_daily_points_after_all_bonus, $final_daily_points_after_all_bonus);
                            $stmt_upsert_daily_bonus_win->execute();
                            $stmt_upsert_daily_bonus_win->close();
                            error_log("Thần Đồng Nhí: Đã cập nhật daily_earned_points với bonus: " . $prodigy_bonus_points_credited_to_db); 
                        }
                    }
                }
            }
        } else {
             error_log("Thần Đồng Nhí: Không đạt ngưỡng streak hoặc không phải nhân vật Thần Đồng. Consecutive: " . ($game_state['consecutive_correct_answers'] ?? 0) . ", Threshold: " . $site_settings['child_prodigy_streak_threshold']); 
        }

        $newly_awarded_badges = [];
        if ($user_id_for_db !== null) {
            $newly_awarded_badges = check_and_award_badges($conn, $user_id_for_db); 
        }
        record_game_session($conn, $user_id_for_db, $game_state['grade_id'], $game_state['character_id'], $game_state['current_score'], true, $game_state['topic_id'], $map_start_time_iso_for_db); 

        if (!empty($newly_awarded_badges)) {
            $_SESSION['newly_awarded_badges'] = $newly_awarded_badges;
        }
        close_db_connection($conn);
        header('Location: game.php?stage=game_win' . $redirect_params . '&final_score=' . $game_state['current_score']);
        exit;
    }
    elseif ($game_state['current_question_index_in_map'] >= count($game_state['question_ids_for_map'])) {
        $game_state['game_active'] = false; 
        record_game_session($conn, $user_id_for_db, $game_state['grade_id'], $game_state['character_id'], $game_state['current_score'], false, $game_state['topic_id'], $map_start_time_iso_for_db);
        close_db_connection($conn);
        header('Location: game.php?stage=game_over' . $redirect_params . '&final_score=' . $game_state['current_score'] . '&message=ran_out_of_questions');
        exit;
    }

} else { // Trả lời sai
    $game_state['current_lives']--; 
    $_SESSION['show_motivation_popup'] = true; 
    $game_state['current_question_index_in_map']++; 
    $game_state['consecutive_correct_answers'] = 0; 
    error_log("Thần Đồng Nhí: Trả lời sai, reset consecutive_correct_answers."); 


    if ($game_state['current_lives'] <= 0) {
        $game_state['game_active'] = false; 
        record_game_session($conn, $user_id_for_db, $game_state['grade_id'], $game_state['character_id'], $game_state['current_score'], false, $game_state['topic_id'], $map_start_time_iso_for_db);
        close_db_connection($conn);
        header('Location: game.php?stage=game_over' . $redirect_params . '&final_score=' . $game_state['current_score'] . '&reason=no_lives'); 
        exit;
    }
    elseif ($game_state['current_question_index_in_map'] >= count($game_state['question_ids_for_map'])) {
        $game_state['game_active'] = false; 
        record_game_session($conn, $user_id_for_db, $game_state['grade_id'], $game_state['character_id'], $game_state['current_score'], false, $game_state['topic_id'], $map_start_time_iso_for_db);
        close_db_connection($conn);
        header('Location: game.php?stage=game_over' . $redirect_params . '&final_score=' . $game_state['current_score'] . '&message=ran_out_of_questions_on_wrong');
        exit;
    }
}

close_db_connection($conn);
header('Location: game.php?stage=play_game' . $redirect_params);
exit;
?>