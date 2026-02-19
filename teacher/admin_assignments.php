<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "จัดการการบ้าน";

// === Auto-migrate: add missing columns ===
$cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM assignments");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) $cols[] = $c['Field'];
}
if (!in_array('type', $cols)) $conn->query("ALTER TABLE assignments ADD COLUMN `type` varchar(30) DEFAULT 'worksheet' AFTER `title`");
if (!in_array('max_score', $cols)) $conn->query("ALTER TABLE assignments ADD COLUMN `max_score` int DEFAULT 10 AFTER `type`");
if (!in_array('deadline', $cols)) {
    $conn->query("ALTER TABLE assignments ADD COLUMN `deadline` datetime DEFAULT NULL AFTER `max_score`");
    // Copy from due_date if exists
    if (in_array('due_date', $cols)) $conn->query("UPDATE assignments SET deadline = due_date WHERE deadline IS NULL");
}
if (!in_array('link_url', $cols)) $conn->query("ALTER TABLE assignments ADD COLUMN `link_url` varchar(500) DEFAULT NULL AFTER `file_path`");

// Auto-create assignment_submissions table
$conn->query("CREATE TABLE IF NOT EXISTS `assignment_submissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `file_path` varchar(255) DEFAULT NULL,
    `comment` text DEFAULT NULL,
    `score` float DEFAULT NULL,
    `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `assignment_id` (`assignment_id`),
    KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get teacher id
$teacher_user_id = $_SESSION['user_id'];
$t_res = $conn->query("SELECT id FROM teachers WHERE user_id = $teacher_user_id");
$teacher_id = ($t_res && $t_res->num_rows > 0) ? $t_res->fetch_assoc()['id'] : 0;

// Handle Create
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_assignment'])) {
    $title = sanitize($_POST['title']);
    $desc = sanitize($_POST['description']);
    $type = sanitize($_POST['type']);
    $score = (int)$_POST['max_score'];
    $deadline = sanitize($_POST['deadline']);
    $link = sanitize($_POST['link_url'] ?? '');
    
    $filePath = '';
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $targetDir = "../uploads/assignments/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES["file_upload"]["name"]);
        if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $targetDir . $fileName)) {
            $filePath = "uploads/assignments/" . $fileName;
        }
    }

    $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, title, description, type, max_score, deadline, file_path, link_url, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssissss", $teacher_id, $title, $desc, $type, $score, $deadline, $filePath, $link, $deadline);
    
    if ($stmt->execute()) {
        setFlashMessage('success', "✅ สั่งการบ้าน \"$title\" เรียบร้อยแล้ว");
    } else {
        setFlashMessage('error', 'เกิดข้อผิดพลาด: ' . $conn->error);
    }
    header("Location: admin_assignments.php"); exit();
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_assignment'])) {
    $id = (int)$_POST['assignment_id'];
    $conn->query("DELETE FROM assignment_submissions WHERE assignment_id = $id");
    $conn->query("DELETE FROM assignments WHERE id = $id");
    setFlashMessage('success', '🗑️ ลบการบ้านเรียบร้อยแล้ว');
    header("Location: admin_assignments.php"); exit();
}

// Fetch Assignments with submission counts
$assignments = $conn->query("
    SELECT a.*, 
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submit_count
    FROM assignments a 
    ORDER BY a.created_at DESC
");

// Count students
$total_students = $conn->query("SELECT COUNT(*) as cnt FROM students")->fetch_assoc()['cnt'];

// Pending leaves for sidebar
$pending_leaves = 0;
$pl_res = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'");
if ($pl_res) $pending_leaves = $pl_res->fetch_assoc()['cnt'];

require_once '../includes/header.php';

// Convert
$assignments_arr = [];
if ($assignments && $assignments !== false) {
    while ($a = $assignments->fetch_assoc()) {
        $assignments_arr[] = $a;
    }
}

// Thai months
$thai_months_short = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
?>

<div class="relative overflow-hidden min-h-screen">
    <div class="bg-glow top-0 right-0 opacity-20 animate-pulse"></div>
    <div class="bg-glow bottom-0 left-0 opacity-10" style="background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);"></div>

    <div class="container relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4 sticky-sidebar h-fit">
        <div class="glass-panel p-6 text-center">
            <div class="w-24 h-24 bg-indigo-500/20 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden border-2 border-indigo-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-chalkboard-teacher text-4xl text-indigo-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-pie w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
            <a href="gradebook.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา
                <?php if ($pending_leaves > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่า</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <!-- Header -->
        <div class="glass-panel p-6 bg-gradient-to-r from-indigo-900/40 to-slate-800 border-l-8 border-indigo-500">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-book text-indigo-400"></i> จัดการการบ้าน
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">สั่งงาน ดูสถานะการส่ง และจัดการเอกสาร</p>
                </div>
                <button onclick="document.getElementById('createModal').classList.remove('hidden')" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold transition flex items-center gap-2 shadow-lg shadow-indigo-900/30 whitespace-nowrap">
                    <i class="fas fa-plus"></i> สั่งงานใหม่
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="glass-panel p-4 border-l-4 border-indigo-500">
                <p class="text-3xl font-black text-indigo-400"><?php echo count($assignments_arr); ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">งานทั้งหมด</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-yellow-500">
                <?php
                $active_count = 0;
                foreach ($assignments_arr as $a) {
                    $dl = $a['deadline'] ?? $a['due_date'] ?? null;
                    if ($dl && strtotime($dl) > time()) $active_count++;
                }
                ?>
                <p class="text-3xl font-black text-yellow-400"><?php echo $active_count; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">ยังไม่หมดเวลา</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-emerald-500">
                <p class="text-3xl font-black text-emerald-400"><?php echo $total_students; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">นักเรียนในระบบ</p>
            </div>
        </div>

        <!-- Assignments List -->
        <div class="space-y-4">
            <?php if (empty($assignments_arr)): ?>
                <div class="glass-panel text-center py-16 text-gray-500">
                    <i class="fas fa-book-open text-6xl mb-4 block opacity-30"></i>
                    <p class="text-xl font-bold mb-2">ยังไม่มีการบ้านที่สั่ง</p>
                    <p class="text-sm text-gray-600 mb-6">กดปุ่ม "สั่งงานใหม่" เพื่อเริ่มสร้างงาน</p>
                    <button onclick="document.getElementById('createModal').classList.remove('hidden')" 
                        class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl font-bold transition inline-flex items-center gap-2">
                        <i class="fas fa-plus"></i> สั่งงานแรก
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($assignments_arr as $row): ?>
                    <?php 
                        $deadline = $row['deadline'] ?? $row['due_date'] ?? null;
                        $isExpired = ($deadline && strtotime($deadline) < time());
                        $submitted = $row['submit_count'] ?? 0;
                        $submit_pct = $total_students > 0 ? round($submitted / $total_students * 100) : 0;
                        
                        // Time remaining
                        $timeLeft = '';
                        if ($deadline) {
                            $diff = strtotime($deadline) - time();
                            if ($diff > 0) {
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);
                                if ($days > 0) $timeLeft = "เหลือ $days วัน $hours ชม.";
                                else $timeLeft = "เหลือ $hours ชม.";
                            } else {
                                $timeLeft = "หมดเวลาแล้ว";
                            }
                        }
                        
                        // Type badge
                        $typeBadges = [
                            'worksheet' => ['ใบงาน', 'bg-blue-500/20 text-blue-400 border-blue-500/30'],
                            'project' => ['โครงงาน', 'bg-purple-500/20 text-purple-400 border-purple-500/30'],
                            'exercise' => ['แบบฝึกหัด', 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30'],
                            'quiz' => ['สอบย่อย', 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30'],
                        ];
                        $type = $row['type'] ?? 'worksheet';
                        $badge = $typeBadges[$type] ?? ['งาน', 'bg-gray-500/20 text-gray-400 border-gray-500/30'];
                        
                        // Date display
                        $dateDisplay = '';
                        if ($deadline) {
                            $ts = strtotime($deadline);
                            $dateDisplay = (int)date('j', $ts) . ' ' . $thai_months_short[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543) . ' ' . date('H:i', $ts);
                        }
                    ?>
                    <div class="glass-panel p-5 border-l-4 <?php echo $isExpired ? 'border-red-500 bg-red-900/5' : 'border-indigo-500'; ?> hover:bg-white/[0.02] transition">
                        <div class="flex flex-col md:flex-row gap-4">
                            <!-- Left: Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full border <?php echo $badge[1]; ?>">
                                        <?php echo $badge[0]; ?>
                                    </span>
                                    <h3 class="text-lg font-bold text-white"><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <?php if ($isExpired): ?>
                                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30">
                                            <i class="fas fa-clock mr-1"></i>หมดเวลา
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($row['description'])): ?>
                                    <p class="text-gray-400 text-sm mb-3 line-clamp-2"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                                    <?php if ($deadline): ?>
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-calendar-alt text-indigo-400"></i> 
                                            <?php echo $dateDisplay; ?>
                                        </span>
                                        <span class="flex items-center gap-1 <?php echo $isExpired ? 'text-red-400' : 'text-yellow-400'; ?>">
                                            <i class="fas fa-hourglass-half"></i>
                                            <?php echo $timeLeft; ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-star text-yellow-400"></i> 
                                        <?php echo $row['max_score'] ?? 10; ?> คะแนน
                                    </span>
                                    <?php if (!empty($row['file_path'])): ?>
                                        <a href="../<?php echo $row['file_path']; ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition">
                                            <i class="fas fa-paperclip"></i> ไฟล์แนบ
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['link_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['link_url']); ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 transition">
                                            <i class="fas fa-link"></i> ลิงก์
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right: Stats + Actions -->
                            <div class="flex flex-col items-end gap-3 min-w-[160px]">
                                <!-- Submit progress -->
                                <div class="text-right w-full">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-400">ส่งงานแล้ว</span>
                                        <span class="font-bold <?php echo $submit_pct >= 80 ? 'text-green-400' : ($submit_pct >= 50 ? 'text-yellow-400' : 'text-red-400'); ?>">
                                            <?php echo $submitted; ?>/<?php echo $total_students; ?>
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 <?php echo $submit_pct >= 80 ? 'bg-green-500' : ($submit_pct >= 50 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                            style="width: <?php echo $submit_pct; ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex gap-2 w-full">
                                    <a href="view_submissions.php?id=<?php echo $row['id']; ?>" 
                                        class="flex-1 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white text-center py-2 px-3 rounded-lg text-xs font-bold transition">
                                        <i class="fas fa-eye mr-1"></i> ดูงาน
                                    </a>
                                    <form method="POST" onsubmit="return confirm('⚠️ ยืนยันลบการบ้านนี้?\nงานที่ส่งมาทั้งหมดจะถูกลบด้วย');" class="flex-shrink-0">
                                        <input type="hidden" name="delete_assignment" value="1">
                                        <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white py-2 px-3 rounded-lg text-xs font-bold transition">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
    <div class="bg-slate-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-2xl p-6 relative" style="max-height: 90vh; overflow-y: auto;">
        <button onclick="document.getElementById('createModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
            <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
            <i class="fas fa-plus-circle text-indigo-400"></i> สั่งงานใหม่
        </h3>
        <p class="text-gray-400 text-sm mb-6">สร้างการบ้านเพื่อส่งให้นักเรียน</p>
        
        <form action="admin_assignments.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-heading mr-2 text-indigo-400"></i>หัวข้อการบ้าน <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="เช่น แบบฝึกหัดบทที่ 1 - การเขียนโปรแกรม"
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition placeholder:text-gray-600">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-tag mr-2 text-purple-400"></i>ประเภทงาน</label>
                    <select name="type" class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                        <option value="worksheet">📄 ใบงาน / การบ้าน</option>
                        <option value="exercise">📝 แบบฝึกหัด</option>
                        <option value="project">🎯 โครงงาน / โปรเจกต์</option>
                        <option value="quiz">📋 สอบย่อย</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-star mr-2 text-yellow-400"></i>คะแนนเต็ม</label>
                    <input type="number" name="max_score" value="10" min="0" step="1"
                        class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-clock mr-2 text-red-400"></i>กำหนดส่ง <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="deadline" required
                        class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-paperclip mr-2 text-emerald-400"></i>แนบไฟล์ (ถ้ามี)</label>
                    <input type="file" name="file_upload"
                        class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-link mr-2 text-cyan-400"></i>ลิงก์เพิ่มเติม</label>
                    <input type="url" name="link_url" placeholder="https://youtube.com/..."
                        class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition placeholder:text-gray-600">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-align-left mr-2 text-gray-400"></i>รายละเอียด / คำสั่ง</label>
                <textarea name="description" rows="4" placeholder="อธิบายรายละเอียดของงาน..."
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition placeholder:text-gray-600 resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" name="create_assignment" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-900/30">
                    <i class="fas fa-paper-plane"></i> สั่งงาน
                </button>
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" 
                    class="px-6 bg-gray-700 hover:bg-gray-600 text-gray-300 py-3 rounded-xl font-bold transition">
                    ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
