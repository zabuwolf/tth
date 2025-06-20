<?php
// admin/includes/sidebar.php
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
?>
<aside id="adminSidebar" class="sidebar w-64 min-h-screen p-4 space-y-2 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-200 ease-in-out z-30">
    <div class="text-center py-2 relative">
        <a href="index.php" class="font-baloo text-2xl font-bold text-white">Admin Panel</a>
        <button id="closeSidebarButton" class="md:hidden absolute top-2 right-2 text-gray-400 hover:text-white">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>
    <nav>
        <a href="index.php" class="block py-2.5 px-4 rounded <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard
        </a>
        <a href="manage_questions.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_questions.php', 'add_edit_question.php']) ? 'active' : ''; ?>">
            <i class="fas fa-question-circle mr-2 w-5 text-center"></i>Quản lý Câu hỏi
        </a>
        <a href="manage_users.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_users.php', 'edit_user.php']) ? 'active' : ''; ?>">
            <i class="fas fa-users mr-2 w-5 text-center"></i>Quản lý Người dùng
        </a>
        <a href="manage_characters.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_characters.php', 'add_edit_character.php']) ? 'active' : ''; ?>">
            <i class="fas fa-user-astronaut mr-2 w-5 text-center"></i>Quản lý Nhân vật
        </a>
        <a href="manage_topics.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_topics.php', 'add_edit_topic.php']) ? 'active' : ''; ?>">
            <i class="fas fa-book-open mr-2 w-5 text-center"></i>Quản lý Chủ đề
        </a>
        <a href="manage_grades.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_grades.php', 'add_edit_grade.php']) ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap mr-2 w-5 text-center"></i>Quản lý Khối lớp
        </a>
        <a href="manage_badges.php" class="block py-2.5 px-4 rounded <?php echo in_array($current_page, ['manage_badges.php', 'add_edit_badge.php']) ? 'active' : ''; ?>">
            <i class="fas fa-medal mr-2 w-5 text-center"></i>Quản lý Huy hiệu
        </a>
        <a href="settings.php" class="block py-2.5 px-4 rounded <?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog mr-2 w-5 text-center"></i>Cài đặt chung
        </a>
        <a href="view_activity_logs.php" class="block py-2.5 px-4 rounded <?php echo ($current_page === 'view_activity_logs.php') ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list mr-2 w-5 text-center"></i>Xem Log Hoạt Động
        </a>
    </nav>
</aside>
<div id="sidebarOverlay" class="md:hidden fixed inset-0 bg-black opacity-50 hidden z-20"></div>