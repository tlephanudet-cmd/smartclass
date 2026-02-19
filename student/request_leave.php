<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$student_id = $_SESSION['student_id'];
$pageTitle = "ขออนุญาตลา";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = sanitize($_POST['leave_type']);
    $start_date = sanitize($_POST['start_date']);
    $end_date   = sanitize($_POST['end_date']);
    $reason     = sanitize($_POST['reason']);
    $file_path  = null;

    // Validate dates
    if ($end_date < $start_date) {
        setFlashMessage('error', 'วันที่สิ้นสุดต้องมากกว่าหรือเท่ากับวันที่เริ่มต้น');
        header("Location: request_leave.php");
        exit();
    }

    // Handle file upload
    if (isset($_FILES['medical_file']) && $_FILES['medical_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file = $_FILES['medical_file'];

        if (!in_array($file['type'], $allowed_types)) {
            setFlashMessage('error', 'ไฟล์ที่อนุญาต: JPG, PNG, WebP, PDF เท่านั้น');
            header("Location: request_leave.php");
            exit();
        }
        if ($file['size'] > $max_size) {
            setFlashMessage('error', 'ขนาดไฟล์ต้องไม่เกิน 5MB');
            header("Location: request_leave.php");
            exit();
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'leave_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        $upload_dir = '../uploads/leaves/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $file_path = 'uploads/leaves/' . $filename;
        }
    }

    // Insert to DB
    $stmt = $conn->prepare("INSERT INTO leave_requests (student_id, leave_type, start_date, end_date, reason, file_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $student_id, $leave_type, $start_date, $end_date, $reason, $file_path);

    if ($stmt->execute()) {
        setFlashMessage('success', 'ส่งใบลาเรียบร้อยแล้ว รอครูตรวจสอบ');
        header("Location: request_leave.php");
        exit();
    } else {
        setFlashMessage('error', 'เกิดข้อผิดพลาด: ' . $stmt->error);
        header("Location: request_leave.php");
        exit();
    }
}

// Fetch leave history
$leaves = $conn->query("SELECT * FROM leave_requests WHERE student_id = $student_id ORDER BY created_at DESC");

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 bg-emerald-600 text-white rounded"><i class="fas fa-calendar-minus w-8"></i> ขอลา</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <!-- Leave Request Form -->
        <div class="glass-panel p-6 border-l-4 border-emerald-500">
            <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                <i class="fas fa-file-medical text-emerald-400"></i> แบบฟอร์มขอลา
            </h2>
            <p class="text-gray-400 text-sm mb-6">กรอกข้อมูลเพื่อส่งใบลาให้ครูตรวจสอบ</p>

            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <!-- Leave Type -->
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-300">ประเภทการลา</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="leave_type" value="sick" class="hidden peer" checked>
                            <div class="peer-checked:bg-emerald-600 peer-checked:border-emerald-400 peer-checked:ring-2 peer-checked:ring-emerald-300 bg-gray-800 border border-gray-700 rounded-lg p-4 text-center transition-all hover:bg-gray-700">
                                <i class="fas fa-thermometer-half text-2xl text-red-400 mb-2"></i>
                                <p class="font-bold text-sm">ลาป่วย</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="leave_type" value="business" class="hidden peer">
                            <div class="peer-checked:bg-emerald-600 peer-checked:border-emerald-400 peer-checked:ring-2 peer-checked:ring-emerald-300 bg-gray-800 border border-gray-700 rounded-lg p-4 text-center transition-all hover:bg-gray-700">
                                <i class="fas fa-briefcase text-2xl text-blue-400 mb-2"></i>
                                <p class="font-bold text-sm">ลากิจ</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-300">วันที่เริ่มลา</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-300">วันที่สิ้นสุด</label>
                        <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                </div>

                <!-- Reason -->
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-300">เหตุผลการลา</label>
                    <textarea name="reason" rows="3" required placeholder="เช่น ไม่สบาย มีไข้สูง / มีธุระจำเป็นที่บ้าน"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg p-3 text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition"></textarea>
                </div>

                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-300">แนบไฟล์ใบรับรองแพทย์ (ถ้ามี)</label>
                    <div class="relative">
                        <input type="file" name="medical_file" id="medical_file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="hidden" onchange="showFileName(this)">
                        <label for="medical_file" 
                            class="cursor-pointer flex items-center gap-3 bg-gray-800 border-2 border-dashed border-gray-600 hover:border-emerald-500 rounded-lg p-4 transition-all">
                            <div class="bg-emerald-600/20 p-3 rounded-lg">
                                <i class="fas fa-cloud-upload-alt text-2xl text-emerald-400"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm" id="file-label">คลิกเพื่อเลือกไฟล์</p>
                                <p class="text-xs text-gray-500">รองรับ JPG, PNG, PDF (ไม่เกิน 5MB)</p>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" name="submit_leave" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-lg transition-all flex items-center justify-center gap-2 text-lg">
                    <i class="fas fa-paper-plane"></i> ส่งใบลา
                </button>
            </form>
        </div>

        <!-- Leave History -->
        <div class="glass-panel p-6">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-history text-gray-400"></i> ประวัติการลา
            </h3>

            <?php if ($leaves && $leaves->num_rows > 0): ?>
                <div class="space-y-3">
                    <?php while($lv = $leaves->fetch_assoc()): ?>
                        <?php
                            $statusBadge = '';
                            $statusText = '';
                            if ($lv['status'] == 'pending') {
                                $statusBadge = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                                $statusText = '⏳ รอตรวจสอบ';
                            } elseif ($lv['status'] == 'approved') {
                                $statusBadge = 'bg-green-500/20 text-green-400 border-green-500/30';
                                $statusText = '✅ อนุมัติแล้ว';
                            } else {
                                $statusBadge = 'bg-red-500/20 text-red-400 border-red-500/30';
                                $statusText = '❌ ไม่อนุมัติ';
                            }
                            $leaveLabel = $lv['leave_type'] == 'sick' ? '🤒 ลาป่วย' : '📋 ลากิจ';
                        ?>
                        <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-gray-600 transition">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold"><?php echo $leaveLabel; ?></span>
                                    <span class="text-xs text-gray-400">
                                        <?php echo date('d/m/Y', strtotime($lv['start_date'])); ?>
                                        <?php if ($lv['start_date'] != $lv['end_date']): ?>
                                            - <?php echo date('d/m/Y', strtotime($lv['end_date'])); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="text-xs font-bold px-3 py-1 rounded-full border <?php echo $statusBadge; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($lv['reason']); ?></p>
                            <?php if (!empty($lv['file_path'])): ?>
                                <a href="../<?php echo $lv['file_path']; ?>" target="_blank" 
                                    class="inline-flex items-center gap-1 mt-2 text-xs text-emerald-400 hover:text-emerald-300 transition">
                                    <i class="fas fa-paperclip"></i> ดูไฟล์แนบ
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                    <p>ยังไม่มีประวัติการลา</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showFileName(input) {
    const label = document.getElementById('file-label');
    if (input.files.length > 0) {
        label.textContent = '📎 ' + input.files[0].name;
        label.classList.add('text-emerald-400');
    } else {
        label.textContent = 'คลิกเพื่อเลือกไฟล์';
        label.classList.remove('text-emerald-400');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
