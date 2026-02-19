<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "โปรไฟล์นักเรียน";

// Get student ID
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($student_id <= 0) {
    setFlashMessage('error', 'ไม่พบข้อมูลนักเรียน');
    header("Location: students.php");
    exit();
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

// Attendance Stats
$att_present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id AND status = 'present'")->fetch_assoc()['c'];
$att_late = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id AND status = 'late'")->fetch_assoc()['c'];
$att_absent = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id AND status = 'absent'")->fetch_assoc()['c'];
$att_leave = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id = $student_id AND status = 'leave'")->fetch_assoc()['c'];
$att_total = $att_present + $att_late + $att_absent + $att_leave;
$att_percent = $att_total > 0 ? round(($att_present + $att_late) / $att_total * 100, 1) : 0;

// Recent Attendance (last 15 records)
$recent_att = $conn->query("SELECT date, status, check_in_time FROM attendance WHERE student_id = $student_id ORDER BY date DESC LIMIT 15");

// Leave Requests
$leaves = null;
$result_check = $conn->query("SHOW TABLES LIKE 'leave_requests'");
if ($result_check && $result_check->num_rows > 0) {
    $leaves = $conn->query("SELECT * FROM leave_requests WHERE student_id = $student_id ORDER BY created_at DESC LIMIT 10");
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
            <div class="flex items-center gap-4">
                <a href="students.php" class="w-10 h-10 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center text-white transition">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold">โปรไฟล์นักเรียน</h2>
                    <p class="text-gray-400 text-sm">ข้อมูลรายละเอียดและสถิติ</p>
                </div>
                <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-edit mr-1"></i> แก้ไข
                </a>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="glass-panel p-6">
            <div class="flex flex-col md:flex-row gap-6 items-center md:items-start">
                <!-- Photo -->
                <div class="flex-shrink-0">
                    <div class="w-32 h-32 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 rounded-2xl overflow-hidden border-2 border-indigo-500/30">
                        <?php if ($student['profile_image']): ?>
                            <img src="../<?php echo $student['profile_image']; ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <i class="fas fa-user text-4xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Info -->
                <div class="flex-1 text-center md:text-left">
                    <h3 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                    <?php if (!empty($student['nickname'])): ?>
                        <p class="text-indigo-400 text-lg mb-2">"<?php echo htmlspecialchars($student['nickname']); ?>"</p>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-3 justify-center md:justify-start text-sm">
                        <span class="bg-gray-700 px-3 py-1 rounded-full">
                            <i class="fas fa-id-badge mr-1 text-indigo-400"></i> <?php echo htmlspecialchars($student['student_code']); ?>
                        </span>
                        <span class="bg-gray-700 px-3 py-1 rounded-full">
                            <i class="fas fa-school mr-1 text-green-400"></i> ชั้น <?php echo htmlspecialchars($student['class_level']); ?>/<?php echo htmlspecialchars($student['room']); ?>
                        </span>
                        <span class="bg-gray-700 px-3 py-1 rounded-full">
                            <i class="fas fa-hashtag mr-1 text-yellow-400"></i> เลขที่ <?php echo $student['number']; ?>
                        </span>
                    </div>
                </div>
                <!-- XP/Points -->
                <div class="flex gap-3 flex-shrink-0">
                    <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-4 text-center min-w-[80px]">
                        <div class="text-2xl font-bold text-indigo-400"><?php echo $student['xp']; ?></div>
                        <div class="text-xs text-gray-400 font-bold">XP</div>
                    </div>
                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 text-center min-w-[80px]">
                        <div class="text-2xl font-bold text-yellow-400"><?php echo $student['points']; ?></div>
                        <div class="text-xs text-gray-400 font-bold">แต้ม</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Stats -->
        <div class="glass-panel p-6">
            <h3 class="text-lg font-bold mb-4"><i class="fas fa-chart-bar mr-2 text-indigo-400"></i>สถิติการมาเรียน</h3>
            
            <!-- Percentage Bar -->
            <div class="mb-5">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-400">อัตราการเข้าเรียน</span>
                    <span class="text-lg font-bold <?php echo $att_percent >= 80 ? 'text-green-400' : ($att_percent >= 60 ? 'text-yellow-400' : 'text-red-400'); ?>">
                        <?php echo $att_percent; ?>%
                    </span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 <?php echo $att_percent >= 80 ? 'bg-gradient-to-r from-green-500 to-emerald-400' : ($att_percent >= 60 ? 'bg-gradient-to-r from-yellow-500 to-orange-400' : 'bg-gradient-to-r from-red-500 to-pink-400'); ?>"
                        style="width: <?php echo $att_percent; ?>%"></div>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-gray-800 rounded-xl p-3 text-center border border-gray-700">
                    <div class="text-2xl font-bold text-white"><?php echo $att_total; ?></div>
                    <div class="text-xs text-gray-400">บันทึกทั้งหมด</div>
                </div>
                <div class="bg-green-500/10 rounded-xl p-3 text-center border border-green-500/30">
                    <div class="text-2xl font-bold text-green-400"><?php echo $att_present; ?></div>
                    <div class="text-xs text-gray-400">✅ มาเรียน</div>
                </div>
                <div class="bg-yellow-500/10 rounded-xl p-3 text-center border border-yellow-500/30">
                    <div class="text-2xl font-bold text-yellow-400"><?php echo $att_late; ?></div>
                    <div class="text-xs text-gray-400">⏰ สาย</div>
                </div>
                <div class="bg-red-500/10 rounded-xl p-3 text-center border border-red-500/30">
                    <div class="text-2xl font-bold text-red-400"><?php echo $att_absent; ?></div>
                    <div class="text-xs text-gray-400">❌ ขาด</div>
                </div>
                <div class="bg-blue-500/10 rounded-xl p-3 text-center border border-blue-500/30">
                    <div class="text-2xl font-bold text-blue-400"><?php echo $att_leave; ?></div>
                    <div class="text-xs text-gray-400">📋 ลา</div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="glass-panel p-6">
            <h3 class="text-lg font-bold mb-4"><i class="fas fa-history mr-2 text-indigo-400"></i>ประวัติการเช็คชื่อล่าสุด</h3>
            <?php if ($recent_att && $recent_att->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-400 text-left border-b border-gray-700">
                                <th class="pb-2 px-3">วันที่</th>
                                <th class="pb-2 px-3 text-center">สถานะ</th>
                                <th class="pb-2 px-3 text-center">เวลาเข้า</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($att = $recent_att->fetch_assoc()): ?>
                                <?php
                                    $d = strtotime($att['date']);
                                    $thai_months_short = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
                                    $date_display = (int)date('j', $d) . ' ' . $thai_months_short[(int)date('n', $d)] . ' ' . ((int)date('Y', $d) + 543);
                                    
                                    $status_map = [
                                        'present' => ['text' => '✅ มาเรียน', 'class' => 'bg-green-500/20 text-green-400 border-green-500/30'],
                                        'late' => ['text' => '⏰ มาสาย', 'class' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30'],
                                        'absent' => ['text' => '❌ ขาดเรียน', 'class' => 'bg-red-500/20 text-red-400 border-red-500/30'],
                                        'leave' => ['text' => '📋 ลา', 'class' => 'bg-blue-500/20 text-blue-400 border-blue-500/30'],
                                    ];
                                    $s = $status_map[$att['status']] ?? ['text' => '-', 'class' => 'bg-gray-700 text-gray-400'];
                                ?>
                                <tr class="border-b border-gray-800 hover:bg-gray-800/50 transition">
                                    <td class="py-2.5 px-3 text-gray-300"><?php echo $date_display; ?></td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="text-xs font-bold px-3 py-1 rounded-full border <?php echo $s['class']; ?>">
                                            <?php echo $s['text']; ?>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-gray-400">
                                        <?php echo !empty($att['check_in_time']) ? date('H:i', strtotime($att['check_in_time'])) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-500">
                    <i class="fas fa-clipboard-list text-3xl mb-2"></i>
                    <p>ยังไม่มีประวัติการเช็คชื่อ</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Leave Requests -->
        <?php if ($leaves !== null): ?>
        <div class="glass-panel p-6">
            <h3 class="text-lg font-bold mb-4"><i class="fas fa-calendar-minus mr-2 text-indigo-400"></i>ประวัติการลา</h3>
            <?php if ($leaves->num_rows > 0): ?>
                <div class="space-y-3">
                    <?php while ($lv = $leaves->fetch_assoc()): ?>
                        <?php
                            $status_colors = [
                                'pending' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
                                'approved' => 'bg-green-500/20 text-green-400 border-green-500/30',
                                'rejected' => 'bg-red-500/20 text-red-400 border-red-500/30',
                            ];
                            $status_labels = [
                                'pending' => '⏳ รอดำเนินการ',
                                'approved' => '✅ อนุมัติ',
                                'rejected' => '❌ ไม่อนุมัติ',
                            ];
                            $sc = $status_colors[$lv['status']] ?? 'bg-gray-700 text-gray-400';
                            $sl = $status_labels[$lv['status']] ?? $lv['status'];
                            
                            $ld = strtotime($lv['leave_date'] ?? $lv['created_at']);
                            $leave_date_display = date('j', $ld) . ' ' . $thai_months_short[(int)date('n', $ld)] . ' ' . ((int)date('Y', $ld) + 543);
                        ?>
                        <div class="flex items-center gap-4 bg-gray-800/50 rounded-lg p-3 border border-gray-700">
                            <div class="flex-1">
                                <div class="font-bold text-sm"><?php echo htmlspecialchars($lv['reason'] ?? 'ลา'); ?></div>
                                <div class="text-xs text-gray-400"><?php echo $leave_date_display; ?></div>
                            </div>
                            <span class="text-xs font-bold px-3 py-1 rounded-full border <?php echo $sc; ?>">
                                <?php echo $sl; ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-500">
                    <i class="fas fa-calendar-check text-3xl mb-2"></i>
                    <p>ยังไม่มีประวัติการลา</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
