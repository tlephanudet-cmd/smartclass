<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "จัดการใบลา";

// Handle approve/reject via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $leave_id = (int)$_POST['leave_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action == 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $leave_id);
        
        if ($stmt->execute()) {
            // If approved, auto-update attendance to 'leave' for the date range
            if ($action == 'approve') {
                $leave = $conn->query("SELECT * FROM leave_requests WHERE id = $leave_id")->fetch_assoc();
                if ($leave) {
                    $start = new DateTime($leave['start_date']);
                    $end = new DateTime($leave['end_date']);
                    $end->modify('+1 day');
                    $interval = new DateInterval('P1D');
                    $period = new DatePeriod($start, $interval, $end);
                    
                    foreach ($period as $dt) {
                        $d = $dt->format('Y-m-d');
                        $sid = $leave['student_id'];
                        $conn->query("INSERT INTO attendance (student_id, date, status) VALUES ($sid, '$d', 'leave') ON DUPLICATE KEY UPDATE status='leave'");
                    }
                }
            }
            setFlashMessage('success', $action == 'approve' ? '✅ อนุมัติใบลาเรียบร้อย (อัปเดตเช็คชื่ออัตโนมัติ)' : '❌ ปฏิเสธใบลาเรียบร้อย');
        }
    }
    header("Location: manage_leaves.php");
    exit();
}

// Counts
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['cnt'];
$history_count = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status != 'pending'")->fetch_assoc()['cnt'];

// Fetch pending leaves
$pending_leaves = $conn->query("
    SELECT lr.*, s.full_name, s.student_code 
    FROM leave_requests lr 
    JOIN students s ON lr.student_id = s.id 
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at DESC
");

// Fetch history (approved/rejected)
$history_leaves = $conn->query("
    SELECT lr.*, s.full_name, s.student_code 
    FROM leave_requests lr 
    JOIN students s ON lr.student_id = s.id 
    WHERE lr.status != 'pending'
    ORDER BY lr.created_at DESC
");

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
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา
                <?php if ($pending_count > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full ml-1"><?php echo $pending_count; ?></span>
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
            <h2 class="text-2xl font-bold flex items-center gap-3">
                <i class="fas fa-clipboard-list text-indigo-400"></i> จัดการใบลาของนักเรียน
            </h2>
            <p class="text-gray-400 text-sm mt-1">ตรวจสอบเอกสาร อนุมัติหรือปฏิเสธคำขอลาของนักเรียน</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-4">
            <div class="glass-panel p-4 border-l-4 border-yellow-500 text-center">
                <p class="text-3xl font-black text-yellow-400"><?php echo $pending_count; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">รอตรวจสอบ</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-green-500 text-center">
                <?php $approved = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status='approved'")->fetch_assoc()['cnt']; ?>
                <p class="text-3xl font-black text-green-400"><?php echo $approved; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">อนุมัติแล้ว</p>
            </div>
            <div class="glass-panel p-4 border-l-4 border-red-500 text-center">
                <?php $rejected = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status='rejected'")->fetch_assoc()['cnt']; ?>
                <p class="text-3xl font-black text-red-400"><?php echo $rejected; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1">ไม่อนุมัติ</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="glass-panel overflow-hidden">
            <div class="flex border-b border-gray-700">
                <button onclick="switchTab('pending')" id="tab-pending" 
                    class="tab-btn flex-1 px-6 py-4 text-center font-bold transition-all border-b-2 border-yellow-500 text-yellow-400 bg-yellow-500/5">
                    <i class="fas fa-hourglass-half mr-2"></i> รอตรวจสอบ 
                    <?php if ($pending_count > 0): ?>
                        <span class="bg-yellow-500 text-black text-xs font-bold px-2 py-0.5 rounded-full ml-1"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="switchTab('history')" id="tab-history" 
                    class="tab-btn flex-1 px-6 py-4 text-center font-bold transition-all border-b-2 border-transparent text-gray-400 hover:text-gray-300">
                    <i class="fas fa-history mr-2"></i> ประวัติย้อนหลัง
                    <span class="text-xs text-gray-500 ml-1">(<?php echo $history_count; ?>)</span>
                </button>
            </div>

            <!-- Pending Tab Content -->
            <div id="content-pending" class="p-4">
                <?php if ($pending_leaves && $pending_leaves->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-700 text-gray-400 uppercase text-xs">
                                    <th class="text-left py-3 px-3">วันที่ยื่น</th>
                                    <th class="text-left py-3 px-3">นักเรียน</th>
                                    <th class="text-left py-3 px-3">ประเภท</th>
                                    <th class="text-left py-3 px-3">วันที่ลา</th>
                                    <th class="text-left py-3 px-3">เหตุผล</th>
                                    <th class="text-center py-3 px-3">หลักฐาน</th>
                                    <th class="text-center py-3 px-3">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($lv = $pending_leaves->fetch_assoc()): ?>
                                    <?php
                                        $leaveIcon = $lv['leave_type'] == 'sick' ? '🤒' : '📋';
                                        $leaveText = $lv['leave_type'] == 'sick' ? 'ลาป่วย' : 'ลากิจ';
                                        $start = new DateTime($lv['start_date']);
                                        $end = new DateTime($lv['end_date']);
                                        $days = $start->diff($end)->days + 1;
                                    ?>
                                    <tr class="border-b border-gray-800 hover:bg-yellow-500/5 transition">
                                        <td class="py-3 px-3 text-gray-400 text-xs whitespace-nowrap">
                                            <?php echo date('d/m/y H:i', strtotime($lv['created_at'])); ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <p class="font-bold text-white"><?php echo htmlspecialchars($lv['full_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $lv['student_code']; ?></p>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="<?php echo $lv['leave_type'] == 'sick' ? 'text-red-400' : 'text-blue-400'; ?>">
                                                <?php echo $leaveIcon . ' ' . $leaveText; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <p class="text-white"><?php echo date('d/m/y', strtotime($lv['start_date'])); ?>
                                            <?php if ($lv['start_date'] != $lv['end_date']): ?>
                                                <span class="text-gray-500">ถึง</span> <?php echo date('d/m/y', strtotime($lv['end_date'])); ?>
                                            <?php endif; ?></p>
                                            <p class="text-xs text-gray-500">(<?php echo $days; ?> วัน)</p>
                                        </td>
                                        <td class="py-3 px-3 text-gray-300 max-w-[200px]">
                                            <p class="truncate" title="<?php echo htmlspecialchars($lv['reason']); ?>"><?php echo htmlspecialchars($lv['reason']); ?></p>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if (!empty($lv['file_path'])): ?>
                                                <a href="../<?php echo $lv['file_path']; ?>" target="_blank" 
                                                    class="inline-flex items-center gap-1 text-xs bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 px-3 py-1.5 rounded-lg transition font-bold">
                                                    <i class="fas fa-file-image"></i> ดูใบรับรอง
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-600">ไม่มีไฟล์</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex gap-2 justify-center">
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันอนุมัติใบลานี้?')">
                                                    <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-lg shadow-green-900/30">
                                                        <i class="fas fa-check"></i> อนุมัติ
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันปฏิเสธใบลานี้?')">
                                                    <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-lg shadow-red-900/30">
                                                        <i class="fas fa-times"></i> ปฏิเสธ
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-check-double text-5xl mb-4 block text-green-500/30"></i>
                        <p class="text-lg font-bold">ไม่มีใบลาที่รอตรวจสอบ 🎉</p>
                        <p class="text-sm text-gray-600 mt-1">ใบลาทั้งหมดได้รับการจัดการแล้ว</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- History Tab Content -->
            <div id="content-history" class="p-4 hidden">
                <?php if ($history_leaves && $history_leaves->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-700 text-gray-400 uppercase text-xs">
                                    <th class="text-left py-3 px-3">วันที่ยื่น</th>
                                    <th class="text-left py-3 px-3">นักเรียน</th>
                                    <th class="text-left py-3 px-3">ประเภท</th>
                                    <th class="text-left py-3 px-3">วันที่ลา</th>
                                    <th class="text-left py-3 px-3">เหตุผล</th>
                                    <th class="text-center py-3 px-3">หลักฐาน</th>
                                    <th class="text-center py-3 px-3">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($lv = $history_leaves->fetch_assoc()): ?>
                                    <?php
                                        $leaveIcon = $lv['leave_type'] == 'sick' ? '🤒' : '📋';
                                        $leaveText = $lv['leave_type'] == 'sick' ? 'ลาป่วย' : 'ลากิจ';
                                        $start = new DateTime($lv['start_date']);
                                        $end = new DateTime($lv['end_date']);
                                        $days = $start->diff($end)->days + 1;
                                        
                                        if ($lv['status'] == 'approved') {
                                            $badge = 'bg-green-500/20 text-green-400 border-green-500/30';
                                            $badgeText = '✅ อนุมัติ';
                                        } else {
                                            $badge = 'bg-red-500/20 text-red-400 border-red-500/30';
                                            $badgeText = '❌ ไม่อนุมัติ';
                                        }
                                    ?>
                                    <tr class="border-b border-gray-800 hover:bg-gray-800/50 transition">
                                        <td class="py-3 px-3 text-gray-400 text-xs whitespace-nowrap">
                                            <?php echo date('d/m/y H:i', strtotime($lv['created_at'])); ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <p class="font-bold text-white"><?php echo htmlspecialchars($lv['full_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $lv['student_code']; ?></p>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="<?php echo $lv['leave_type'] == 'sick' ? 'text-red-400' : 'text-blue-400'; ?>">
                                                <?php echo $leaveIcon . ' ' . $leaveText; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <p class="text-white"><?php echo date('d/m/y', strtotime($lv['start_date'])); ?>
                                            <?php if ($lv['start_date'] != $lv['end_date']): ?>
                                                <span class="text-gray-500">ถึง</span> <?php echo date('d/m/y', strtotime($lv['end_date'])); ?>
                                            <?php endif; ?></p>
                                            <p class="text-xs text-gray-500">(<?php echo $days; ?> วัน)</p>
                                        </td>
                                        <td class="py-3 px-3 text-gray-300 max-w-[200px]">
                                            <p class="truncate" title="<?php echo htmlspecialchars($lv['reason']); ?>"><?php echo htmlspecialchars($lv['reason']); ?></p>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if (!empty($lv['file_path'])): ?>
                                                <a href="../<?php echo $lv['file_path']; ?>" target="_blank" 
                                                    class="inline-flex items-center gap-1 text-xs bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 px-3 py-1.5 rounded-lg transition font-bold">
                                                    <i class="fas fa-file-image"></i> ดูไฟล์
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-600">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <span class="text-xs font-bold px-3 py-1.5 rounded-full border <?php echo $badge; ?>">
                                                <?php echo $badgeText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-5xl mb-4 block"></i>
                        <p class="text-lg">ยังไม่มีประวัติการจัดการใบลา</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all content
    document.getElementById('content-pending').classList.add('hidden');
    document.getElementById('content-history').classList.add('hidden');
    
    // Reset all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-yellow-500', 'text-yellow-400', 'bg-yellow-500/5', 'border-indigo-500', 'text-indigo-400', 'bg-indigo-500/5');
        btn.classList.add('border-transparent', 'text-gray-400');
    });
    
    // Show selected & style tab
    document.getElementById('content-' + tab).classList.remove('hidden');
    const activeTab = document.getElementById('tab-' + tab);
    activeTab.classList.remove('border-transparent', 'text-gray-400');
    
    if (tab === 'pending') {
        activeTab.classList.add('border-yellow-500', 'text-yellow-400', 'bg-yellow-500/5');
    } else {
        activeTab.classList.add('border-indigo-500', 'text-indigo-400', 'bg-indigo-500/5');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
