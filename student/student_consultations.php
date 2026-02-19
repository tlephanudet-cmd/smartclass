<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "กล่องปรึกษาครูเติ้ล";
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['full_name'] ?? 'นักเรียน';

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS `consultations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) DEFAULT NULL,
    `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
    `topic_category` varchar(100) DEFAULT NULL,
    `message` text DEFAULT NULL,
    `status` varchar(20) DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure is_anonymous column exists (for existing tables)
$conn->query("ALTER TABLE `consultations` ADD COLUMN `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 AFTER `student_id`");

$conn->query("CREATE TABLE IF NOT EXISTS `consultation_replies` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` int(11) NOT NULL,
    `sender_type` enum('teacher','student') DEFAULT 'teacher',
    `message` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ========== Handle New Message ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_consult'])) {
    $mode = sanitize($_POST['mode'] ?? 'identity');
    $topic = sanitize($_POST['topic']);
    $message = sanitize($_POST['message']);
    
    // Always save real student_id, use is_anonymous flag to hide identity
    $is_anonymous = ($mode == 'anonymous') ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO consultations (student_id, is_anonymous, topic_category, message) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        // Try fallback with 'topic' column for older schemas
        $stmt = $conn->prepare("INSERT INTO consultations (student_id, is_anonymous, topic, message) VALUES (?, ?, ?, ?)");
    }
    
    if ($stmt) {
        $stmt->bind_param("iiss", $student_id, $is_anonymous, $topic, $message);
        
        if ($stmt->execute()) {
            // ===== LINE Messaging API Broadcast =====
            $lineAccessToken = 'uFTUUvcYesi3BshZ+TM+5gUzNesaDVGRPQ9V7DFwJF0AZsogt60yQu8QnpCm1QpIGjoeSRycmGn+iGcsyHFlWXswYAzbSrjBOrHVPom4s7SR7dWYP1TZXolIVaw2y0qtpVS3bZmX01rInglv+2h46AdB04t89/1O/w1cDnyilFU=';
            $lineEndpoint  = 'https://api.line.me/v2/bot/message/broadcast';

            // Dynamic message based on identity mode
            $senderDisplay = ($mode == 'anonymous') ? 'ผู้ไม่ประสงค์ออกนาม 🕵️' : "👨‍🎓 $student_name";
            $urgentTag = (strpos($topic, 'ด่วน') !== false || strpos($topic, 'SOS') !== false) ? '🚨 ด่วน! ' : '';
            $msgPreview = mb_strlen($message) > 80 ? mb_substr($message, 0, 80) . '...' : $message;
            
            $lineMsg = "{$urgentTag}💬 มีข้อความปรึกษาใหม่\n━━━━━━━━━━━━━━━\n📌 จาก: {$senderDisplay}\n📝 เรื่อง: {$topic}\n💭 ข้อความ: {$msgPreview}\n━━━━━━━━━━━━━━━\n🔗 ตรวจสอบในเว็บไซต์";

            $linePayload = json_encode([
                'messages' => [
                    ['type' => 'text', 'text' => $lineMsg]
                ]
            ]);

            $ch = curl_init($lineEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $linePayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $lineAccessToken
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
            // ===== END LINE Notification =====

            setFlashMessage('success', '✅ ส่งข้อความถึงครูเติ้ลเรียบร้อยแล้ว! รอการตอบกลับนะครับ');
            header("Location: student_consultations.php");
            exit();
        } else {
            setFlashMessage('error', '❌ บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error);
        }
    } else {
        setFlashMessage('error', '❌ เกิดข้อผิดพลาดในระบบ: ' . $conn->error);
    }
    header("Location: student_consultations.php");
    exit();
}

// Fetch my history
$my_consultations = $conn->query("SELECT * FROM consultations WHERE student_id = $student_id ORDER BY created_at DESC");
if (!$my_consultations) {
    $my_consultations = $conn->query("SELECT * FROM consultations WHERE student_id = $student_id ORDER BY created_at DESC");
}

// Stats
$total_msgs = 0;
$pending_msgs = 0;
$resolved_msgs = 0;
$msg_arr = [];
if ($my_consultations) {
    while ($r = $my_consultations->fetch_assoc()) {
        $msg_arr[] = $r;
        $total_msgs++;
        if (($r['status'] ?? 'pending') == 'pending' || ($r['status'] ?? 'pending') == 'processing') $pending_msgs++;
        else $resolved_msgs++;
    }
}

$thai_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-20 h-20 bg-pink-500/20 rounded-full flex items-center justify-center mx-auto mb-3 overflow-hidden border-2 border-pink-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-graduate text-3xl text-pink-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-pink-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="grades.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-line w-8"></i> ผลการเรียน</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded bg-pink-600 text-white shadow-lg"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> ขอลา</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <!-- Header Banner -->
        <div class="glass-panel p-6 bg-gradient-to-r from-pink-900/50 to-slate-800 border-l-8 border-pink-500 relative overflow-hidden">
            <div class="absolute right-[-10px] top-[-10px] opacity-10 rotate-12">
                <i class="fas fa-heart text-8xl text-pink-400"></i>
            </div>
            <div class="relative z-10">
                <h1 class="text-2xl font-bold text-pink-300 flex items-center gap-3">
                    <i class="fas fa-heartbeat animate-pulse"></i> กล่องปรึกษาครูเติ้ล
                </h1>
                <p class="text-gray-300 text-sm mt-1">พื้นที่ปลอดภัยสำหรับนักเรียน ปรึกษาได้ทุกเรื่อง • เรียน • เพื่อน • ครอบครัว</p>
            </div>
        </div>

        <!-- Stats Mini Cards -->
        <div class="grid grid-cols-3 gap-3">
            <div class="glass-panel p-3 text-center border-t-4 border-pink-500">
                <p class="text-2xl font-black text-pink-400"><?php echo $total_msgs; ?></p>
                <p class="text-xs text-gray-400 font-bold mt-1">ข้อความทั้งหมด</p>
            </div>
            <div class="glass-panel p-3 text-center border-t-4 border-yellow-500">
                <p class="text-2xl font-black text-yellow-400"><?php echo $pending_msgs; ?></p>
                <p class="text-xs text-gray-400 font-bold mt-1">รอตอบกลับ</p>
            </div>
            <div class="glass-panel p-3 text-center border-t-4 border-green-500">
                <p class="text-2xl font-black text-green-400"><?php echo $resolved_msgs; ?></p>
                <p class="text-xs text-gray-400 font-bold mt-1">ตอบแล้ว</p>
            </div>
        </div>

        <!-- 2-Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            
            <!-- LEFT: Send Form (2 cols) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="glass-panel p-6 border border-pink-500/20">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2 text-pink-300">
                        <i class="fas fa-paper-plane"></i> ส่งข้อความถึงครู
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="send_consult" value="1">
                        
                        <!-- Mode Selection -->
                        <div>
                            <label class="block text-sm font-bold text-gray-300 mb-2">
                                <i class="fas fa-user-shield mr-1 text-pink-400"></i>รูปแบบการส่ง
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block cursor-pointer">
                                    <input type="radio" name="mode" value="identity" checked class="hidden peer">
                                    <div class="peer-checked:bg-pink-600/30 peer-checked:border-pink-500 peer-checked:text-pink-300 bg-gray-800 border-2 border-gray-700 rounded-xl p-3 text-center text-sm transition hover:bg-gray-700">
                                        <i class="fas fa-user text-lg block mb-1"></i>
                                        <span class="font-bold">ระบุตัวตน</span>
                                    </div>
                                </label>
                                <label class="block cursor-pointer">
                                    <input type="radio" name="mode" value="anonymous" class="hidden peer">
                                    <div class="peer-checked:bg-gray-600/30 peer-checked:border-gray-500 peer-checked:text-gray-300 bg-gray-800 border-2 border-gray-700 rounded-xl p-3 text-center text-sm transition hover:bg-gray-700">
                                        <i class="fas fa-user-secret text-lg block mb-1"></i>
                                        <span class="font-bold">ไม่ระบุตัวตน</span>
                                    </div>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">* เลือก "ไม่ระบุตัวตน" สำหรับเรื่องละเอียดอ่อน</p>
                        </div>

                        <!-- Topic Dropdown -->
                        <div>
                            <label class="block text-sm font-bold text-gray-300 mb-2">
                                <i class="fas fa-tag mr-1 text-pink-400"></i>หัวข้อ
                            </label>
                            <select name="topic" required 
                                class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition">
                                <option value="ทั่วไป">🔵 ทั่วไป / ข้อเสนอแนะ</option>
                                <option value="การเรียน">🟢 การเรียน / การบ้าน</option>
                                <option value="เรื่องเพื่อน">🟡 เรื่องเพื่อน</option>
                                <option value="เรื่องครอบครัว">🟠 เรื่องครอบครัว</option>
                                <option value="เรื่องส่วนตัว">🟣 เรื่องส่วนตัว</option>
                                <option value="ด่วน/ถูกรังแก">🔴 ด่วน / ถูกรังแก / SOS</option>
                            </select>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-bold text-gray-300 mb-2">
                                <i class="fas fa-comment-dots mr-1 text-pink-400"></i>ข้อความ
                            </label>
                            <textarea name="message" rows="5" required placeholder="เล่าให้ครูฟังเลยนะ... ครูพร้อมรับฟังเสมอ 💕"
                                class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition placeholder:text-gray-600 resize-none"></textarea>
                        </div>

                        <!-- Submit -->
                        <button type="submit" 
                            class="w-full bg-gradient-to-r from-pink-600 to-rose-500 hover:from-pink-500 hover:to-rose-400 text-white py-3 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-pink-900/30 text-sm">
                            <i class="fas fa-paper-plane"></i> ส่งข้อความถึงครูเติ้ล
                        </button>
                    </form>

                    <div class="mt-4 p-3 bg-pink-500/10 border border-pink-500/20 rounded-xl text-xs text-pink-300 flex items-start gap-2">
                        <i class="fas fa-shield-alt mt-0.5 text-pink-400"></i>
                        <span>ข้อความของคุณจะถูกส่งถึงครูเติ้ลโดยตรง พร้อมแจ้งเตือนทาง LINE ทันที ปลอดภัย 100%</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: History (3 cols) -->
            <div class="lg:col-span-3 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold flex items-center gap-2 text-gray-300">
                        <i class="fas fa-history text-pink-400"></i> ประวัติการปรึกษา
                    </h2>
                    <?php if ($total_msgs > 0): ?>
                        <span class="text-xs text-gray-500"><?php echo $total_msgs; ?> รายการ</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($msg_arr)): ?>
                    <div class="glass-panel text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-5xl mb-3 block opacity-30"></i>
                        <p class="font-bold mb-1">ยังไม่มีข้อความ</p>
                        <p class="text-sm">ส่งข้อความแรกจากฟอร์มด้านซ้ายเลย!</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1" style="scrollbar-width: thin; scrollbar-color: #ec4899 #1e293b;">
                        <?php foreach ($msg_arr as $row): ?>
                            <?php
                                $status = $row['status'] ?? 'pending';
                                $topicText = $row['topic_category'] ?? $row['topic'] ?? 'ไม่มีหัวข้อ';
                                
                                $statusConfig = [
                                    'pending' => ['text' => 'รออ่าน', 'class' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30', 'icon' => 'fas fa-clock'],
                                    'processing' => ['text' => 'อ่านแล้ว', 'class' => 'bg-blue-500/20 text-blue-400 border-blue-500/30', 'icon' => 'fas fa-eye'],
                                    'resolved' => ['text' => 'ตอบแล้ว', 'class' => 'bg-green-500/20 text-green-400 border-green-500/30', 'icon' => 'fas fa-check-circle'],
                                ];
                                $sc = $statusConfig[$status] ?? $statusConfig['pending'];
                                
                                $topicColors = [
                                    'ทั่วไป' => 'text-blue-400',
                                    'การเรียน' => 'text-green-400',
                                    'เรื่องเพื่อน' => 'text-yellow-400',
                                    'เรื่องครอบครัว' => 'text-orange-400',
                                    'เรื่องส่วนตัว' => 'text-purple-400',
                                    'ด่วน/ถูกรังแก' => 'text-red-400',
                                ];
                                $topicColor = $topicColors[$topicText] ?? 'text-gray-400';
                                
                                $ts = strtotime($row['created_at']);
                                $dateStr = (int)date('j',$ts).' '.$thai_months[(int)date('n',$ts)].' '.date('H:i',$ts);
                            ?>
                            <div class="glass-panel p-4 border-l-4 <?php echo $status == 'resolved' ? 'border-green-500' : ($status == 'processing' ? 'border-blue-500' : 'border-pink-500'); ?> hover:bg-white/[0.02] transition">
                                <!-- Header -->
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <span class="font-bold text-sm <?php echo $topicColor; ?>"><?php echo htmlspecialchars($topicText); ?></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full border <?php echo $sc['class']; ?>">
                                                <i class="<?php echo $sc['icon']; ?> mr-1"></i><?php echo $sc['text']; ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i><?php echo $dateStr; ?></p>
                                    </div>
                                    <button onclick="deleteConsultation(<?php echo $row['id']; ?>)" 
                                        class="text-red-400/50 hover:text-red-400 hover:bg-red-500/10 p-1.5 rounded-lg transition flex-shrink-0" 
                                        title="ลบข้อความนี้">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>

                                <!-- Message Preview -->
                                <p class="text-sm text-gray-300 mb-3 whitespace-pre-line"><?php echo htmlspecialchars($row['message'] ?? ''); ?></p>

                                <!-- Replies Thread -->
                                <?php
                                    $cid = $row['id'];
                                    $replies = $conn->query("SELECT * FROM consultation_replies WHERE consultation_id = $cid ORDER BY created_at ASC");
                                    if ($replies && $replies->num_rows > 0):
                                ?>
                                    <div class="bg-black/20 rounded-xl p-3 space-y-2 mb-3">
                                        <?php while($rep = $replies->fetch_assoc()): ?>
                                            <?php 
                                                $senderType = $rep['sender_type'] ?? 'teacher';
                                                $isMe = ($senderType == 'student');
                                                $repTs = strtotime($rep['created_at']);
                                                $repTime = date('H:i', $repTs);
                                            ?>
                                            <div class="flex <?php echo $isMe ? 'justify-end' : 'justify-start'; ?>">
                                                <div class="<?php echo $isMe ? 'bg-pink-900/40 border-pink-500/20' : 'bg-indigo-900/40 border-indigo-500/20'; ?> border p-2.5 rounded-xl text-sm max-w-[85%]">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-bold text-xs <?php echo $isMe ? 'text-pink-400' : 'text-indigo-400'; ?>">
                                                            <?php echo $isMe ? '🙋 ฉัน' : '👨‍🏫 ครูเติ้ล'; ?>
                                                        </span>
                                                        <span class="text-[10px] text-gray-500"><?php echo $repTime; ?></span>
                                                    </div>
                                                    <p class="text-gray-200"><?php echo htmlspecialchars($rep['message']); ?></p>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Reply Form -->
                                <div class="pt-2 border-t border-gray-700/50">
                                    <form onsubmit="sendReply(event, <?php echo $row['id']; ?>)" id="reply-form-<?php echo $row['id']; ?>" class="flex gap-2">
                                        <input type="text" name="message" 
                                            class="flex-1 bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-white text-sm focus:border-pink-500 outline-none transition placeholder:text-gray-600" 
                                            placeholder="ตอบกลับครูเติ้ล..." required>
                                        <button type="submit" class="bg-pink-600 hover:bg-pink-500 text-white px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-1 shadow-lg shadow-pink-900/20">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function sendReply(e, id) {
    e.preventDefault();
    const form = document.getElementById('reply-form-' + id);
    const input = form.querySelector('input[name="message"]');
    const msg = input.value.trim();
    const btn = form.querySelector('button');

    if (!msg) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('consultation_id', id);
    formData.append('message', msg);

    fetch('api_save_reply.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        
        if(data.status === 'success') {
            input.value = '';
            location.reload(); 
        } else {
            alert('เกิดข้อผิดพลาด: ' + (data.message || 'Error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        alert('เชื่อมต่อล้มเหลว');
    });
}

function deleteConsultation(id) {
    if (!confirm('⚠️ ต้องการลบข้อความนี้ใช่ไหม?\nข้อความและการตอบกลับทั้งหมดจะถูกลบถาวร')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('api_delete_consultation.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'เกิดข้อผิดพลาด'));
        }
    })
    .catch(() => alert('เชื่อมต่อล้มเหลว'));
}
</script>

<?php require_once '../includes/footer.php'; ?>
