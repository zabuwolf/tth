<?php
session_start();

// Hủy tất cả các biến session của admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_fullname']);

// Hủy các session chung của người dùng nếu admin đăng xuất
// (để đảm bảo đăng xuất hoàn toàn khỏi hệ thống)
unset($_SESSION['logged_in']);
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['fullname']); // fullname chung, admin_fullname đã unset ở trên
unset($_SESSION['avatar_url']);
unset($_SESSION['points']);
unset($_SESSION['is_admin']);

// Cân nhắc hủy thêm các session game nếu cần
unset($_SESSION['is_playing_as_guest']);
if (isset($_SESSION['game_state'])) {
    unset($_SESSION['game_state']);
}
// Hoặc hủy toàn bộ session nếu muốn đơn giản nhất:
// session_destroy(); 
// Tuy nhiên, việc unset từng key cụ thể sẽ an toàn hơn nếu có các session khác không liên quan.

// Chuyển hướng về trang đăng nhập chính
header('Location: ../login.php');
exit;
?>