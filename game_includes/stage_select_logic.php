<?php
// game_includes/stage_select_logic.php
// Các biến $conn, $stage, $selected_grade_id, $selected_topic_id, &$topics_list, &$characters_list đã được định nghĩa

if ($stage === 'select_topic' && $selected_grade_id) {
    $sql_topics = "SELECT id, name, description FROM topics WHERE grade_id = ? ORDER BY sort_order ASC, name ASC";
    $stmt_topics = $conn->prepare($sql_topics);
    if ($stmt_topics) {
        $stmt_topics->bind_param("i", $selected_grade_id);
        $stmt_topics->execute();
        $result_topics = $stmt_topics->get_result();
        while ($row_topic = $result_topics->fetch_assoc()) {
            $topics_list[] = $row_topic;
        }
        $stmt_topics->close();
    } else {
        error_log("Lỗi prepare SQL lấy topics cho grade $selected_grade_id: " . $conn->error);
    }
} elseif ($stage === 'select_character' && $selected_grade_id && $selected_topic_id) {
    $sql_characters = "SELECT id, name, description, image_url, base_lives, special_ability_code FROM characters ORDER BY id ASC"; 
    $result_characters = $conn->query($sql_characters);
    if ($result_characters) {
        while ($row_char = $result_characters->fetch_assoc()) {
            $characters_list[] = $row_char;
        }
        $result_characters->free();
    } else {
        error_log("Lỗi truy vấn characters: " . $conn->error);
    }
}
?>