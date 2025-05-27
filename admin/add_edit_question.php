<?php
session_start();
require_once '../config/db_config.php'; // Đi ra một cấp để vào config

// Kiểm tra xem admin đã đăng nhập chưa
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['admin_login_error'] = "Vui lòng đăng nhập để truy cập trang quản trị.";
    header('Location: admin_login.php');
    exit;
}

$admin_fullname = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Kết nối CSDL
$conn = connect_db();
if (!$conn) {
    die("Lỗi hệ thống nghiêm trọng: Không thể kết nối đến cơ sở dữ liệu.");
}

// Khởi tạo các biến
$page_title = "Thêm Câu Hỏi Mới";
$form_action = "add_edit_question.php"; // Mặc định là thêm mới
$question_id = null;
$current_question = [
    'grade_id' => '',
    'topic_id' => '',
    'question_text' => '',
    'option_1' => '',
    'option_2' => '',
    'option_3' => '',
    'option_4' => '',
    'correct_option' => '',
    'difficulty' => 'medium' // Mặc định độ khó
];
$errors = $_SESSION['form_errors_admin'] ?? [];
$old_input = $_SESSION['old_form_input_admin'] ?? [];
unset($_SESSION['form_errors_admin'], $_SESSION['old_form_input_admin']);


// Kiểm tra xem có phải là chế độ sửa không (có 'id' trong URL)
if (isset($_GET['id'])) {
    $question_id = (int)$_GET['id'];
    $page_title = "Sửa Câu Hỏi (ID: $question_id)";
    $form_action = "add_edit_question.php?id=" . $question_id; // Action sẽ bao gồm id

    $sql_fetch_question = "SELECT * FROM questions WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_question);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $question_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $current_question = $result_fetch->fetch_assoc();
        } else {
            $_SESSION['error_message_admin'] = "Không tìm thấy câu hỏi với ID: $question_id";
            header('Location: manage_questions.php');
            exit;
        }
        $stmt_fetch->close();
    } else {
        $_SESSION['error_message_admin'] = "Lỗi chuẩn bị truy vấn câu hỏi: " . $conn->error;
        header('Location: manage_questions.php');
        exit;
    }
}

// Xử lý khi form được submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $current_question['grade_id'] = trim($_POST['grade_id'] ?? '');
    $current_question['topic_id'] = trim($_POST['topic_id'] ?? '');
    $current_question['question_text'] = trim($_POST['question_text'] ?? '');
    $current_question['option_1'] = trim($_POST['option_1'] ?? '');
    $current_question['option_2'] = trim($_POST['option_2'] ?? '');
    $current_question['option_3'] = trim($_POST['option_3'] ?? '');
    $current_question['option_4'] = trim($_POST['option_4'] ?? '');
    $current_question['correct_option'] = trim($_POST['correct_option'] ?? '');
    $current_question['difficulty'] = trim($_POST['difficulty'] ?? 'medium');

    // Validate dữ liệu (cần chi tiết hơn)
    if (empty($current_question['grade_id'])) $errors['grade_id'] = "Vui lòng chọn khối lớp.";
    if (empty($current_question['topic_id'])) $errors['topic_id'] = "Vui lòng chọn chủ đề.";
    if (empty($current_question['question_text'])) $errors['question_text'] = "Nội dung câu hỏi không được để trống.";
    if (empty($current_question['option_1'])) $errors['option_1'] = "Lựa chọn 1 không được để trống.";
    if (empty($current_question['option_2'])) $errors['option_2'] = "Lựa chọn 2 không được để trống.";
    if (empty($current_question['option_3'])) $errors['option_3'] = "Lựa chọn 3 không được để trống.";
    if (empty($current_question['option_4'])) $errors['option_4'] = "Lựa chọn 4 không được để trống.";
    if (empty($current_question['correct_option']) || !in_array($current_question['correct_option'], [1, 2, 3, 4])) {
        $errors['correct_option'] = "Vui lòng chọn đáp án đúng hợp lệ (1-4).";
    }
    if (empty($current_question['difficulty']) || !in_array($current_question['difficulty'], ['easy', 'medium', 'hard'])) {
        $errors['difficulty'] = "Vui lòng chọn độ khó hợp lệ.";
    }

    if (empty($errors)) {
        if ($question_id) { // Chế độ Sửa
            $sql_update = "UPDATE questions SET grade_id=?, topic_id=?, question_text=?, option_1=?, option_2=?, option_3=?, option_4=?, correct_option=?, difficulty=? WHERE id=?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("iisssssisi", 
                    $current_question['grade_id'], $current_question['topic_id'], $current_question['question_text'],
                    $current_question['option_1'], $current_question['option_2'], $current_question['option_3'], $current_question['option_4'],
                    $current_question['correct_option'], $current_question['difficulty'], $question_id
                );
                if ($stmt_update->execute()) {
                    $_SESSION['success_message_admin'] = "Đã cập nhật câu hỏi (ID: $question_id) thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi cập nhật câu hỏi: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị cập nhật: " . $conn->error;
            }
        } else { // Chế độ Thêm mới
            $sql_insert = "INSERT INTO questions (grade_id, topic_id, question_text, option_1, option_2, option_3, option_4, correct_option, difficulty, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                 $stmt_insert->bind_param("iisssssis", 
                    $current_question['grade_id'], $current_question['topic_id'], $current_question['question_text'],
                    $current_question['option_1'], $current_question['option_2'], $current_question['option_3'], $current_question['option_4'],
                    $current_question['correct_option'], $current_question['difficulty']
                );
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message_admin'] = "Đã thêm câu hỏi mới thành công!";
                } else {
                    $_SESSION['error_message_admin'] = "Lỗi khi thêm câu hỏi mới: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                 $_SESSION['error_message_admin'] = "Lỗi chuẩn bị thêm mới: " . $conn->error;
            }
        }
        header('Location: manage_questions.php');
        exit;
    } else {
        // Lưu lỗi và dữ liệu cũ vào session để hiển thị lại trên form
        $_SESSION['form_errors_admin'] = $errors;
        $_SESSION['old_form_input_admin'] = $_POST; // Lưu toàn bộ POST data
        header('Location: ' . $form_action); // Chuyển hướng lại form hiện tại (thêm hoặc sửa)
        exit;
    }
}


// Lấy danh sách Khối lớp và Chủ đề để điền vào dropdown
$grades_options = [];
$sql_grades = "SELECT id, name FROM grades ORDER BY id ASC";
$result_grades = $conn->query($sql_grades);
if ($result_grades) {
    while ($row = $result_grades->fetch_assoc()) {
        $grades_options[] = $row;
    }
    $result_grades->free();
}

$topics_options = [];
// Nếu đang sửa, hoặc nếu có grade_id được chọn từ lần submit lỗi trước đó, tải topic theo grade đó
$grade_id_for_topics = $current_question['grade_id'] ?: ($old_input['grade_id'] ?? null);

if ($grade_id_for_topics) {
    $sql_topics = "SELECT id, name FROM topics WHERE grade_id = ? ORDER BY sort_order ASC, name ASC";
    $stmt_topics_form = $conn->prepare($sql_topics);
    if ($stmt_topics_form) {
        $stmt_topics_form->bind_param("i", $grade_id_for_topics);
        $stmt_topics_form->execute();
        $result_topics_form = $stmt_topics_form->get_result();
        while ($row = $result_topics_form->fetch_assoc()) {
            $topics_options[] = $row;
        }
        $stmt_topics_form->close();
    }
} else {
    // Nếu thêm mới và chưa chọn grade, có thể tải tất cả topic hoặc để trống và yêu cầu chọn grade trước
    // Để đơn giản, ban đầu sẽ để trống, JS sẽ cập nhật sau khi chọn grade
}


// Điền lại giá trị từ old_input nếu có lỗi validate
if (!empty($old_input)) {
    foreach ($old_input as $key => $value) {
        if (isset($current_question[$key])) { // Chỉ ghi đè các key có trong $current_question
            $current_question[$key] = htmlspecialchars($value);
        }
    }
}


?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F9FAFB; }
        .admin-header { background-color: #4F46E5; color: white; }
        .sidebar { background-color: #1F2937; color: #D1D5DB; }
        .sidebar a { color: #D1D5DB; transition: background-color 0.2s, color 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #374151; color: white; }
        .content-card { background-color: white; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06); }
        .form-input, .form-select, .form-textarea {
            border-radius: 0.375rem; border: 1px solid #D1D5DB; padding: 0.5rem 0.75rem; width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9rem;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #6366F1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .form-label { display: block; text-sm font-medium text-gray-700 mb-1; }
        .btn-save { background-color: #10B981; color: white; }
        .btn-save:hover { background-color: #059669; }
        .btn-cancel { background-color: #6B7280; color: white; }
        .btn-cancel:hover { background-color: #4B5563; }
        .error-text { color: #EF4444; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
</head>
<body class="flex h-screen">

    <aside class="sidebar w-64 min-h-screen p-4 space-y-2">
        <div class="text-center py-4">
            <a href="index.php" class="font-baloo text-2xl font-bold text-white">Admin Panel</a>
        </div>
        <nav>
            <a href="index.php" class="block py-2.5 px-4 rounded"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
            <a href="manage_questions.php" class="block py-2.5 px-4 rounded active"><i class="fas fa-question-circle mr-2"></i>Quản lý Câu hỏi</a>
            <a href="manage_users.php" class="block py-2.5 px-4 rounded"><i class="fas fa-users mr-2"></i>Quản lý Người dùng</a>
            <a href="manage_characters.php" class="block py-2.5 px-4 rounded"><i class="fas fa-user-astronaut mr-2"></i>Quản lý Nhân vật</a>
            <a href="manage_topics.php" class="block py-2.5 px-4 rounded"><i class="fas fa-book-open mr-2"></i>Quản lý Chủ đề</a>
            <a href="manage_grades.php" class="block py-2.5 px-4 rounded"><i class="fas fa-graduation-cap mr-2"></i>Quản lý Khối lớp</a>
            <a href="manage_badges.php" class="block py-2.5 px-4 rounded"><i class="fas fa-medal mr-2"></i>Quản lý Huy hiệu</a>
            <a href="settings.php" class="block py-2.5 px-4 rounded"><i class="fas fa-cog mr-2"></i>Cài đặt chung</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="admin-header p-4 shadow-md flex justify-between items-center">
            <div><h1 class="text-xl font-semibold"><?php echo htmlspecialchars($page_title); ?></h1></div>
            <div>
                <span class="mr-3">Chào, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
                <a href="admin_logout.php" class="text-sm hover:text-indigo-200"><i class="fas fa-sign-out-alt mr-1"></i>Đăng xuất</a>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-y-auto">
            <div class="content-card p-6 max-w-3xl mx-auto">
                <form action="<?php echo htmlspecialchars($form_action); ?>" method="POST" class="space-y-6">
                    
                    <div>
                        <label for="grade_id" class="form-label">Khối lớp <span class="text-red-500">*</span></label>
                        <select id="grade_id" name="grade_id" class="form-select" required>
                            <option value="">-- Chọn Khối lớp --</option>
                            <?php foreach ($grades_options as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo ($current_question['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['grade_id'])): ?><p class="error-text"><?php echo $errors['grade_id']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="topic_id" class="form-label">Chủ đề <span class="text-red-500">*</span></label>
                        <select id="topic_id" name="topic_id" class="form-select" required>
                            <option value="">-- Chọn Chủ đề (Chọn Khối trước) --</option>
                            <?php foreach ($topics_options as $topic): // $topics_options được tải dựa trên grade_id ở trên ?>
                                <option value="<?php echo $topic['id']; ?>" <?php echo ($current_question['topic_id'] == $topic['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['topic_id'])): ?><p class="error-text"><?php echo $errors['topic_id']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="question_text" class="form-label">Nội dung Câu hỏi <span class="text-red-500">*</span></label>
                        <textarea id="question_text" name="question_text" rows="4" class="form-textarea" required placeholder="Nhập nội dung câu hỏi..."><?php echo htmlspecialchars($current_question['question_text']); ?></textarea>
                        <?php if (isset($errors['question_text'])): ?><p class="error-text"><?php echo $errors['question_text']; ?></p><?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="option_1" class="form-label">Lựa chọn 1 <span class="text-red-500">*</span></label>
                            <input type="text" id="option_1" name="option_1" class="form-input" required value="<?php echo htmlspecialchars($current_question['option_1']); ?>">
                            <?php if (isset($errors['option_1'])): ?><p class="error-text"><?php echo $errors['option_1']; ?></p><?php endif; ?>
                        </div>
                        <div>
                            <label for="option_2" class="form-label">Lựa chọn 2 <span class="text-red-500">*</span></label>
                            <input type="text" id="option_2" name="option_2" class="form-input" required value="<?php echo htmlspecialchars($current_question['option_2']); ?>">
                            <?php if (isset($errors['option_2'])): ?><p class="error-text"><?php echo $errors['option_2']; ?></p><?php endif; ?>
                        </div>
                        <div>
                            <label for="option_3" class="form-label">Lựa chọn 3 <span class="text-red-500">*</span></label>
                            <input type="text" id="option_3" name="option_3" class="form-input" required value="<?php echo htmlspecialchars($current_question['option_3']); ?>">
                            <?php if (isset($errors['option_3'])): ?><p class="error-text"><?php echo $errors['option_3']; ?></p><?php endif; ?>
                        </div>
                        <div>
                            <label for="option_4" class="form-label">Lựa chọn 4 <span class="text-red-500">*</span></label>
                            <input type="text" id="option_4" name="option_4" class="form-input" required value="<?php echo htmlspecialchars($current_question['option_4']); ?>">
                            <?php if (isset($errors['option_4'])): ?><p class="error-text"><?php echo $errors['option_4']; ?></p><?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Đáp án đúng <span class="text-red-500">*</span></label>
                        <div class="mt-2 space-y-2 md:space-y-0 md:flex md:space-x-4">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <label class="inline-flex items-center">
                                <input type="radio" name="correct_option" value="<?php echo $i; ?>" class="form-radio text-indigo-600 focus:ring-indigo-500" <?php echo ($current_question['correct_option'] == $i) ? 'checked' : ''; ?> required>
                                <span class="ml-2 text-sm text-gray-700">Lựa chọn <?php echo $i; ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <?php if (isset($errors['correct_option'])): ?><p class="error-text"><?php echo $errors['correct_option']; ?></p><?php endif; ?>
                    </div>

                    <div>
                        <label for="difficulty" class="form-label">Độ khó <span class="text-red-500">*</span></label>
                        <select id="difficulty" name="difficulty" class="form-select" required>
                            <option value="easy" <?php echo ($current_question['difficulty'] == 'easy') ? 'selected' : ''; ?>>Dễ</option>
                            <option value="medium" <?php echo ($current_question['difficulty'] == 'medium') ? 'selected' : ''; ?>>Trung bình</option>
                            <option value="hard" <?php echo ($current_question['difficulty'] == 'hard') ? 'selected' : ''; ?>>Khó</option>
                        </select>
                        <?php if (isset($errors['difficulty'])): ?><p class="error-text"><?php echo $errors['difficulty']; ?></p><?php endif; ?>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <a href="manage_questions.php" class="px-4 py-2 rounded-md text-sm font-medium btn-cancel">Hủy</a>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium btn-save">
                            <i class="fas fa-save mr-1"></i> <?php echo $question_id ? 'Lưu Thay Đổi' : 'Thêm Câu Hỏi'; ?>
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>
    <?php
    if ($conn) {
        close_db_connection($conn);
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const gradeSelect = document.getElementById('grade_id');
            const topicSelect = document.getElementById('topic_id');
            const currentTopicId = '<?php echo $current_question['topic_id'] ?? ($old_input['topic_id'] ?? ''); ?>';

            function fetchTopics(gradeId) {
                if (!gradeId) {
                    topicSelect.innerHTML = '<option value="">-- Chọn Chủ đề (Chọn Khối trước) --</option>';
                    topicSelect.disabled = true;
                    return;
                }
                // Sử dụng '../' vì script này nằm trong thư mục admin
                fetch(`../api_get_topics.php?grade_id=${gradeId}`) 
                    .then(response => response.json())
                    .then(data => {
                        topicSelect.innerHTML = '<option value="">-- Chọn Chủ đề --</option>';
                        if (data.success && data.topics) {
                            data.topics.forEach(topic => {
                                const option = document.createElement('option');
                                option.value = topic.id;
                                option.textContent = topic.name;
                                if (topic.id == currentTopicId) { // Giữ lại topic đã chọn nếu có
                                    option.selected = true;
                                }
                                topicSelect.appendChild(option);
                            });
                            topicSelect.disabled = false;
                        } else {
                            topicSelect.innerHTML = '<option value="">-- Không có chủ đề cho khối này --</option>';
                            topicSelect.disabled = true;
                            console.error('Error fetching topics:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        topicSelect.innerHTML = '<option value="">-- Lỗi tải chủ đề --</option>';
                        topicSelect.disabled = true;
                    });
            }

            if (gradeSelect) {
                gradeSelect.addEventListener('change', function () {
                    fetchTopics(this.value);
                });

                // Tải topics ban đầu nếu grade_id đã được chọn (ví dụ khi sửa hoặc có lỗi validate)
                if (gradeSelect.value) {
                    fetchTopics(gradeSelect.value);
                } else {
                    topicSelect.disabled = true;
                }
            }
        });
    </script>
</body>
</html>
