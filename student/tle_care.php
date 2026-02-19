<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_ticket'])) {
    $mode = $_POST['mode'];
    $topic = sanitize($_POST['topic']);
    $message = sanitize($_POST['message']);
    $student_id = $_SESSION['student_id'];
    if ($mode == 'anonymous') { $student_id = 'NULL'; } else { $student_id = "'$student_id'"; }
    $sql = "INSERT INTO consultations (student_id, topic, message) VALUES ($student_id, '$topic', '$message')";
    if ($conn->query($sql)) {
        setFlashMessage('success', 'ส่งข้อความเรียบร้อยแล้ว ครูเติ้ลจะอ่านเร็วๆ นี้');
        if (strpos($topic, 'ด่วน') !== false || strpos($topic, 'SOS') !== false) {
            $sender = ($mode == 'anonymous') ? "นักเรียนไม่ระบุตัวตน" : $_SESSION['full_name'];
            sendLineNotify("\n🚨 ด่วน!\nจาก: $sender\nหัวข้อ: $topic\nข้อความ: $message");
        } else {
            $sender = ($mode == 'anonymous') ? "นักเรียนไม่ระบุตัวตน" : $_SESSION['full_name'];
            sendLineNotify("\n💬 ข้อความปรึกษาใหม่\nจาก: $sender\nหัวข้อ: $topic");
        }
    } else {
        setFlashMessage('error', 'เกิดข้อผิดพลาดในการส่งข้อความ');
    }
}
$pageTitle = "กล่องปรึกษาครู";
require_once '../includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="tle_care.php" class="block px-4 py-2 rounded bg-pink-600 text-white"><i class="fas fa-heart w-8"></i> กล่องปรึกษาครู</a>
        </nav>
    </div>
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>
        <div class="glass-panel p-6 bg-gradient-to-r from-pink-900/50 to-gray-800">
            <h1 class="text-3xl font-bold mb-2 text-pink-400"><i class="fas fa-heartbeat mr-2"></i> กล่องปรึกษาครูเติ้ล</h1>
            <p class="text-gray-300">พื้นที่ปลอดภัยสำหรับนักเรียน ปรึกษาได้ทุกเรื่อง (เรียน, เพื่อน, ครอบครัว)</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-paper-plane mr-2"></i> ส่งข้อความถึงครูเติ้ล</h2>
                <form action="tle_care.php" method="POST" class="space-y-4">
                    <input type="hidden" name="send_ticket" value="1">
                    <div>
                        <label class="block text-gray-400 mb-1">รูปแบบการส่ง</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="mode" value="identity" checked class="text-pink-500 focus:ring-pink-500">
                                <span>ระบุตัวตน</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="mode" value="anonymous" class="text-gray-500 focus:ring-gray-500">
                                <span>ไม่ระบุตัวตน</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">* เลือกไม่ระบุตัวตนสำหรับเรื่องละเอียดอ่อน (ถูกรังแก ฯลฯ)</p>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1">หัวข้อ</label>
                        <select name="topic" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white">
                            <option value="ทั่วไป">🔵 ทั่วไป / ข้อเสนอแนะ</option>
                            <option value="การเรียน">🟢 การเรียน / การบ้าน</option>
                            <option value="เรื่องส่วนตัว">🟡 เรื่องส่วนตัว / ครอบครัว</option>
                            <option value="ด่วน/ถูกรังแก">🔴 ด่วน / ถูกรังแก / ต้องการความช่วยเหลือ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1">ข้อความ</label>
                        <textarea name="message" rows="5" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" required placeholder="เล่าให้ครูฟังเลยนะ..."></textarea>
                    </div>
                    <button type="submit" class="w-full btn btn-primary bg-pink-600 hover:bg-pink-700 py-2">ส่งข้อความ</button>
                </form>
            </div>
            <div class="glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-history mr-2"></i> ประวัติของฉัน</h2>
                <?php
                    $sid = $_SESSION['student_id'];
                    $history = $conn->query("SELECT * FROM consultations WHERE student_id = $sid ORDER BY created_at DESC LIMIT 5");
                ?>
                <div class="space-y-3">
                    <?php while($row = $history->fetch_assoc()): ?>
                        <div class="p-3 bg-gray-800 rounded border border-gray-700">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-sm <?php echo (isset($row['topic']) && strpos($row['topic'], 'ด่วน') !== false) ? 'text-red-400' : 'text-gray-300'; ?>">
                                    <?php echo htmlspecialchars($row['topic'] ?? $row['topic_category'] ?? 'ไม่มีหัวข้อ'); ?>
                                </span>
                                <span class="text-xs text-gray-500"><?php echo date('d/m', strtotime($row['created_at'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-400 truncate"><?php echo $row['message']; ?></p>
                            <div class="mt-2 text-xs">
                                สถานะ: 
                                <?php 
                                    if($row['status'] == 'pending') echo '<span class="text-yellow-400">รอดำเนินการ</span>';
                                    elseif($row['status'] == 'processing') echo '<span class="text-blue-400">อ่านแล้ว/กำลังดูแล</span>';
                                    else echo '<span class="text-green-400">เสร็จสิ้น</span>';
                                ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php if($history->num_rows == 0): ?>
                        <p class="text-gray-500 text-center">ยังไม่มีประวัติ</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
