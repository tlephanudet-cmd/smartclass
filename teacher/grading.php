<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }
$pageTitle = "สมุดคะแนนและ XP";
$sql = "SELECT id, full_name, student_code, xp FROM students ORDER BY student_code";
$result = $conn->query($sql);
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
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-check w-8"></i> เช็คชื่อ</a>
            <a href="assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="grading.php" class="block px-4 py-2 rounded bg-indigo-600 text-white"><i class="fas fa-star w-8"></i> ให้คะแนน</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
        </nav>
    </div>
    <div class="md:col-span-3 space-y-6">
        <div class="flex justify-between items-center glass-panel p-4">
            <h2 class="text-2xl font-bold">สมุดคะแนนและ XP</h2>
            <div class="flex gap-2">
                <button class="btn btn-secondary btn-sm" onclick="exportPDF()"><i class="fas fa-file-pdf mr-2"></i> ส่งออกรายงาน</button>
                <button class="btn btn-primary btn-sm" onclick="saveScores()"><i class="fas fa-save mr-2"></i> บันทึก</button>
            </div>
        </div>
        <div class="glass-panel p-4 overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-700">
                        <th class="p-3">รหัส</th>
                        <th class="p-3">ชื่อ</th>
                        <th class="p-3 text-center">XP</th>
                        <th class="p-3 text-center w-24">เข้าเรียน (10)</th>
                        <th class="p-3 text-center w-24">การบ้าน (20)</th>
                        <th class="p-3 text-center w-24">กลางภาค (30)</th>
                        <th class="p-3 text-center w-24">ปลายภาค (30)</th>
                        <th class="p-3 text-center w-24">พฤติกรรม (10)</th>
                        <th class="p-3 text-center">รวม</th>
                        <th class="p-3 text-center">เกรด</th>
                        <th class="p-3 w-48">ความเห็น</th>
                    </tr>
                </thead>
                <tbody id="scoreTable">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($student = $result->fetch_assoc()): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                                <td class="p-3"><?php echo $student['student_code']; ?></td>
                                <td class="p-3 font-medium"><?php echo $student['full_name']; ?></td>
                                <td class="p-3 text-center"><span class="bg-indigo-500/20 text-indigo-300 px-2 py-1 rounded text-xs font-bold"><?php echo $student['xp']; ?> XP</span></td>
                                <td class="p-3"><input type="number" class="w-full text-center bg-gray-900 border-gray-700 rounded px-1 py-1 text-sm score-input" data-student="<?php echo $student['id']; ?>" data-type="attendance" max="10" value="0"></td>
                                <td class="p-3"><input type="number" class="w-full text-center bg-gray-900 border-gray-700 rounded px-1 py-1 text-sm score-input" data-student="<?php echo $student['id']; ?>" data-type="homework" max="20" value="0"></td>
                                <td class="p-3"><input type="number" class="w-full text-center bg-gray-900 border-gray-700 rounded px-1 py-1 text-sm score-input" data-student="<?php echo $student['id']; ?>" data-type="midterm" max="30" value="0"></td>
                                <td class="p-3"><input type="number" class="w-full text-center bg-gray-900 border-gray-700 rounded px-1 py-1 text-sm score-input" data-student="<?php echo $student['id']; ?>" data-type="final" max="30" value="0"></td>
                                <td class="p-3"><input type="number" class="w-full text-center bg-gray-900 border-gray-700 rounded px-1 py-1 text-sm score-input" data-student="<?php echo $student['id']; ?>" data-type="behavior" max="10" value="0"></td>
                                <td class="p-3 text-center font-bold text-lg total-score" id="total-<?php echo $student['id']; ?>">0</td>
                                <td class="p-3 text-center"><span class="grade-badge px-2 py-1 rounded text-xs font-bold bg-gray-700" id="grade-<?php echo $student['id']; ?>">-</span></td>
                                <td class="p-3">
                                    <div class="flex gap-1">
                                        <input type="text" class="w-full bg-gray-900 border-gray-700 rounded px-2 py-1 text-xs" id="comment-<?php echo $student['id']; ?>" placeholder="ความเห็นอัตโนมัติ...">
                                        <button class="btn btn-secondary text-xs px-2 py-1" onclick="autoComment(<?php echo $student['id']; ?>)"><i class="fas fa-magic"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="p-4 text-center text-gray-400">ไม่พบข้อมูลนักเรียน</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex justify-end">
             <button class="btn btn-secondary btn-sm" onclick="autoFillCommentsAll()"><i class="fas fa-magic mr-2"></i> สร้างความเห็นอัตโนมัติทั้งหมด</button>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() { calculateGrade(this.dataset.student); });
    });
    function calculateGrade(studentId) {
        let total = 0;
        document.querySelectorAll(`.score-input[data-student="${studentId}"]`).forEach(inp => { total += parseFloat(inp.value) || 0; });
        document.getElementById(`total-${studentId}`).innerText = total;
        let grade = '-', colorClass = 'bg-gray-700';
        if (total >= 80) { grade = '4'; colorClass = 'bg-green-500'; }
        else if (total >= 75) { grade = '3.5'; colorClass = 'bg-green-400'; }
        else if (total >= 70) { grade = '3'; colorClass = 'bg-blue-500'; }
        else if (total >= 65) { grade = '2.5'; colorClass = 'bg-blue-400'; }
        else if (total >= 60) { grade = '2'; colorClass = 'bg-yellow-500'; }
        else if (total >= 55) { grade = '1.5'; colorClass = 'bg-yellow-400'; }
        else if (total >= 50) { grade = '1'; colorClass = 'bg-orange-500'; }
        else { grade = '0'; colorClass = 'bg-red-500'; }
        const gradeBadge = document.getElementById(`grade-${studentId}`);
        gradeBadge.innerText = grade;
        gradeBadge.className = `grade-badge px-2 py-1 rounded text-xs font-bold ${colorClass}`;
    }
    const praises = ["ยอดเยี่ยมมาก!", "ผลงานดีเด่น!", "ทำดีมากเลย!", "เป็นแบบอย่างที่ดี"];
    const suggestions = ["ดี แต่สามารถทำได้ดีกว่านี้", "ตั้งใจทำงานเพิ่มเติมอีกนิด", "ทบทวนบทเรียนเพิ่มนะ"];
    const warnings = ["ต้องปรับปรุง", "ส่งงานให้ตรงเวลาด้วยนะ", "มาพบครูหลังเลิกเรียนด้วย"];
    function autoComment(studentId) {
        const total = parseFloat(document.getElementById(`total-${studentId}`).innerText) || 0;
        let comment = "";
        if (total >= 80) comment = praises[Math.floor(Math.random() * praises.length)];
        else if (total >= 50) comment = suggestions[Math.floor(Math.random() * suggestions.length)];
        else comment = warnings[Math.floor(Math.random() * warnings.length)];
        document.getElementById(`comment-${studentId}`).value = comment;
    }
    function autoFillCommentsAll() {
        document.querySelectorAll('.total-score').forEach(el => { autoComment(el.id.split('-')[1]); });
    }
    function saveScores() { alert("กำลังบันทึกคะแนน..."); }
    function exportPDF() { alert("ฟีเจอร์ส่งออก PDF กำลังพัฒนา"); }
</script>
<?php require_once '../includes/footer.php'; ?>
