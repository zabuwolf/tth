<?php
// game_includes/view_data_preparer.php
// Các biến $conn, $stage, $site_settings, $selected_grade_id, $selected_topic_id, $selected_character_id, $is_guest, $total_map_steps đã được định nghĩa.
// $_SESSION['game_state'] cũng đã được xử lý.

$game_display_info = null;
if (isset($_SESSION['game_state']) && ($stage === 'play_game' || $stage === 'game_over' || $stage === 'game_win')) {
    $game_display_info = $_SESSION['game_state'];
}

// Đặc biệt xử lý cho trường hợp hết câu hỏi thì chuyển stage
if ($stage === 'play_game' && isset($_SESSION['game_state']) && ($_SESSION['game_state']['game_active'] ?? false) === false) {
    if (isset($_SESSION['game_state']['current_step']) && $_SESSION['game_state']['current_step'] > ($total_map_steps ?? ($site_settings['default_questions_per_map'] ?? 10))) {
        $stage = 'game_win'; 
    } else {
        if (!isset($_GET['reason']) && !isset($_GET['final_score'])) { 
            $stage = 'game_over';
             // Đảm bảo message được set nếu chưa có, để view_game_play_area.php biết lý do
            if (!isset($_GET['message']) && isset($game_display_info['current_step'])) {
                 $_GET['message'] = 'game_ended_unexpectedly_step_' . $game_display_info['current_step'];
            }
        }
    }
    // Cập nhật lại game_display_info nếu stage thay đổi và session vẫn còn
    if(isset($_SESSION['game_state'])) {
        $game_display_info = $_SESSION['game_state']; 
    }
}


$final_score_from_url = isset($_GET['final_score']) ? (int)$_GET['final_score'] : null;
$game_message_code = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : null; 

$newly_awarded_badges_to_display = $_SESSION['newly_awarded_badges'] ?? [];
unset($_SESSION['newly_awarded_badges']); 

// --- Chuẩn bị dữ liệu cho Popup Kỹ năng Bị động ---
$passive_skill_to_announce_for_view = null; // Biến này sẽ được truyền cho views/game_play_area.php
$passive_skill_name_for_view = '';
$passive_skill_description_for_view = '';
$passive_skill_icon_class_for_view = 'fa-shield-alt'; // Default icon

// Kiểm tra xem có cần hiển thị popup kỹ năng bị động không
// Biến $_SESSION['show_passive_skill_popup'] được đặt trong play_game_logic.php khi game MỚI được tạo
if (isset($_SESSION['show_passive_skill_popup']) && $_SESSION['show_passive_skill_popup'] === true && $game_display_info) {
    $char_ability_code_passive = $game_display_info['special_ability_code'] ?? '';
    $char_name_passive = $game_display_info['character_name'] ?? 'Nhân vật';

    if ($char_ability_code_passive === 'KNIGHT_EXTRA_LIFE') {
        $passive_skill_name_for_view = $char_name_passive;
        $passive_skill_description_for_view = "Với tinh thần dũng cảm, bạn nhận thêm 1 mạng sống khi bắt đầu cuộc phiêu lưu!";
        $passive_skill_icon_class_for_view = 'fa-heart'; 
        $passive_skill_to_announce_for_view = true;
    } elseif ($char_ability_code_passive === 'TIME_PRINCE_BONUS') {
        $passive_skill_name_for_view = $char_name_passive;
        $passive_skill_description_for_view = "Bạn được ban tặng thêm 30 giây quý giá để hoàn thành thử thách này!";
        $passive_skill_icon_class_for_view = 'fa-hourglass-half'; 
        $passive_skill_to_announce_for_view = true;
    }
    // Thêm các case khác cho kỹ năng bị động ở đây

    if($passive_skill_to_announce_for_view) {
        unset($_SESSION['show_passive_skill_popup']); // Xóa session sau khi đã đọc để không hiện lại
    }
}
// --- Kết thúc chuẩn bị dữ liệu cho Popup Kỹ năng Bị động ---


// Chuẩn bị URL cho nút skill (nếu có)
$skill_button_url = '';
if ($stage === 'play_game' && $selected_grade_id && $selected_topic_id && $selected_character_id) {
    $skill_button_base_params = "stage=play_game&grade_id={$selected_grade_id}&topic_id={$selected_topic_id}&character_id={$selected_character_id}";
    if ($is_guest) { $skill_button_base_params .= "&action=guest_play"; }
    $skill_button_url = "game.php?" . $skill_button_base_params . "&game_action=use_wizard_skill";
}

// Chuẩn bị các biến cho JavaScript
$map_end_timestamp_for_js = $game_display_info['map_end_timestamp'] ?? 0;
$is_game_active_for_js = (isset($game_display_info['game_active']) && $game_display_info['game_active'] && $stage === 'play_game') ? 'true' : 'false';
// if ($stage !== 'play_game') $is_game_active_for_js = 'false'; // Đã gộp vào dòng trên

$total_time_seconds_for_js = $game_display_info['map_total_time_seconds'] ?? ($site_settings['total_time_per_map_seconds'] ?? 600);


$gs_grade_id_js = $selected_grade_id ?? $game_display_info['grade_id'] ?? '';
$gs_topic_id_js = $selected_topic_id ?? $game_display_info['topic_id'] ?? '';
$gs_char_id_js = $selected_character_id ?? $game_display_info['character_id'] ?? '';
$gs_score_js = $game_display_info['current_score'] ?? 0;

$game_over_url_js = "game.php?stage=game_over&grade_id=" . $gs_grade_id_js .
                    "&topic_id=" . $gs_topic_id_js .
                    "&character_id=" . $gs_char_id_js .
                    "&final_score=" . $gs_score_js . 
                    "&reason=time_out";
if($is_guest) $game_over_url_js .= "&action=guest_play";

?>
