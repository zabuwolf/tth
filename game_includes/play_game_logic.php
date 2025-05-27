<?php
// game_includes/play_game_logic.php
// Các biến $conn, $stage, $selected_grade_id, $selected_topic_id, $selected_character_id, 
// $game_action, $site_settings, $total_map_steps, &$current_question_data, &$character_info_for_game đã được định nghĩa.
global $is_guest; 

if ($stage === 'play_game' && $selected_grade_id && $selected_topic_id && $selected_character_id) {
    $force_new_game = true; 
    
    if (isset($_SESSION['game_state'])) {
        if (($_SESSION['game_state']['game_active'] ?? false) === true && 
            isset($_SESSION['game_state']['map_end_timestamp']) && 
            ($_SESSION['game_state']['map_total_time_seconds'] ?? 0) > 0 && 
            $_SESSION['game_state']['map_end_timestamp'] < time()) {
            $_SESSION['game_state']['game_active'] = false;
            error_log("play_game_logic: Corrected game_active to false due to passed map_end_timestamp for session.");
        }

        if (($_SESSION['game_state']['game_active'] ?? false) === true) { 
            if ($_SESSION['game_state']['grade_id'] == $selected_grade_id &&
                $_SESSION['game_state']['topic_id'] == $selected_topic_id &&
                $_SESSION['game_state']['character_id'] == $selected_character_id) {
                $force_new_game = false; 
            } else {
                unset($_SESSION['game_state']);
            }
        }
    }

    if ($force_new_game || !isset($_SESSION['game_state']) || ($_SESSION['game_state']['game_active'] ?? false) === false) {
        $current_map_total_time_init = $site_settings['total_time_per_map_seconds'] ?? 600; 

        $_SESSION['game_state'] = [
            'grade_id' => $selected_grade_id,
            'topic_id' => $selected_topic_id,
            'character_id' => $selected_character_id,
            'current_score' => 0,
            'current_step' => 1, 
            'question_ids_for_map' => [], 
            'current_question_index_in_map' => 0, 
            'game_active' => true, 
            'wizard_skill_uses_left_this_map' => 0, 
            'wizard_skill_used_this_question' => false, 
            'removed_option_keys_for_current_q' => [], 
            'consecutive_correct_answers' => 0, 
            'map_start_time' => time(), 
            'character_name' => '',
            'character_image_url' => '',
            'special_ability_code' => '',
            'current_lives' => 0,
            'map_total_time_seconds' => $current_map_total_time_init, 
            'map_end_timestamp' => 0, 
        ];

        // Đặt lại cờ hiển thị popup kỹ năng bị động cho game mới
        $_SESSION['show_passive_skill_popup'] = false; // Mặc định là false

        $sql_char_info = "SELECT id, name, image_url, base_lives, special_ability_code FROM characters WHERE id = ? LIMIT 1";
        $stmt_char = $conn->prepare($sql_char_info);
        if ($stmt_char) {
            $stmt_char->bind_param("i", $selected_character_id);
            $stmt_char->execute();
            $result_char = $stmt_char->get_result();
            $character_data_from_db = $result_char->fetch_assoc(); 
            
            if ($character_data_from_db) {
                $_SESSION['game_state']['character_name'] = $character_data_from_db['name'];
                $_SESSION['game_state']['character_image_url'] = $character_data_from_db['image_url'];
                $_SESSION['game_state']['special_ability_code'] = $character_data_from_db['special_ability_code'];
                $character_info_for_game = $character_data_from_db; // Gán cho biến global để view sử dụng

                $base_lives = (int)$character_data_from_db['base_lives'];
                if ($character_data_from_db['special_ability_code'] === 'KNIGHT_EXTRA_LIFE') {
                    $_SESSION['game_state']['current_lives'] = $base_lives + 1;
                    $_SESSION['show_passive_skill_popup'] = true; // Kích hoạt popup cho Hiệp Sĩ
                } else {
                    $_SESSION['game_state']['current_lives'] = $base_lives;
                }

                if ($character_data_from_db['special_ability_code'] === 'WIZARD_REMOVE_WRONG') {
                    $_SESSION['game_state']['wizard_skill_uses_left_this_map'] = 1; 
                }
                if ($character_data_from_db['special_ability_code'] === 'TIME_PRINCE_BONUS') {
                    $_SESSION['game_state']['map_total_time_seconds'] += 30; 
                    $_SESSION['show_passive_skill_popup'] = true; // Kích hoạt popup cho Hoàng Tử Thời Gian
                }
                // Thêm các kiểm tra kỹ năng bị động khác ở đây và đặt $_SESSION['show_passive_skill_popup'] = true;

                $_SESSION['game_state']['map_end_timestamp'] = ($_SESSION['game_state']['map_start_time']) + $_SESSION['game_state']['map_total_time_seconds'];

            } else {
                $_SESSION['game_state']['game_active'] = false; 
                error_log("Không tìm thấy nhân vật ID: $selected_character_id trong play_game_logic.php (khởi tạo game mới)");
            }
            $stmt_char->close();
        } else {
            $_SESSION['game_state']['game_active'] = false; 
            error_log("Lỗi prepare SQL lấy thông tin nhân vật trong play_game_logic.php (khởi tạo game mới): " . $conn->error);
        }
        
        if ($_SESSION['game_state']['game_active']) { 
            $questions_buffer_factor = 3; 
            $current_total_map_steps = $total_map_steps ?? ($site_settings['default_questions_per_map'] ?? 10);
            $questions_to_fetch = $current_total_map_steps * $questions_buffer_factor; 

            $sql_questions = "SELECT id FROM questions WHERE topic_id = ? AND grade_id = ? ORDER BY RAND() LIMIT ?";
            $stmt_q = $conn->prepare($sql_questions);
            if ($stmt_q) {
                $stmt_q->bind_param("iii", $selected_topic_id, $selected_grade_id, $questions_to_fetch);
                $stmt_q->execute();
                $result_q = $stmt_q->get_result();
                while ($row_q = $result_q->fetch_assoc()) {
                    $_SESSION['game_state']['question_ids_for_map'][] = $row_q['id'];
                }
                $stmt_q->close();
                
                $actual_questions_loaded = count($_SESSION['game_state']['question_ids_for_map']);

                if ($actual_questions_loaded == 0) {
                    $_SESSION['game_state']['game_active'] = false; 
                    error_log("Không có câu hỏi nào cho topic $selected_topic_id, grade $selected_grade_id trong play_game_logic.php (khởi tạo game mới).");
                } elseif ($actual_questions_loaded < $current_total_map_steps) {
                    error_log("Cảnh báo QUAN TRỌNG: Số câu hỏi thực tế tải được ({$actual_questions_loaded}) ít hơn số bước yêu cầu của map ({$current_total_map_steps}) cho topic {$selected_topic_id}, grade {$selected_grade_id}. Người chơi có thể không thể thắng.");
                } elseif ($actual_questions_loaded < $questions_to_fetch) {
                     error_log("Cảnh báo: Số câu hỏi thực tế tải được ({$actual_questions_loaded}) ít hơn số lượng dự kiến ({$questions_to_fetch}) nhưng vẫn nhiều hơn hoặc bằng số bước map. Có thể giới hạn số câu hỏi trong DB.");
                }
            } else {
                $_SESSION['game_state']['game_active'] = false; 
                error_log("Lỗi prepare SQL lấy câu hỏi trong play_game_logic.php (khởi tạo game mới): " . $conn->error);
            }
        }

    } else { // Tiếp tục game cũ
        if (!isset($_SESSION['game_state']['character_image_url']) || empty($character_info_for_game) ) {
            $sql_char_info_resume = "SELECT id, name, image_url, base_lives, special_ability_code FROM characters WHERE id = ? LIMIT 1";
            $stmt_char_resume = $conn->prepare($sql_char_info_resume);
            if ($stmt_char_resume) {
                $stmt_char_resume->bind_param("i", $_SESSION['game_state']['character_id']);
                $stmt_char_resume->execute();
                $result_char_resume = $stmt_char_resume->get_result();
                if ($char_data_resume = $result_char_resume->fetch_assoc()) {
                    $_SESSION['game_state']['character_name'] = $char_data_resume['name']; 
                    $_SESSION['game_state']['character_image_url'] = $char_data_resume['image_url'];
                    $_SESSION['game_state']['special_ability_code'] = $char_data_resume['special_ability_code'];
                    $character_info_for_game = $char_data_resume;
                }
                $stmt_char_resume->close();
            }
        } else {
             $character_info_for_game = [
                'id' => $_SESSION['game_state']['character_id'],
                'name' => $_SESSION['game_state']['character_name'],
                'image_url' => $_SESSION['game_state']['character_image_url'],
                'special_ability_code' => $_SESSION['game_state']['special_ability_code'],
            ];
        }

        if (!isset($_SESSION['game_state']['map_end_timestamp']) || $_SESSION['game_state']['map_end_timestamp'] == 0 ||
            !isset($_SESSION['game_state']['map_start_time']) || !isset($_SESSION['game_state']['map_total_time_seconds']) ) {
            
            $resume_map_total_time = $_SESSION['game_state']['map_total_time_seconds'] ?? ($site_settings['total_time_per_map_seconds'] ?? 600);
            $initial_total_time = $site_settings['total_time_per_map_seconds'] ?? 600;

            if (($_SESSION['game_state']['special_ability_code'] ?? null) === 'TIME_PRINCE_BONUS') {
                if ($resume_map_total_time == $initial_total_time) { // Chỉ cộng nếu chưa được cộng
                     $resume_map_total_time += 30;
                }
            }
            $_SESSION['game_state']['map_total_time_seconds'] = $resume_map_total_time;
            $_SESSION['game_state']['map_start_time'] = $_SESSION['game_state']['map_start_time'] ?? time(); 
            $_SESSION['game_state']['map_end_timestamp'] = $_SESSION['game_state']['map_start_time'] + $_SESSION['game_state']['map_total_time_seconds'];
        }
    }

    if ($game_action === 'use_wizard_skill' &&
        isset($_SESSION['game_state']['special_ability_code']) && $_SESSION['game_state']['special_ability_code'] === 'WIZARD_REMOVE_WRONG' &&
        isset($_SESSION['game_state']['wizard_skill_uses_left_this_map']) && $_SESSION['game_state']['wizard_skill_uses_left_this_map'] > 0 &&
        ($_SESSION['game_state']['wizard_skill_used_this_question'] ?? false) === false &&
        !empty($_SESSION['game_state']['shuffled_options']) 
    ) {
        $options_to_filter = $_SESSION['game_state']['shuffled_options'];
        $correct_key = $_SESSION['game_state']['current_correct_option_key_original']; 
        $wrong_options_keys = []; 

        foreach ($options_to_filter as $opt_item) {
            if ($opt_item['original_key'] != $correct_key) {
                $wrong_options_keys[] = $opt_item['original_key'];
            }
        }

        if (!empty($wrong_options_keys)) {
            $key_to_remove_index = array_rand($wrong_options_keys); 
            $removed_key = $wrong_options_keys[$key_to_remove_index]; 

            $_SESSION['game_state']['removed_option_keys_for_current_q'][] = $removed_key;
            $_SESSION['game_state']['wizard_skill_uses_left_this_map']--;
            $_SESSION['game_state']['wizard_skill_used_this_question'] = true;

            $redirect_url_after_skill = "game.php?stage=play_game&grade_id={$selected_grade_id}&topic_id={$selected_topic_id}&character_id={$selected_character_id}";
            if ($is_guest) { $redirect_url_after_skill .= "&action=guest_play"; }
            header("Location: " . $redirect_url_after_skill);
            exit;
        }
    }

    if (($_SESSION['game_state']['game_active'] ?? false) === true && !empty($_SESSION['game_state']['question_ids_for_map'])) {
        $current_q_index = $_SESSION['game_state']['current_question_index_in_map'];

        if (isset($_SESSION['game_state']['question_ids_for_map'][$current_q_index])) {
            $current_question_db_id = $_SESSION['game_state']['question_ids_for_map'][$current_q_index];
            $_SESSION['game_state']['current_question_db_id'] = $current_question_db_id; 

            $sql_load_q = "SELECT id, question_text, option_1, option_2, option_3, option_4, correct_option FROM questions WHERE id = ? LIMIT 1";
            $stmt_load_q = $conn->prepare($sql_load_q);
            if ($stmt_load_q) {
                $stmt_load_q->bind_param("i", $current_question_db_id);
                $stmt_load_q->execute();
                $result_load_q = $stmt_load_q->get_result();
                $current_question_data = $result_load_q->fetch_assoc(); 
                $stmt_load_q->close();

                if (!$current_question_data) {
                    $_SESSION['game_state']['game_active'] = false; 
                    error_log("Không tải được dữ liệu cho câu hỏi ID: $current_question_db_id trong play_game_logic.php");
                    $_GET['message'] = 'failed_to_load_question_data';
                } else {
                    $_SESSION['game_state']['current_correct_option_key_original'] = (int)$current_question_data['correct_option'];
                    $options_for_shuffle = [
                        1 => $current_question_data['option_1'],
                        2 => $current_question_data['option_2'],
                        3 => $current_question_data['option_3'],
                        4 => $current_question_data['option_4']
                    ];
                    $shuffled_options_array = [];
                    foreach($options_for_shuffle as $k => $t){
                        $shuffled_options_array[] = ['original_key' => $k, 'text' => $t];
                    }
                    if (empty($_SESSION['game_state']['removed_option_keys_for_current_q'])) {
                         shuffle($shuffled_options_array);
                    }
                    $_SESSION['game_state']['shuffled_options'] = $shuffled_options_array;
                }
            } else {
                $_SESSION['game_state']['game_active'] = false; 
                error_log("Lỗi prepare SQL tải câu hỏi trong play_game_logic.php: " . $conn->error);
                $_GET['message'] = 'sql_error_loading_question';
            }
        } else { 
             error_log("Hết câu hỏi trong danh sách question_ids_for_map. Current index: $current_q_index. Game step: " . ($_SESSION['game_state']['current_step'] ?? 'N/A'));
             $_SESSION['game_state']['game_active'] = false; 
             $_GET['message'] = 'ran_out_of_questions_in_list'; 
        }
    } elseif (($_SESSION['game_state']['game_active'] ?? false) === true && empty($_SESSION['game_state']['question_ids_for_map'])) {
        $_SESSION['game_state']['game_active'] = false;
        error_log("Play game stage: Game active nhưng không có câu hỏi nào được tải (question_ids_for_map rỗng) cho topic $selected_topic_id, grade $selected_grade_id.");
        $_GET['message'] = 'no_questions_loaded_at_all';
    }
    
    if (($_SESSION['game_state']['game_active'] ?? false) === false && $current_question_data === null && $stage === 'play_game'){
        // $current_question_data đã là null, không cần làm gì thêm.
    }
}
?>