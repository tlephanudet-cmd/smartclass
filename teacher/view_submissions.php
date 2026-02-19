<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

if (!isset($_GET['id'])) {
    header("Location: assignments.php");
    exit();
}

$assign_id = (int)$_GET['id'];
$assignment = $conn->query("SELECT * FROM assignments WHERE id = $assign_id")->fetch_assoc();

if (!$assignment) {
    echo "ไม่พบการบ้าน";
    exit();
}

$pageTitle = "ตรวจสอบการส่งงาน: " . $assignment['title'];

// Verify teacher ownership if strict security needed, skipping for verified teacher role

$submissions = $conn->query("SELECT s.*, st.full_name, st.student_code, st.profile_image 
                            FROM assignment_submissions s 
                            JOIN students st ON s.student_id = st.id 
                            WHERE s.assignment_id = $assign_id 
                            ORDER BY s.submitted_at DESC");

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-arrow-left mr-2"></i> กลับ</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <div class="glass-panel p-6">
            <h2 class="text-2xl font-bold mb-2"><?php echo $assignment['title']; ?></h2>
            <p class="text-gray-400 text-sm mb-4">กำหนดส่ง: <?php echo date('d/m/Y H:i', strtotime($assignment['deadline'])); ?></p>
            
            <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-2">รายชื่อนักเรียนที่ส่งงาน (<?php echo $submissions->num_rows; ?>)</h3>

            <div class="space-y-4">
                <?php if ($submissions->num_rows > 0): ?>
                    <?php while($row = $submissions->fetch_assoc()): ?>
                        <div class="bg-gray-800 rounded-lg p-4 flex flex-col md:flex-row gap-4 items-start md:items-center justify-between border border-gray-700">
                            <!-- Student Info -->
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gray-700 rounded-full overflow-hidden">
                                     <?php if ($row['profile_image']): ?>
                                        <img src="../<?php echo $row['profile_image']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-sm"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold"><?php echo $row['full_name']; ?></h4>
                                    <p class="text-xs text-gray-400">รหัส: <?php echo $row['student_code']; ?></p>
                                    <p class="text-xs text-emerald-400 mt-1">
                                        <i class="fas fa-check-circle"></i> ส่งเมื่อ <?php echo date('d/m/Y H:i', strtotime($row['submitted_at'])); ?>
                                        <?php if(strtotime($row['submitted_at']) > strtotime($assignment['deadline'])) echo '<span class="text-red-400 ml-2">(ส่งช้า)</span>'; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Submission Content -->
                            <div class="flex-1 bg-gray-900/50 p-3 rounded text-sm text-gray-300">
                                <?php if($row['comment']): ?>
                                    <p class="mb-2"><i class="fas fa-comment-alt mr-1 text-gray-500"></i> "<?php echo $row['comment']; ?>"</p>
                                <?php endif; ?>
                                <?php if($row['file_path']): ?>
                                    <a href="../<?php echo $row['file_path']; ?>" target="_blank" class="text-indigo-400 hover:text-white inline-flex items-center gap-1">
                                        <i class="fas fa-paperclip"></i> ดูไฟล์แนบ
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">ไม่มีไฟล์แนบ</span>
                                <?php endif; ?>
                            </div>

                            <!-- Grading (Mock) -->
                             <div class="text-center min-w-[100px]">
                                <span class="block text-xs text-gray-500 mb-1">คะแนน</span>
                                <div class="font-mono font-bold text-xl bg-gray-900 px-3 py-1 rounded text-yellow-400">
                                    <?php echo $row['score'] ? $row['score'] : '-'; ?>/<?php echo $assignment['max_score']; ?>
                                </div>
                             </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center py-8 text-gray-500">ยังไม่มีใครส่งงาน</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
