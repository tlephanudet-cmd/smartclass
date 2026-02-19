<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }
$pageTitle = "เช็คชื่อนักเรียน";
$today = date('Y-m-d');

// Get all students (always needed for card rendering)
$students_result = $conn->query("SELECT id, full_name, student_code, profile_image FROM students ORDER BY student_code");
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

// Get today's attendance for initial render
$att_data = [];
$att_result = $conn->query("SELECT student_id, status FROM attendance WHERE date = '$today'");
while ($row = $att_result->fetch_assoc()) {
    $att_data[$row['student_id']] = $row['status'];
}

// Counts for today
$count_present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date = '$today' AND status = 'present'")->fetch_assoc()['c'];
$count_late = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date = '$today' AND status = 'late'")->fetch_assoc()['c'];
$count_absent = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date = '$today' AND status = 'absent'")->fetch_assoc()['c'];
$count_leave = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date = '$today' AND status = 'leave'")->fetch_assoc()['c'];
$count_unchecked = count($students) - ($count_present + $count_late + $count_absent + $count_leave);

require_once '../includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
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
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-pie w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
            <a href="gradebook.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่า</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>
    <div class="md:col-span-3 space-y-6">
        <!-- Header with Date Picker -->
        <div class="glass-panel p-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                     <h2 class="text-2xl font-bold mb-1" id="date-heading">เช็คชื่อ: <?php echo date('D, d M Y'); ?></h2>
                     <p class="text-gray-400 text-sm">จัดการการเช็คชื่อรายวัน หรือเลือกวันที่ย้อนหลัง</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="changeDate(-1)" class="w-9 h-9 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center text-white transition" title="วันก่อนหน้า">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <input type="date" id="attendance-date" value="<?php echo $today; ?>" 
                        class="bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition text-center font-bold"
                        onchange="loadAttendanceForDate(this.value)">
                    <button onclick="changeDate(1)" class="w-9 h-9 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center text-white transition" title="วันถัดไป">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button onclick="goToToday()" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm px-3 py-2 rounded-lg transition font-bold">
                        วันนี้
                    </button>
                    <button onclick="printReport()" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-3 py-2 rounded-lg transition font-bold" title="ออกรายงาน PDF">
                        <i class="fas fa-file-pdf mr-1"></i> ออกรายงาน PDF
                    </button>
                    <button onclick="toggleQRCode()" class="btn btn-primary"><i class="fas fa-qrcode mr-2"></i> QR</button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="flex flex-wrap gap-3 mt-4 pt-4 border-t border-gray-700" id="stats-bar">
                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-green-500/20 text-green-400 border border-green-500/30" id="stat-present">✅ มา <?php echo $count_present; ?></span>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-yellow-500/20 text-yellow-400 border border-yellow-500/30" id="stat-late">⏰ สาย <?php echo $count_late; ?></span>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-red-500/20 text-red-400 border border-red-500/30" id="stat-absent">❌ ขาด <?php echo $count_absent; ?></span>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-blue-500/20 text-blue-400 border border-blue-500/30" id="stat-leave">📋 ลา <?php echo $count_leave; ?></span>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-gray-700 text-gray-400 border border-gray-600" id="stat-unchecked">⬜ ยังไม่เช็ค <?php echo $count_unchecked; ?></span>
            </div>

            <div id="qr-section" class="hidden mt-6 text-center border-t border-gray-700 pt-6">
                <div class="bg-white p-4 inline-block rounded-lg">
                    <div id="qrcode"></div>
                </div>
                <p class="mt-4 text-white text-lg font-bold">สแกนเพื่อเช็คชื่อ</p>
                <div class="mt-2 text-sm text-gray-400">รหัสจะอัปเดตทุก 5 นาที (ทดสอบ)</div>
            </div>
        </div>

        <!-- Batch Actions -->
        <div class="glass-panel p-3 flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-400 font-bold mr-2">เช็คทั้งหมด:</span>
            <button onclick="markAll('present')" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg transition font-bold"><i class="fas fa-check-double mr-1"></i> มาทุกคน</button>
            <button onclick="markAll('absent')" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg transition font-bold"><i class="fas fa-times mr-1"></i> ขาดทุกคน</button>
        </div>

        <!-- Student Cards -->
        <div class="glass-panel p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="students-grid">
                <?php foreach ($students as $student): ?>
                    <?php 
                        $sid = $student['id'];
                        $status = $att_data[$sid] ?? '';
                        $statusColor = $status == 'present' ? 'bg-green-500/20 border-green-500' : 
                                      ($status == 'late' ? 'bg-yellow-500/20 border-yellow-500' : 
                                      ($status == 'leave' ? 'bg-blue-500/20 border-blue-500' : 
                                      ($status == 'absent' ? 'bg-red-500/20 border-red-500' : 'bg-gray-800 border-gray-700')));
                    ?>
                    <div class="border rounded-lg p-3 flex items-center justify-between <?php echo $statusColor; ?> transition-all" id="card-<?php echo $sid; ?>" data-student-id="<?php echo $sid; ?>">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-700 rounded-full overflow-hidden">
                                <?php if ($student['profile_image']): ?>
                                    <img src="../<?php echo $student['profile_image']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-xs"></i></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm"><?php echo $student['full_name']; ?></h4>
                                <p class="text-xs text-gray-400"><?php echo $student['student_code']; ?></p>
                                <p class="text-xs mt-0.5 font-semibold" id="status-label-<?php echo $sid; ?>">
                                    <?php 
                                    if ($status == 'present') echo '<span class="text-green-400">✅ มาเรียน</span>';
                                    elseif ($status == 'late') echo '<span class="text-yellow-400">⏰ มาสาย</span>';
                                    elseif ($status == 'absent') echo '<span class="text-red-400">❌ ขาดเรียน</span>';
                                    elseif ($status == 'leave') echo '<span class="text-blue-400">📋 ลา</span>';
                                    else echo '<span class="text-gray-500">— ยังไม่เช็ค</span>';
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-1" id="btns-<?php echo $sid; ?>">
                            <button onclick="markAttendance(<?php echo $sid; ?>, 'present')" 
                                class="att-btn w-7 h-7 rounded-full flex items-center justify-center text-xs text-white transition-all duration-200 <?php echo $status=='present' ? 'bg-green-500 ring-2 ring-green-300 scale-110' : 'bg-green-500/40 hover:bg-green-500'; ?>" 
                                data-status="present" title="มาเรียน">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="markAttendance(<?php echo $sid; ?>, 'late')" 
                                class="att-btn w-7 h-7 rounded-full flex items-center justify-center text-xs text-white transition-all duration-200 <?php echo $status=='late' ? 'bg-yellow-500 ring-2 ring-yellow-300 scale-110' : 'bg-yellow-500/40 hover:bg-yellow-500'; ?>" 
                                data-status="late" title="มาสาย">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button onclick="markAttendance(<?php echo $sid; ?>, 'absent')" 
                                class="att-btn w-7 h-7 rounded-full flex items-center justify-center text-xs text-white transition-all duration-200 <?php echo $status=='absent' ? 'bg-red-500 ring-2 ring-red-300 scale-110' : 'bg-red-500/40 hover:bg-red-500'; ?>" 
                                data-status="absent" title="ขาดเรียน">
                                <i class="fas fa-times"></i>
                            </button>
                            <a href="chat_room.php?student_id=<?php echo $sid; ?>" 
                                class="w-7 h-7 rounded-full bg-blue-500/40 hover:bg-blue-500 flex items-center justify-center text-xs text-white transition-all duration-200" 
                                title="ส่งข้อความหานักเรียน">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                     <div class="col-span-full text-center py-4">ไม่พบนักเรียนในระบบ</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    let qrGenerated = false;
    let currentDate = '<?php echo $today; ?>';
    const todayDate = '<?php echo $today; ?>';

    function toggleQRCode() {
        const qrSection = document.getElementById('qr-section');
        qrSection.classList.toggle('hidden');
        if (!qrSection.classList.contains('hidden') && !qrGenerated) {
            const checkinUrl = "<?php echo $base_url; ?>/api/checkin.php?class=M1/1&timestamp=" + Date.now();
            new QRCode(document.getElementById("qrcode"), { text: checkinUrl, width: 256, height: 256 });
            qrGenerated = true;
        }
    }

    // ===== Date Navigation =====
    function changeDate(offset) {
        const dateInput = document.getElementById('attendance-date');
        const d = new Date(dateInput.value);
        d.setDate(d.getDate() + offset);
        const newDate = d.toISOString().split('T')[0];
        dateInput.value = newDate;
        loadAttendanceForDate(newDate);
    }

    function goToToday() {
        const dateInput = document.getElementById('attendance-date');
        dateInput.value = todayDate;
        loadAttendanceForDate(todayDate);
    }

    function loadAttendanceForDate(date) {
        currentDate = date;

        // Update heading
        const dateObj = new Date(date + 'T00:00:00');
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const formattedDate = dateObj.toLocaleDateString('th-TH', options);
        const heading = document.getElementById('date-heading');
        
        const isToday = date === todayDate;
        heading.innerHTML = 'เช็คชื่อ: ' + formattedDate + (isToday ? '' : ' <span class="text-xs bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full ml-2">ย้อนหลัง</span>');

        // Fetch data
        fetch('api_get_attendance.php?date=' + date)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                applyAttendanceData(data.attendance);
            }
        })
        .catch(err => {
            console.error('Error loading attendance:', err);
        });
    }

    function applyAttendanceData(attendanceList) {
        // Build a map: student_id -> status
        const statusMap = {};
        attendanceList.forEach(item => {
            statusMap[item.student_id] = item.status || '';
        });

        // Counters
        let cPresent = 0, cLate = 0, cAbsent = 0, cLeave = 0, cUnchecked = 0;

        // Update every student card
        document.querySelectorAll('[data-student-id]').forEach(card => {
            const sid = card.getAttribute('data-student-id');
            const status = statusMap[sid] || '';

            // Count
            if (status === 'present') cPresent++;
            else if (status === 'late') cLate++;
            else if (status === 'absent') cAbsent++;
            else if (status === 'leave') cLeave++;
            else cUnchecked++;

            // Update card color
            card.className = card.className.replace(/bg-\w+-500\/20|border-\w+-500|bg-gray-800|border-gray-700/g, '');
            const cardColors = {
                'present': 'bg-green-500/20 border-green-500',
                'late': 'bg-yellow-500/20 border-yellow-500',
                'absent': 'bg-red-500/20 border-red-500',
                'leave': 'bg-blue-500/20 border-blue-500',
                '': 'bg-gray-800 border-gray-700'
            };
            card.classList.add(...(cardColors[status] || cardColors['']).split(' '));

            // Update status label
            const label = document.getElementById('status-label-' + sid);
            const labels = {
                'present': '<span class="text-green-400">✅ มาเรียน</span>',
                'late': '<span class="text-yellow-400">⏰ มาสาย</span>',
                'absent': '<span class="text-red-400">❌ ขาดเรียน</span>',
                'leave': '<span class="text-blue-400">📋 ลา</span>',
                '': '<span class="text-gray-500">— ยังไม่เช็ค</span>'
            };
            label.innerHTML = labels[status] || labels[''];

            // Update buttons
            const btnGroup = document.getElementById('btns-' + sid);
            const btns = btnGroup.querySelectorAll('.att-btn');
            btns.forEach(btn => {
                const s = btn.getAttribute('data-status');
                btn.classList.remove('ring-2', 'ring-green-300', 'ring-yellow-300', 'ring-red-300', 'scale-110');
                // Reset to dimmed
                btn.className = btn.className.replace(/bg-green-500(?!\/)/g, '').replace(/bg-yellow-500(?!\/)/g, '').replace(/bg-red-500(?!\/)/g, '');
                if (s === 'present') btn.classList.add(status === 'present' ? 'bg-green-500' : 'bg-green-500/40');
                if (s === 'late') btn.classList.add(status === 'late' ? 'bg-yellow-500' : 'bg-yellow-500/40');
                if (s === 'absent') btn.classList.add(status === 'absent' ? 'bg-red-500' : 'bg-red-500/40');

                // Highlight active
                if (s === status) {
                    const ringMap = { present: 'ring-green-300', late: 'ring-yellow-300', absent: 'ring-red-300' };
                    btn.classList.add('ring-2', ringMap[s], 'scale-110');
                }
            });
        });

        // Update stats
        document.getElementById('stat-present').innerHTML = '✅ มา ' + cPresent;
        document.getElementById('stat-late').innerHTML = '⏰ สาย ' + cLate;
        document.getElementById('stat-absent').innerHTML = '❌ ขาด ' + cAbsent;
        document.getElementById('stat-leave').innerHTML = '📋 ลา ' + cLeave;
        document.getElementById('stat-unchecked').innerHTML = '⬜ ยังไม่เช็ค ' + cUnchecked;
    }

    // ===== Mark Attendance =====
    function markAttendance(studentId, status) {
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('status', status);
        formData.append('date', currentDate);

        // Optimistic UI update
        const btnGroup = document.getElementById('btns-' + studentId);
        const btns = btnGroup.querySelectorAll('.att-btn');
        btns.forEach(btn => {
            btn.classList.remove('ring-2', 'ring-green-300', 'ring-yellow-300', 'ring-red-300', 'scale-110');
            const s = btn.getAttribute('data-status');
            btn.className = btn.className.replace(/bg-green-500(?!\/)/g, '').replace(/bg-yellow-500(?!\/)/g, '').replace(/bg-red-500(?!\/)/g, '');
            if (s === 'present') btn.classList.add('bg-green-500/40');
            if (s === 'late') btn.classList.add('bg-yellow-500/40');
            if (s === 'absent') btn.classList.add('bg-red-500/40');
        });

        const activeBtn = btnGroup.querySelector(`[data-status="${status}"]`);
        if (status === 'present') {
            activeBtn.classList.remove('bg-green-500/40');
            activeBtn.classList.add('bg-green-500', 'ring-2', 'ring-green-300', 'scale-110');
        } else if (status === 'late') {
            activeBtn.classList.remove('bg-yellow-500/40');
            activeBtn.classList.add('bg-yellow-500', 'ring-2', 'ring-yellow-300', 'scale-110');
        } else if (status === 'absent') {
            activeBtn.classList.remove('bg-red-500/40');
            activeBtn.classList.add('bg-red-500', 'ring-2', 'ring-red-300', 'scale-110');
        }

        // Update label
        const label = document.getElementById('status-label-' + studentId);
        const labels = {
            'present': '<span class="text-green-400">✅ มาเรียน</span>',
            'late': '<span class="text-yellow-400">⏰ มาสาย</span>',
            'absent': '<span class="text-red-400">❌ ขาดเรียน</span>'
        };
        label.innerHTML = labels[status] || '';

        // Update card color
        const card = document.getElementById('card-' + studentId);
        card.className = card.className.replace(/bg-\w+-500\/20|border-\w+-500|bg-gray-800|border-gray-700/g, '');
        const cardColors = { present: 'bg-green-500/20 border-green-500', late: 'bg-yellow-500/20 border-yellow-500', absent: 'bg-red-500/20 border-red-500' };
        card.classList.add(...(cardColors[status] || '').split(' '));

        // Update stat counters by reloading from server
        fetch('api_save_attendance.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status !== 'success') {
                alert('บันทึกไม่สำเร็จ: ' + (data.message || 'Error'));
            } else {
                // Refresh stats
                loadAttendanceForDate(currentDate);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('ไม่สามารถเชื่อมต่อได้');
        });
    }

    // ===== Mark All =====
    function markAll(status) {
        const label = status === 'present' ? 'มาเรียน' : 'ขาดเรียน';
        if (!confirm(`ยืนยันเช็คชื่อ "${label}" ทั้งหมดสำหรับวันที่ ${currentDate}?`)) return;

        const cards = document.querySelectorAll('[data-student-id]');
        cards.forEach(card => {
            const sid = card.getAttribute('data-student-id');
            markAttendance(parseInt(sid), status);
        });
    }

    // ===== Print Report =====
    function printReport() {
        const date = document.getElementById('attendance-date').value;
        window.open('print_attendance.php?date=' + date, '_blank');
    }
</script>
<?php require_once '../includes/footer.php'; ?>
