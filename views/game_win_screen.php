<?php
// views/game_win_screen.php
// Các biến $final_score_from_url, $game_display_info, $selected_topic_name, $selected_grade_name, 
// $newly_awarded_badges_to_display, $is_guest đã được chuẩn bị.
?>
<div class="game-end-card win">
    <div class="icon"><i class="fas fa-trophy"></i></div>
    <h2>Chúc Mừng Chiến Thắng!</h2>
    <?php
        $winMessage = "Bạn đã hoàn thành xuất sắc tất cả các thử thách!";
        if ($selected_topic_name) { // Ưu tiên hiển thị tên chủ đề nếu có
            $winMessage = "Bạn đã chinh phục chủ đề: <strong>" . htmlspecialchars($selected_topic_name) . "</strong>!";
        } elseif ($selected_grade_name) {
             $winMessage = "Bạn đã hoàn thành các thử thách của <strong>" . htmlspecialchars($selected_grade_name) . "</strong>!";
        }
    ?>
    <p class="text-base sm:text-lg text-gray-700 mb-2"><?php echo $winMessage; ?></p>
    <div class="score mb-2">Điểm số: <?php echo htmlspecialchars($final_score_from_url ?? ($game_display_info['current_score'] ?? 0)); ?></div>
    <?php
    if (isset($_SESSION['prodigy_bonus_applied']) && $_SESSION['prodigy_bonus_applied']): ?>
        <p class="text-xs sm:text-sm text-yellow-600 font-semibold mb-3 sm:mb-4 animate-pulse">
            <i class="fas fa-medal"></i> +1 điểm thưởng Thần Đồng!
        </p>
    <?php
        unset($_SESSION['prodigy_bonus_applied']);
    endif;
    ?>

    <?php if (!empty($newly_awarded_badges_to_display)): ?>
        <div class="new-badges-section">
            <h3 class="flex items-center justify-center gap-2"><i class="fas fa-award"></i> Huy hiệu mới! <i class="fas fa-award"></i></h3>
            <div class="new-badges-grid grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                <?php foreach ($newly_awarded_badges_to_display as $awarded_badge): ?>
                    <div class="new-badge-item" title="<?php echo htmlspecialchars($awarded_badge['name'] . ' - ' . ($awarded_badge['description'] ?? '')); ?>">
                        <img src="<?php echo htmlspecialchars(!empty($awarded_badge['image_url']) ? $awarded_badge['image_url'] : 'https://placehold.co/80x80/FFD700/78350F?text=Badge'); ?>"
                             alt="<?php echo htmlspecialchars($awarded_badge['name']); ?>">
                        <div class="badge-name"><?php echo htmlspecialchars($awarded_badge['name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="actions mt-4 sm:mt-6 space-y-2 sm:space-y-0 sm:space-x-3 flex flex-col sm:flex-row justify-center">
        <a href="game.php?stage=select_grade<?php if($is_guest) echo '&action=guest_play'; ?>" class="btn-action bg-green-500 hover:bg-green-600 text-sm sm:text-base">Chơi Tiếp</a>
        <a href="index.php" class="btn-action bg-gray-500 hover:bg-gray-600 text-sm sm:text-base">Về Trang Chủ</a>
        <?php if (!$is_guest): // Chỉ hiển thị nút Dashboard cho người dùng đã đăng nhập ?>
        <a href="dashboard.php" class="btn-action bg-pink-500 hover:bg-pink-600 text-sm sm:text-base">Về Bảng Điều Khiển</a>
        <?php endif; ?>
    </div>               
</div>