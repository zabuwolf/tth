<?php
// views/game_select_topic.php
// Biến $topics_list, $selected_grade_name, $selected_grade_id, $is_guest được truyền từ game.php
?>
<div class="text-center">
    <p class="text-gray-700 mb-4 text-lg">
        Bạn đã chọn: <strong class="font-baloo text-pink-600"><?php echo htmlspecialchars($selected_grade_name); ?></strong>.
    </p>
    <p class="text-gray-600 mb-8 text-md">
        Giờ hãy chọn một chủ đề học tập để bắt đầu nhé!
    </p>
    
    <?php if (!empty($topics_list)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($topics_list as $topic): ?>
        <a href="game.php?stage=select_character&grade_id=<?php echo $selected_grade_id; ?>&topic_id=<?php echo $topic['id']; ?><?php if($is_guest) echo '&action=guest_play'; ?>"
           class="btn-grade bg-sky-500 hover:bg-sky-600 flex flex-col items-center justify-center p-4 h-32">
            <span class="font-baloo text-xl mb-1"><?php echo htmlspecialchars($topic['name']); ?></span>
            <?php if (!empty($topic['description'])): ?>
                <span class="text-xs text-sky-100 font-normal normal-case"><?php echo htmlspecialchars(substr($topic['description'], 0, 50)) . (strlen($topic['description']) > 50 ? '...' : ''); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="text-red-500">Không tìm thấy chủ đề nào cho khối lớp này. Vui lòng thử lại hoặc liên hệ quản trị viên.</p>
    <?php endif; ?>

    <p class="mt-8">
        <a href="game.php?stage=select_grade<?php if($is_guest) echo '&action=guest_play'; ?>" class="text-sm text-gray-600 hover:text-pink-600">&larr; Quay lại chọn khối</a>
    </p>
</div>