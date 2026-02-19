<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }
if (isset($_GET['track_id'])) {
    $id = (int)$_GET['track_id'];
    $conn->query("UPDATE learning_resources SET download_count = download_count + 1 WHERE id = $id");
    exit();
}
$pageTitle = "คลังความรู้";
require_once '../includes/header.php';
$resources = $conn->query("SELECT * FROM learning_resources ORDER BY uploaded_at DESC");
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-photo-video w-8"></i> คลังความรู้</a>
            <a href="resources.php" class="block px-4 py-2 rounded bg-emerald-600 text-white"><i class="fas fa-book-reader w-8"></i> เอกสารและสื่อ</a>
        </nav>
    </div>
    <div class="md:col-span-3 space-y-6">
        <div class="glass-panel p-6 min-h-[500px]">
            <h1 class="text-3xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-500">
                <i class="fas fa-book-reader mr-2"></i> คลังความรู้
            </h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($res = $resources->fetch_assoc()): ?>
                    <div class="glass-panel p-0 overflow-hidden hover: transform hover:-translate-y-1 transition duration-300 group border border-gray-700">
                        <div class="h-32 bg-gray-800 flex items-center justify-center relative">
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent opacity-60"></div>
                            <?php 
                                if($res['file_type']=='pdf') echo '<i class="fas fa-file-pdf text-6xl text-red-500 drop-shadow-lg transform group-hover:scale-110 transition"></i>';
                                elseif($res['file_type']=='video') echo '<i class="fas fa-play-circle text-6xl text-purple-500 drop-shadow-lg transform group-hover:scale-110 transition"></i>';
                                else echo '<i class="fas fa-link text-6xl text-blue-500 drop-shadow-lg transform group-hover:scale-110 transition"></i>';
                            ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-lg mb-1 truncate"><?php echo $res['title']; ?></h3>
                            <p class="text-xs text-gray-400 mb-4"><?php echo date('d/m/Y', strtotime($res['uploaded_at'])); ?></p>
                            <a href="<?php echo $res['file_type']=='link' ? $res['file_path'] : '../'.$res['file_path']; ?>" 
                               target="_blank" 
                               onclick="trackDownload(<?php echo $res['id']; ?>)"
                               class="block w-full text-center py-2 rounded bg-gray-700 hover:bg-emerald-600 hover:text-white transition text-sm font-bold">
                                <?php echo $res['file_type']=='link' ? 'เปิดลิงก์' : 'ดาวน์โหลด'; ?> <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>
<script>
function trackDownload(id) { fetch('resources.php?track_id=' + id); }
</script>
<?php require_once '../includes/footer.php'; ?>
