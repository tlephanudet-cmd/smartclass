<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

// Ensure student_id is in session
if (!isset($_SESSION['student_id'])) {
    $u_id = $_SESSION['user_id'];
    $s_res = $conn->query("SELECT * FROM students WHERE user_id = $u_id");
    if($s_res->num_rows > 0) {
        $student_data = $s_res->fetch_assoc();
        $_SESSION['student_id'] = $student_data['id'];
        $_SESSION['full_name'] = $student_data['full_name'];
        $_SESSION['profile_image'] = $student_data['profile_image'] ?? '';
    }
}

// Refresh profile_image from DB to keep it current
$u_id = $_SESSION['user_id'];
$_img_res = $conn->query("SELECT profile_image FROM students WHERE user_id = $u_id");
if ($_img_res && $_img_row = $_img_res->fetch_assoc()) {
    $_SESSION['profile_image'] = $_img_row['profile_image'] ?? '';
}

// Attendance data
$sid = $_SESSION['student_id'];
$today = date('Y-m-d');

// Today's attendance
$att_today = $conn->query("SELECT status FROM attendance WHERE student_id = $sid AND date = '$today'")->fetch_assoc();
$att_status = $att_today['status'] ?? null;

// Semester stats (last 120 school days)
$att_total = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE student_id = $sid")->fetch_assoc()['total'];
$att_present = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id = $sid AND status IN ('present','late')")->fetch_assoc()['cnt'];
$att_absent = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id = $sid AND status = 'absent'")->fetch_assoc()['cnt'];
$att_leave = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id = $sid AND status = 'leave'")->fetch_assoc()['cnt'];
$att_pct = $att_total > 0 ? round(($att_present / $att_total) * 100) : 0;

// Pending leaves
$pending_leaves = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE student_id = $sid AND status = 'pending'")->fetch_assoc()['cnt'] ?? 0;

// Nearest pending assignment (not submitted, deadline in future)
$upcoming_hw = null;
$hw_query = $conn->query("
    SELECT a.* 
    FROM assignments a 
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = $sid
    WHERE s.id IS NULL AND (a.deadline >= NOW() OR a.due_date >= NOW())
    ORDER BY COALESCE(a.deadline, a.due_date) ASC 
    LIMIT 1
");
if (!$hw_query) {
    // Fallback if assignment_submissions doesn't exist
    $hw_query = $conn->query("SELECT * FROM assignments WHERE deadline >= NOW() OR due_date >= NOW() ORDER BY COALESCE(deadline, due_date) ASC LIMIT 1");
}
if ($hw_query && $hw_query->num_rows > 0) {
    $upcoming_hw = $hw_query->fetch_assoc();
}

// Count all pending assignments
$pending_hw_count = 0;
$phw_q = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM assignments a 
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = $sid
    WHERE s.id IS NULL AND (a.deadline >= NOW() OR a.due_date >= NOW())
");
if ($phw_q) $pending_hw_count = $phw_q->fetch_assoc()['cnt'];

$thai_months_short = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// ===== Announcements =====
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20) DEFAULT 'general',
    title VARCHAR(255) DEFAULT '',
    message TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Safe migration: rename old 'content' column to 'message' if needed
$has_content = $conn->query("SHOW COLUMNS FROM announcements LIKE 'content'");
$has_message = $conn->query("SHOW COLUMNS FROM announcements LIKE 'message'");
if ($has_content && $has_content->num_rows > 0 && $has_message && $has_message->num_rows == 0) {
    $conn->query("ALTER TABLE announcements CHANGE COLUMN `content` `message` TEXT NOT NULL");
}
$has_category = $conn->query("SHOW COLUMNS FROM announcements LIKE 'category'");
if ($has_category && $has_category->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN category VARCHAR(20) DEFAULT 'general' AFTER id");
}
$has_desc = $conn->query("SHOW COLUMNS FROM announcements LIKE 'description'");
if ($has_desc && $has_desc->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN description TEXT DEFAULT NULL AFTER message");
}

// Fetch latest active announcement (all fields)
$ann_data = null;
$announcement_msg = 'ยินดีต้อนรับสู่ห้องเรียนอัจฉริยะ';
$ann_category = 'general';
$ann_title = '';
$ann_description = '';
$ann_date = '';

$ann_res = @$conn->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
if ($ann_res && $ann_res->num_rows > 0) {
    $ann_data = $ann_res->fetch_assoc();
    if (!empty($ann_data['message'])) $announcement_msg = $ann_data['message'];
    $ann_category = $ann_data['category'] ?? 'general';
    $ann_title = $ann_data['title'] ?? '';
    $ann_description = $ann_data['description'] ?? '';
    $ann_date = $ann_data['updated_at'] ?? '';
}

// Category styling
$is_urgent = ($ann_category === 'urgent');
$bar_gradient = $is_urgent 
    ? 'from-red-600 via-red-500 to-rose-500' 
    : 'from-amber-500 via-orange-500 to-amber-500';
$bar_shadow = $is_urgent ? 'shadow-red-500/25' : 'shadow-amber-500/25';
$bar_border = $is_urgent ? 'border-red-400/30' : 'border-amber-400/30';
$badge_bg = $is_urgent ? 'bg-red-800/90 border-red-700' : 'bg-orange-700/90 border-orange-600';
$badge_icon = $is_urgent ? '🚨' : '📢';
$badge_label = $is_urgent ? 'ด่วน!' : 'ประกาศ';

$pageTitle = "ห้องเรียนของฉัน";
require_once '../includes/header.php';
?>

<div class="relative overflow-hidden min-h-screen">
    <!-- Background Gimmicks -->
    <div class="bg-glow top-20 left-0 opacity-20 animate-float"></div>
    <div class="bg-glow bottom-0 right-0 opacity-10" style="background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);"></div>

    <div class="container relative z-10">

        <!-- 📢 Scrolling Announcement Bar (FULL WIDTH) -->
        <div class="mb-6 rounded-xl bg-gradient-to-r <?php echo $bar_gradient; ?> shadow-lg <?php echo $bar_shadow; ?> overflow-hidden border <?php echo $bar_border; ?> cursor-pointer" onclick="document.getElementById('annDetailModal').classList.remove('hidden')">
            <div class="flex items-center">
                <!-- Badge -->
                <div class="<?php echo $badge_bg; ?> px-5 py-3.5 flex items-center gap-2 flex-shrink-0 border-r">
                    <span class="text-xl"><?php echo $badge_icon; ?></span>
                    <span class="font-black text-white text-sm uppercase tracking-widest"><?php echo $badge_label; ?></span>
                </div>
                <!-- Marquee -->
                <div class="flex-1 overflow-hidden py-3 px-3">
                    <marquee behavior="scroll" direction="left" scrollamount="4" class="text-white font-bold text-base">
                        <?php if (!empty($ann_title)): ?>
                            【<?php echo htmlspecialchars($ann_title); ?>】
                        <?php endif; ?>
                        <?php echo htmlspecialchars($announcement_msg); ?>
                        <?php if (!empty($ann_description)): ?>
                            &nbsp;&nbsp;👉 คลิกเพื่ออ่านเพิ่มเติม
                        <?php endif; ?>
                    </marquee>
                </div>
                <!-- Read More Button -->
                <div class="flex-shrink-0 pr-4">
                    <span class="bg-white/20 hover:bg-white/30 text-white text-xs font-bold px-3 py-1.5 rounded-full transition whitespace-nowrap">
                        <i class="fas fa-eye mr-1"></i> อ่านเพิ่มเติม
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4 sticky-sidebar h-fit">
        <div class="glass-panel p-6 text-center">
            <div class="w-24 h-24 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden border-2 border-emerald-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <i class="fas fa-user-graduate text-4xl text-emerald-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded bg-emerald-600 text-white shadow-lg"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="grades.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-line w-8"></i> ผลการเรียน</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> ขอลา
                <?php if ($pending_leaves > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full ml-1"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
        
        <!-- Quick Scan Button -->
        <button class="w-full glass-panel p-4 flex items-center justify-center gap-2 hover:bg-emerald-600/20 transition border border-emerald-500 text-emerald-400 font-bold">
            <i class="fas fa-qrcode text-2xl"></i> สแกนเช็คชื่อ
        </button>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">

        <!-- Welcome & Mood Check-in -->
        <div class="glass-panel p-8 bg-gradient-to-r from-emerald-900/40 to-slate-800 card-reveal active border-l-8 border-emerald-500 relative overflow-hidden">
            <!-- Gimmick Icon -->
            <div class="absolute right-[-20px] top-[-20px] opacity-10 rotate-12 scale-150">
                <i class="fas fa-rocket text-9xl text-emerald-400"></i>
            </div>

            <div class="relative z-10 flex items-start gap-5">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <div class="flex-shrink-0 w-20 h-20 rounded-full overflow-hidden border-3 border-emerald-400/50 shadow-lg shadow-emerald-500/20 hidden md:block">
                        <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-3xl font-black mb-2 flex items-center gap-3">
                        <i class="fas fa-hand-sparkles text-yellow-400 animate-float"></i>
                        ยินดีต้อนรับ, <?php echo $_SESSION['full_name']; ?>!
                    </h1>
                    <p class="text-gray-300 text-lg">ครูเติ้ลเตรียมบทเรียนสนุกๆ ไว้ให้แล้ว พร้อมอัพเวลกันหรือยังครับ?</p>
                
                    <div class="mt-6 flex flex-wrap gap-4">
                        <div class="bg-black/30 px-4 py-2 rounded-xl text-xs font-bold border border-white/5 flex items-center gap-2">
                            <i class="fas fa-star text-yellow-400"></i> อันดับที่ 3 ในห้อง
                        </div>
                        <div class="bg-black/30 px-4 py-2 rounded-xl text-xs font-bold border border-white/5 flex items-center gap-2">
                            <i class="fas fa-fire text-orange-500"></i> Streak 5 วันติด!
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Today + Stats Widget -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Today's Status -->
            <div class="glass-panel p-5 border-l-4 <?php 
                if ($att_status == 'present') echo 'border-green-500';
                elseif ($att_status == 'late') echo 'border-yellow-500';
                elseif ($att_status == 'absent') echo 'border-red-500';
                elseif ($att_status == 'leave') echo 'border-blue-500';
                else echo 'border-gray-600';
            ?>">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2">สถานะวันนี้</p>
                <?php if ($att_status == 'present'): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center"><i class="fas fa-check-circle text-2xl text-green-400"></i></div>
                        <div><p class="text-lg font-bold text-green-400">มาเรียน</p><p class="text-xs text-gray-500">เช็คชื่อแล้ว</p></div>
                    </div>
                <?php elseif ($att_status == 'late'): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center"><i class="fas fa-clock text-2xl text-yellow-400"></i></div>
                        <div><p class="text-lg font-bold text-yellow-400">มาสาย</p><p class="text-xs text-gray-500">มาสายวันนี้</p></div>
                    </div>
                <?php elseif ($att_status == 'absent'): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center"><i class="fas fa-times-circle text-2xl text-red-400"></i></div>
                        <div><p class="text-lg font-bold text-red-400">ขาดเรียน</p><p class="text-xs text-gray-500">ขาดเรียนวันนี้</p></div>
                    </div>
                <?php elseif ($att_status == 'leave'): ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center"><i class="fas fa-calendar-check text-2xl text-blue-400"></i></div>
                        <div><p class="text-lg font-bold text-blue-400">ลา</p><p class="text-xs text-gray-500">ลาเรียนวันนี้</p></div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-700 rounded-full flex items-center justify-center"><i class="fas fa-hourglass-half text-2xl text-gray-400 animate-pulse"></i></div>
                        <div><p class="text-lg font-bold text-gray-400">รอเช็คชื่อ</p><p class="text-xs text-gray-500">ครูยังไม่เช็คชื่อ</p></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attendance % -->
            <div class="glass-panel p-5">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2">อัตราเข้าเรียน</p>
                <div class="flex items-end gap-2 mb-2">
                    <span class="text-3xl font-black <?php echo $att_pct >= 80 ? 'text-green-400' : ($att_pct >= 60 ? 'text-yellow-400' : 'text-red-400'); ?>"><?php echo $att_pct; ?>%</span>
                    <span class="text-sm text-gray-500 mb-1">ของเทอมนี้</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2.5">
                    <div class="h-2.5 rounded-full transition-all <?php echo $att_pct >= 80 ? 'bg-green-400' : ($att_pct >= 60 ? 'bg-yellow-400' : 'bg-red-400'); ?>" style="width: <?php echo $att_pct; ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-2">
                    <span>มา <?php echo $att_present; ?></span>
                    <span>ขาด <?php echo $att_absent; ?></span>
                    <span>ลา <?php echo $att_leave; ?></span>
                </div>
            </div>

            <!-- Quick Leave -->
            <a href="request_leave.php" class="glass-panel p-5 hover:bg-emerald-600/10 transition-all group cursor-pointer block">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2">ขอลาเรียน</p>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center group-hover:scale-110 transition">
                        <i class="fas fa-file-medical text-2xl text-emerald-400"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">ส่งใบลา</p>
                        <p class="text-xs text-gray-500">ลาป่วย / ลากิจ</p>
                    </div>
                </div>
                <?php if ($pending_leaves > 0): ?>
                    <div class="mt-2 text-xs text-yellow-400 font-bold"><i class="fas fa-clock mr-1"></i>รอตรวจสอบ <?php echo $pending_leaves; ?> รายการ</div>
                <?php endif; ?>
            </a>
        </div>

        <!-- Mood Meter Modal (Hidden by default) -->
        <div id="moodModal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-md"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-4">
                <div class="glass-panel p-8 text-center border ring-2 ring-emerald-500/50 shadow-2xl relative">
                    <h2 class="text-2xl font-bold mb-4 text-white">วันนี้รู้สึกยังไงบ้าง?</h2>
                    <p class="text-gray-400 mb-6">เช็คอินอารมณ์กับครูเติ้ลหน่อยเร็ว</p>
                    
                    <div class="grid grid-cols-5 gap-2 mb-6">
                        <button onclick="selectMood(5)" class="mood-btn hover:scale-110 transition text-4xl p-2 rounded-lg bg-gray-700 hover:bg-green-500">🤩</button>
                        <button onclick="selectMood(4)" class="mood-btn hover:scale-110 transition text-4xl p-2 rounded-lg bg-gray-700 hover:bg-blue-400">🙂</button>
                        <button onclick="selectMood(3)" class="mood-btn hover:scale-110 transition text-4xl p-2 rounded-lg bg-gray-700 hover:bg-yellow-400">😴</button>
                        <button onclick="selectMood(2)" class="mood-btn hover:scale-110 transition text-4xl p-2 rounded-lg bg-gray-700 hover:bg-orange-500">😟</button>
                        <button onclick="selectMood(1)" class="mood-btn hover:scale-110 transition text-4xl p-2 rounded-lg bg-gray-700 hover:bg-red-500">🤒</button>
                    </div>

                    <textarea id="moodNote" class="w-full bg-gray-900 border border-gray-600 rounded p-3 text-white mb-4 placeholder-gray-500" placeholder="มีอะไรอยากบอกครูไหม? (ไม่บังคับ)" rows="2"></textarea>

                    <button onclick="submitMood()" class="btn btn-primary w-full py-3 font-bold">ส่งเช็คอิน <i class="fas fa-check ml-2"></i></button>
                </div>
            </div>
        </div>

        <script>
            let selectedMood = 0;
            
            // Check mood status on load
            fetch('../api/mood_api.php?action=check_today')
            .then(res => res.json())
            .then(data => {
                if(data.status === 'not_logged') {
                    document.getElementById('moodModal').classList.remove('hidden');
                }
            });

            function selectMood(level) {
                selectedMood = level;
                document.querySelectorAll('.mood-btn').forEach(btn => btn.classList.remove('ring-4', 'ring-white'));
                event.currentTarget.classList.add('ring-4', 'ring-white');
            }

            function submitMood() {
                if(selectedMood === 0) return alert('เลือกอิโมจิอารมณ์ก่อนนะ!');
                
                const note = document.getElementById('moodNote').value;
                const formData = new FormData();
                formData.append('action', 'log_mood');
                formData.append('mood', selectedMood);
                formData.append('note', note);

                fetch('../api/mood_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        document.getElementById('moodModal').classList.add('hidden');
                        alert('ขอบคุณที่เช็คอินครับ!');
                    }
                });
            }
        </script>

        <!-- Live Poll Widget -->
        <div id="poll-widget" class="glass-panel p-6 border-l-4 border-yellow-500 hidden fade-in">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-2 py-1 rounded uppercase animate-pulse">กำลังเปิดโหวต</span>
                    <h2 class="text-xl font-bold mt-2" id="poll-question">กำลังโหลดคำถาม...</h2>
                </div>
                <i class="fas fa-poll text-4xl text-yellow-500 opacity-50"></i>
            </div>
            
            <div id="poll-options" class="space-y-3">
                <!-- Options injected via JS -->
            </div>
            
            <div id="voted-msg" class="hidden text-center py-4 text-green-400 font-bold text-lg">
                <i class="fas fa-check-circle mr-2"></i> ส่งคำตอบแล้ว!
            </div>
        </div>

        <!-- Dashboard Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- XP Card -->
            <div class="glass-panel p-6">
                <h3 class="font-bold mb-4 text-gray-400 uppercase text-xs">คะแนนสะสม (XP)</h3>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-3xl font-bold text-yellow-400">เลเวล 5</span>
                    <span class="text-sm text-gray-400">1,250 แต้ม</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2.5">
                    <div class="bg-yellow-400 h-2.5 rounded-full" style="width: 70%"></div>
                </div>
                <p class="text-xs text-right mt-1 text-gray-500">ขาดอีก 250 แต้มเพื่ออัพเลเวล</p>
            </div>

            <!-- Homework Due -->
            <div class="glass-panel p-6">
                 <h3 class="font-bold mb-4 text-gray-400 uppercase text-xs flex items-center justify-between">
                     งานที่ต้องส่ง
                     <?php if ($pending_hw_count > 0): ?>
                         <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full normal-case"><?php echo $pending_hw_count; ?> ชิ้น</span>
                     <?php endif; ?>
                 </h3>
                 <?php if ($upcoming_hw): ?>
                     <?php
                         $hw_deadline = $upcoming_hw['deadline'] ?? $upcoming_hw['due_date'] ?? null;
                         $hw_diff = $hw_deadline ? (strtotime($hw_deadline) - time()) : 0;
                         $hw_days = floor($hw_diff / 86400);
                         $hw_hours = floor(($hw_diff % 86400) / 3600);
                         $hw_urgent = ($hw_diff > 0 && $hw_diff < 86400); // less than 1 day
                         
                         $hw_date_display = '';
                         if ($hw_deadline) {
                             $ts = strtotime($hw_deadline);
                             $hw_date_display = (int)date('j',$ts).' '.$thai_months_short[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543).' '.date('H:i',$ts).' น.';
                         }
                         
                         $hw_time_text = '';
                         if ($hw_diff > 0) {
                             $hw_time_text = $hw_days > 0 ? "เหลือ {$hw_days} วัน {$hw_hours} ชม." : "เหลือ {$hw_hours} ชม.";
                         }
                     ?>
                     <div class="flex items-center gap-4">
                         <div class="<?php echo $hw_urgent ? 'bg-red-500/20 animate-pulse' : 'bg-orange-500/20'; ?> p-3 rounded-lg <?php echo $hw_urgent ? 'text-red-400' : 'text-orange-400'; ?>">
                             <i class="fas fa-<?php echo $hw_urgent ? 'exclamation-triangle' : 'book'; ?> text-2xl"></i>
                         </div>
                         <div class="flex-1 min-w-0">
                             <h4 class="font-bold text-white truncate"><?php echo htmlspecialchars($upcoming_hw['title']); ?></h4>
                             <p class="text-xs text-gray-400 mt-0.5">กำหนดส่ง: <?php echo $hw_date_display; ?></p>
                             <?php if ($hw_time_text): ?>
                                 <p class="text-xs font-bold mt-1 <?php echo $hw_urgent ? 'text-red-400' : 'text-yellow-400'; ?>">
                                     <i class="fas fa-hourglass-half mr-1"></i><?php echo $hw_time_text; ?>
                                 </p>
                             <?php endif; ?>
                         </div>
                     </div>
                     <a href="student_assignments.php" class="mt-4 w-full block text-center bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-xl text-sm font-bold transition shadow-lg shadow-emerald-900/20">
                         <i class="fas fa-arrow-right mr-1"></i> ดูรายละเอียด
                     </a>
                 <?php else: ?>
                     <div class="flex items-center gap-4">
                         <div class="bg-emerald-500/20 p-3 rounded-lg text-emerald-400">
                             <i class="fas fa-check-circle text-2xl"></i>
                         </div>
                         <div>
                             <h4 class="font-bold text-emerald-400">🎉 เย้! ไม่มีงานค้าง</h4>
                             <p class="text-sm text-gray-500">ยังไม่มีงานที่ต้องส่งตอนนี้</p>
                         </div>
                     </div>
                     <a href="student_assignments.php" class="mt-4 w-full block text-center bg-gray-700 hover:bg-gray-600 text-gray-400 py-2.5 rounded-xl text-sm font-bold transition">
                         ดูการบ้านทั้งหมด
                     </a>
                 <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    // Poll Polling Logic
    function checkPoll() {
        fetch('../api/poll_api.php?action=get_active_poll')
        .then(res => res.json())
        .then(data => {
            const widget = document.getElementById('poll-widget');
            const optionsDiv = document.getElementById('poll-options');
            const votedMsg = document.getElementById('voted-msg');
            const question = document.getElementById('poll-question');

            if (data.status === 'success') {
                widget.classList.remove('hidden');
                question.innerText = data.poll.question;
                
                if (data.voted) {
                    optionsDiv.classList.add('hidden');
                    votedMsg.classList.remove('hidden');
                } else {
                    optionsDiv.classList.remove('hidden');
                    votedMsg.classList.add('hidden');
                    
                    optionsDiv.innerHTML = '';
                    data.options.forEach(opt => {
                        const btn = document.createElement('button');
                        btn.className = 'w-full text-left p-3 rounded bg-gray-700 hover:bg-indigo-600 transition border border-gray-600';
                        btn.innerHTML = `<span class="font-bold mx-2">➤</span> ${opt.option_text}`;
                        btn.onclick = () => submitVote(data.poll.id, opt.id);
                        optionsDiv.appendChild(btn);
                    });
                }
            } else {
                widget.classList.add('hidden');
            }
        });
    }

    function submitVote(pollId, optionId) {
        const formData = new FormData();
        formData.append('action', 'vote');
        formData.append('poll_id', pollId);
        formData.append('option_id', optionId);

        fetch('../api/poll_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                checkPoll();
            } else {
                alert(data.message);
            }
        });
    }

    setInterval(checkPoll, 3000);
    checkPoll();
</script>

<!-- 📢 Announcement Detail Modal -->
<div id="annDetailModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="document.getElementById('annDetailModal').classList.add('hidden')"></div>
    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r <?php echo $is_urgent ? 'from-red-600 to-rose-600' : 'from-amber-600 to-orange-600'; ?> p-5 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black flex items-center gap-3 text-white">
                    <span class="text-2xl"><?php echo $badge_icon; ?></span> ประกาศจากครูผู้สอน
                </h3>
                <button onclick="document.getElementById('annDetailModal').classList.add('hidden')" class="text-white/70 hover:text-white text-2xl transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Content -->
        <div class="p-6 space-y-4">
            <!-- Category Tag -->
            <div>
                <?php if ($is_urgent): ?>
                    <span class="bg-red-500/20 text-red-400 text-sm font-bold px-3 py-1 rounded-full border border-red-500/30">
                        🚨 เรื่องด่วน
                    </span>
                <?php else: ?>
                    <span class="bg-sky-500/20 text-sky-400 text-sm font-bold px-3 py-1 rounded-full border border-sky-500/30">
                        📢 ประกาศทั่วไป
                    </span>
                <?php endif; ?>
            </div>

            <!-- Title -->
            <?php if (!empty($ann_title)): ?>
            <h2 class="text-2xl font-black text-white leading-snug"><?php echo htmlspecialchars($ann_title); ?></h2>
            <?php endif; ?>

            <!-- Message -->
            <div class="bg-black/20 rounded-lg p-4 border border-white/5">
                <p class="text-amber-300 font-bold text-sm">
                    <i class="fas fa-bullhorn mr-2 opacity-60"></i><?php echo htmlspecialchars($announcement_msg); ?>
                </p>
            </div>

            <!-- Description -->
            <?php if (!empty($ann_description)): ?>
            <div class="bg-gray-800/50 rounded-lg p-4 border border-white/5">
                <h4 class="text-sm font-bold text-gray-400 mb-2"><i class="fas fa-file-alt mr-1"></i> รายละเอียด</h4>
                <p class="text-gray-300 text-sm leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($ann_description); ?></p>
            </div>
            <?php endif; ?>

            <!-- Date -->
            <?php if (!empty($ann_date)): ?>
            <div class="flex items-center gap-2 text-gray-500 text-xs pt-2 border-t border-white/5">
                <i class="fas fa-clock"></i>
                <span>ประกาศเมื่อ: <?php echo date('d/m/Y H:i', strtotime($ann_date)); ?> น.</span>
            </div>
            <?php endif; ?>
        </div>
        <!-- Footer -->
        <div class="p-4 border-t border-white/5">
            <button onclick="document.getElementById('annDetailModal').classList.add('hidden')" class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition">
                <i class="fas fa-check mr-1"></i> รับทราบ
            </button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
