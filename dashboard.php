<?php
// File: dashboard.php
// Nhiệm vụ: Hiển thị trang tổng quan sau khi đăng nhập, bao gồm nút nhận thưởng hàng ngày.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Vui lòng đăng nhập để tiếp tục!');
    exit;
}

require_once 'config/db_config.php'; // File cấu hình kết nối CSDL

$conn = connect_db(); // Hàm kết nối CSDL từ db_config.php
// Không đóng kết nối ở đây ngay, sẽ đóng sau khi hoàn tất các truy vấn cần thiết

$user_id = $_SESSION['user_id'];
$user_fullname = $_SESSION['fullname'] ?? 'Người Chơi';
// Sử dụng placeholder nếu avatar không có hoặc bị lỗi
$user_avatar_url = $_SESSION['avatar_url'] ?? 'https://placehold.co/100x100/E0E0E0/757575?text=' . strtoupper(substr($user_fullname, 0, 1));
$user_points = $_SESSION['points'] ?? 0; // Lấy điểm từ session, sẽ được cập nhật nếu nhận thưởng

// Biến kiểm soát trạng thái nút nhận thưởng
$can_claim_daily_bonus_today = true; // Mặc định là có thể nhận
$daily_bonus_button_text = "🎁 Nhận Thưởng Đăng Nhập (+1 Điểm)";
$daily_bonus_button_disabled_attr = ""; // Thuộc tính disabled cho nút

if ($conn) {
    $today_date_string = date("Y-m-d");
    // Lấy thông tin ngày nhận thưởng cuối cùng của người dùng
    // Cần lấy cả points để cập nhật chính xác sau khi nhận thưởng thành công trên UI
    $sql_user_info = "SELECT points, last_daily_bonus_claimed_date FROM users WHERE id = ? LIMIT 1";
    $stmt_user_info = $conn->prepare($sql_user_info);

    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $user_id);
        $stmt_user_info->execute();
        $result_user_info = $stmt_user_info->get_result();
        if ($current_user_data = $result_user_info->fetch_assoc()) {
            // Cập nhật điểm từ CSDL (để đảm bảo luôn mới nhất khi tải trang)
            $user_points = (int)($current_user_data['points'] ?? 0);
            $_SESSION['points'] = $user_points; // Cập nhật lại session points

            if (!empty($current_user_data['last_daily_bonus_claimed_date']) && $current_user_data['last_daily_bonus_claimed_date'] == $today_date_string) {
                $can_claim_daily_bonus_today = false;
                $daily_bonus_button_text = "✅ Đã Nhận Thưởng Hôm Nay";
                $daily_bonus_button_disabled_attr = "disabled";
            }
        }
        $stmt_user_info->close();
    } else {
        error_log("Dashboard: Lỗi chuẩn bị câu lệnh lấy thông tin người dùng: " . $conn->error);
        // Nếu có lỗi, có thể không cho phép nhận thưởng để tránh sự cố
        $daily_bonus_button_text = "Lỗi kiểm tra thưởng";
        $daily_bonus_button_disabled_attr = "disabled";
    }
    // Đóng kết nối CSDL sau khi hoàn tất các truy vấn cần thiết cho trang này
    close_db_connection($conn);
} else {
    error_log("Dashboard: Không thể kết nối CSDL để kiểm tra thưởng hàng ngày.");
    $daily_bonus_button_text = "Lỗi kết nối CSDL";
    $daily_bonus_button_disabled_attr = "disabled";
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển - Hiệp Sĩ Toán Học</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: linear-gradient(135deg, #f0e7ff 0%, #c3daff 100%);
            color: #374151;
        }
        .font-baloo { font-family: 'Baloo 2', cursive; }
        .dashboard-container { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; }
        .dashboard-card { background-color: rgba(255, 255, 255, 0.98); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); padding: 1.5rem; width: 100%; max-width: 700px; text-align: center; }
        .user-avatar { width: 96px; height: 96px; border-radius: 50%; border: 4px solid #a78bfa; margin: 0 auto 1rem; object-fit: cover; box-shadow: 0 4px 15px rgba(167, 139, 250, 0.3); }
        .welcome-message { font-family: 'Baloo 2', cursive; font-size: 2rem; font-weight: 700; color: #6d28d9; margin-bottom: 0.25rem; }
        .user-name { font-size: 1.125rem; font-weight: 600; color: #7c3aed; margin-bottom: 1.5rem; }
        .user-points-display { font-size: 0.9rem; color: #581c87; margin-bottom: 2rem; background-color: #ede9fe; padding: 0.5rem 1rem; border-radius: 9999px; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .user-points-display i { color: #facc15; }
        .daily-bonus-section { margin-bottom: 2rem; padding: 1rem; background-color: #f5f3ff; border-radius: 1rem; border: 1px solid #ddd6fe; }
        #dailyBonusButton { background: linear-gradient(45deg, #8b5cf6, #a78bfa); color: white; font-family: 'Baloo 2', cursive; font-size: 1.125rem; padding: 0.75rem 1.5rem; border-radius: 0.75rem; border: none; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); transition: all 0.3s ease; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        #dailyBonusButton:hover:not(:disabled) { transform: translateY(-3px) scale(1.05); box-shadow: 0 6px 15px rgba(139, 92, 246, 0.4); }
        #dailyBonusButton:disabled { background: #e5e7eb; color: #6b7280; cursor: not-allowed; box-shadow: none; }
        #dailyBonusButton:disabled i { color: #9ca3af; }
        #dailyBonusButton i { transition: transform 0.3s ease; }
        #dailyBonusButton:hover:not(:disabled) i.fa-gift { animation: shakeGift 0.8s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shakeGift { 10%, 90% { transform: translate3d(-1px, 0, 0) rotate(-1deg); } 20%, 80% { transform: translate3d(2px, 0, 0) rotate(2deg); } 30%, 50%, 70% { transform: translate3d(-3px, 0, 0) rotate(-3deg); } 40%, 60% { transform: translate3d(3px, 0, 0) rotate(3deg); } }
        .nav-options-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .nav-options-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); } }
        .nav-option-card { background: #ffffff; border-radius: 0.75rem; padding: 1.25rem 1rem; text-decoration: none; color: #4b5563; font-weight: 600; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); box-shadow: 0 4px 10px rgba(0,0,0,0.07); border: 1px solid #e5e7eb; }
        .nav-option-card:hover { transform: translateY(-5px) scale(1.03); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .nav-option-card i { font-size: 2rem; margin-bottom: 0.75rem; transition: transform 0.3s ease; }
        .nav-option-card:hover i { transform: scale(1.15) rotate(-3deg); }
        .nav-option-card span { font-family: 'Baloo 2', cursive; font-size: 1.125rem; }
        .nav-option-card.play-game { border-top: 4px solid #60a5fa; } .nav-option-card.play-game:hover { color: #3b82f6; } .nav-option-card.play-game i { color: #60a5fa; }
        .nav-option-card.profile { border-top: 4px solid #34d399; } .nav-option-card.profile:hover { color: #10b981; } .nav-option-card.profile i { color: #34d399; }
        .nav-option-card.leaderboard { border-top: 4px solid #fbbf24; } .nav-option-card.leaderboard:hover { color: #f59e0b; } .nav-option-card.leaderboard i { color: #fbbf24; }
        .logout-button { display: inline-block; margin-top: 2rem; padding: 0.6rem 1.5rem; background-color: #78716c; color: white; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: background-color 0.2s ease; }
        .logout-button:hover { background-color: #57534e; }
        .bonus-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.65); display: flex; justify-content: center; align-items: center; z-index: 1050; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .bonus-popup-overlay.active { opacity: 1; visibility: visible; }
        .bonus-popup-content { background: white; padding: 2rem; border-radius: 1rem; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.25); transform: scale(0.95) translateY(10px); transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease; max-width: 420px; width: 90%; border-top: 6px solid #8b5cf6; position: relative; }
        .bonus-popup-overlay.active .bonus-popup-content { transform: scale(1) translateY(0); }
        .bonus-popup-content .popup-icon-wrapper { width: 80px; height: 80px; background-color: #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: -60px auto 1.5rem auto; box-shadow: 0 8px 15px rgba(139, 92, 246, 0.4); border: 4px solid white; }
        .bonus-popup-content .popup-icon-wrapper i { font-size: 2.5rem; color: white; animation: popupIconAnimation 1s ease-out; }
        @keyframes popupIconAnimation { 0% { transform: scale(0.5); opacity: 0; } 60% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
        .bonus-popup-content h3 { font-family: 'Baloo 2', cursive; font-size: 1.75rem; color: #5b21b6; margin-bottom: 0.75rem; }
        .bonus-popup-content p { color: #4b5563; margin-bottom: 1.5rem; font-size: 1rem; }
        .bonus-popup-close-btn { background-color: #8b5cf6; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background-color 0.2s; }
        .bonus-popup-close-btn:hover { background-color: #7c3aed; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-card">
            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" 
                 alt="Ảnh đại diện của <?php echo htmlspecialchars($user_fullname); ?>" 
                 class="user-avatar"
                 onerror="this.onerror=null; this.src='https://placehold.co/100x100/E0E0E0/757575?text=A';">
            
            <h1 class="welcome-message">Chào Mừng Trở Lại!</h1>
            <p class="user-name"><?php echo htmlspecialchars($user_fullname); ?></p>
            <div class="user-points-display">
                <i class="fas fa-star"></i> Điểm hiện tại: <strong id="currentUserPoints"><?php echo htmlspecialchars($user_points); ?></strong>
            </div>

            <div class="daily-bonus-section">
                <button id="dailyBonusButton" <?php echo $daily_bonus_button_disabled_attr; ?>>
                    <i class="fas <?php echo $can_claim_daily_bonus_today ? 'fa-gift' : 'fa-check-circle'; ?>"></i>
                    <span id="dailyBonusButtonText"><?php echo $daily_bonus_button_text; ?></span>
                </button>
            </div>

            <div class="nav-options-grid">
                <a href="game.php?stage=select_grade" class="nav-option-card play-game">
                    <i class="fas fa-gamepad"></i>
                    <span>Chơi Game</span>
                </a>
                <a href="profile.php" class="nav-option-card profile">
                    <i class="fas fa-user-astronaut"></i>
                    <span>Hồ Sơ Chiến Binh</span>
                </a>
                <a href="leaderboard.php" class="nav-option-card leaderboard">
                    <i class="fas fa-trophy"></i>
                    <span>Bảng Xếp Hạng</span>
                </a>
            </div>

            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt mr-2"></i>Đăng Xuất
            </a>
        </div>
    </div>

    <div class="bonus-popup-overlay" id="bonusPopupOverlay">
        <div class="bonus-popup-content">
            <div class="popup-icon-wrapper">
                 <i class="fas fa-gift" id="popupMainIcon"></i>
            </div>
            <h3 id="bonusPopupTitle">Tuyệt Vời!</h3>
            <p id="bonusPopupMessage">Chúc mừng bạn đã nhận thưởng đăng nhập hàng ngày thành công và được +1 điểm!</p>
            <button class="bonus-popup-close-btn" id="closeBonusPopupButton">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dailyBonusButton = document.getElementById('dailyBonusButton');
            const dailyBonusButtonTextSpan = document.getElementById('dailyBonusButtonText');
            const bonusPopupOverlay = document.getElementById('bonusPopupOverlay');
            const bonusPopupTitle = document.getElementById('bonusPopupTitle');
            const bonusPopupMessage = document.getElementById('bonusPopupMessage');
            const closeBonusPopupButton = document.getElementById('closeBonusPopupButton');
            const currentUserPointsSpan = document.getElementById('currentUserPoints');
            const popupMainIcon = document.getElementById('popupMainIcon');

            if (dailyBonusButton) {
                dailyBonusButton.addEventListener('click', function() {
                    if (this.disabled) return;

                    this.disabled = true;
                    const originalButtonText = dailyBonusButtonTextSpan.textContent;
                    const originalIconClasses = dailyBonusButton.querySelector('i').className;
                    dailyBonusButtonTextSpan.textContent = 'Đang xử lý...';
                    
                    fetch('process_daily_bonus.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errData => {
                                throw new Error(errData.message || `Lỗi HTTP: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            bonusPopupTitle.textContent = 'Thành Công!';
                            popupMainIcon.className = 'fas fa-party-popper';
                            bonusPopupMessage.textContent = data.message || 'Chúc mừng bạn đã nhận thưởng đăng nhập hàng ngày thành công và được +1 điểm!';
                            bonusPopupOverlay.classList.add('active');
                            
                            dailyBonusButtonTextSpan.textContent = '✅ Đã Nhận Thưởng Hôm Nay';
                            const icon = dailyBonusButton.querySelector('i');
                            if (icon) {
                                icon.className = 'fas fa-check-circle';
                            }
                            if (currentUserPointsSpan && data.new_points !== undefined) {
                                currentUserPointsSpan.textContent = data.new_points;
                                <?php // Cập nhật session points phía client nếu cần, nhưng tốt hơn là server đã làm ?>
                                <?php // Ví dụ: (nếu bạn muốn cập nhật session ngay lập tức mà không reload) ?>
                                <?php // $_SESSION['points'] = data.new_points; // Dòng này không chạy được trong JS, chỉ là ý tưởng ?>
                            }
                        } else {
                            bonusPopupTitle.textContent = 'Thất Bại!';
                            popupMainIcon.className = 'fas fa-exclamation-triangle';
                            bonusPopupMessage.textContent = data.message || 'Không thể nhận thưởng lúc này.';
                            bonusPopupOverlay.classList.add('active');
                            
                            if (data.message && data.message.includes("đã nhận thưởng")) {
                                dailyBonusButtonTextSpan.textContent = '✅ Đã Nhận Thưởng Hôm Nay';
                                const icon = dailyBonusButton.querySelector('i');
                                if (icon) icon.className = 'fas fa-check-circle';
                            } else {
                                dailyBonusButton.disabled = false;
                                dailyBonusButtonTextSpan.textContent = originalButtonText;
                                dailyBonusButton.querySelector('i').className = originalIconClasses;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error claiming daily bonus:', error);
                        bonusPopupTitle.textContent = 'Lỗi!';
                        popupMainIcon.className = 'fas fa-times-circle';
                        bonusPopupMessage.textContent = error.message || 'Có lỗi xảy ra khi kết nối đến máy chủ. Vui lòng thử lại sau.';
                        bonusPopupOverlay.classList.add('active');
                        
                        dailyBonusButton.disabled = false;
                        dailyBonusButtonTextSpan.textContent = originalButtonText;
                        dailyBonusButton.querySelector('i').className = originalIconClasses;
                    });
                });
            }

            if (closeBonusPopupButton) {
                closeBonusPopupButton.addEventListener('click', function() {
                    bonusPopupOverlay.classList.remove('active');
                });
            }
            
            if (bonusPopupOverlay) {
                 bonusPopupOverlay.addEventListener('click', function(event) {
                    if (event.target === this) { 
                        bonusPopupOverlay.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html>
