<?php
// game_includes/parameter_loader.php
// Biến $conn, $site_settings được truyền từ bootstrap.php

// 3. Lấy các tham số từ URL và xử lý giai đoạn game
$stage = $_GET['stage'] ?? 'select_grade'; 
$selected_grade_id = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : null;
$selected_topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : null;
$selected_character_id = isset($_GET['character_id']) ? (int)$_GET['character_id'] : null;
$game_action = $_GET['game_action'] ?? null; // Dùng cho các hành động trong game như dùng skill

// Khởi tạo các biến sẽ được sử dụng sau
$grades = []; 
$topics_list = []; 
$characters_list = []; 
$selected_grade_name = ''; 
$selected_topic_name = ''; 
$current_question_data = null; 
$character_info_for_game = null; 
$total_map_steps = $site_settings['default_questions_per_map']; // Số bước/câu hỏi mặc định cho một map

// Tải danh sách tất cả các khối lớp
$sql_grades_all = "SELECT id, name FROM grades ORDER BY id ASC";
$result_grades_all = $conn->query($sql_grades_all);
if ($result_grades_all) {
    while ($row_grade_all = $result_grades_all->fetch_assoc()) {
        $grades[] = $row_grade_all;
    }
    $result_grades_all->free();
} else {
    error_log("Lỗi truy vấn grades (all) trong parameter_loader.php: " . $conn->error);
}

// Lấy tên khối lớp đã chọn (nếu có)
if ($selected_grade_id) {
     foreach ($grades as $grade_item) { 
        if ($grade_item['id'] == $selected_grade_id) {
            $selected_grade_name = $grade_item['name'];
            break;
        }
    }
}

// Lấy tên chủ đề đã chọn (nếu có)
if ($selected_topic_id && $selected_grade_id) {
    $sql_topic_name_lookup = "SELECT name FROM topics WHERE id = ? AND grade_id = ? LIMIT 1";
    $stmt_topic_name = $conn->prepare($sql_topic_name_lookup);
    if($stmt_topic_name){
        $stmt_topic_name->bind_param("ii", $selected_topic_id, $selected_grade_id);
        $stmt_topic_name->execute();
        $result_topic_name = $stmt_topic_name->get_result();
        if($topic_data_lookup = $result_topic_name->fetch_assoc()){
            $selected_topic_name = $topic_data_lookup['name'];
        }
        $stmt_topic_name->close();
    } else {
        error_log("Lỗi prepare SQL lấy tên topic trong parameter_loader.php: " . $conn->error);
    }
}
?>