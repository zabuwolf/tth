<?php
// config/db_config.php

// Thông tin kết nối cơ sở dữ liệu
// Thay thế bằng thông tin thực tế của bạn khi triển khai
define('DB_SERVER', 'localhost'); // Giữ nguyên nếu CSDL chạy trên cùng server với web
define('DB_USERNAME', 'toan_tieuhoc'); // Tên người dùng CSDL của bạn
define('DB_PASSWORD', 'Minh@7894');    // Mật khẩu CSDL của bạn
define('DB_NAME', 'toan_tieuhoc');      // Tên CSDL của bạn


function connect_db() {
    $connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($connection->connect_error) {
        error_log("Lỗi Kết Nối Cơ Sở Dữ Liệu: " . $connection->connect_error . 
                  " (Máy chủ: " . DB_SERVER . ", Người dùng: " . DB_USERNAME . ", CSDL: " . DB_NAME . ")");
        return null; 
    }
    if (!$connection->set_charset("utf8mb4")) {
        error_log("Lỗi khi thiết lập bảng mã utf8mb4 cho kết nối CSDL: " . $connection->error);
    }
    return $connection;
}

/**
 * Hàm đóng kết nối cơ sở dữ liệu hiện tại.
 * @param mysqli|null $connection Đối tượng kết nối mysqli cần được đóng.
 */
function close_db_connection($connection) {
    if ($connection !== null && $connection instanceof mysqli) {
        $connection->close();
    }
}
?>