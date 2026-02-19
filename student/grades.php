<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "ผลการเรียนของฉัน";
$student_id = $_SESSION['student_id'];

// Ensure gradebook tables exist
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

// Fetch all assignments with student's scores (LEFT JOIN)
$stmt = $conn->prepare("
    SELECT a.id, a.title, a.max_score, a.sort_order, s.score
    FROM gradebook_assignments a
    LEFT JOIN gradebook_scores s ON a.id = s.assignment_id AND s.student_id = ?
    ORDER BY a.sort_order ASC, a.created_at ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$grades = [];
$total_score = 0;
$total_max = 0;
$graded_count = 0;
$total_count = 0;

while ($row = $result->fetch_assoc()) {
    $grades[] = $row;
    $total_count++;
    $total_max += $row['max_score'];
    if ($row['score'] !== null) {
        $total_score += $row['score'];
        $graded_count++;
    }
}

$overall_percent = $total_max > 0 ? round(($total_score / $total_max) * 100, 1) : 0;

// Pending leave count for student sidebar
$pending_leaves = 0;
$pl_res = $conn->prepare("SELECT COUNT(*) as cnt FROM leave_requests WHERE student_id = ? AND status = 'pending'");
if ($pl_res) {
    $pl_res->bind_param("i", $student_id);
    $pl_res->execute();
    $pending_leaves = $pl_res->get_result()->fetch_assoc()['cnt'] ?? 0;
}

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-20 h-20 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-3 overflow-hidden border-2 border-emerald-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-graduate text-3xl text-emerald-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="grades.php" class="block px-4 py-2 rounded bg-emerald-600 text-white shadow-lg"><i class="fas fa-chart-line w-8"></i> ผลการเรียน</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> ขอลา
                <?php if ($pending_leaves > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full ml-1"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">

        <!-- Header -->
        <div class="glass-panel p-6 bg-gradient-to-r from-emerald-900/40 to-slate-800 border-l-8 border-emerald-500">
            <h2 class="text-2xl font-bold flex items-center gap-3">
                <i class="fas fa-chart-bar text-emerald-400"></i> ผลการเรียนของฉัน
            </h2>
            <p class="text-gray-400 text-sm mt-1">สรุปคะแนนเก็บทุกรายการจากระบบสมุดคะแนน</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- คะแนนรวมสะสม -->
            <div class="glass-panel p-5 border-t-4 border-emerald-500 text-center">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2"><i class="fas fa-trophy mr-1 text-emerald-400"></i>คะแนนรวมสะสม</p>
                <p class="text-3xl font-black text-emerald-400"><?php echo number_format($total_score, 1); ?></p>
                <p class="text-gray-500 text-sm">จากคะแนนเต็ม <?php echo number_format($total_max, 1); ?></p>
            </div>
            <!-- ร้อยละรวม -->
            <div class="glass-panel p-5 border-t-4 border-<?php echo $overall_percent >= 50 ? 'cyan' : 'red'; ?>-500 text-center">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2"><i class="fas fa-percentage mr-1 text-cyan-400"></i>ร้อยละรวม</p>
                <p class="text-3xl font-black text-<?php echo $overall_percent >= 50 ? 'cyan' : 'red'; ?>-400"><?php echo $overall_percent; ?>%</p>
                <p class="text-gray-500 text-sm"><?php echo $overall_percent >= 50 ? '✅ ระดับผ่านเกณฑ์' : '⚠️ ต่ำกว่าเกณฑ์'; ?></p>
            </div>
            <!-- จำนวนรายการ -->
            <div class="glass-panel p-5 border-t-4 border-purple-500 text-center">
                <p class="text-xs text-gray-400 font-bold uppercase mb-2"><i class="fas fa-list-ol mr-1 text-purple-400"></i>สถานะการเก็บคะแนน</p>
                <p class="text-3xl font-black text-purple-400"><?php echo $graded_count; ?> <span class="text-lg text-gray-500">/ <?php echo $total_count; ?></span></p>
                <p class="text-gray-500 text-sm">รายการที่มีคะแนนแล้ว</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <?php if ($total_count > 0): ?>
        <div class="glass-panel p-4">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-400 font-bold"><i class="fas fa-chart-line mr-1"></i>ความก้าวหน้าเฉลี่ย</span>
                <span class="font-bold <?php echo $overall_percent >= 50 ? 'text-emerald-400' : 'text-red-400'; ?>"><?php echo $overall_percent; ?>%</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-3">
                <div class="h-3 rounded-full transition-all duration-500 <?php echo $overall_percent >= 80 ? 'bg-gradient-to-r from-emerald-500 to-green-400' : ($overall_percent >= 50 ? 'bg-gradient-to-r from-yellow-500 to-amber-400' : 'bg-gradient-to-r from-red-500 to-rose-400'); ?>" style="width: <?php echo min($overall_percent, 100); ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Score Table -->
        <?php if (empty($grades)): ?>
            <div class="glass-panel text-center py-16 text-gray-500">
                <i class="fas fa-clipboard-list text-6xl mb-4 block opacity-30"></i>
                <p class="text-xl font-bold mb-2">ยังไม่มีรายการคะแนน</p>
                <p class="text-sm text-gray-600">ครูยังไม่ได้สร้างรายการเก็บคะแนนในระบบ</p>
            </div>
        <?php else: ?>
            <div class="glass-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-800/60 text-gray-300">
                                <th class="text-left px-5 py-3.5 font-bold">#</th>
                                <th class="text-left px-5 py-3.5 font-bold"><i class="fas fa-bookmark mr-1 text-cyan-400"></i>หัวข้อการเก็บคะแนน</th>
                                <th class="text-center px-5 py-3.5 font-bold"><i class="fas fa-star mr-1 text-yellow-400"></i>คะแนนเต็ม</th>
                                <th class="text-center px-5 py-3.5 font-bold"><i class="fas fa-pen mr-1 text-emerald-400"></i>คะแนนที่ได้</th>
                                <th class="text-center px-5 py-3.5 font-bold"><i class="fas fa-percentage mr-1 text-blue-400"></i>ร้อยละ</th>
                                <th class="text-center px-5 py-3.5 font-bold"><i class="fas fa-flag mr-1 text-purple-400"></i>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $idx = 0;
                            foreach ($grades as $g): 
                                $idx++;
                                $has_score = ($g['score'] !== null);
                                $percent = ($has_score && $g['max_score'] > 0) ? round(($g['score'] / $g['max_score']) * 100, 1) : null;
                                $passed = ($percent !== null && $percent >= 50);
                            ?>
                            <tr class="border-t border-gray-700/50 hover:bg-white/[0.02] transition">
                                <td class="px-5 py-3.5 text-gray-500 font-mono"><?php echo $idx; ?></td>
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-white"><?php echo htmlspecialchars($g['title']); ?></span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="text-yellow-400 font-bold"><?php echo number_format($g['max_score'], 1); ?></span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <?php if ($has_score): ?>
                                        <span class="font-black text-lg <?php echo $passed ? 'text-emerald-400' : 'text-red-400'; ?>"><?php echo number_format($g['score'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-500 italic">— ยังไม่มี —</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <?php if ($percent !== null): ?>
                                        <span class="font-bold <?php echo $passed ? 'text-cyan-400' : 'text-red-400'; ?>"><?php echo $percent; ?>%</span>
                                    <?php else: ?>
                                        <span class="text-gray-500">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <?php if ($percent !== null): ?>
                                        <?php if ($passed): ?>
                                            <span class="bg-emerald-500/20 text-emerald-400 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/30">✅ ผ่าน</span>
                                        <?php else: ?>
                                            <span class="bg-red-500/20 text-red-400 text-xs font-bold px-3 py-1 rounded-full border border-red-500/30">⚠️ ปรับปรุง</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="bg-gray-600/30 text-gray-500 text-xs font-bold px-3 py-1 rounded-full border border-gray-600/30">⏳ รอคะแนน</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <!-- Footer Summary Row -->
                        <tfoot>
                            <tr class="bg-gray-800/80 border-t-2 border-emerald-500/30">
                                <td class="px-5 py-4" colspan="2">
                                    <span class="font-black text-white"><i class="fas fa-calculator mr-2 text-emerald-400"></i>รวมทั้งหมด</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="font-bold text-yellow-400"><?php echo number_format($total_max, 1); ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="font-black text-lg <?php echo $overall_percent >= 50 ? 'text-emerald-400' : 'text-red-400'; ?>"><?php echo number_format($total_score, 1); ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="font-black text-lg <?php echo $overall_percent >= 50 ? 'text-cyan-400' : 'text-red-400'; ?>"><?php echo $overall_percent; ?>%</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php if ($overall_percent >= 80): ?>
                                        <span class="bg-emerald-500/20 text-emerald-300 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/30">🏆 ดีมาก</span>
                                    <?php elseif ($overall_percent >= 50): ?>
                                        <span class="bg-yellow-500/20 text-yellow-300 text-xs font-bold px-3 py-1 rounded-full border border-yellow-500/30">✅ ผ่าน</span>
                                    <?php else: ?>
                                        <span class="bg-red-500/20 text-red-300 text-xs font-bold px-3 py-1 rounded-full border border-red-500/30">⚠️ ควรปรับปรุง</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
