<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$pageTitle = "จัดการนักเรียน";

// Handle Delete
if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $uid = $res->fetch_assoc()['user_id'];
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param("i", $uid);
        if ($del->execute()) {
            setFlashMessage('success', "ลบนักเรียนเรียบร้อยแล้ว");
        } else {
            setFlashMessage('error', "ไม่สามารถลบนักเรียนได้");
        }
    }
    header("Location: students.php");
    exit();
}

// Fetch Students
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$where = "1";
if (!empty($search)) {
    $where = "(full_name LIKE '%$search%' OR student_code LIKE '%$search%' OR nickname LIKE '%$search%')";
}

$sql = "SELECT * FROM students WHERE $where ORDER BY class_level, room, number";
$result = $conn->query($sql);

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
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-chart-pie w-8"></i> ภาพรวม</a>
            <a href="students.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-users w-8"></i> จัดการนักเรียน</a>
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
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

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <div class="flex justify-between items-center glass-panel p-4">
            <h2 class="text-2xl font-bold">รายชื่อนักเรียน</h2>
            <a href="../auth/register.php?role=student" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-2"></i> เพิ่มนักเรียน</a>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Search Bar -->
        <div class="glass-panel p-4">
            <form action="students.php" method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="ค้นหาตามชื่อ, รหัส, หรือชื่อเล่น..." value="<?php echo $search; ?>">
                <button type="submit" class="btn btn-secondary">ค้นหา</button>
            </form>
        </div>

        <!-- Students Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while($student = $result->fetch_assoc()): ?>
                    <div class="glass-panel p-4 flex flex-col items-center text-center relative hover:bg-gray-800/50 transition">
                        <div class="absolute top-2 right-2 cursor-pointer text-gray-500 hover:text-red-500" onclick="if(confirm('ต้องการลบนักเรียนคนนี้หรือไม่?')) window.location.href='students.php?delete=<?php echo $student['id']; ?>'">
                            <i class="fas fa-trash"></i>
                        </div>
                        <div class="w-20 h-20 bg-gray-700 rounded-full mb-3 overflow-hidden">
                            <?php if ($student['profile_image']): ?>
                                <img src="../<?php echo $student['profile_image']; ?>" alt="รูปโปรไฟล์" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <i class="fas fa-user text-2xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-bold text-lg"><?php echo $student['full_name']; ?></h3>
                        <p class="text-indigo-400 text-sm mb-1">"<?php echo $student['nickname']; ?>"</p>
                        <div class="text-sm text-gray-400 mb-3">
                            รหัส: <?php echo $student['student_code']; ?> | ชั้น: <?php echo $student['class_level']; ?>/<?php echo $student['room']; ?> เลขที่ <?php echo $student['number']; ?>
                        </div>
                        <div class="flex gap-2 w-full mt-auto">
                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary btn-sm flex-1 text-xs"><i class="fas fa-edit mr-1"></i>แก้ไข</a>
                            <a href="student_profile.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm flex-1 text-xs"><i class="fas fa-id-card mr-1"></i>โปรไฟล์</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-8 text-gray-500">
                    ไม่พบข้อมูลนักเรียน
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
