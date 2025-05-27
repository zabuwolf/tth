<?php
// views/game_select_grade.php
// Biến $grades và $is_guest được truyền từ game.php
?>
<div class="text-center">
    <p class="text-gray-700 mb-8 text-lg">Hãy chọn khối lớp bạn muốn thử sức nhé!</p>
    <?php if (!empty($grades)): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($grades as $grade_item): ?>
        <a href="game.php?stage=select_topic&grade_id=<?php echo $grade_item['id']; ?><?php if($is_guest) echo '&action=guest_play'; ?>"
           class="btn-grade flex items-center justify-center font-baloo">
            <?php echo htmlspecialchars($grade_item['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="text-red-500">Không tải được danh sách khối lớp. Vui lòng thử lại sau hoặc liên hệ quản trị viên.</p>
    <?php endif; ?>
</div>
