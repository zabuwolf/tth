<?php
// logout.php
session_start(); // Bắt đầu hoặc tiếp tục session hiện tại

// 1. Hủy tất cả các biến session
$_SESSION = array(); // Ghi đè mảng session bằng một mảng rỗng

// 2. Nếu bạn muốn hủy session hoàn toàn, bạn cũng cần hủy cookie session.
// Lưu ý: Điều này sẽ phá hủy session, không chỉ dữ liệu session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Cuối cùng, hủy session.
session_destroy();

// 4. Chuyển hướng người dùng về trang chủ (hoặc trang đăng nhập)
// Bạn có thể chọn chuyển hướng đến login.php nếu muốn người dùng đăng nhập lại ngay
header("Location: index.php");
exit;
?>
