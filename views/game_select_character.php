<?php
// views/game_select_character.php
// Biến $characters_list, $selected_grade_name, $selected_topic_name, $selected_grade_id, $selected_topic_id, $is_guest được truyền từ game.php
?>
<div class="text-center">
    <p class="text-gray-700 mb-2 text-lg">
        Khối: <strong class="font-baloo text-pink-500"><?php echo htmlspecialchars($selected_grade_name); ?></strong>
    </p>
    <p class="text-gray-700 mb-4 text-lg">
        Chủ đề: <strong class="font-baloo text-sky-500"><?php echo htmlspecialchars($selected_topic_name); ?></strong>
    </p>
    <p class="text-gray-600 mb-8 text-md">
        Hãy chọn một người bạn đồng hành cho thử thách này!
    </p>
    
    <?php if (!empty($characters_list)): ?>
    <div class="character-selection-container grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4" id="characterSelectionContainer">
        <?php foreach ($characters_list as $char): ?>
        <?php
            // Chuẩn bị text cho placeholder nếu không có image_url
            $placeholder_text = "Nhân Vật"; // Mặc định
            if (!empty($char['name'])) {
                // Lấy một vài từ đầu của tên nhân vật làm placeholder text
                $name_parts = explode(" ", $char['name']);
                $placeholder_text = implode(" ", array_slice($name_parts, 0, 2)); // Lấy tối đa 2 từ đầu
                if (count($name_parts) == 1) $placeholder_text = $name_parts[0]; // Nếu chỉ có 1 từ
            }
            $placeholder_image_url = "https://placehold.co/150x200/DBEAFE/1E3A8A?text=" . urlencode($placeholder_text);
            // Màu nền xanh nhạt (DBEAFE), màu chữ xanh đậm (1E3A8A)
        ?>
        <div class="character-item" data-char-id="<?php echo $char['id']; ?>" onclick="selectCharacter(this)">
            <div class="character-image-wrapper w-16 h-20 sm:w-20 sm:h-24 md:w-24 md:h-28 lg:w-28 lg:h-36">
                <img src="<?php echo htmlspecialchars(!empty($char['image_url']) ? $char['image_url'] : $placeholder_image_url); ?>" 
                     alt="<?php echo htmlspecialchars($char['name']); ?>"
                     onerror="this.src='<?php echo $placeholder_image_url; ?>'; this.onerror=null;">
            </div>
            <div class="character-name-plate"><?php echo htmlspecialchars($char['name']); ?></div>
            <p class="text-xs text-gray-500 mt-2">Mạng: <span class="font-semibold"><?php echo $char['base_lives']; ?></span></p>
            <p class="text-xs text-gray-500 mt-1 px-1">
                <?php echo htmlspecialchars($char['description']); ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-8">
        <a href="#" id="confirmCharacterBtn" class="btn-action opacity-50 cursor-not-allowed" aria-disabled="true">
            Xác Nhận Chọn
        </a>
    </div>

    <?php else: ?>
         <p class="text-red-500">Không tải được danh sách nhân vật. Vui lòng thử lại sau hoặc liên hệ quản trị viên.</p>
    <?php endif; ?>

    <p class="mt-8">
        <a href="game.php?stage=select_topic&grade_id=<?php echo $selected_grade_id; ?><?php if($is_guest) echo '&action=guest_play'; ?>" class="text-sm text-gray-600 hover:text-pink-600">&larr; Quay lại chọn chủ đề</a>
    </p>
</div>
