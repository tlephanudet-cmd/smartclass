<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "การบ้านของฉัน";

// Auto-create assignment_submissions table if missing
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

// Ensure student_id
if (!isset($_SESSION['student_id'])) {
    $u_id = $_SESSION['user_id'];
    $s_res = $conn->query("SELECT id, full_name FROM students WHERE user_id = $u_id");
    if($s_res->num_rows > 0) {
        $student_data = $s_res->fetch_assoc();
        $_SESSION['student_id'] = $student_data['id'];
        $_SESSION['full_name'] = $student_data['full_name'];
    }
}
$student_id = $_SESSION['student_id'];

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assignment'])) {
    $assign_id = (int)$_POST['assignment_id'];
    $comment = sanitize($_POST['comment']);
    
    $filePath = '';
    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] == 0) {
        $targetDir = "../uploads/submissions/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . '_' . $student_id . '_' . basename($_FILES["file_upload"]["name"]);
        if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $targetDir . $fileName)) {
            $filePath = "uploads/submissions/" . $fileName;
        }
    }

    // Check if already submitted (Update or Insert)
    $check = $conn->query("SELECT id FROM assignment_submissions WHERE assignment_id = $assign_id AND student_id = $student_id");
    if ($check && $check->num_rows > 0) {
        // Update logic (Optional, mostly simpler to just insert or block)
        // For now let's just update
        $sql = "UPDATE assignment_submissions SET file_path = ?, comment = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $filePath, $comment, $assign_id, $student_id);
    } else {
        $sql = "INSERT INTO assignment_submissions (assignment_id, student_id, file_path, comment) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $assign_id, $student_id, $filePath, $comment);
    }

    if ($stmt->execute()) {
        $student_name = $_SESSION['full_name'];
        // Fetch assignment title
        $title_res = $conn->query("SELECT title FROM assignments WHERE id = $assign_id");
        $a_title = $title_res->fetch_assoc()['title'];
        
        sendLineNotify("\n📝 งานใหม่มาแล้ว!\n$student_name ส่งงานวิชา: $a_title");
        
        setFlashMessage('success', 'ส่งงานเรียบร้อยแล้ว');
    } else {
         setFlashMessage('error', 'เกิดข้อผิดพลาดในการส่งงาน');
    }
}

// Fetch Assignments
// We want to see: My status (submitted or not)
$sql = "SELECT a.*, s.id as submission_id, s.submitted_at, s.score 
        FROM assignments a 
        LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = $student_id 
        ORDER BY a.created_at DESC";
$assignments = $conn->query($sql);
if (!$assignments) {
    // Fallback: query without join if table structure differs
    $assignments = $conn->query("SELECT * FROM assignments ORDER BY created_at DESC");
}

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded bg-emerald-600 text-white shadow-lg"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="grades.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-line w-8"></i> ผลการเรียน</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
            <a href="request_leave.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> ขอลา</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <h1 class="text-3xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-500">
            <i class="fas fa-book-open mr-2"></i> การบ้านของฉัน
        </h1>

        <?php if ($assignments && $assignments->num_rows > 0): ?>
            <div class="space-y-4">
                <?php 
                $thai_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
                $typeBadges = [
                    'worksheet' => ['📄 ใบงาน', 'bg-blue-500/20 text-blue-400 border border-blue-500/30'],
                    'project' => ['🎯 โครงงาน', 'bg-purple-500/20 text-purple-400 border border-purple-500/30'],
                    'exercise' => ['📝 แบบฝึกหัด', 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30'],
                    'quiz' => ['📋 สอบย่อย', 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'],
                ];
                while($row = $assignments->fetch_assoc()): ?>
                    <?php 
                        $isSubmitted = !empty($row['submission_id']);
                        $deadline = $row['deadline'] ?? $row['due_date'] ?? null;
                        $isLate = (!$isSubmitted && $deadline && time() > strtotime($deadline));
                        
                        $statusClass = $isSubmitted ? 'border-green-500/50 bg-green-900/10' : ($isLate ? 'border-red-500/50 bg-red-900/10' : 'border-gray-700 bg-gray-800');
                        $statusText = $isSubmitted ? '<span class="text-green-400"><i class="fas fa-check-circle"></i> ส่งแล้ว</span>' : ($isLate ? '<span class="text-red-400"><i class="fas fa-exclamation-triangle"></i> เลยกำหนดส่ง</span>' : '<span class="text-yellow-400"><i class="fas fa-clock"></i> รอดำเนินการ</span>');
                        
                        $type = $row['type'] ?? 'worksheet';
                        $badge = $typeBadges[$type] ?? ['📄 งาน', 'bg-gray-500/20 text-gray-400 border border-gray-500/30'];
                        
                        // Time remaining
                        $timeLeft = '';
                        if ($deadline) {
                            $diff = strtotime($deadline) - time();
                            if ($diff > 0) {
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);
                                $timeLeft = $days > 0 ? "เหลือ {$days} วัน {$hours} ชม." : "เหลือ {$hours} ชม.";
                            } else {
                                $timeLeft = 'หมดเวลาแล้ว';
                            }
                        }
                        
                        // Thai date
                        $dateDisplay = '';
                        if ($deadline) {
                            $ts = strtotime($deadline);
                            $dateDisplay = (int)date('j',$ts).' '.$thai_months[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543).' '.date('H:i',$ts);
                        }
                    ?>
                    
                    <div class="border rounded-xl p-6 flex flex-col md:flex-row gap-6 <?php echo $statusClass; ?> transition hover:shadow-lg hover:shadow-black/20">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $badge[1]; ?>"><?php echo $badge[0]; ?></span>
                                <h3 class="text-lg font-bold text-white"><?php echo htmlspecialchars($row['title']); ?></h3>
                                <div class="ml-auto md:hidden"><?php echo $statusText; ?></div>
                            </div>
                            
                            <?php if (!empty($row['description'])): ?>
                                <p class="text-gray-400 text-sm mb-3 line-clamp-2"><?php echo nl2br(htmlspecialchars(mb_strimwidth($row['description'], 0, 120, '...'))); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-500">
                                <?php if ($deadline): ?>
                                    <span><i class="fas fa-calendar-alt text-indigo-400 mr-1"></i> <?php echo $dateDisplay; ?></span>
                                    <span class="<?php echo $isLate ? 'text-red-400' : 'text-yellow-400'; ?>"><i class="fas fa-hourglass-half mr-1"></i> <?php echo $timeLeft; ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-star text-yellow-400 mr-1"></i> <?php echo $row['max_score'] ?? 10; ?> คะแนน</span>
                                <?php if(!empty($row['file_path'])): ?>
                                    <span class="text-emerald-400"><i class="fas fa-paperclip mr-1"></i> มีไฟล์แนบ</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 min-w-[180px] border-l border-gray-700/50 pl-0 md:pl-5 pt-4 md:pt-0">
                            <div class="hidden md:block text-right mb-1"><?php echo $statusText; ?></div>
                            
                            <?php if($isSubmitted): ?>
                                <div class="bg-gray-900/60 p-3 rounded-lg text-sm mb-auto">
                                    <p class="text-gray-400 text-xs mb-1">ส่งเมื่อ: <?php echo date('d/m H:i', strtotime($row['submitted_at'])); ?></p>
                                    <?php if($row['score']): ?>
                                        <p class="font-bold text-green-400">คะแนนที่ได้: <?php echo $row['score']; ?>/<?php echo $row['max_score']; ?></p>
                                    <?php else: ?>
                                        <p class="text-gray-500">รอตรวจ</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- View Details Button -->
                            <button onclick='openDetailModal(<?php echo json_encode([
                                "title" => $row["title"],
                                "type" => $badge[0],
                                "description" => $row["description"] ?? "",
                                "max_score" => $row["max_score"] ?? 10,
                                "deadline" => $dateDisplay,
                                "timeLeft" => $timeLeft,
                                "isLate" => $isLate,
                                "file_path" => $row["file_path"] ?? "",
                                "link_url" => $row["link_url"] ?? "",
                            ], JSON_UNESCAPED_UNICODE); ?>)'
                                class="w-full bg-cyan-600/20 hover:bg-cyan-600 text-cyan-400 hover:text-white py-2 px-4 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2">
                                <i class="fas fa-file-alt"></i> ดูรายละเอียด / ใบงาน
                            </button>
                            
                            <?php if(!$isSubmitted): ?>
                                <button onclick="openSubmitModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')" 
                                    class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2 px-4 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-900/30">
                                    <i class="fas fa-upload"></i> ส่งงาน
                                </button>
                            <?php else: ?>
                                <button class="w-full bg-gray-700/50 text-gray-500 py-2 px-4 rounded-lg text-sm font-bold cursor-not-allowed flex items-center justify-center gap-2" disabled>
                                    <i class="fas fa-check"></i> ส่งงานแล้ว
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-10 text-gray-500">
                <i class="fas fa-mug-hot text-6xl mb-4 opacity-20"></i>
                <p>เย้! ไม่มีการบ้าน</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Submit Modal -->
<div id="submitModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeSubmitModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <form action="student_assignments.php" method="POST" enctype="multipart/form-data" class="glass-panel p-6 border ring-2 ring-emerald-500 relative">
            <button type="button" class="absolute top-4 right-4 text-gray-400 hover:text-white" onclick="closeSubmitModal()"><i class="fas fa-times text-xl"></i></button>
            <h2 class="text-xl font-bold mb-4 text-emerald-400">ส่งงาน: <span id="modalTitle"></span></h2>
            <input type="hidden" name="submit_assignment" value="1">
            <input type="hidden" name="assignment_id" id="modalAssignId">
            
            <div class="space-y-4">
                <div>
                    <label>แนบไฟล์งาน (รูป/PDF/Word)</label>
                    <input type="file" name="file_upload" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer">
                </div>
                <div>
                    <label>ข้อความถึงครู (ถ้ามี)</label>
                    <textarea name="comment" rows="3" class="w-full bg-gray-800 border-gray-700 rounded p-2 text-white" placeholder="ผมส่งงานแล้วครับ..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-full bg-emerald-600 hover:bg-emerald-700">ยืนยันการส่ง</button>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.75); backdrop-filter: blur(4px);">
    <div class="bg-slate-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-lg relative" style="max-height: 85vh; overflow-y: auto;">
        <button onclick="closeDetailModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white transition z-10">
            <i class="fas fa-times text-lg"></i>
        </button>
        
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-700/50">
            <span id="detailType" class="text-xs font-bold px-2.5 py-1 rounded-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/30"></span>
            <h3 id="detailTitle" class="text-xl font-bold text-white mt-3"></h3>
        </div>
        
        <!-- Body -->
        <div class="p-6 space-y-5">
            <!-- Score & Deadline -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-900/60 rounded-xl p-3 text-center">
                    <p class="text-2xl font-black text-yellow-400" id="detailScore"></p>
                    <p class="text-xs text-gray-400 font-bold mt-1">คะแนนเต็ม</p>
                </div>
                <div class="bg-gray-900/60 rounded-xl p-3 text-center">
                    <p class="text-sm font-bold text-indigo-400" id="detailDeadline"></p>
                    <p class="text-xs font-bold mt-1" id="detailTimeLeft"></p>
                </div>
            </div>
            
            <!-- Description -->
            <div id="detailDescSection">
                <h4 class="text-sm font-bold text-gray-300 mb-2 flex items-center gap-2">
                    <i class="fas fa-align-left text-indigo-400"></i> คำชี้แจง / รายละเอียด
                </h4>
                <div id="detailDesc" class="bg-gray-900/40 rounded-xl p-4 text-gray-300 text-sm leading-relaxed border border-gray-700/30"></div>
            </div>
            
            <!-- File Download -->
            <div id="detailFileSection" class="hidden">
                <h4 class="text-sm font-bold text-gray-300 mb-2 flex items-center gap-2">
                    <i class="fas fa-paperclip text-emerald-400"></i> ไฟล์แนบจากครู
                </h4>
                <a id="detailFileLink" href="#" target="_blank" 
                    class="flex items-center gap-3 bg-emerald-600/15 hover:bg-emerald-600/30 border border-emerald-500/30 rounded-xl p-4 transition group">
                    <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-download text-emerald-400 group-hover:animate-bounce"></i>
                    </div>
                    <div>
                        <p class="text-emerald-400 font-bold text-sm">⬇️ ดาวน์โหลดไฟล์แนบ / ใบงาน</p>
                        <p id="detailFileName" class="text-gray-500 text-xs mt-0.5"></p>
                    </div>
                </a>
            </div>
            
            <!-- External Link -->
            <div id="detailLinkSection" class="hidden">
                <h4 class="text-sm font-bold text-gray-300 mb-2 flex items-center gap-2">
                    <i class="fas fa-link text-cyan-400"></i> ลิงก์เพิ่มเติม
                </h4>
                <a id="detailLinkUrl" href="#" target="_blank" 
                    class="flex items-center gap-3 bg-cyan-600/15 hover:bg-cyan-600/30 border border-cyan-500/30 rounded-xl p-4 transition group">
                    <div class="w-10 h-10 bg-cyan-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-external-link-alt text-cyan-400"></i>
                    </div>
                    <div>
                        <p class="text-cyan-400 font-bold text-sm">🔗 เปิดลิงก์</p>
                        <p id="detailLinkText" class="text-gray-500 text-xs mt-0.5 truncate max-w-[250px]"></p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="p-6 pt-3 border-t border-gray-700/50">
            <button onclick="closeDetailModal()" class="w-full bg-gray-700 hover:bg-gray-600 text-gray-300 py-2.5 rounded-xl font-bold transition">
                ปิด
            </button>
        </div>
    </div>
</div>

<script>
    function openSubmitModal(id, title) {
        document.getElementById('modalAssignId').value = id;
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('submitModal').classList.remove('hidden');
    }
    function closeSubmitModal() {
        document.getElementById('submitModal').classList.add('hidden');
    }
    
    function openDetailModal(data) {
        document.getElementById('detailTitle').textContent = data.title;
        document.getElementById('detailType').textContent = data.type;
        document.getElementById('detailScore').textContent = data.max_score;
        document.getElementById('detailDeadline').textContent = data.deadline || 'ไม่ระบุ';
        
        const timeLeftEl = document.getElementById('detailTimeLeft');
        timeLeftEl.textContent = data.timeLeft || '';
        timeLeftEl.className = 'text-xs font-bold mt-1 ' + (data.isLate ? 'text-red-400' : 'text-emerald-400');
        
        // Description
        const descSection = document.getElementById('detailDescSection');
        const descEl = document.getElementById('detailDesc');
        if (data.description && data.description.trim()) {
            descEl.innerHTML = data.description.replace(/\n/g, '<br>');
            descSection.classList.remove('hidden');
        } else {
            descSection.classList.add('hidden');
        }
        
        // File
        const fileSection = document.getElementById('detailFileSection');
        if (data.file_path && data.file_path.trim()) {
            document.getElementById('detailFileLink').href = '../' + data.file_path;
            const fname = data.file_path.split('/').pop();
            document.getElementById('detailFileName').textContent = fname;
            fileSection.classList.remove('hidden');
        } else {
            fileSection.classList.add('hidden');
        }
        
        // Link
        const linkSection = document.getElementById('detailLinkSection');
        if (data.link_url && data.link_url.trim()) {
            document.getElementById('detailLinkUrl').href = data.link_url;
            document.getElementById('detailLinkText').textContent = data.link_url;
            linkSection.classList.remove('hidden');
        } else {
            linkSection.classList.add('hidden');
        }
        
        document.getElementById('detailModal').classList.remove('hidden');
    }
    
    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
    }
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDetailModal();
            closeSubmitModal();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
