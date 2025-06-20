<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Nếu người dùng đã đăng nhập, chuyển hướng đến dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Bao gồm file cấu hình CSDL và kết nối
require_once 'config/db_config.php';
$conn = connect_db();

$top_players = [];
if ($conn) {
    // Lấy top 3-5 người chơi có điểm cao nhất (không phải admin)
    // Cập nhật câu SQL để lấy thêm school_name
    $sql_top_players = "SELECT fullname, username, school_name, points, avatar_url 
                        FROM users 
                        WHERE is_admin = FALSE AND points > 0
                        ORDER BY points DESC 
                        LIMIT 3"; // Lấy top 3, bạn có thể thay đổi số này
    $result_top_players = $conn->query($sql_top_players);
    if ($result_top_players) {
        while ($row = $result_top_players->fetch_assoc()) {
            $top_players[] = $row;
        }
        $result_top_players->free();
    } else {
        error_log("Lỗi truy vấn top người chơi trên index.php: " . $conn->error);
    }
    close_db_connection($conn);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toán Vui Tiểu Học - Bé Học Toán Giỏi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FCE4EC; /* Lighter Pink background for a softer feel */
        }
        .font-baloo {
            font-family: 'Baloo 2', cursive;
        }
        .custom-header {
            background-color: #FFFFFF; /* White header */
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); /* Softer shadow */
        }
        .logo-text {
            font-family: 'Baloo 2', cursive;
            font-weight: 700;
            font-size: 1.75rem; /* 28px */
            color: #EC4899; /* Pink 500 */
        }
        .nav-link {
            color: #4B5563; /* Gray 600 */
            font-weight: 600;
            margin-left: 1.5rem; /* 24px */
            transition: color 0.3s ease;
            font-size: 0.95rem;
        }
        .nav-link:hover {
            color: #EC4899; /* Pink 500 */
        }
        .btn-primary-action { /* Renamed from btn-quiz for clarity */
            background-color: #22C55E; /* Green 500 */
            color: white;
            font-weight: 700;
            padding: 0.7rem 1.4rem; /* Slightly adjusted padding */
            border-radius: 0.5rem; /* 8px */
            transition: background-color 0.3s ease, transform 0.1s ease;
            box-shadow: 0 3px 6px rgba(34,197,94,0.2);
        }
        .btn-primary-action:hover {
            background-color: #16A34A; /* Green 600 */
            transform: translateY(-1px);
        }
        .btn-register-main {
            background-color: #F97316; /* Orange 500 */
            color: white;
            font-weight: 700;
            padding: 0.85rem 2.2rem; /* Adjusted padding */
            border-radius: 2rem; /* Pill shape */
            transition: background-color 0.3s ease, transform 0.1s ease;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(249,115,22,0.25);
        }
        .btn-register-main:hover {
            background-color: #EA580C; /* Orange 600 */
            transform: translateY(-2px);
        }
        .btn-login-secondary { /* Renamed for clarity */
            background-color: #3B82F6; /* Blue 500 */
            color: white;
            font-weight: 600; /* Adjusted font-weight */
            padding: 0.7rem 1.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, transform 0.1s ease;
            box-shadow: 0 3px 6px rgba(59,130,246,0.2);
        }
        .btn-login-secondary:hover {
            background-color: #2563EB; /* Blue 600 */
            transform: translateY(-1px);
        }
        
        .main-content-card {
            background-color: #FFFFFF; /* White card for cleaner look */
            border-radius: 1rem; /* 16px */
            padding: 2.5rem; /* Increased padding */
            margin-top: 1.5rem; /* Adjusted margin */
            box-shadow: 0 8px 25px rgba(0,0,0,0.08); /* Refined shadow */
        }
        .star-rating span {
            color: #FACC15; /* Yellow 400 */
            font-size: 1.3rem; /* Adjusted size */
        }
        
        /* --- Bảng Xếp Hạng Mini --- */
        .leaderboard-mini-container {
            background-color: #FFF7ED; /* Màu nền nhẹ nhàng cho bảng xếp hạng */
            border-radius: 0.75rem; /* 12px */
            padding: 1.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .leaderboard-mini-title {
            font-family: 'Baloo 2', cursive;
            font-size: 1.5rem; /* 24px */
            font-weight: 700;
            color: #D97706; /* Amber 600 */
            text-align: center;
            margin-bottom: 1rem;
        }
        .leaderboard-mini-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 0;
            border-bottom: 1px solid #FDE68A; /* Amber 200 */
        }
        .leaderboard-mini-item:last-child {
            border-bottom: none;
        }
        .leaderboard-mini-rank {
            font-family: 'Baloo 2', cursive;
            font-size: 1.1rem;
            font-weight: 700;
            color: #92400E; /* Amber 700 */
            width: 2.5rem; /* ~40px */
            text-align: center;
        }
        .leaderboard-mini-avatar {
            width: 36px; /* Giảm kích thước avatar */
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 0.5rem;
            margin-right: 0.75rem;
            border: 2px solid #FDBA74; /* Amber 300 */
        }
        .leaderboard-mini-info {
            flex-grow: 1;
        }
        .leaderboard-mini-name {
            font-weight: 600;
            color: #1F2937; /* Gray 800 */
            font-size: 0.9rem;
        }
        .leaderboard-mini-sub-info { 
            font-size: 0.75rem;
            color: #6B7280; /* Gray 500 */
        }
        .leaderboard-mini-points {
            font-family: 'Baloo 2', cursive;
            font-size: 1rem;
            font-weight: 700;
            color: #F59E0B; /* Amber 500 */
        }
        .leaderboard-mini-points i {
            margin-right: 0.25rem;
            color: #FACC15; /* Yellow 400 */
        }
        .no-leaderboard-data {
            text-align: center;
            color: #78350F; /* Amber 800 */
            padding: 1rem 0;
            font-style: italic;
        }
        /* --- Kết thúc Bảng Xếp Hạng Mini --- */

        .text-shadow-custom {
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
        }
        .section-title {
            font-family: 'Baloo 2', cursive;
            font-size: 1.75rem; /* 28px */
            font-weight: 700;
            color: #374151; /* Gray 700 */
            margin-bottom: 0.75rem; /* 12px */
        }
        .instruction-item {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem; /* 8px */
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .instruction-item strong {
            font-weight: 600;
        }
         .map-placeholder {
            background-color: #E0F2FE; /* Sky 100 */
            border: 2px dashed #7DD3FC; /* Sky 300 */
            border-radius: 0.75rem; /* 12px */
            padding: 1.5rem;
            min-height: 260px; 
            display: flex;
            flex-direction: column; /* Allow text to stack */
            align-items: center;
            justify-content: center;
            color: #0369A1; /* Sky 700 */
            font-weight: 500; /* Adjusted font-weight */
            text-align: center;
        }
    </style>
</head>
<body class="min-h-screen">

    <header class="custom-header">
        <div class="container mx-auto flex items-center justify-between">
            <div class="logo-text">Toán Vui</div>
            <nav class="hidden md:flex items-center">
                <a href="index.php" class="nav-link">Trang chủ</a>
                <a href="#features" class="nav-link">Tính năng</a>
                <a href="#how-to-play" class="nav-link">Cách chơi</a>
            </nav>
            <div>
                <a href="login.php" class="btn-primary-action">Bắt Đầu!</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto p-4 md:px-6 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-8 items-start">
            <div class="md:col-span-7 main-content-card">
                <h1 class="font-baloo text-4xl md:text-5xl font-extrabold text-pink-600 mb-3 text-shadow-custom leading-tight">
                    Thử Thách Toán Học Vui Nhộn!
                </h1>
                <div class="star-rating mb-4">
                    <span>★</span><span>★</span><span>★</span><span>★</span><span class="text-gray-300">★</span>
                    <span class="text-sm text-gray-500 ml-2">(Dựa trên 1,234 đánh giá)</span>
                </div>
                <p class="text-gray-600 mb-8 text-base md:text-lg leading-relaxed">
                    Chào mừng các em đến với sân chơi toán học đầy màu sắc! Khám phá những bài toán thú vị, rèn luyện tư duy và chinh phục các thử thách hấp dẫn.
                </p>
                <div class="mb-10">
                    <a href="register.php" class="btn-register-main inline-block">Đăng Ký Miễn Phí</a>
                </div>

                <div class="border-t pt-6">
                    <h3 class="section-title text-gray-700 mb-4">Đã có tài khoản?</h3>
                    <div class="flex items-center space-x-3 mb-4">
                        <img src="https://placehold.co/48x48/3B82F6/FFFFFF?text=AVT" alt="Avatar" class="rounded-full shadow-md">
                        <p class="text-gray-700 font-medium">Chào mừng bạn trở lại!</p>
                    </div>
                    <a href="login.php" class="btn-login-secondary inline-block">Đăng Nhập Ngay</a>
                </div>
            </div>

            <div class="md:col-span-5">
                <div class="leaderboard-mini-container">
                    <h3 class="leaderboard-mini-title"><i class="fas fa-trophy mr-2"></i>Bảng Vàng Anh Hùng</h3>
                    <?php if (!empty($top_players)): ?>
                        <?php foreach ($top_players as $index => $player): ?>
                            <?php
                                $rank_display = $index + 1;
                                $avatar_url_display = $player['avatar_url'] 
                                    ? htmlspecialchars($player['avatar_url']) 
                                    : 'https://placehold.co/36x36/cccccc/757575?text=' . strtoupper(substr($player['fullname'], 0, 1));
                            ?>
                            <div class="leaderboard-mini-item">
                                <span class="leaderboard-mini-rank">
                                    <?php if ($rank_display == 1): ?>
                                        <i class="fas fa-crown text-yellow-400"></i>
                                    <?php elseif ($rank_display == 2): ?>
                                        <i class="fas fa-medal text-gray-400"></i>
                                    <?php elseif ($rank_display == 3): ?>
                                        <i class="fas fa-award text-yellow-600"></i>
                                    <?php else: ?>
                                        <?php echo $rank_display; ?>
                                    <?php endif; ?>
                                </span>
                                <img src="<?php echo $avatar_url_display; ?>" alt="Avatar của <?php echo htmlspecialchars($player['fullname']); ?>" class="leaderboard-mini-avatar" onerror="this.src='https://placehold.co/36x36/E0E0E0/757575?text=Lỗi'; this.onerror=null;">
                                <div class="leaderboard-mini-info">
                                    <div class="leaderboard-mini-name"><?php echo htmlspecialchars($player['fullname']); ?></div>
                                    <div class="leaderboard-mini-sub-info">
                                        <?php if (!empty($player['school_name'])): ?>
                                            <i class="fas fa-school fa-xs mr-1 text-sky-500"></i><?php echo htmlspecialchars($player['school_name']); ?>
                                        <?php else: ?>
                                            <span class="italic">Chưa có tên trường</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="leaderboard-mini-points">
                                    <i class="fas fa-star"></i><?php echo htmlspecialchars($player['points']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                         <div class="text-center mt-4">
                            <a href="leaderboard.php" class="text-sm text-pink-600 hover:text-pink-700 font-semibold">
                                Xem Bảng Xếp Hạng Đầy Đủ <i class="fas fa-arrow-right fa-xs ml-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="no-leaderboard-data">Chưa có anh hùng nào trên bảng vàng. Hãy là người đầu tiên!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="features" class="main-content-card mt-10 md:mt-12">
            <h2 class="section-title text-center mb-6">Tại Sao Chọn Toán Vui?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-center">
                <div class="p-4 bg-sky-50 rounded-lg shadow-sm">
                    <div class="text-3xl mb-2">🧠</div>
                    <h3 class="font-baloo text-lg font-semibold text-sky-700 mb-1">Rèn Luyện Tư Duy</h3>
                    <p class="text-sm text-gray-600">Câu hỏi đa dạng, kích thích sáng tạo.</p>
                </div>
                <div class="p-4 bg-green-50 rounded-lg shadow-sm">
                    <div class="text-3xl mb-2">🎮</div>
                    <h3 class="font-baloo text-lg font-semibold text-green-700 mb-1">Học Mà Chơi</h3>
                    <p class="text-sm text-gray-600">Giao diện thân thiện, nhân vật ngộ nghĩnh.</p>
                </div>
                <div class="p-4 bg-yellow-50 rounded-lg shadow-sm">
                    <div class="text-3xl mb-2">🏆</div>
                    <h3 class="font-baloo text-lg font-semibold text-yellow-700 mb-1">Huy Hiệu & Điểm</h3>
                    <p class="text-sm text-gray-600">Thi đua và sưu tập phần thưởng hấp dẫn.</p>
                </div>
            </div>
        </div>
        
        <div class="main-content-card mt-10 md:mt-12">
            <h2 class="section-title text-center mb-6">Khám Phá Bản Đồ Tri Thức!</h2>
            <p class="text-gray-600 text-center mb-6 max-w-xl mx-auto">
                Vượt qua các thử thách trên bản đồ, mở khóa những vùng đất mới và trở thành nhà vô địch toán học!
            </p>
            <div class="map-placeholder">
                <img src="https://placehold.co/500x300/90EE90/2E8B57?text=Bản+Đồ+Phiêu+Lưu" alt="Bản đồ game placeholder" class="rounded-md shadow-md max-w-full h-auto">
                <p class="mt-3 text-sm italic">Các nhân vật sẽ di chuyển và khám phá trên bản đồ này.</p>
            </div>
        </div>


         <div id="how-to-play" class="main-content-card mt-10 md:mt-12 text-left">
            <h2 class="section-title text-center mb-8">🚀 Cách Chơi Đơn Giản 🚀</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div class="instruction-item bg-sky-50 border-l-4 border-sky-500">
                    <strong class="text-sky-700">1. Chọn Lớp & Nhân Vật:</strong> Lựa chọn cấp độ và người bạn đồng hành để bắt đầu cuộc phiêu lưu toán học.
                </div>
                <div class="instruction-item bg-sky-50 border-l-4 border-sky-500">
                    <strong class="text-sky-700">2. Trả Lời Câu Hỏi:</strong> Mỗi màn chơi gồm 10 câu hỏi thử thách. Hãy chọn 1 trong 4 đáp án được đưa ra.
                </div>
                <div class="instruction-item bg-green-50 border-l-4 border-green-500">
                    <strong class="text-green-700">3. Đúng Thì Tiến Bước:</strong> Trả lời chính xác để giúp nhân vật của bạn tiến về phía trước trên bản đồ.
                </div>
                <div class="instruction-item bg-red-50 border-l-4 border-red-500">
                    <strong class="text-red-700">4. Sai Mất Mạng Chơi:</strong> Trả lời sai, bạn sẽ phải thử lại câu hỏi đó và mất một mạng. Cẩn thận kẻo hết 3 mạng nhé!
                </div>
                 <div class="instruction-item bg-yellow-50 border-l-4 border-yellow-500 col-span-1 sm:col-span-2">
                    <strong class="text-yellow-800">5. Thắng Nhận Thưởng:</strong> Hoàn thành bản đồ để được cộng điểm, nhận huy hiệu và mở khóa các thử thách mới!
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center p-8 mt-8 bg-white border-t border-gray-200">
        <p class="text-sm text-gray-500">
            &copy; <?php echo date("Y"); ?> Toán Vui Tiểu Học.
            <br>
            <span class="text-xs">Phát triển với ❤️ bởi Zabu Wolf</span>
        </p>
    </footer>

    <script>
        console.log("Trang chủ với giao diện mới và bảng xếp hạng mini đã tải!");
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetElement = document.querySelector(this.getAttribute('href'));
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>