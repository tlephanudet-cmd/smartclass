<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$today = date('Y-m-d');
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$submitted_work = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE DATE(submitted_at) = '$today'")->fetch_assoc()['count'];
$pending_leaves = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['cnt'] ?? 0;

// ===== Announcements System =====
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20) DEFAULT 'general',
    title VARCHAR(255) DEFAULT '',
    message TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Safe migration: ensure all expected columns exist
// 1) If old 'content' column exists but 'message' does not, rename it
$has_content = $conn->query("SHOW COLUMNS FROM announcements LIKE 'content'");
$has_message = $conn->query("SHOW COLUMNS FROM announcements LIKE 'message'");
if ($has_content && $has_content->num_rows > 0 && $has_message && $has_message->num_rows == 0) {
    $conn->query("ALTER TABLE announcements CHANGE COLUMN `content` `message` TEXT NOT NULL");
}
// 2) If 'message' still doesn't exist (edge case), add it
$has_message2 = $conn->query("SHOW COLUMNS FROM announcements LIKE 'message'");
if ($has_message2 && $has_message2->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN message TEXT NOT NULL AFTER title");
}
// 3) Add 'category' if missing (copy value from 'type' if that exists)
$has_category = $conn->query("SHOW COLUMNS FROM announcements LIKE 'category'");
if ($has_category && $has_category->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN category VARCHAR(20) DEFAULT 'general' AFTER id");
    // Migrate data from 'type' to 'category' if 'type' column exists
    $has_type = $conn->query("SHOW COLUMNS FROM announcements LIKE 'type'");
    if ($has_type && $has_type->num_rows > 0) {
        $conn->query("UPDATE announcements SET category = CASE WHEN type = 'urgent' THEN 'urgent' ELSE 'general' END");
    }
}
// 4) Add 'title' if missing
$has_title = $conn->query("SHOW COLUMNS FROM announcements LIKE 'title'");
if ($has_title && $has_title->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN title VARCHAR(255) DEFAULT '' AFTER category");
}
// 5) Add 'description' if missing
$has_desc = $conn->query("SHOW COLUMNS FROM announcements LIKE 'description'");
if ($has_desc && $has_desc->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN description TEXT DEFAULT NULL AFTER message");
}
// 6) Add 'updated_at' if missing
$has_updated = $conn->query("SHOW COLUMNS FROM announcements LIKE 'updated_at'");
if ($has_updated && $has_updated->num_rows == 0) {
    $conn->query("ALTER TABLE announcements ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Handle save announcement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_announcement'])) {
    $category = sanitize($_POST['ann_category'] ?? 'general');
    $title = sanitize($_POST['ann_title'] ?? '');
    $msg = sanitize($_POST['announcement_message'] ?? '');
    $desc = sanitize($_POST['ann_description'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;
    
    $existing = $conn->query("SELECT id FROM announcements LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $ann_id = $existing->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE announcements SET category=?, title=?, message=?, description=?, is_active=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssii", $category, $title, $msg, $desc, $active, $ann_id);
            $stmt->execute();
            $stmt->close();
        } else {
            setFlashMessage('error', '❌ SQL Error: ' . $conn->error);
            header("Location: admin_dashboard.php");
            exit();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (category, title, message, description, is_active) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssi", $category, $title, $msg, $desc, $active);
            $stmt->execute();
            $stmt->close();
        } else {
            setFlashMessage('error', '❌ SQL Error: ' . $conn->error);
            header("Location: admin_dashboard.php");
            exit();
        }
    }
    setFlashMessage('success', '📢 บันทึกประกาศเรียบร้อยแล้ว!');
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch current announcement
$current_ann = ['category' => 'general', 'title' => '', 'message' => '', 'description' => '', 'is_active' => 1, 'updated_at' => ''];
$ann_res = $conn->query("SELECT * FROM announcements ORDER BY updated_at DESC LIMIT 1");
if ($ann_res && $ann_res->num_rows > 0) {
    $current_ann = $ann_res->fetch_assoc();
}

$pageTitle = "ห้องพักครู"; // Teacher Room

// Greeting Logic
$hour = date('H');
if ($hour < 12) {
    $greeting = "สวัสดีตอนเช้าครับ";
    $g_icon = "fa-sun text-yellow-500";
} elseif ($hour < 18) {
    $greeting = "สวัสดีตอนบ่ายครับ";
    $g_icon = "fa-cloud-sun text-orange-400";
} else {
    $greeting = "สวัสดีตอนเย็นครับ";
    $g_icon = "fa-moon text-indigo-400";
}

require_once '../includes/header.php';
?>

<div class="relative overflow-hidden min-h-screen">
    <!-- Background Gimmicks -->
    <div class="bg-glow top-0 right-0 opacity-20 animate-pulse"></div>
    <div class="bg-glow bottom-0 left-0 opacity-10" style="background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);"></div>

    <div class="container relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4 sticky-sidebar h-fit">
        <div class="glass-panel p-6 text-center">
            <div class="w-24 h-24 bg-indigo-500/20 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden border-2 border-indigo-500">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-chalkboard-teacher text-4xl text-indigo-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-chart-pie w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
            <a href="gradebook.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา<?php if ($pending_leaves > 0): ?> <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full"><?php echo $pending_leaves; ?></span><?php endif; ?></a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่า</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-8">
        
        <?php displayFlashMessage(); ?>

        <!-- Welcome Header -->
        <div class="glass-panel p-8 bg-gradient-to-r from-indigo-900/40 to-slate-800 card-reveal active border-l-8 border-indigo-500">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h1 class="text-3xl font-black mb-2 flex items-center gap-3">
                        <i class="fas <?php echo $g_icon; ?> animate-float"></i>
                        <?php echo $greeting; ?>, ครู<?php echo $_SESSION['full_name']; ?>
                    </h1>
                    <p class="text-gray-400 text-lg">วันนี้ครูมีตารางสอนและงานที่ต้องจัดการอย่างไรบ้างครับ?</p>
                </div>
                <div class="hidden md:block">
                     <div class="flex items-center gap-3 bg-black/30 px-6 py-3 rounded-2xl border border-white/5">
                        <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></div>
                        <span class="text-emerald-400 font-bold tracking-widest uppercase text-xs">System Online</span>
                     </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-panel p-6 flex items-center justify-between border-l-4 border-indigo-500">
                <div>
                    <h3 class="text-gray-400 text-sm font-bold uppercase">นักเรียนทั้งหมด</h3>
                    <p class="text-3xl font-bold mt-1"><?php echo $total_students; ?></p>
                </div>
                <div class="bg-indigo-500/20 p-3 rounded-lg"><i class="fas fa-users text-indigo-400 text-2xl"></i></div>
            </div>

            <div class="glass-panel p-6 flex items-center justify-between border-l-4 border-emerald-500">
                <div>
                    <h3 class="text-gray-400 text-sm font-bold uppercase">งานที่ส่งวันนี้</h3>
                    <p class="text-3xl font-bold mt-1"><?php echo $submitted_work; ?></p>
                </div>
                <div class="bg-emerald-500/20 p-3 rounded-lg"><i class="fas fa-file-upload text-emerald-400 text-2xl"></i></div>
            </div>

             <div class="glass-panel p-6 flex items-center justify-between border-l-4 border-pink-500">
                <div>
                    <h3 class="text-gray-400 text-sm font-bold uppercase">แจ้งเตือนด่วน</h3>
                    <p class="text-3xl font-bold mt-1">0</p>
                </div>
                <div class="bg-pink-500/20 p-3 rounded-lg"><i class="fas fa-bell text-pink-400 text-2xl"></i></div>
            </div>
        </div>

        <!-- 📢 Announcement Editor Widget -->
        <div class="glass-panel p-6 border-l-4 border-amber-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <span class="text-xl"><?php echo ($current_ann['category'] ?? 'general') == 'urgent' ? '🚨' : '📢'; ?></span> แถบประกาศข่าวสาร
                    <?php if ($current_ann['is_active']): ?>
                        <span class="bg-emerald-500/20 text-emerald-400 text-xs font-bold px-2 py-0.5 rounded-full border border-emerald-500/30">กำลังแสดง</span>
                    <?php else: ?>
                        <span class="bg-gray-600/30 text-gray-500 text-xs font-bold px-2 py-0.5 rounded-full border border-gray-600/30">ปิดอยู่</span>
                    <?php endif; ?>
                    <?php if (($current_ann['category'] ?? 'general') == 'urgent'): ?>
                        <span class="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full border border-red-500/30">เรื่องด่วน</span>
                    <?php else: ?>
                        <span class="bg-sky-500/20 text-sky-400 text-xs font-bold px-2 py-0.5 rounded-full border border-sky-500/30">ทั่วไป</span>
                    <?php endif; ?>
                </h3>
                <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" class="bg-amber-500 hover:bg-amber-600 text-black font-bold px-4 py-2 rounded-lg transition text-sm flex items-center gap-2">
                    <i class="fas fa-edit"></i> แก้ไขประกาศ
                </button>
            </div>
            <?php if (!empty($current_ann['title']) || !empty($current_ann['message'])): ?>
                <div class="bg-black/20 rounded-lg p-4 border border-white/5 space-y-2">
                    <?php if (!empty($current_ann['title'])): ?>
                        <p class="text-white font-bold"><i class="fas fa-heading mr-1 text-amber-400"></i> <?php echo htmlspecialchars($current_ann['title']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($current_ann['message'])): ?>
                        <p class="text-amber-300 text-sm"><i class="fas fa-quote-left mr-1 opacity-50"></i> <?php echo htmlspecialchars($current_ann['message']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($current_ann['description'])): ?>
                        <p class="text-gray-400 text-xs mt-1 line-clamp-2"><i class="fas fa-file-alt mr-1"></i> <?php echo htmlspecialchars(mb_substr($current_ann['description'], 0, 100)); ?>...</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm italic">ยังไม่มีข้อความประกาศ — กดปุ่ม "แก้ไขประกาศ" เพื่อเพิ่มข้อความ</p>
            <?php endif; ?>
        </div>

        <!-- Birthday Section -->
        <?php
        $month = date('m');
        $birthdays = $conn->query("SELECT full_name, nickname FROM students WHERE MONTH(created_at) = '$month' LIMIT 5"); // Mock logic (created_at is not dob)
        // Ideally we need dob column. Using placeholder.
        ?>
        <div class="glass-panel p-6 bg-gradient-to-r from-purple-900/50 to-indigo-900/50">
            <h2 class="text-xl font-bold mb-4 flex items-center"><i class="fas fa-birthday-cake text-pink-400 mr-2"></i> สุขสันต์วันเกิดเดือนนี้</h2>
            <div class="flex gap-4 overflow-x-auto pb-2">
                <!-- Example Static Data -->
                 <div class="flex-shrink-0 text-center">
                    <div class="w-12 h-12 bg-pink-500 rounded-full flex items-center justify-center mx-auto mb-2 text-xl">🎂</div>
                    <span class="text-sm font-bold">น้องเอ</span>
                </div>
                 <div class="flex-shrink-0 text-center">
                    <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-2 text-xl">🎁</div>
                    <span class="text-sm font-bold">น้องบี</span>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Announcement Modal -->
<div id="announcementModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="document.getElementById('announcementModal').classList.add('hidden')"></div>
    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-amber-600 to-orange-600 p-5 rounded-t-2xl">
            <h3 class="text-xl font-black flex items-center gap-3 text-white">
                <span class="text-2xl">📢</span> จัดการประกาศข่าวสาร
            </h3>
            <p class="text-amber-100 text-sm mt-1">ข้อความนี้จะแสดงเป็นแถบวิ่งในหน้า Dashboard ของนักเรียน</p>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <!-- Category -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-tag mr-1 text-amber-400"></i>ประเภทประกาศ</label>
                <select name="ann_category" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
                    <option value="general" <?php echo ($current_ann['category'] ?? 'general') == 'general' ? 'selected' : ''; ?>>📢 ทั่วไป</option>
                    <option value="urgent" <?php echo ($current_ann['category'] ?? '') == 'urgent' ? 'selected' : ''; ?>>🚨 เรื่องด่วน</option>
                </select>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-heading mr-1 text-amber-400"></i>หัวข้อประกาศ</label>
                <input type="text" name="ann_title" value="<?php echo htmlspecialchars($current_ann['title'] ?? ''); ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition" placeholder="เช่น: แจ้งหยุดเรียน / ส่งงานด่วน">
            </div>

            <!-- Message (marquee text) -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-pen mr-1 text-amber-400"></i>ข้อความตัววิ่ง <span class="text-gray-500 text-xs font-normal">(แสดงบนแถบประกาศ)</span></label>
                <textarea name="announcement_message" rows="2" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition" placeholder="พิมพ์ข้อความสั้นๆ ที่จะวิ่งบนแถบประกาศ..."><?php echo htmlspecialchars($current_ann['message'] ?? ''); ?></textarea>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-file-alt mr-1 text-amber-400"></i>รายละเอียดเพิ่มเติม <span class="text-gray-500 text-xs font-normal">(แสดงเมื่อกด "อ่านเพิ่มเติม")</span></label>
                <textarea name="ann_description" rows="4" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition" placeholder="พิมพ์รายละเอียดฉบับเต็ม เช่น กำหนดการ, สถานที่, หมายเหตุ..."><?php echo htmlspecialchars($current_ann['description'] ?? ''); ?></textarea>
            </div>

            <!-- Active Toggle -->
            <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
                <div>
                    <p class="font-bold text-sm">เปิดการแสดงผล</p>
                    <p class="text-gray-500 text-xs">เมื่อเปิด นักเรียนจะเห็นแถบประกาศนี้</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" class="sr-only peer" <?php echo $current_ann['is_active'] ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('announcementModal').classList.add('hidden')" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition">ยกเลิก</button>
                <button type="submit" name="save_announcement" class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-black font-black py-3 rounded-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const reveals = document.querySelectorAll('.card-reveal');
        reveals.forEach(el => el.classList.add('active'));
    });
</script>

<?php require_once '../includes/footer.php'; ?>
