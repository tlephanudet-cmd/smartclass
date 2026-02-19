<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }
$pageTitle = "คลังความรู้";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_resource'])) {
    $title = sanitize($_POST['title']);
    $type = sanitize($_POST['type']);
    $filePath = '';
    if ($type == 'link') {
        $filePath = sanitize($_POST['link_url']);
    } else {
        $targetDir = "../uploads/resources/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES["file_upload"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $targetFile)) {
            $filePath = "uploads/resources/" . $fileName;
        } else {
            setFlashMessage('error', 'อัพโหลดไฟล์ไม่สำเร็จ');
        }
    }
    if ($filePath) {
        $stmt = $conn->prepare("INSERT INTO learning_resources (title, file_type, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $type, $filePath);
        if ($stmt->execute()) { setFlashMessage('success', 'เพิ่มสื่อเรียบร้อย'); }
        else { setFlashMessage('error', 'เกิดข้อผิดพลาด'); }
    }
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM learning_resources WHERE id = $id");
    header("Location: resources.php"); exit();
}
$resources = $conn->query("SELECT * FROM learning_resources ORDER BY uploaded_at DESC");
require_once '../includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-gray-400 text-sm">ครูประจำวิชา</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="resources.php" class="block px-4 py-2 rounded bg-indigo-600 text-white"><i class="fas fa-book-reader w-8"></i> คลังความรู้</a>
        </nav>
    </div>
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>
        <h1 class="text-3xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500">
            <i class="fas fa-book-reader mr-2"></i> คลังความรู้
        </h1>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-cloud-upload-alt mr-2"></i> อัพโหลดเนื้อหา</h2>
                <form action="resources.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="upload_resource" value="1">
                    <div><label>ชื่อเรื่อง / หัวข้อ</label>
                    <input type="text" name="title" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" required></div>
                    <div><label>ประเภทเนื้อหา</label>
                    <select name="type" id="contentType" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" onchange="toggleUploadField()">
                        <option value="pdf">ไฟล์เอกสาร / PDF</option>
                        <option value="video">ไฟล์วิดีโอ</option>
                        <option value="link">ลิงก์ภายนอก / YouTube</option>
                    </select></div>
                    <div id="fileField"><label>ไฟล์</label>
                    <input type="file" name="file_upload" class="w-full text-gray-400 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700"></div>
                    <div id="linkField" class="hidden"><label>ลิงก์</label>
                    <input type="url" name="link_url" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" placeholder="https://..."></div>
                    <button type="submit" class="w-full btn btn-primary py-2 mt-2">อัพโหลดสื่อการเรียน</button>
                </form>
            </div>
            <div class="lg:col-span-2 glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-layer-group mr-2"></i> รายการสื่อการเรียน</h2>
                <div class="space-y-3">
                    <?php if($resources->num_rows == 0): ?>
                        <p class="text-gray-500 text-center py-8">ยังไม่มีสื่อที่อัพโหลด</p>
                    <?php endif; ?>
                    <?php while($res = $resources->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg border border-gray-700 hover:bg-gray-700 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-gray-900 text-2xl">
                                    <?php if($res['file_type']=='pdf') echo '<i class="fas fa-file-pdf text-red-500"></i>'; elseif($res['file_type']=='video') echo '<i class="fas fa-video text-purple-500"></i>'; else echo '<i class="fas fa-link text-blue-500"></i>'; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-lg"><?php echo $res['title']; ?></h4>
                                    <p class="text-xs text-gray-400">อัพโหลดเมื่อ: <?php echo date('d/m/Y', strtotime($res['uploaded_at'])); ?> • ดาวน์โหลด: <?php echo $res['download_count']; ?> ครั้ง</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="<?php echo $res['file_type']=='link' ? $res['file_path'] : '../'.$res['file_path']; ?>" target="_blank" class="btn btn-sm bg-gray-600 hover:bg-gray-500"><i class="fas fa-external-link-alt"></i></a>
                                <a href="resources.php?delete=<?php echo $res['id']; ?>" class="btn btn-sm bg-red-900/50 text-red-400 hover:bg-red-900" onclick="return confirm('ต้องการลบหรือไม่?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleUploadField() {
    const type = document.getElementById('contentType').value;
    if (type=='link') { document.getElementById('linkField').classList.remove('hidden'); document.getElementById('fileField').classList.add('hidden'); }
    else { document.getElementById('linkField').classList.add('hidden'); document.getElementById('fileField').classList.remove('hidden'); }
}
</script>
<?php require_once '../includes/footer.php'; ?>
