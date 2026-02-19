<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

// Auto-create gradebook tables if missing
$conn->query("CREATE TABLE IF NOT EXISTS `gradebook_assignments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `max_score` float NOT NULL DEFAULT 10,
    `sort_order` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `gradebook_scores` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `score` float DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_score` (`assignment_id`, `student_id`),
    KEY `assignment_id` (`assignment_id`),
    KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle add assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_assignment'])) {
    $title = sanitize($_POST['title']);
    $max_score = floatval($_POST['max_score']);
    if (!empty($title) && $max_score > 0) {
        $order = $conn->query("SELECT COALESCE(MAX(sort_order),0)+1 as next_order FROM gradebook_assignments")->fetch_assoc()['next_order'];
        $stmt = $conn->prepare("INSERT INTO gradebook_assignments (title, max_score, sort_order) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $title, $max_score, $order);
        $stmt->execute();
        setFlashMessage('success', "✅ เพิ่มช่องคะแนน \"$title\" (เต็ม $max_score) เรียบร้อย");
    } else {
        setFlashMessage('error', "กรุณากรอกชื่อหัวข้อและคะแนนเต็ม");
    }
    header("Location: gradebook.php"); exit();
}

// Handle delete assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_assignment'])) {
    $del_id = (int)$_POST['assignment_id'];
    $conn->query("DELETE FROM gradebook_scores WHERE assignment_id = $del_id");
    $conn->query("DELETE FROM gradebook_assignments WHERE id = $del_id");
    setFlashMessage('success', "🗑️ ลบช่องคะแนนเรียบร้อย");
    header("Location: gradebook.php"); exit();
}

// Fetch data
$assignments = $conn->query("SELECT * FROM gradebook_assignments ORDER BY sort_order ASC, id ASC");
$students = $conn->query("SELECT * FROM students ORDER BY class_level, room, number ASC");

// Build scores lookup: scores[student_id][assignment_id] = score
$all_scores = $conn->query("SELECT * FROM gradebook_scores");
$scores_map = [];
if ($all_scores) {
    while ($s = $all_scores->fetch_assoc()) {
        $scores_map[$s['student_id']][$s['assignment_id']] = $s['score'];
    }
}

// Pending leaves for sidebar
$pending_leaves = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['cnt'] ?? 0;

$pageTitle = "สมุดคะแนน";
require_once '../includes/header.php';

// Convert assignments to array for reuse
$assignments_arr = [];
if ($assignments) {
    while ($a = $assignments->fetch_assoc()) {
        $assignments_arr[] = $a;
    }
}

// Convert students to array
$students_arr = [];
if ($students) {
    while ($st = $students->fetch_assoc()) {
        $students_arr[] = $st;
    }
}
?>

<style>
    .score-input {
        width: 70px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(99, 102, 241, 0.3);
        color: #e2e8f0;
        text-align: center;
        padding: 6px 4px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
        outline: none;
    }
    .score-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        background: rgba(30, 41, 59, 1);
    }
    .score-input.saved {
        border-color: #22c55e;
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
    }
    .score-input.error {
        border-color: #ef4444;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
    }
    .score-input.over-max {
        border-color: #f59e0b;
        color: #f59e0b;
    }
    .gradebook-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .gradebook-table th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(8px);
    }
    .gradebook-table th:first-child,
    .gradebook-table td:first-child {
        position: sticky;
        left: 0;
        z-index: 5;
        background: rgba(15, 23, 42, 0.95);
    }
    .gradebook-table th:first-child {
        z-index: 15;
    }
    .toast-notification {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        padding: 12px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: slideUp 0.3s ease-out;
        pointer-events: none;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .total-cell {
        font-weight: 800;
        font-size: 15px;
        padding: 4px 8px;
        border-radius: 8px;
    }
</style>

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
            <a href="gradebook.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
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
                        <i class="fas fa-clipboard-list text-indigo-400"></i> สมุดคะแนน
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">บันทึกและจัดการคะแนนเก็บของนักเรียน • Auto-Save เมื่อกรอกคะแนน</p>
                </div>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold transition flex items-center gap-2 shadow-lg shadow-indigo-900/30 whitespace-nowrap">
                    <i class="fas fa-plus"></i> เพิ่มช่องคะแนน
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="glass-panel p-4 border-l-4 border-indigo-500">
                <p class="text-3xl font-black text-indigo-400"><?php echo count($students_arr); ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">นักเรียน</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-purple-500">
                <p class="text-3xl font-black text-purple-400"><?php echo count($assignments_arr); ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">ช่องคะแนน</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-emerald-500">
                <?php
                $total_max = 0;
                foreach ($assignments_arr as $a) $total_max += $a['max_score'];
                ?>
                <p class="text-3xl font-black text-emerald-400"><?php echo $total_max; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">คะแนนเต็มรวม</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-yellow-500">
                <?php
                $filled = 0;
                $total_cells = count($students_arr) * count($assignments_arr);
                foreach ($students_arr as $st) {
                    foreach ($assignments_arr as $a) {
                        if (isset($scores_map[$st['id']][$a['id']]) && $scores_map[$st['id']][$a['id']] !== null) $filled++;
                    }
                }
                $fill_pct = $total_cells > 0 ? round($filled / $total_cells * 100) : 0;
                ?>
                <p class="text-3xl font-black text-yellow-400"><?php echo $fill_pct; ?>%</p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">กรอกแล้ว</p>
            </div>
        </div>

        <!-- Score Table -->
        <div class="glass-panel overflow-hidden">
            <?php if (empty($assignments_arr)): ?>
                <div class="text-center py-16 text-gray-500">
                    <i class="fas fa-clipboard-list text-6xl mb-4 block opacity-30"></i>
                    <p class="text-xl font-bold mb-2">ยังไม่มีช่องคะแนน</p>
                    <p class="text-sm text-gray-600 mb-6">กดปุ่ม "+ เพิ่มช่องคะแนน" เพื่อเริ่มสร้างหัวข้อคะแนน</p>
                    <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                        class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl font-bold transition inline-flex items-center gap-2">
                        <i class="fas fa-plus"></i> เพิ่มช่องคะแนนแรก
                    </button>
                </div>
            <?php elseif (empty($students_arr)): ?>
                <div class="text-center py-16 text-gray-500">
                    <i class="fas fa-users text-6xl mb-4 block opacity-30"></i>
                    <p class="text-xl font-bold">ยังไม่มีนักเรียนในระบบ</p>
                    <p class="text-sm text-gray-600">เพิ่มนักเรียนก่อนเพื่อเริ่มบันทึกคะแนน</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto" style="max-height: 70vh;">
                    <table class="gradebook-table w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-3 px-4 text-gray-400 uppercase text-xs font-bold min-w-[200px]">
                                    <i class="fas fa-user mr-1"></i> นักเรียน
                                </th>
                                <?php foreach ($assignments_arr as $a): ?>
                                    <th class="text-center py-3 px-3 min-w-[110px]">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-gray-200 font-bold text-xs truncate max-w-[100px]" title="<?php echo htmlspecialchars($a['title']); ?>">
                                                <?php echo htmlspecialchars($a['title']); ?>
                                            </span>
                                            <span class="text-indigo-400 text-[11px] font-bold">(<?php echo $a['max_score']; ?>)</span>
                                            <button onclick="confirmDelete(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars($a['title'], ENT_QUOTES); ?>')" 
                                                class="text-gray-600 hover:text-red-400 transition text-[10px]" title="ลบคอลัมน์นี้">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <th class="text-center py-3 px-4 text-yellow-400 uppercase text-xs font-bold min-w-[90px]">
                                    <i class="fas fa-calculator mr-1"></i> รวม
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_arr as $idx => $st): ?>
                                <tr class="border-b border-gray-800/50 hover:bg-indigo-500/5 transition">
                                    <td class="py-2.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-indigo-500/20 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                <?php if (!empty($st['profile_image']) && file_exists('../' . $st['profile_image'])): ?>
                                                    <img src="../<?php echo $st['profile_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                                <?php else: ?>
                                                    <span class="text-indigo-400 text-xs font-bold"><?php echo $st['number']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-white text-xs"><?php echo htmlspecialchars($st['full_name']); ?></p>
                                                <p class="text-gray-500 text-[11px]"><?php echo $st['student_code']; ?> • <?php echo $st['class_level']; ?>/<?php echo $st['room']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <?php 
                                    $student_total = 0;
                                    foreach ($assignments_arr as $a): 
                                        $current_score = $scores_map[$st['id']][$a['id']] ?? '';
                                        if ($current_score !== '' && $current_score !== null) $student_total += floatval($current_score);
                                    ?>
                                        <td class="text-center py-2 px-2">
                                            <input type="number" 
                                                class="score-input" 
                                                data-student="<?php echo $st['id']; ?>" 
                                                data-assignment="<?php echo $a['id']; ?>" 
                                                data-max="<?php echo $a['max_score']; ?>"
                                                value="<?php echo $current_score !== null && $current_score !== '' ? $current_score : ''; ?>"
                                                min="0" 
                                                max="<?php echo $a['max_score']; ?>"
                                                step="0.5"
                                                placeholder="-">
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center py-2 px-3">
                                        <span class="total-cell text-yellow-400" id="total-<?php echo $st['id']; ?>">
                                            <?php echo $student_total > 0 ? $student_total : '-'; ?>
                                        </span>
                                        <span class="text-gray-600 text-[11px]">/<?php echo $total_max; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
        </div>
    </div>
</div>

<!-- Add Assignment Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
    <div class="bg-slate-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-md p-6 relative animate-fade-in">
        <button onclick="document.getElementById('addModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
            <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
            <i class="fas fa-plus-circle text-indigo-400"></i> เพิ่มช่องคะแนน
        </h3>
        <p class="text-gray-400 text-sm mb-6">สร้างคอลัมน์คะแนนใหม่ในสมุดคะแนน</p>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-tag mr-2 text-indigo-400"></i>ชื่อหัวข้อ</label>
                <input type="text" name="title" required placeholder="เช่น สอบกลางภาค, แบบฝึกหัดที่ 1" 
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition placeholder:text-gray-600">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-star mr-2 text-yellow-400"></i>คะแนนเต็ม</label>
                <input type="number" name="max_score" required value="10" min="1" step="0.5"
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" name="add_assignment" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-3 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-900/30">
                    <i class="fas fa-check"></i> เพิ่มช่องคะแนน
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" 
                    class="px-6 bg-gray-700 hover:bg-gray-600 text-gray-300 py-3 rounded-xl font-bold transition">
                    ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Form (hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_assignment" value="1">
    <input type="hidden" name="assignment_id" id="deleteAssignmentId" value="">
</form>

<script>
// Auto-save score via AJAX
document.querySelectorAll('.score-input').forEach(input => {
    let saveTimeout;
    
    input.addEventListener('input', function() {
        const max = parseFloat(this.dataset.max);
        const val = parseFloat(this.value);
        
        // Visual feedback for over-max
        if (val > max) {
            this.classList.add('over-max');
        } else {
            this.classList.remove('over-max');
        }
        
        // Debounced auto-save
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => saveScore(this), 800);
    });
    
    input.addEventListener('change', function() {
        clearTimeout(saveTimeout);
        saveScore(this);
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(saveTimeout);
            saveScore(this);
            // Move to next input
            const inputs = Array.from(document.querySelectorAll('.score-input'));
            const idx = inputs.indexOf(this);
            if (idx < inputs.length - 1) inputs[idx + 1].focus();
        }
        // Tab navigation
        if (e.key === 'Tab') {
            clearTimeout(saveTimeout);
            saveScore(this);
        }
    });
});

function saveScore(input) {
    const studentId = input.dataset.student;
    const assignmentId = input.dataset.assignment;
    const score = input.value;
    const max = parseFloat(input.dataset.max);
    
    // Validate
    if (score !== '' && parseFloat(score) > max) {
        input.value = max;
    }
    if (score !== '' && parseFloat(score) < 0) {
        input.value = 0;
    }
    
    // Send AJAX
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('assignment_id', assignmentId);
    formData.append('score', input.value);
    
    fetch('api_save_score.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.classList.remove('error');
            input.classList.add('saved');
            setTimeout(() => input.classList.remove('saved'), 1500);
            
            // Update total
            updateStudentTotal(studentId);
            
            showToast('✅ บันทึกคะแนนแล้ว', 'success');
        } else {
            input.classList.add('error');
            showToast('❌ ' + (data.error || 'เกิดข้อผิดพลาด'), 'error');
        }
    })
    .catch(() => {
        input.classList.add('error');
        showToast('❌ ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
    });
}

function updateStudentTotal(studentId) {
    const inputs = document.querySelectorAll(`.score-input[data-student="${studentId}"]`);
    let total = 0;
    let hasValue = false;
    inputs.forEach(inp => {
        if (inp.value !== '') {
            total += parseFloat(inp.value) || 0;
            hasValue = true;
        }
    });
    const totalEl = document.getElementById('total-' + studentId);
    if (totalEl) {
        totalEl.textContent = hasValue ? total : '-';
    }
}

let toastTimer;
function showToast(message, type) {
    // Remove existing
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.style.background = type === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)';
    toast.style.color = type === 'success' ? '#4ade80' : '#f87171';
    toast.style.border = type === 'success' ? '1px solid rgba(34, 197, 94, 0.3)' : '1px solid rgba(239, 68, 68, 0.3)';
    toast.style.backdropFilter = 'blur(8px)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.remove(), 2000);
}

function confirmDelete(id, title) {
    if (confirm('⚠️ ลบช่องคะแนน "' + title + '" ?\n\nคะแนนทั้งหมดในช่องนี้จะถูกลบด้วย')) {
        document.getElementById('deleteAssignmentId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
