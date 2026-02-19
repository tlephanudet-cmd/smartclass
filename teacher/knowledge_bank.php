<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "คลังสื่อการสอน";

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS `knowledge_bank` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uploaded_by` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `media_type` enum('video','pdf','image','link') NOT NULL DEFAULT 'link',
    `file_path` varchar(500) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$teacher_user_id = $_SESSION['user_id'];

// === Handle Create ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_media'])) {
    $title = sanitize($_POST['title']);
    $desc = sanitize($_POST['description'] ?? '');
    $type = sanitize($_POST['media_type']);
    $filePath = '';

    if ($type == 'video' || $type == 'link') {
        $filePath = sanitize($_POST['media_url']);
    } else {
        // File upload for pdf/image
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
            $targetDir = "../uploads/materials/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $fileName = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $targetDir . $fileName)) {
                $filePath = "uploads/materials/" . $fileName;
            } else {
                setFlashMessage('error', 'อัปโหลดไฟล์ไม่สำเร็จ');
                header("Location: knowledge_bank.php"); exit();
            }
        }
    }

    if ($filePath) {
        $stmt = $conn->prepare("INSERT INTO knowledge_bank (uploaded_by, title, description, media_type, file_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $teacher_user_id, $title, $desc, $type, $filePath);
        if ($stmt->execute()) {
            setFlashMessage('success', "✅ เพิ่มสื่อ \"$title\" เรียบร้อย");
        } else {
            setFlashMessage('error', 'เกิดข้อผิดพลาด: ' . $conn->error);
        }
    } else {
        setFlashMessage('error', 'กรุณาแนบไฟล์หรือใส่ URL');
    }
    header("Location: knowledge_bank.php"); exit();
}

// === Handle Delete ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_media'])) {
    $id = (int)$_POST['media_id'];
    // Delete file from disk if exists
    $f = $conn->query("SELECT file_path, media_type FROM knowledge_bank WHERE id = $id");
    if ($f && $row = $f->fetch_assoc()) {
        if (in_array($row['media_type'], ['pdf','image']) && !empty($row['file_path']) && file_exists('../' . $row['file_path'])) {
            unlink('../' . $row['file_path']);
        }
    }
    $conn->query("DELETE FROM knowledge_bank WHERE id = $id");
    setFlashMessage('success', '🗑️ ลบสื่อเรียบร้อยแล้ว');
    header("Location: knowledge_bank.php"); exit();
}

// === Handle Edit ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_media'])) {
    $id = (int)$_POST['media_id'];
    $title = sanitize($_POST['title']);
    $desc = sanitize($_POST['description'] ?? '');
    $stmt = $conn->prepare("UPDATE knowledge_bank SET title = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $desc, $id);
    $stmt->execute();
    setFlashMessage('success', '✏️ แก้ไขสื่อเรียบร้อย');
    header("Location: knowledge_bank.php"); exit();
}

// Fetch all media
$media = $conn->query("SELECT * FROM knowledge_bank ORDER BY created_at DESC");
$media_arr = [];
if ($media) { while ($m = $media->fetch_assoc()) $media_arr[] = $m; }

// Stats
$count_video = $count_pdf = $count_image = $count_link = 0;
foreach ($media_arr as $m) {
    if ($m['media_type'] == 'video') $count_video++;
    elseif ($m['media_type'] == 'pdf') $count_pdf++;
    elseif ($m['media_type'] == 'image') $count_image++;
    else $count_link++;
}

// Pending leaves for sidebar
$pending_leaves = 0;
$pl_res = $conn->query("SELECT COUNT(*) as cnt FROM leave_requests WHERE status = 'pending'");
if ($pl_res) $pending_leaves = $pl_res->fetch_assoc()['cnt'];

$thai_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

require_once '../includes/header.php';
?>

<div class="relative overflow-hidden min-h-screen">
    <div class="bg-glow top-0 right-0 opacity-20 animate-pulse"></div>
    <div class="container relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4 sticky-sidebar h-fit">
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
            <a href="attendance.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-check w-8"></i> เช็คชื่อ</a>
            <a href="gradebook.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-clipboard-list w-8"></i> สมุดคะแนน</a>
            <a href="admin_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> สั่งการบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded bg-indigo-600 text-white shadow-lg"><i class="fas fa-photo-video w-8"></i> คลังสื่อการสอน</a>
            <a href="admin_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-comments w-8"></i> กล่องปรึกษา</a>
            <a href="manage_leaves.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-calendar-minus w-8"></i> จัดการใบลา
                <?php if ($pending_leaves > 0): ?>
                    <span class="bg-yellow-500 text-black text-xs font-bold px-1.5 py-0.5 rounded-full"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="profile.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-user-cog w-8"></i> โปรไฟล์ของฉัน</a>
            <a href="settings.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-cog w-8"></i> ตั้งค่า</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <?php displayFlashMessage(); ?>

        <!-- Header -->
        <div class="glass-panel p-6 bg-gradient-to-r from-cyan-900/40 to-slate-800 border-l-8 border-cyan-500">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold flex items-center gap-3">
                        <i class="fas fa-photo-video text-cyan-400"></i> คลังสื่อการสอน
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">อัปโหลดวิดีโอ เอกสาร รูปภาพ และลิงก์ เพื่อแชร์ให้นักเรียน</p>
                </div>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="bg-cyan-600 hover:bg-cyan-500 text-white px-5 py-2.5 rounded-xl font-bold transition flex items-center gap-2 shadow-lg shadow-cyan-900/30 whitespace-nowrap">
                    <i class="fas fa-plus"></i> เพิ่มสื่อการเรียนรู้
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="glass-panel p-4 text-center border-t-4 border-purple-500">
                <p class="text-3xl font-black text-purple-400"><?php echo $count_video; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1"><i class="fas fa-video mr-1"></i>วิดีโอ</p>
            </div>
            <div class="glass-panel p-4 text-center border-t-4 border-red-500">
                <p class="text-3xl font-black text-red-400"><?php echo $count_pdf; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1"><i class="fas fa-file-pdf mr-1"></i>PDF</p>
            </div>
            <div class="glass-panel p-4 text-center border-t-4 border-emerald-500">
                <p class="text-3xl font-black text-emerald-400"><?php echo $count_image; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1"><i class="fas fa-image mr-1"></i>รูปภาพ</p>
            </div>
            <div class="glass-panel p-4 text-center border-t-4 border-blue-500">
                <p class="text-3xl font-black text-blue-400"><?php echo $count_link; ?></p>
                <p class="text-xs text-gray-400 font-bold uppercase mt-1"><i class="fas fa-link mr-1"></i>ลิงก์</p>
            </div>
        </div>

        <!-- Media Grid -->
        <?php if (empty($media_arr)): ?>
            <div class="glass-panel text-center py-16 text-gray-500">
                <i class="fas fa-photo-video text-6xl mb-4 block opacity-30"></i>
                <p class="text-xl font-bold mb-2">ยังไม่มีสื่อการสอน</p>
                <p class="text-sm text-gray-600 mb-6">เริ่มเพิ่มวิดีโอ เอกสาร หรือลิงก์ เพื่อแชร์ให้นักเรียนดู</p>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="bg-cyan-600 hover:bg-cyan-500 text-white px-6 py-3 rounded-xl font-bold transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i> เพิ่มสื่อชิ้นแรก
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($media_arr as $m): ?>
                    <?php
                        $typeIcons = [
                            'video' => ['fas fa-play-circle', 'text-purple-400', 'bg-purple-500/15 border-purple-500/30', '▶️ วิดีโอ'],
                            'pdf'   => ['fas fa-file-pdf', 'text-red-400', 'bg-red-500/15 border-red-500/30', '📄 PDF'],
                            'image' => ['fas fa-image', 'text-emerald-400', 'bg-emerald-500/15 border-emerald-500/30', '🖼️ รูปภาพ'],
                            'link'  => ['fas fa-link', 'text-blue-400', 'bg-blue-500/15 border-blue-500/30', '🔗 ลิงก์'],
                        ];
                        $ti = $typeIcons[$m['media_type']] ?? $typeIcons['link'];
                        
                        $ts = strtotime($m['created_at']);
                        $dateDisplay = (int)date('j',$ts).' '.$thai_months[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543).' '.date('H:i',$ts);
                        
                        // For preview URL
                        $previewUrl = '';
                        if (in_array($m['media_type'], ['video','link'])) {
                            $previewUrl = $m['file_path'];
                        } else {
                            $previewUrl = '../' . $m['file_path'];
                        }
                        
                        // YouTube thumbnail
                        $ytThumb = '';
                        if ($m['media_type'] == 'video' && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $m['file_path'], $match)) {
                            $ytThumb = 'https://img.youtube.com/vi/' . $match[1] . '/mqdefault.jpg';
                        }
                    ?>
                    <div class="glass-panel p-4 flex flex-col md:flex-row gap-4 items-center border-l-4 <?php echo str_replace('bg-', 'border-', explode(' ', $ti[2])[0]); ?> hover:bg-white/[0.02] transition">
                        <!-- Thumbnail / Icon -->
                        <div class="w-20 h-20 rounded-xl flex items-center justify-center flex-shrink-0 border <?php echo $ti[2]; ?> overflow-hidden">
                            <?php if ($ytThumb): ?>
                                <img src="<?php echo $ytThumb; ?>" alt="thumb" class="w-full h-full object-cover">
                            <?php elseif ($m['media_type'] == 'image' && !empty($m['file_path'])): ?>
                                <img src="../<?php echo $m['file_path']; ?>" alt="thumb" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="<?php echo $ti[0]; ?> text-3xl <?php echo $ti[1]; ?>"></i>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full border <?php echo $ti[2]; ?> <?php echo $ti[1]; ?>"><?php echo $ti[3]; ?></span>
                                <h3 class="text-lg font-bold text-white truncate"><?php echo htmlspecialchars($m['title']); ?></h3>
                            </div>
                            <?php if (!empty($m['description'])): ?>
                                <p class="text-gray-400 text-sm line-clamp-1 mb-1"><?php echo htmlspecialchars($m['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i><?php echo $dateDisplay; ?></p>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-2 flex-shrink-0">
                            <a href="<?php echo $previewUrl; ?>" target="_blank"
                                class="bg-gray-700 hover:bg-cyan-600 text-gray-300 hover:text-white px-3 py-2 rounded-lg text-sm font-bold transition" title="เปิดดู">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <button onclick='openEditModal(<?php echo json_encode(["id" => $m["id"], "title" => $m["title"], "description" => $m["description"] ?? ""], JSON_UNESCAPED_UNICODE); ?>)'
                                class="bg-yellow-600/20 hover:bg-yellow-600 text-yellow-400 hover:text-white px-3 py-2 rounded-lg text-sm font-bold transition" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('⚠️ ยืนยันลบสื่อนี้?');" class="inline">
                                <input type="hidden" name="delete_media" value="1">
                                <input type="hidden" name="media_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white px-3 py-2 rounded-lg text-sm font-bold transition" title="ลบ">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
    <div class="bg-slate-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-lg p-6 relative" style="max-height: 90vh; overflow-y: auto;">
        <button onclick="document.getElementById('addModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
            <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
            <i class="fas fa-plus-circle text-cyan-400"></i> เพิ่มสื่อการเรียนรู้
        </h3>
        <p class="text-gray-400 text-sm mb-5">อัปโหลดไฟล์หรือแปะลิงก์เพื่อแชร์ให้นักเรียน</p>
        
        <form action="knowledge_bank.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-heading mr-2 text-cyan-400"></i>หัวข้อเรื่อง <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="เช่น วิดีโอสอนบทที่ 1"
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition placeholder:text-gray-600">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-layer-group mr-2 text-purple-400"></i>ประเภทสื่อ <span class="text-red-500">*</span></label>
                <select name="media_type" id="mediaType" onchange="toggleMediaInput()"
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                    <option value="video">🎬 YouTube / วิดีโอ</option>
                    <option value="pdf">📄 ไฟล์ PDF / เอกสาร</option>
                    <option value="image">🖼️ รูปภาพ</option>
                    <option value="link">🔗 ลิงก์เว็บไซต์</option>
                </select>
            </div>

            <!-- URL Input (for video/link) -->
            <div id="urlField">
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-link mr-2 text-blue-400"></i>URL / ลิงก์</label>
                <input type="url" name="media_url" placeholder="https://www.youtube.com/watch?v=..."
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition placeholder:text-gray-600">
            </div>

            <!-- File Input (for pdf/image) -->
            <div id="fileField" class="hidden">
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-cloud-upload-alt mr-2 text-emerald-400"></i>อัปโหลดไฟล์</label>
                <input type="file" name="media_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp"
                    class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-cyan-600 file:text-white hover:file:bg-cyan-700 cursor-pointer">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2"><i class="fas fa-align-left mr-2 text-gray-400"></i>รายละเอียดเพิ่มเติม</label>
                <textarea name="description" rows="3" placeholder="อธิบายเนื้อหาสั้นๆ..."
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition placeholder:text-gray-600 resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" name="add_media" class="flex-1 bg-cyan-600 hover:bg-cyan-500 text-white py-3 rounded-xl font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-cyan-900/30">
                    <i class="fas fa-cloud-upload-alt"></i> อัปโหลดสื่อ
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" 
                    class="px-6 bg-gray-700 hover:bg-gray-600 text-gray-300 py-3 rounded-xl font-bold transition">
                    ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
    <div class="bg-slate-800 rounded-2xl shadow-2xl border border-gray-700 w-full max-w-lg p-6 relative">
        <button onclick="document.getElementById('editModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white transition">
            <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-xl font-bold mb-5 flex items-center gap-2">
            <i class="fas fa-edit text-yellow-400"></i> แก้ไขสื่อ
        </h3>
        
        <form action="knowledge_bank.php" method="POST" class="space-y-4">
            <input type="hidden" name="edit_media" value="1">
            <input type="hidden" name="media_id" id="editId">
            
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2">หัวข้อเรื่อง</label>
                <input type="text" name="title" id="editTitle" required
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-2">รายละเอียด</label>
                <textarea name="description" id="editDesc" rows="3"
                    class="w-full bg-gray-900 border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none transition resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-500 text-white py-3 rounded-xl font-bold transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> บันทึก
                </button>
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                    class="px-6 bg-gray-700 hover:bg-gray-600 text-gray-300 py-3 rounded-xl font-bold transition">
                    ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleMediaInput() {
    const type = document.getElementById('mediaType').value;
    const urlField = document.getElementById('urlField');
    const fileField = document.getElementById('fileField');
    
    if (type === 'video' || type === 'link') {
        urlField.classList.remove('hidden');
        fileField.classList.add('hidden');
    } else {
        urlField.classList.add('hidden');
        fileField.classList.remove('hidden');
    }
}

function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editTitle').value = data.title;
    document.getElementById('editDesc').value = data.description || '';
    document.getElementById('editModal').classList.remove('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('addModal').classList.add('hidden');
        document.getElementById('editModal').classList.add('hidden');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
