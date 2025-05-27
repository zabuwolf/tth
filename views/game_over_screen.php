<?php
// views/game_over_screen.php
// Các biến $final_score_from_url, $game_display_info, $game_message_code, $is_guest đã được chuẩn bị.
?>
<div class="game-end-card over">
    <div class="icon"><i class="fas fa-heart-broken"></i></div>
    <h2>Rất Tiếc, Game Over!</h2>
    <?php
        $gameOverReasonFromUrl = $_GET['reason'] ?? ''; // Lý do từ URL (ví dụ: no_lives, time_out)
        $gameOverMessage = "Bạn đã hết lượt chơi. Đừng nản lòng, hãy thử lại nhé!";

        if ($gameOverReasonFromUrl === 'no_lives') {
            $gameOverMessage = "Bạn đã hết mạng. Cố gắng hơn ở lần sau nhé!";
        } elseif ($gameOverReasonFromUrl === 'time_out') {
            $gameOverMessage = "Rất tiếc, bạn đã hết thời gian làm bài cho màn chơi này!";
        } elseif ($game_message_code === 'ran_out_of_questions' || $game_message_code === 'ran_out_of_questions_on_wrong' || $game_message_code === 'ran_out_of_questions_before_finish') {
            $gameOverMessage = "Rất tiếc, không đủ câu hỏi để hoàn thành. Hãy thử lại hoặc chọn chủ đề khác!";
        }
    ?>
    <p class="text-base sm:text-lg text-gray-700 mb-3 sm:mb-4"><?php echo $gameOverMessage; ?></p>
    <div class="score">Điểm số: <?php echo htmlspecialchars($final_score_from_url ?? ($game_display_info['current_score'] ?? 0)); ?></div>
     <div class="actions mt-4 sm:mt-6 space-y-2 sm:space-y-0 sm:space-x-3 flex flex-col sm:flex-row justify-center">
        <?php 
        // Nút "Thử Lại Màn Này" chỉ nên hiển thị nếu có đủ thông tin game state
        if (isset($game_display_info['grade_id']) && isset($game_display_info['topic_id']) && isset($game_display_info['character_id'])): 
        ?>
        <a href="game.php?stage=play_game&grade_id=<?php echo htmlspecialchars($game_display_info['grade_id']); ?>&topic_id=<?php echo htmlspecialchars($game_display_info['topic_id']); ?>&character_id=<?php echo htmlspecialchars($game_display_info['character_id']); ?><?php if($is_guest) echo '&action=guest_play'; ?>" class="btn-action bg-blue-500 hover:bg-blue-600 text-sm sm:text-base">Thử Lại Màn Này</a>
        <?php endif; ?>
        <a href="game.php?stage=select_grade<?php if($is_guest) echo '&action=guest_play'; ?>" class="btn-action bg-yellow-500 hover:bg-yellow-600 text-sm sm:text-base">Chọn Khối Khác</a>
        <a href="index.php" class="btn-action bg-gray-500 hover:bg-gray-600 text-sm sm:text-base">Về Trang Chủ</a>
        <?php if (!$is_guest): // Chỉ hiển thị nút Dashboard cho người dùng đã đăng nhập ?>
        <a href="dashboard.php" class="btn-action bg-pink-500 hover:bg-pink-600 text-sm sm:text-base">Về Bảng Điều Khiển</a>
        <?php endif; ?>
    </div>
</div>