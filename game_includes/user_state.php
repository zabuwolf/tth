<?php
// game_includes/user_state.php
// Biến $conn được truyền từ bootstrap.php
// Biến $site_settings được truyền từ bootstrap.php

$is_guest = false;
$user_fullname = "";
$user_id = null;
$default_avatar_guest = 'https://placehold.co/60x60/E0E0E0/757575?text=K';
$user_avatar_url = $default_avatar_guest;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $is_guest = false;
    $user_fullname = $_SESSION['fullname'] ?? 'Người chơi';
    $user_id = (int)$_SESSION['user_id'];
    $user_avatar_url = $_SESSION['avatar_url'] ?? 'https://placehold.co/60x60/3B82F6/FFFFFF?text=' . strtoupper(substr($user_fullname, 0, 1));
} elseif (isset($_GET['action']) && $_GET['action'] === 'guest_play') {
    $is_guest = true;
    $_SESSION['is_playing_as_guest'] = true; // Lưu trạng thái chơi khách vào session
    $user_fullname = "Người lạ";
} elseif (isset($_SESSION['is_playing_as_guest']) && $_SESSION['is_playing_as_guest'] === true) {
    // Kiểm tra nếu người dùng đã ở trạng thái chơi khách từ trước (ví dụ: F5 trang)
    $is_guest = true;
    $user_fullname = "Người lạ";
} else {
    // Nếu không phải người dùng đăng nhập và cũng không có action=guest_play (và chưa từng chọn guest_play)
    if ($conn) { close_db_connection($conn); } // Đóng kết nối trước khi chuyển hướng
    header('Location: login.php?error=Vui lòng đăng nhập hoặc chọn chơi ẩn danh để tiếp tục!');
    exit;
}

$daily_bonus_msg = $_SESSION['daily_bonus_message'] ?? null;
unset($_SESSION['daily_bonus_message']);

?>