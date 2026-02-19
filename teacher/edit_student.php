<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "แก้ไขข้อมูลนักเรียน";

// Get student ID
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($student_id <= 0) {
    setFlashMessage('error', 'ไม่พบข้อมูลนักเรียน');
    header("Location: students.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $nickname = sanitize($_POST['nickname']);
    $student_code = sanitize($_POST['student_code']);
    $class_level = sanitize($_POST['class_level']);
    $room = sanitize($_POST['room']);
    $number = (int)$_POST['number'];

    // Validate
    $errors = [];
    if (empty($full_name)) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if (empty($student_code)) $errors[] = 'กรุณากรอกรหัสนักเรียน';
    if (empty($class_level)) $errors[] = 'กรุณาเลือกชั้นเรียน';
    if (empty($room)) $errors[] = 'กรุณากรอกห้อง';
    if ($number <= 0) $errors[] = 'กรุณากรอกเลขที่';

    // Check duplicate student_code (excluding current student)
    if (empty($errors)) {
        $chk = $conn->prepare("SELECT id FROM students WHERE student_code = ? AND id != ?");
        $chk->bind_param("si", $student_code, $student_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errors[] = 'รหัสนักเรียนนี้ถูกใช้แล้ว';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE students SET full_name = ?, nickname = ?, student_code = ?, class_level = ?, room = ?, number = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $full_name, $nickname, $student_code, $class_level, $room, $number, $student_id);
        if ($stmt->execute()) {
            setFlashMessage('success', 'บันทึกการแก้ไขเรียบร้อยแล้ว');
            header("Location: students.php");
            exit();
        } else {
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึก: ' . $conn->error;
        }
    }
}

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    setFlashMessage('error', 'ไม่พบข้อมูลนักเรียน');
    header("Location: students.php");
    exit();
}

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-24 h-24 bg-indigo-500/20 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden border-2 border-indigo-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-tie text-4xl text-indigo-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded bg-indigo-600 text-white"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-check w-8"></i> เช็คชื่อ</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="grading.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-star w-8"></i> ให้คะแนน</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <!-- Header -->
        <div class="glass-panel p-6">
            <div class="flex items-center gap-4 mb-2">
                <a href="students.php" class="w-10 h-10 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center text-white transition">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold">แก้ไขข้อมูลนักเรียน</h2>
                    <p class="text-gray-400 text-sm">แก้ไขข้อมูลของ <?php echo htmlspecialchars($student['full_name']); ?></p>
                </div>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if (!empty($errors)): ?>
            <div class="glass-panel p-4 border border-red-500/50 bg-red-500/10">
                <p class="text-red-400 font-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>พบข้อผิดพลาด:</p>
                <ul class="text-red-300 text-sm list-disc list-inside">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo $e; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="glass-panel p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-20 h-20 bg-gray-700 rounded-full overflow-hidden flex-shrink-0">
                    <?php if ($student['profile_image']): ?>
                        <img src="../<?php echo $student['profile_image']; ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                            <i class="fas fa-user text-2xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                    <p class="text-gray-400 text-sm">รหัส: <?php echo htmlspecialchars($student['student_code']); ?></p>
                </div>
            </div>

            <form method="POST" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Full Name -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-user mr-2 text-indigo-400"></i>ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Nickname -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-smile mr-2 text-indigo-400"></i>ชื่อเล่น</label>
                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($student['nickname'] ?? ''); ?>"
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Student Code -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-id-badge mr-2 text-indigo-400"></i>รหัสนักเรียน <span class="text-red-500">*</span></label>
                        <input type="text" name="student_code" value="<?php echo htmlspecialchars($student['student_code']); ?>" required
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Class Level -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-school mr-2 text-indigo-400"></i>ชั้นเรียน <span class="text-red-500">*</span></label>
                        <select name="class_level" required
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                            <?php
                                $levels = ['ม.1','ม.2','ม.3','ม.4','ม.5','ม.6','ป.1','ป.2','ป.3','ป.4','ป.5','ป.6'];
                                foreach ($levels as $lv) {
                                    $sel = ($student['class_level'] == $lv) ? 'selected' : '';
                                    echo "<option value=\"$lv\" $sel>$lv</option>";
                                }
                            ?>
                        </select>
                    </div>

                    <!-- Room -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-door-open mr-2 text-indigo-400"></i>ห้อง <span class="text-red-500">*</span></label>
                        <input type="text" name="room" value="<?php echo htmlspecialchars($student['room']); ?>" required
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                    </div>

                    <!-- Number -->
                    <div>
                        <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-hashtag mr-2 text-indigo-400"></i>เลขที่ <span class="text-red-500">*</span></label>
                        <input type="number" name="number" value="<?php echo $student['number']; ?>" min="1" required
                            class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                    </div>
                </div>

                <!-- Info Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2">
                    <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-indigo-400"><?php echo $student['xp']; ?></div>
                        <div class="text-xs text-gray-400">XP</div>
                    </div>
                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-yellow-400"><?php echo $student['points']; ?></div>
                        <div class="text-xs text-gray-400">แต้ม</div>
                    </div>
                    <?php
                        $att_total = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id")->fetch_assoc()['c'];
                        $att_present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id AND status = 'present'")->fetch_assoc()['c'];
                    ?>
                    <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-green-400"><?php echo $att_present; ?></div>
                        <div class="text-xs text-gray-400">มาเรียน (วัน)</div>
                    </div>
                    <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-blue-400"><?php echo $att_total; ?></div>
                        <div class="text-xs text-gray-400">บันทึกทั้งหมด</div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-700">
                    <button type="submit" class="btn btn-primary px-6">
                        <i class="fas fa-save mr-2"></i> บันทึกการแก้ไข
                    </button>
                    <a href="students.php" class="btn btn-secondary px-6">
                        <i class="fas fa-times mr-2"></i> ยกเลิก
                    </a>
                    <a href="student_profile.php?id=<?php echo $student_id; ?>" class="btn btn-secondary px-6 ml-auto">
                        <i class="fas fa-id-card mr-2"></i> ดูโปรไฟล์
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
