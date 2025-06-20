<?php
// game_includes/bootstrap.php
if (session_status() == PHP_SESSION_NONE) { //
    session_start(); //
}

require_once __DIR__ . '/../config/db_config.php'; // Điều chỉnh đường dẫn nếu cần

// 1. Khởi tạo kết nối CSDL
$conn = connect_db(); //

if ($conn === null) { //
    error_log("CRITICAL ERROR: Bootstrap - Cannot connect to database."); //
    // Hiển thị trang lỗi thân thiện hoặc die với thông báo lỗi chung
    // die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau hoặc liên hệ quản trị viên.");
    // Đường dẫn __DIR__ trỏ đến thư mục hiện tại (game_includes), vì vậy cần ../ để ra ngoài
    if (file_exists(__DIR__ . '/../views/errors/db_error_page.php')) {
        include __DIR__ . '/../views/errors/db_error_page.php';
    } else {
        // Fallback nếu file error page không tồn tại
        echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Lỗi Hệ Thống</title><style>body{font-family: Arial, sans-serif; text-align: center; padding: 50px;} h1{color: #cc0000;}</style></head><body><h1>Lỗi Hệ Thống Nghiêm Trọng</h1><p>Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau hoặc liên hệ quản trị viên để được hỗ trợ.</p></body></html>";
    }
    exit;
}

// Lấy cài đặt site
$site_settings_raw = []; //
$sql_settings = "SELECT setting_key, setting_value FROM site_settings"; //
$result_settings = $conn->query($sql_settings); //
if ($result_settings) { //
    while ($row_setting = $result_settings->fetch_assoc()) { //
        $site_settings_raw[$row_setting['setting_key']] = $row_setting['setting_value']; //
    }
    $result_settings->free(); //
} else {
    error_log("Lỗi truy vấn site_settings trong bootstrap.php: " . $conn->error); //
}
$site_settings = [ //
    'points_per_correct_answer' => isset($site_settings_raw['points_per_correct_answer']) ? (int)$site_settings_raw['points_per_correct_answer'] : 1, //
    'default_questions_per_map' => isset($site_settings_raw['default_questions_per_map']) ? (int)$site_settings_raw['default_questions_per_map'] : 10, //
    'total_time_per_map_seconds' => isset($site_settings_raw['total_time_per_map_seconds']) ? (int)$site_settings_raw['total_time_per_map_seconds'] : 600, //
];

?>