<?php
// views/game_play_area.php
// Các biến $game_display_info, $current_question_data, $total_map_steps, 
// $skill_button_url, $selected_grade_id, $selected_topic_id, $selected_character_id, $is_guest, $site_settings,
// và các biến cho passive skill popup từ view_data_preparer.php:
// $passive_skill_to_announce_for_view, $passive_skill_name_for_view, 
// $passive_skill_description_for_view, $passive_skill_icon_class_for_view
// đã được chuẩn bị.

// Chuẩn bị SVG icon placeholder cho avatar nhân vật trong game
$default_avatar_svg_string = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24"><rect width="24" height="24" fill="#DBEAFE"/><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="#1E3A8A"/></svg>';
$game_char_avatar_placeholder_data_uri = 'data:image/svg+xml;base64,' . base64_encode($default_avatar_svg_string);

$game_char_name = $game_display_info['character_name'] ?? 'Nhân vật';
$character_avatar_url = !empty($game_display_info['character_image_url']) ? $game_display_info['character_image_url'] : $game_char_avatar_placeholder_data_uri;

?>
<style>
    /* CSS cho Passive Skill Announcement Modal */
    .passive-skill-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.75); /* Overlay tối hơn */
        display: none; 
        align-items: center;
        justify-content: center;
        z-index: 1050; /* Đảm bảo nó ở trên các element khác */
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    .passive-skill-modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    .passive-skill-modal-content {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* Gradient tím-xanh */
        color: white;
        padding: 2rem; 
        border-radius: 1rem; 
        box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        width: 100%;
        max-width: 480px; 
        text-align: center;
        position: relative;
        transform: scale(0.9);
        transition: transform 0.3s ease-in-out;
        border: 2px solid rgba(255,255,255,0.3);
    }
    .passive-skill-modal-overlay.active .passive-skill-modal-content {
        transform: scale(1);
    }
    .passive-skill-modal-content .modal-close-btn {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        background: rgba(255,255,255,0.2);
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        font-size: 1.3rem;
        color: white; 
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }
    .passive-skill-modal-content .modal-close-btn:hover {
        background-color: rgba(255,255,255,0.4);
    }
    .passive-skill-modal-content .skill-icon-large {
        font-size: 4rem; 
        color: #FFD700; /* Màu vàng gold cho icon */
        margin-bottom: 1rem;
        text-shadow: 0 0 15px rgba(255,215,0,0.5);
    }
    .passive-skill-modal-content h3 {
        font-family: 'Baloo 2', cursive;
        font-size: 2rem; 
        margin-bottom: 0.75rem;
        font-weight: 700;
    }
    .passive-skill-modal-content p {
        margin-bottom: 1.5rem;
        font-size: 1.1rem;
        line-height: 1.6;
    }
    .passive-skill-modal-content .btn-action-passive-ok {
        background-color: #FFD700; /* Nút màu vàng */
        color: #4A00E0; /* Chữ màu tím */
        font-weight: bold;
        padding: 0.75rem 2rem;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border: none;
        cursor: pointer;
    }
    .passive-skill-modal-content .btn-action-passive-ok:hover {
        background-color: #ffc400;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }
</style>

<?php if ($game_display_info && $current_question_data && ($game_display_info['game_active'] ?? false) === true): ?>
<div class="play-game-area flex flex-col justify-between">
    <div> 
        <div class="game-top-bar flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4 mb-3 sm:mb-4">
            <div class="game-character-display flex items-center bg-white/90 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full shadow-sm">
                <img src="<?php echo htmlspecialchars($character_avatar_url); ?>" 
                     alt="<?php echo htmlspecialchars($game_char_name); ?>"
                     onerror="this.src='<?php echo $game_char_avatar_placeholder_data_uri; ?>'; this.onerror=null;"
                     class="w-9 h-9 sm:w-10 sm:h-10 rounded-full object-cover border-2 border-pink-300">
                <span class="name font-baloo text-sm sm:text-base font-semibold text-slate-700 ml-2"><?php echo htmlspecialchars($game_char_name); ?></span>
                <?php
                if (($game_display_info['special_ability_code'] ?? null) === 'CHILD_PRODIGY_STREAK' && ($game_display_info['consecutive_correct_answers'] ?? 0) > 0) : ?>
                    <span class="streak-counter ml-2 text-xs sm:text-sm text-orange-500">
                        <i class="fas fa-fire"></i> <?php echo $game_display_info['consecutive_correct_answers']; ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <div id="gameTimerDisplay" class="game-timer">
                    <i class="fas fa-clock text-sm"></i> <span id="timeRemaining">--:--</span>
                </div>
                <div class="game-stats flex gap-2 sm:gap-3">
                    <div class="stat-item flex items-center gap-1">
                        <div class="value"><i class="fas fa-heart text-red-500"></i> &times; <?php echo htmlspecialchars($game_display_info['current_lives']); ?></div>
                    </div>
                    <div class="stat-item flex items-center gap-1">
                        <div class="value"><i class="fas fa-star text-yellow-400"></i> <?php echo htmlspecialchars($game_display_info['current_score']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="game-map-area">
            <div class="map-path flex items-center justify-around sm:justify-between">
                <?php
                $display_total_map_steps = $total_map_steps ?? ($site_settings['default_questions_per_map'] ?? 10);
                
                $player_map_step = min($game_display_info['current_step'], $display_total_map_steps + 1);
                for ($i = 1; $i <= $display_total_map_steps; $i++):
                    $is_completed = ($i < $player_map_step);
                    $is_current = ($i == $player_map_step && $player_map_step <= $display_total_map_steps);
                ?>
                <div class="map-step-dot flex justify-center items-center <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ''); ?>">
                    <?php if ($is_current): ?>
                        <img src="<?php echo htmlspecialchars($character_avatar_url); ?>" 
                             alt="Player" class="player-on-map-icon"
                             onerror="this.src='<?php echo $game_char_avatar_placeholder_data_uri; ?>'; this.onerror=null;">
                    <?php endif; ?>
                    <?php if ($i == $display_total_map_steps): ?>
                        <i class="fas fa-flag-checkered finish-flag-icon"></i>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div> 
        <div class="question-box">
            <p class="question-text-game"><?php echo nl2br(htmlspecialchars($current_question_data['question_text'])); ?></p>
        </div>

        <?php
        $can_use_wizard_skill = isset($game_display_info['special_ability_code']) &&
                                 $game_display_info['special_ability_code'] === 'WIZARD_REMOVE_WRONG' &&
                                 isset($game_display_info['wizard_skill_uses_left_this_map']) && $game_display_info['wizard_skill_uses_left_this_map'] > 0 &&
                                 isset($game_display_info['wizard_skill_used_this_question']) && $game_display_info['wizard_skill_used_this_question'] === false;
        ?>
        <?php if ($can_use_wizard_skill && !empty($skill_button_url)): ?>
            <div class="text-center mb-3 sm:mb-4">
                <a href="<?php echo $skill_button_url; ?>" class="btn-skill text-xs sm:text-sm">
                    <i class="fas fa-wand-magic-sparkles mr-1 sm:mr-2"></i>Dùng Phép Thuật
                    <span class="hidden sm:inline">(Loại 1 Đáp Án Sai)</span>
                    <span class="block sm:inline text-xs opacity-80">(Còn <?php echo $game_display_info['wizard_skill_uses_left_this_map']; ?>)</span>
                </a>
            </div>
        <?php elseif(isset($game_display_info['special_ability_code']) && $game_display_info['special_ability_code'] === 'WIZARD_REMOVE_WRONG' && ($game_display_info['wizard_skill_used_this_question'] ?? false) === true): ?>
             <div class="text-center mb-3 sm:mb-4">
                <button class="btn-skill text-xs sm:text-sm" disabled>
                    <i class="fas fa-wand-magic-sparkles mr-1 sm:mr-2"></i>Kỹ năng đã dùng
                </button>
            </div>
        <?php elseif(isset($game_display_info['special_ability_code']) && $game_display_info['special_ability_code'] === 'WIZARD_REMOVE_WRONG' && ($game_display_info['wizard_skill_uses_left_this_map'] ?? 0) <= 0): ?>
             <div class="text-center mb-3 sm:mb-4">
                <button class="btn-skill text-xs sm:text-sm" disabled>
                    <i class="fas fa-wand-magic-sparkles mr-1 sm:mr-2"></i>Hết lượt dùng kỹ năng
                </button>
            </div>
        <?php endif; ?>

        <form id="answerForm" action="game_process_answer.php" method="POST">
            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($game_display_info['current_question_db_id']); ?>">
            <input type="hidden" name="grade_id" value="<?php echo htmlspecialchars($selected_grade_id); ?>">
            <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($selected_topic_id); ?>">
            <input type="hidden" name="character_id" value="<?php echo htmlspecialchars($selected_character_id); ?>">
            <?php if($is_guest) echo '<input type="hidden" name="action" value="guest_play">'; ?>

            <div class="answer-options-game grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <?php
                $removed_keys = $game_display_info['removed_option_keys_for_current_q'] ?? [];
                // Đảm bảo $_SESSION['game_state']['shuffled_options'] tồn tại trước khi lặp
                if (isset($_SESSION['game_state']['shuffled_options']) && is_array($_SESSION['game_state']['shuffled_options'])):
                    foreach ($_SESSION['game_state']['shuffled_options'] as $index => $option_item):
                        $is_removed = in_array($option_item['original_key'], $removed_keys);
                ?>
                    <button type="submit" name="answer"
                            value="<?php echo htmlspecialchars($option_item['original_key']); ?>"
                            class="answer-btn-game flex items-center <?php echo $is_removed ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            <?php echo $is_removed ? 'disabled title="Đáp án này đã bị loại bỏ bởi Phù Thủy"' : ''; ?>>
                        <span class="option-number inline-flex items-center justify-center"><?php echo chr(65 + $index); ?></span>
                        <span class="option-text"><?php echo htmlspecialchars($option_item['text']); ?></span>
                    </button>
                <?php 
                    endforeach;
                else:
                    // Xử lý trường hợp không có shuffled_options (ví dụ: hiển thị thông báo lỗi)
                    echo "<p class='col-span-full text-center text-red-500'>Lỗi: Không tải được các lựa chọn trả lời.</p>";
                endif;
                ?>
            </div>
        </form>
    </div>
</div>

<?php 
// Các biến $passive_skill_to_announce_for_view, $passive_skill_name_for_view, 
// $passive_skill_description_for_view, $passive_skill_icon_class_for_view 
// được chuẩn bị từ view_data_preparer.php
if (isset($passive_skill_to_announce_for_view) && $passive_skill_to_announce_for_view === true): 
?>
<div id="passiveSkillModal" class="passive-skill-modal-overlay">
    <div class="passive-skill-modal-content">
        <button class="modal-close-btn" onclick="closePassiveSkillModal()">&times;</button>
        <div class="skill-icon-large"><i class="fas <?php echo htmlspecialchars($passive_skill_icon_class_for_view ?? 'fa-shield-alt'); ?>"></i></div>
        <h3>Kỹ Năng Đặc Biệt Kích Hoạt!</h3>
        <p><strong><?php echo htmlspecialchars($passive_skill_name_for_view ?? 'Nhân vật'); ?></strong>: <?php echo htmlspecialchars($passive_skill_description_for_view ?? 'Kỹ năng đã được kích hoạt.'); ?></p>
        <button class="btn-action-passive-ok" onclick="closePassiveSkillModal()">Đã hiểu!</button>
    </div>
</div>
<?php endif; ?>


<?php elseif ($stage === 'play_game' && (!isset($_SESSION['game_state']) || ($game_display_info['game_active'] ?? false) === false || !$current_question_data)): ?>
    <?php // Trường hợp này là game không active ngay từ đầu (ví dụ không tải được câu hỏi) hoặc $current_question_data không có ?>
    <div class="text-center p-4 sm:p-6 bg-red-100 border border-red-300 rounded-lg">
        <p class="text-red-700 font-semibold text-base sm:text-lg">
            <i class="fas fa-exclamation-triangle mr-2"></i>Rất tiếc, không thể bắt đầu hoặc tiếp tục trò chơi!
        </p>
        <p class="mt-2 text-gray-600 text-sm sm:text-base">
            <?php 
            $error_message_detail = "Có thể không có đủ câu hỏi cho chủ đề bạn chọn, hoặc đã có lỗi xảy ra khi tải dữ liệu.";
            if (isset($_GET['message'])) {
                if ($_GET['message'] === 'ran_out_of_questions_in_list' || $_GET['message'] === 'no_questions_loaded_at_all') {
                     $error_message_detail = "Không đủ câu hỏi cho chủ đề này để bắt đầu màn chơi.";
                } elseif ($_GET['message'] === 'failed_to_load_question_data' || $_GET['message'] === 'sql_error_loading_question'){
                     $error_message_detail = "Đã có lỗi xảy ra khi tải dữ liệu câu hỏi.";
                }
            } elseif (!$current_question_data && ($game_display_info['game_active'] ?? false) === true && ($game_display_info['current_step'] ?? 0) > 0) {
                 $error_message_detail = "Lỗi tải dữ liệu câu hỏi cho bước hiện tại. Vui lòng thử lại.";
            }
            echo $error_message_detail;
            ?>
            Vui lòng thử lại hoặc chọn một chủ đề khác.
        </p>
        <a href="game.php?stage=select_grade<?php if($is_guest) echo '&action=guest_play'; ?>" class="mt-4 inline-block btn-action bg-blue-500 hover:bg-blue-600 text-sm sm:text-base">Chọn Lại Khối Lớp</a>
    </div>
<?php endif; ?>

<script>
    // JavaScript cho Passive Skill Modal
    const passiveSkillModal = document.getElementById('passiveSkillModal');
    
    // Quan trọng: Các biến timer sau đây phải được khai báo ở scope global trong file game.php (file chính include view này)
    // để hàm closePassiveSkillModal có thể truy cập và khởi động lại timer.
    // Ví dụ, trong game.php (bên trong thẻ <script> chính):
    // var gameTimerInterval; // Hoặc không có var/let/const nếu ở top-level của script
    // var isGameActiveJS = '<?php echo $is_game_active_for_js; // được chuẩn bị bởi view_data_preparer.php ?>'; 
    // var mapEndTime = <?php echo (float)($map_end_timestamp_for_js ?? 0); ?> * 1000; 
    // var totalTimeForMapJS = <?php echo (float)($total_time_seconds_for_js ?? 0); ?>; 
    // function updateTimer() { /* định nghĩa ở game.php */ }
    // // ... và khởi tạo timer ban đầu ...
    // if (isGameActiveJS === 'true' && mapEndTime > 0 && totalTimeForMapJS > 0) {
    //    updateTimer();
    //    gameTimerInterval = setInterval(updateTimer, 1000);
    // } else if (isGameActiveJS === 'true' && totalTimeForMapJS <= 0) { /* xử lý timer vô hạn */ }


    function showPassiveSkillModal() {
        if (passiveSkillModal) {
            // Cố gắng tạm dừng timer nếu nó đang chạy
            if (typeof window.gameTimerInterval !== 'undefined' && typeof clearInterval === 'function') {
                clearInterval(window.gameTimerInterval);
                console.log("Game timer paused for passive skill announcement.");
            } else {
                console.warn("showPassiveSkillModal: gameTimerInterval is not defined or clearInterval is not a function. Timer might not be paused.");
            }
            passiveSkillModal.classList.add('active');
        }
    }

    function closePassiveSkillModal() {
        if (passiveSkillModal) {
            passiveSkillModal.classList.remove('active');
            // Cố gắng khởi động lại timer nếu game vẫn active và các biến cần thiết tồn tại
            if (typeof window.isGameActiveJS !== 'undefined' && window.isGameActiveJS === 'true' &&
                typeof window.updateTimer === 'function' && 
                typeof window.mapEndTime !== 'undefined' && window.mapEndTime > Date.now() && // Chỉ resume nếu thời gian chưa hết
                typeof window.totalTimeForMapJS !== 'undefined' && window.totalTimeForMapJS > 0) {
                
                window.updateTimer(); // Cập nhật ngay
                // Kiểm tra lại gameTimerInterval trước khi gán, tránh tạo nhiều interval
                if (typeof window.gameTimerInterval !== 'undefined') clearInterval(window.gameTimerInterval);
                window.gameTimerInterval = setInterval(window.updateTimer, 1000);
                console.log("Game timer resumed after passive skill modal.");
            } else if (typeof window.isGameActiveJS !== 'undefined' && window.isGameActiveJS === 'true' && typeof window.totalTimeForMapJS !== 'undefined' && window.totalTimeForMapJS <= 0) {
                // Trường hợp timer vô hạn, không cần làm gì thêm sau khi đóng popup
                console.log("Passive skill modal closed, game has infinite time.");
            }
             else {
                console.warn("Could not resume game timer after closing passive skill modal. Conditions not met or timer already expired.");
                // Nếu mapEndTime đã qua, không nên resume timer.
                if(typeof window.mapEndTime !== 'undefined' && window.mapEndTime <= Date.now()){
                    console.log("Map time has already ended.");
                    // Có thể cần gọi hàm xử lý hết giờ ở đây nếu chưa được gọi tự động
                    // Ví dụ: if(typeof handleTimeOut === 'function') handleTimeOut();
                }
            }
        }
    }

    <?php 
    // Chỉ gọi showPassiveSkillModal nếu biến PHP tương ứng là true
    // Biến $passive_skill_to_announce_for_view được chuẩn bị bởi view_data_preparer.php
    if (isset($passive_skill_to_announce_for_view) && $passive_skill_to_announce_for_view === true): 
    ?>
    document.addEventListener('DOMContentLoaded', function() {
        showPassiveSkillModal();
    });
    <?php endif; ?>
    
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            if (passiveSkillModal && passiveSkillModal.classList.contains('active')) {
                closePassiveSkillModal();
            }
        }
    });

    // JavaScript Debug cho form trả lời (giữ nguyên)
    document.addEventListener('DOMContentLoaded', function() {
        const answerForm = document.getElementById('answerForm');
        if (answerForm) {
            const answerButtons = answerForm.querySelectorAll('button[name="answer"]');
            answerButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    console.log('Answer button clicked. Value:', this.value, 'For Question ID:', answerForm.querySelector('input[name="question_id"]').value);
                    
                    answerButtons.forEach(btn => { 
                        btn.classList.remove('submitting');
                        const optionNumberSpan = btn.querySelector('.option-number');
                        const optionTextSpan = btn.querySelector('.option-text');
                        if (optionNumberSpan && optionTextSpan && btn.querySelector('i.fa-spinner')) {
                             btn.innerHTML = ''; 
                             btn.appendChild(optionNumberSpan);
                             btn.appendChild(optionTextSpan);
                        }
                    });

                    this.classList.add('submitting');
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang kiểm tra...';

                    answerButtons.forEach(btn => { 
                        if (btn !== this) {
                            btn.disabled = true;
                        }
                    });
                });
            });
        }
    });
</script>
