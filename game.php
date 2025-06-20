<?php
// game.php (Tệp chính)

// 1. Khởi tạo và Cài đặt cơ bản
require_once 'game_includes/bootstrap.php'; // $conn, $site_settings

// 2. Xác định trạng thái người dùng
require_once 'game_includes/user_state.php'; // $is_guest, $user_fullname, $user_id, $user_avatar_url, $daily_bonus_msg

// 3. Tải tham số và các biến game cơ bản
require_once 'game_includes/parameter_loader.php'; 
// $stage, $selected_grade_id, $selected_topic_id, $selected_character_id, $game_action
// $grades, $topics_list, $characters_list, $selected_grade_name, $selected_topic_name
// $current_question_data, $character_info_for_game, $total_map_steps

// 4. Logic cho các màn hình lựa chọn
require_once 'game_includes/stage_select_logic.php'; // Cập nhật $topics_list, $characters_list

// 5. Logic cho màn hình chơi game
if ($stage === 'play_game') {
    require_once 'game_includes/play_game_logic.php'; 
    // Cập nhật $_SESSION['game_state'], $current_question_data, $character_info_for_game
}

// 6. Chuẩn bị dữ liệu cuối cùng cho view (bao gồm cả việc xác định lại $stage nếu cần)
require_once 'game_includes/view_data_preparer.php';
// $game_display_info, $final_score_from_url, $game_message_code, $newly_awarded_badges_to_display
// $skill_button_url, $map_end_timestamp_for_js, $is_game_active_for_js, $total_time_seconds_for_js, $game_over_url_js

// --- LOGIC CHO NÚT HEADER ĐỘNG ---
// Mặc định là nút Đăng xuất
$header_button_href = 'logout.php';
$header_button_title = 'Đăng xuất';
$header_button_icon_class = 'fas fa-sign-out-alt';
$header_button_text = 'Thoát'; // Text cho di động

if ($stage === 'select_character') {
    // Quay lại trang chọn chủ đề, cần grade_id
    $grade_param_for_back = isset($selected_grade_id) ? "&grade_id=" . urlencode($selected_grade_id) : "";
    $guest_param_for_back = $is_guest ? "&action=guest_play" : "";
    $header_button_href = 'game.php?stage=select_topic' . $grade_param_for_back . $guest_param_for_back;
    $header_button_title = 'Quay lại chọn chủ đề';
    $header_button_icon_class = 'fas fa-arrow-left';
    $header_button_text = 'Quay Lại';
} elseif ($stage === 'select_topic') {
    // Quay lại trang chọn khối lớp
    $guest_param_for_back = $is_guest ? "&action=guest_play" : "";
    $header_button_href = 'game.php?stage=select_grade' . $guest_param_for_back;
    $header_button_title = 'Quay lại chọn khối';
    $header_button_icon_class = 'fas fa-arrow-left';
    $header_button_text = 'Quay Lại';
} elseif ($stage === 'select_grade') {
    if ($is_guest) {
        $header_button_href = 'index.php'; // Khách thì về trang chủ
        $header_button_title = 'Về Trang Chủ';
        $header_button_icon_class = 'fas fa-home';
        $header_button_text = 'Trang Chủ';
    } else {
        $header_button_href = 'profile.php'; // Người dùng đã đăng nhập thì về trang hồ sơ
        $header_button_title = 'Về Hồ Sơ';
        $header_button_icon_class = 'fas fa-user-circle';
        $header_button_text = 'Hồ Sơ';
    }
}
// Đối với các stage khác (play_game, game_over, game_win), nút sẽ giữ nguyên là "Đăng xuất" theo giá trị mặc định ở trên.
// --- KẾT THÚC LOGIC CHO NÚT HEADER ĐỘNG ---

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chơi Game - Toán Tiểu Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #FDF2F8; /* Màu hồng phấn nhẹ */ }
        .font-baloo { font-family: 'Baloo 2', cursive; }

        /* --- Game Card --- */
        .game-card {
            background-color: white;
            border-radius: 1rem; /* sm:rounded-2xl */
            padding: 0;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); /* Tailwind's shadow-xl */
            width: 100%;
            margin: 1rem auto; /* sm:my-8 */
            overflow: hidden;
        }

        /* --- Header Bar --- */
        .header-bar {
            background: linear-gradient(135deg, #EC4899 0%, #D946EF 100%); /* Màu hồng tím */
            color: white;
        }
        .header-bar .user-info img {
            width: 32px; height: 32px; /* sm:w-10 sm:h-10 */
            border-radius: 50%; margin-right: 0.5rem; /* sm:mr-3 */
            border: 2px solid white;
        }
        .header-bar .header-button-link { /* Đổi tên class từ logout-link để tổng quát hơn */
            color: white; font-size: 0.875rem; /* sm:text-sm */
            text-decoration: none; opacity: 0.9; transition: opacity 0.2s;
            padding: 0.25rem 0.5rem; border-radius: 0.25rem;
            display: inline-flex; /* Thêm để icon và text căn tốt hơn */
            align-items: center; /* Thêm để icon và text căn tốt hơn */
        }
        .header-bar .header-button-link:hover { opacity: 1; background-color: rgba(255,255,255,0.1); }


        /* --- Buttons --- */
        .btn-action {
            background-color: #10B981; /* Xanh lá cây */ color: white;
            font-weight: 700; padding: 0.75rem 1.5rem; 
            border-radius: 0.5rem; transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(16,185,129,0.2), 0 2px 4px -1px rgba(16,185,129,0.12); 
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-action:hover:not(:disabled) { background-color: #059669; transform: translateY(-2px); }
        .btn-action:disabled, .btn-skill:disabled { background-color: #9CA3AF; cursor: not-allowed; opacity: 0.7; }

        .btn-grade, .btn-topic {
            background-color: #F97316; /* Cam */ color: white;
            font-weight: 700; padding: 1rem; /* sm:p-5 */ border-radius: 0.75rem; /* sm:rounded-xl */
            transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(249,115,22,0.2), 0 2px 4px -1px rgba(249,115,22,0.12);
            font-size: 1.125rem; /* sm:text-xl */
        }
        .btn-grade:hover, .btn-topic:hover { background-color: #EA580C; transform: scale(1.03); }
        .btn-topic { background-color: #3B82F6; /* Xanh dương */ }
        .btn-topic:hover { background-color: #2563EB; }

        .btn-skill {
            background-color: #8B5CF6; /* Tím */ color: white; font-weight: 600;
            padding: 0.5rem 1rem; /* sm:px-5 sm:py-2 */ border-radius: 0.5rem; transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(139,92,246,0.3);
            display: inline-block; 
        }
        .btn-skill:hover:not(:disabled) { background-color: #7C3AED; transform: translateY(-1px); }


        /* --- Character Selection --- */
        .character-selection-container {
            margin-bottom: 1.5rem; /* sm:mb-6 md:mb-8 */
        }

        .character-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center; 
            cursor: pointer;
            padding: 0.75rem; /* sm:p-3 md:p-4 */
            border-radius: 0.75rem; /* sm:rounded-lg */
            border: 3px solid transparent;
            transition: all 0.2s ease-in-out;
            background-color: #FFF7ED; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .character-item:hover {
            transform: translateY(-5px) scale(1.03); 
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            border-color: #FDBA74; 
        }
        .character-item.selected {
            border-color: #F97316; 
            background-color: #FFE4C6; 
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.5), 0 4px 10px rgba(249,115,22,0.3); 
            transform: scale(1.05); 
        }
        .character-image-wrapper {
            margin-bottom: 0.5rem; /* sm:mb-2 md:mb-3 */
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #FEF3C7; 
            border-radius: 0.5rem; 
            border: 1px solid #FDE68A; 
        }
        .character-image-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; 
        }
        .character-name-plate {
            background-color: #FDE68A; 
            color: #78350F; 
            font-family: 'Baloo 2', cursive;
            font-weight: 600;
            padding: 0.25rem 0.75rem; /* sm:px-3 sm:py-1.5 */
            border-radius: 9999px; /* rounded-full */
            text-align: center;
            width: auto; 
            max-width: 100%; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            font-size: 0.7rem; /* sm:text-xs md:text-sm */
            line-height: 1.3; 
            margin-top: 0.25rem; 
        }
        .character-item.selected .character-name-plate {
            background-color: #F97316; 
            color: white;
        }


        /* --- Play Game Area --- */
        .play-game-area {
            background-color: #E0F2FE; /* Xanh dương rất nhạt */
            background-size: cover; background-position: center;
            border-radius: 0.75rem; /* sm:rounded-lg */
            padding: 1rem; /* sm:p-6 */
            min-height: 480px; /* sm:min-height: 550px */
        }
        .game-character-display img {
            width: 36px; height: 36px; /* sm:w-10 sm:h-10 */
            border-radius: 50%; margin-right: 0.375rem; /* sm:mr-2 */ object-fit: cover; border: 2px solid #F97316;
        }
        .stat-item {
            background-color: rgba(255,255,255,0.9); padding: 0.375rem 0.75rem; /* sm:px-4 sm:py-2 */
            border-radius: 9999px; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }
        .stat-item .value { font-size: 0.875rem; /* sm:text-base */ font-weight: 700; color: #D97706; }
        .stat-item .value i { margin-right: 0.25rem; }

        .game-timer {
            background-color: rgba(236, 72, 153, 0.85); color: white;
            padding: 0.375rem 0.75rem; /* sm:px-4 sm:py-2 */ border-radius: 9999px; font-weight: 700;
            font-family: 'Baloo 2', cursive; font-size: 1rem; /* sm:text-lg */
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .game-timer.time-low { background-color: rgba(239, 68, 68, 0.85); /* Đỏ khi sắp hết giờ */ }

        /* --- Game Map Area --- */
        .game-map-area {
            width: 100%; margin-bottom: 1rem; /* sm:mb-6 */ padding: 0.75rem; /* sm:p-4 */
            background-color: rgba(255, 255, 255, 0.6); border-radius: 0.5rem; /* sm:rounded-md */
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .map-path {
            height: 48px; /* sm:h-16 */ border-radius: 24px; /* sm:rounded-3xl */
            background: linear-gradient(to right, #BFDBFE, #60A5FA, #3B82F6); position: relative;
            padding: 0 10px; /* sm:px-4 */ box-shadow: inset 0 1px 2px rgba(0,0,0,0.15);
        }
        .map-step-dot {
            width: 18px; height: 18px; /* sm:w-6 sm:h-6 */ background-color: #DBEAFE; border-radius: 50%;
            border: 2px solid #93C5FD; /* sm:border-3 */ box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            position: relative; z-index: 1;
            font-size: 0.6rem; /* sm:text-xs */ font-weight: bold; color: #1E40AF;
        }
        .map-step-dot.completed { background-color: #10B981; border-color: #059669; color: white; }
        .map-step-dot.current { background-color: #FBBF24; border-color: #F59E0B; transform: scale(1.1); color: #78350F; z-index: 3; }
        .player-on-map-icon {
            width: 28px; height: 28px; /* sm:w-10 sm:h-10 */ border-radius: 50%; border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2); object-fit: cover;
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 2;
        }
        .finish-flag-icon {
            font-size: 1.25rem; /* sm:text-2xl */ color: #D97706; position: absolute;
            top: 50%; left: 50%; transform: translate(25%, -65%); /* sm:translate(30%, -70%) */
        }

        /* --- Question Box --- */
        .question-box {
            background-color: rgba(255, 255, 255, 0.95); padding: 1rem; /* sm:p-6 */
            border-radius: 0.75rem; /* sm:rounded-lg */ margin-bottom: 1rem; /* sm:mb-6 */ text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #E5E7EB; /* sm:border-2 */
        }
        .question-text-game {
            font-family: 'Baloo 2', cursive; font-size: 1.25rem; /* sm:text-2xl md:text-3xl */
            font-weight: 600; color: #1F2937; margin-bottom: 1rem; /* sm:mb-6 */ line-height: 1.4; /* sm:line-height-relaxed */
        }
        /* --- Answer Options --- */
        .answer-btn-game {
            background-color: #FFFFFF; color: #374151; border: 2px solid #D1D5DB;
            padding: 0.75rem 1rem; /* sm:p-4 */ border-radius: 0.5rem; /* sm:rounded-lg */
            font-size: 0.9rem; /* sm:text-base */ font-weight: 600; font-family: 'Nunito', sans-serif;
            cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            text-align: left;
        }
        .answer-btn-game .option-number {
            background-color: #E5E7EB; color: #4B5563; border-radius: 0.375rem;
            width: 24px; height: 24px; /* sm:w-7 sm:h-7 */
            margin-right: 0.5rem; /* sm:mr-3 */ font-weight: 700; font-size: 0.8rem; /* sm:text-sm */
        }
        .answer-btn-game:hover:not(:disabled) {
            border-color: #60A5FA; background-color: #EFF6FF; color: #1D4ED8;
            transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .answer-btn-game:hover:not(:disabled) .option-number { background-color: #60A5FA; color: white; }
        .answer-btn-game:disabled {
            background-color: #E5E7EB; color: #9CA3AF; cursor: not-allowed;
            border-color: #D1D5DB; opacity: 0.7;
        }
        .answer-btn-game:disabled .option-number { background-color: #D1D5DB; color: #6B7280; }

        /* --- Motivation Popup --- */
        .motivation-popup-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000; opacity: 0; visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .motivation-popup-overlay.active { opacity: 1; visibility: visible; }
        .motivation-popup-content {
            background-color: white; padding: 1.5rem; /* sm:p-8 */ border-radius: 0.75rem; /* sm:rounded-lg */
            text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: scale(0.9); transition: transform 0.3s ease;
            max-width: 360px; /* sm:max-w-md */ width: 90%;
        }
        .motivation-popup-overlay.active .motivation-popup-content { transform: scale(1); }
        .motivation-popup-content h3 {
            font-family: 'Baloo 2', cursive; font-size: 1.5rem; /* sm:text-2xl */
            color: #EC4899; margin-bottom: 0.75rem; /* sm:mb-4 */
        }
        .motivation-popup-content p { color: #4B5563; margin-bottom: 1rem; /* sm:mb-6 */ font-size: 0.9rem; /* sm:text-base */ }
        .motivation-popup-content .popup-icon { font-size: 2.5rem; /* sm:text-5xl */ color: #FBBF24; margin-bottom: 0.75rem; /* sm:mb-4 */ }
        .motivation-popup-close-btn {
            background-color: #EC4899; color: white; border: none;
            padding: 0.6rem 1.2rem; /* sm:px-6 sm:py-3 */ border-radius: 0.5rem;
            font-weight: 600; cursor: pointer; transition: background-color 0.2s;
            font-size: 0.9rem; /* sm:text-base */
        }
        .motivation-popup-close-btn:hover { background-color: #DB2777; }

        /* --- Game End Card --- */
        .game-end-card {
            text-align: center; padding: 1.5rem; /* sm:p-8 */ background-color: #FFF7ED; border-radius: 0.75rem; /* sm:rounded-lg */
        }
        .game-end-card h2 {
            font-family: 'Baloo 2', cursive; font-size: 1.8rem; /* sm:text-3xl */
            font-weight: 800; margin-bottom: 0.5rem; /* sm:mb-3 */
        }
        .game-end-card .score { font-size: 1.25rem; /* sm:text-2xl */ font-weight: 700; margin-bottom: 0.75rem; /* sm:mb-4 */ }
        .game-end-card .icon { font-size: 3rem; /* sm:text-6xl */ margin-bottom: 0.75rem; /* sm:mb-4 */ }
        .game-end-card.win h2, .game-end-card.win .score { color: #059669; }
        .game-end-card.win .icon { color: #FBBF24; }
        .game-end-card.over h2, .game-end-card.over .score { color: #DC2626; }
        .game-end-card.over .icon { color: #71717A; }
        
        /* --- New Badges Section --- */
        .new-badges-section {
            margin-top: 1rem; /* sm:mt-6 */ padding: 1rem; /* sm:p-6 */
            border-top: 2px dashed #F472B6; background-color: #FFF0F5;
            border-radius: 0 0 0.75rem 0.75rem; /* sm:rounded-b-lg */
        }
        .new-badges-section h3 {
            font-family: 'Baloo 2', cursive; font-size: 1.5rem; /* sm:text-2xl */
            color: #DB2777; margin-bottom: 1rem; /* sm:mb-6 */ text-align: center;
        }
        .new-badge-item {
            background-color: #FFFFFF; border: 1px solid #FBCFE8; border-radius: 0.5rem; /* sm:rounded-lg */
            padding: 0.75rem; /* sm:p-4 */ text-align: center;
            box-shadow: 0 2px 5px rgba(236, 72, 153, 0.1); transition: transform 0.2s ease-out;
        }
        .new-badge-item:hover { transform: translateY(-4px) scale(1.03); }
        .new-badge-item img {
            width: 50px; height: 50px; /* sm:w-16 sm:h-16 md:w-[70px] md:h-[70px] */
            object-fit: contain; margin: 0 auto 0.5rem auto; /* sm:mb-3 */
            background-color: #FDF2F8; padding: 4px; /* sm:p-1 */ border-radius: 50%; border: 2px solid #F9A8D4;
        }
        .new-badge-item .badge-name { font-size: 0.8rem; /* sm:text-sm */ font-weight: 700; color: #831843; line-height: 1.2; }

        /* --- Daily Bonus Toast --- */
        .daily-bonus-toast {
            position: fixed; bottom: 1rem; /* sm:bottom-5 */ right: 1rem; /* sm:right-5 */
            background-color: #10B981; color: white;
            padding: 0.75rem 1.25rem; /* sm:px-6 sm:py-4 */ border-radius: 0.5rem; /* sm:rounded-md */
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); z-index: 1050;
            opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease, transform 0.5s ease;
            font-size: 0.875rem; /* sm:text-base */
        }
        .daily-bonus-toast.show { opacity: 1; transform: translateY(0); }
        .daily-bonus-toast i { margin-right: 0.5rem; /* sm:mr-3 */ font-size: 1.25rem; /* sm:text-2xl */ }

        /* Tailwind custom scrollbar (optional) */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }
        .game-map-area {
    /* Đảm bảo có style cho background-image nếu dùng cách trên */
}
.map-path-container {
    /* Có thể không cần nếu dùng flex items-center justify-around */
}
.map-node {
    /* Các style cơ bản đã có trong class Tailwind */
    /* Thêm style cho background-image nếu bạn muốn mỗi node có hình riêng */
    /* background-image: url('path/to/node-image.png'); */
    /* background-size: contain; */
    /* background-repeat: no-repeat; */
    /* background-position: center; */
}
.player-on-map-icon { /* Đã có animation ở trên nếu bạn muốn áp dụng */
    /* animation: idleBobbing 1.5s ease-in-out infinite;  */
}
.map-connector {
    /* Style cho đường nối giữa các bước */
    /* Ví dụ: */
    /* height: 4px; */
    /* background-color: #FDBA74; Amber-300 */
}

/* Responsive adjustments cho map */
@media (max-width: 640px) { /* sm breakpoint */
    .map-node {
        /* width: 32px; height: 32px; */ /* Giảm kích thước node trên mobile */
    }
    .player-on-map-icon {
        /* width: 28px; height: 28px; */ /* Giảm kích thước avatar player trên mobile */
    }
    .game-map-area {
        padding: 0.5rem;
    }
    .map-path-container {
        /* Có thể cần overflow-x: auto; nếu quá nhiều node không vừa */
        /* justify-content: flex-start; */ /* Để có thể cuộn ngang */
    }
}
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start p-2 sm:p-4 text-gray-800">

    <div class="game-card w-full max-w-md md:max-w-2xl lg:max-w-4xl">
        <div class="header-bar p-3 sm:p-4 flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
            <div class="user-info flex items-center">
                <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="Avatar người dùng" class="w-8 h-8 sm:w-10 sm:h-10">
                <span class="username text-sm sm:text-base font-semibold ml-2 sm:ml-3"><?php echo htmlspecialchars($user_fullname); ?></span>
            </div>
            <div class="game-title font-baloo text-lg sm:text-xl md:text-2xl font-bold text-center sm:text-left">
                <?php
                    if ($stage === 'select_grade') {
                        echo "Chọn Khối Lớp";
                    } elseif ($stage === 'select_topic' && $selected_grade_name) {
                        echo "<span class='block text-xs sm:text-sm opacity-80'>Khối: " . htmlspecialchars($selected_grade_name) . "</span>Chọn Chủ Đề";
                    } elseif ($stage === 'select_character' && $selected_topic_name) {
                        echo "<span class='block text-xs sm:text-sm opacity-80'>" . htmlspecialchars($selected_grade_name). " &raquo; ".htmlspecialchars($selected_topic_name)."</span>Chọn Bạn Đồng Hành";
                    } elseif ($stage === 'play_game' && $game_display_info && ($game_display_info['game_active'] ?? false) === true) {
                        echo "<span class='hidden sm:inline'>Bước </span>" . htmlspecialchars($game_display_info['current_step']) . "/" . $total_map_steps;
                    } elseif ($stage === 'game_over') {
                        echo "Kết Thúc";
                    } elseif ($stage === 'game_win') {
                        echo "Chiến Thắng!";
                    } elseif ($stage === 'play_game' && ($game_display_info['game_active'] ?? false) === false) {
                        echo "Lỗi Trò Chơi";
                    }
                ?>
            </div>
            <a href="<?php echo htmlspecialchars($header_button_href); ?>" class="header-button-link" title="<?php echo htmlspecialchars($header_button_title); ?>">
                <i class="<?php echo $header_button_icon_class; ?> text-lg sm:text-xl"></i>
                <span class="sm:hidden ml-1 text-xs"><?php echo htmlspecialchars($header_button_text); ?></span>
            </a>
        </div>

        <div class="game-content-area p-3 sm:p-5 md:p-6">
            <?php 
            if ($stage === 'select_grade'):
                include 'views/game_select_grade.php'; 
            elseif ($stage === 'select_topic' && $selected_grade_id):
                include 'views/game_select_topic.php'; 
            elseif ($stage === 'select_character' && $selected_grade_id && $selected_topic_id):
                include 'views/game_select_character.php'; 
            elseif ($stage === 'play_game'):
                include 'views/game_play_area.php';
            elseif ($stage === 'game_win'):
                include 'views/game_win_screen.php';
            elseif ($stage === 'game_over'):
                include 'views/game_over_screen.php';
            else:
                echo "<p class='text-red-500 text-center'>Lỗi: Giai đoạn không xác định.</p>";
                $fallback_guest_param_stage = ($is_guest ? "&action=guest_play" : "");
                echo "<p class='text-center mt-4'><a href='game.php?stage=select_grade" . $fallback_guest_param_stage . "' class='btn-action'>Quay lại chọn khối</a></p>";
            endif;
            ?>
        </div>
    </div> <?php // Kết thúc game-card ?>

    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>

    <?php if (isset($_SESSION['show_motivation_popup']) && $_SESSION['show_motivation_popup']): ?>
    <div class="motivation-popup-overlay active flex justify-center items-center" id="motivationPopup">
        <div class="motivation-popup-content">
            <div class="popup-icon"><i class="fas fa-lightbulb"></i></div>
            <h3>Đừng Buồn Nhé!</h3>
            <p>Sai một chút không sao cả. Cố gắng ở câu tiếp theo nhé, bạn làm được mà!</p>
            <button class="motivation-popup-close-btn" onclick="closeMotivationPopup()">Mình Hiểu Rồi!</button>
        </div>
    </div>
    <script>
        function closeMotivationPopup() {
            const popup = document.getElementById('motivationPopup');
            if (popup) {
                popup.classList.remove('active');
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const popup = document.getElementById('motivationPopup');
            if (popup) {
                document.addEventListener('keydown', function(event) {
                    if (event.key === "Escape") {
                        if (popup.classList.contains('active')) {
                            closeMotivationPopup();
                        }
                    }
                });
            }
        });
    </script>
    <?php unset($_SESSION['show_motivation_popup']); ?>
    <?php endif; ?>


    <?php if ($stage === 'select_character' && $selected_grade_id && $selected_topic_id): ?>
    <script>
        let selectedCharacterId = null;
        const confirmButton = document.getElementById('confirmCharacterBtn');
        const characterItems = document.querySelectorAll('.character-item');
        const baseUrl = `game.php?stage=play_game&grade_id=<?php echo $selected_grade_id; ?>&topic_id=<?php echo $selected_topic_id; ?><?php echo $is_guest ? '&action=guest_play' : ''; ?>`;

        function selectCharacter(element) {
            characterItems.forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            selectedCharacterId = element.getAttribute('data-char-id');
            if (selectedCharacterId && confirmButton) {
                confirmButton.href = `${baseUrl}&character_id=${selectedCharacterId}`;
                confirmButton.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-gray-400');
                confirmButton.classList.add('bg-green-500', 'hover:bg-green-600'); 
                confirmButton.removeAttribute('aria-disabled');
                confirmButton.disabled = false;
            } else {
                disableConfirmButton();
            }
        }
        function disableConfirmButton() {
            if(confirmButton){
                confirmButton.href = '#_'; 
                confirmButton.classList.add('opacity-50', 'cursor-not-allowed', 'bg-gray-400');
                confirmButton.classList.remove('bg-green-500', 'hover:bg-green-600');
                confirmButton.setAttribute('aria-disabled', 'true');
                confirmButton.disabled = true;
            }
        }
        if(confirmButton) {
             disableConfirmButton(); 
        }
        characterItems.forEach(item => {
            item.addEventListener('click', function() {
                selectCharacter(this);
            });
        });
    </script>
    <?php endif; ?>

    <?php if ($stage === 'play_game' && $is_game_active_for_js === 'true'): ?>
    <script>
        const mapEndTime = <?php echo (float)($map_end_timestamp_for_js ?? 0); ?> * 1000; 
        const timeRemainingElement = document.getElementById('timeRemaining');
        const gameTimerDisplayElement = document.getElementById('gameTimerDisplay');
        const answerForm = document.getElementById('answerForm');
        let gameTimerInterval;
        const totalTimeForMapJS = <?php echo (float)($total_time_seconds_for_js ?? 0); ?>;

        function updateTimer() {
            const now = new Date().getTime();
            const distance = mapEndTime - now;

            if (distance < 0) {
                clearInterval(gameTimerInterval);
                if (timeRemainingElement) timeRemainingElement.textContent = "HẾT GIỜ!";
                if(gameTimerDisplayElement) gameTimerDisplayElement.classList.add('time-low');
                
                if (answerForm && <?php echo (($game_display_info['game_active'] ?? false) === true) ? 'true' : 'false'; ?> ) {
                    const answerButtons = answerForm.querySelectorAll('button[name="answer"]');
                    answerButtons.forEach(btn => btn.disabled = true);
                    window.location.href = '<?php echo $game_over_url_js; ?>';
                }
                return;
            }
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            if(timeRemainingElement) {
                timeRemainingElement.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
            }
            if (distance < 30000 && gameTimerDisplayElement) { 
                gameTimerDisplayElement.classList.add('time-low');
            } else if (gameTimerDisplayElement) {
                gameTimerDisplayElement.classList.remove('time-low');
            }
        }

        if (mapEndTime > 0 && timeRemainingElement && totalTimeForMapJS > 0) {
            updateTimer(); 
            gameTimerInterval = setInterval(updateTimer, 1000);
        } else if (timeRemainingElement && totalTimeForMapJS <= 0 ) { 
            timeRemainingElement.textContent = "∞"; 
            if(gameTimerDisplayElement) {
                gameTimerDisplayElement.classList.remove('time-low'); 
                gameTimerDisplayElement.classList.add('bg-opacity-70', 'border', 'border-white/30');
            }
        }

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
    <?php endif; ?>

</body>
</html>