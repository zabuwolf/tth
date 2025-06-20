<?php
// admin/includes/header.php
// Biến $admin_fullname và $page_title cần được định nghĩa trước khi include file này
?>
<header class="admin-header p-4 shadow-md flex justify-between items-center sticky top-0 z-10">
    <div class="flex items-center">
        <button id="openSidebarButton" class="md:hidden mr-3 text-white">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl font-semibold"><?php echo htmlspecialchars($page_title ?? 'Admin Panel'); ?></h1>
    </div>
    <div>
        <span class="mr-3 hidden sm:inline">Chào, <?php echo htmlspecialchars($admin_fullname ?? 'Admin'); ?>!</span>
        <a href="admin_logout.php" class="text-sm hover:text-indigo-200">
            <i class="fas fa-sign-out-alt mr-1"></i>
            <span class="hidden sm:inline">Đăng xuất</span>
        </a>
    </div>
</header>