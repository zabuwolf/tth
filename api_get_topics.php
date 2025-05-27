<?php
// api_get_topics.php
header('Content-Type: application/json'); // Thiết lập header để trả về JSON

require_once 'config/db_config.php'; // Kết nối CSDL

$response = ['success' => false, 'topics' => [], 'error' => ''];

// Kiểm tra xem grade_id có được cung cấp không
if (!isset($_GET['grade_id']) || empty($_GET['grade_id'])) {
    $response['error'] = "Thiếu tham số 'grade_id'.";
    echo json_encode($response);
    exit;
}

$grade_id = (int)$_GET['grade_id'];

if ($grade_id <= 0) {
    $response['error'] = "Giá trị 'grade_id' không hợp lệ.";
    echo json_encode($response);
    exit;
}

$conn = connect_db();

if (!$conn) {
    $response['error'] = "Lỗi kết nối cơ sở dữ liệu.";
    // Ghi log lỗi chi tiết ở server, không lộ ra cho client
    error_log("API get_topics: Lỗi kết nối CSDL.");
    echo json_encode($response);
    exit;
}

$topics = [];
$sql = "SELECT id, name FROM topics WHERE grade_id = ? ORDER BY sort_order ASC, name ASC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $grade_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row;
        }
        $response['success'] = true;
        $response['topics'] = $topics;
    } else {
        $response['error'] = "Lỗi khi thực thi truy vấn lấy chủ đề.";
        error_log("API get_topics: Lỗi thực thi SQL: " . $stmt->error);
    }
    $stmt->close();
} else {
    $response['error'] = "Lỗi khi chuẩn bị truy vấn lấy chủ đề.";
    error_log("API get_topics: Lỗi chuẩn bị SQL: " . $conn->error);
}

close_db_connection($conn);

echo json_encode($response);
exit;
?>
