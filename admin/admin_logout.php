<?php
session_start();

// Hủy tất cả các biến session của admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_fullname']);

// Không cần thiết phải hủy toàn bộ session nếu người dùng có thể đang đăng nhập ở trang người dùng
// session_destroy(); 

// Chuyển hướng về trang đăng nhập admin
header('Location: admin_login.php');
exit;
?>
