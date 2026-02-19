<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'student') { header("Location: ../index.php"); exit(); }

$pageTitle = "คลังสื่อการเรียนรู้";

// Auto-create table (same as teacher side)
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

// Fetch all media, newest first
$media = $conn->query("SELECT kb.*, u.full_name as teacher_name 
    FROM knowledge_bank kb 
    LEFT JOIN users u ON kb.uploaded_by = u.id 
    ORDER BY kb.created_at DESC");
if (!$media) {
    $media = $conn->query("SELECT * FROM knowledge_bank ORDER BY created_at DESC");
}

$media_arr = [];
if ($media) { while ($m = $media->fetch_assoc()) $media_arr[] = $m; }

// Filter counts
$count_all = count($media_arr);
$count_video = $count_pdf = $count_image = $count_link = 0;
foreach ($media_arr as $m) {
    if ($m['media_type'] == 'video') $count_video++;
    elseif ($m['media_type'] == 'pdf') $count_pdf++;
    elseif ($m['media_type'] == 'image') $count_image++;
    else $count_link++;
}

$thai_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Sidebar -->
    <div class="md:col-span-1 space-y-4">
        <div class="glass-panel p-6 text-center">
            <div class="w-20 h-20 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-3 overflow-hidden border-2 border-emerald-500">
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../' . $_SESSION['profile_image'])): ?>
                    <img src="../<?php echo $_SESSION['profile_image']; ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-graduate text-3xl text-emerald-400"></i>
                <?php endif; ?>
            </div>
            <h3 class="text-xl font-bold"><?php echo $_SESSION['full_name']; ?></h3>
            <p class="text-emerald-400 text-sm font-bold">นักเรียน</p>
        </div>
        <nav class="glass-panel p-4 space-y-2">
            <a href="student_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="student_assignments.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-book w-8"></i> การบ้าน</a>
            <a href="knowledge_bank.php" class="block px-4 py-2 rounded bg-emerald-600 text-white shadow-lg"><i class="fas fa-photo-video w-8"></i> คลังสื่อการเรียนรู้</a>
            <a href="student_consultations.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-heartbeat w-8"></i> กล่องปรึกษาครู</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        <!-- Header -->
        <div class="glass-panel p-6 bg-gradient-to-r from-emerald-900/40 to-slate-800 border-l-8 border-emerald-500">
            <h2 class="text-2xl font-bold flex items-center gap-3">
                <i class="fas fa-photo-video text-emerald-400"></i> คลังสื่อการเรียนรู้
            </h2>
            <p class="text-gray-400 text-sm mt-1">เอกสาร วิดีโอ และลิงก์ที่ครูจัดเตรียมให้</p>
        </div>

        <!-- Filter Tabs -->
        <div class="flex gap-2 flex-wrap">
            <button onclick="filterMedia('all')" id="filter-all" class="filter-btn active px-4 py-2 rounded-xl text-sm font-bold transition bg-emerald-600 text-white">
                ทั้งหมด <span class="ml-1 opacity-70">(<?php echo $count_all; ?>)</span>
            </button>
            <button onclick="filterMedia('video')" id="filter-video" class="filter-btn px-4 py-2 rounded-xl text-sm font-bold transition bg-gray-700 text-gray-300 hover:bg-purple-600 hover:text-white">
                🎬 วิดีโอ <span class="ml-1 opacity-70">(<?php echo $count_video; ?>)</span>
            </button>
            <button onclick="filterMedia('pdf')" id="filter-pdf" class="filter-btn px-4 py-2 rounded-xl text-sm font-bold transition bg-gray-700 text-gray-300 hover:bg-red-600 hover:text-white">
                📄 PDF <span class="ml-1 opacity-70">(<?php echo $count_pdf; ?>)</span>
            </button>
            <button onclick="filterMedia('image')" id="filter-image" class="filter-btn px-4 py-2 rounded-xl text-sm font-bold transition bg-gray-700 text-gray-300 hover:bg-emerald-600 hover:text-white">
                🖼️ รูปภาพ <span class="ml-1 opacity-70">(<?php echo $count_image; ?>)</span>
            </button>
            <button onclick="filterMedia('link')" id="filter-link" class="filter-btn px-4 py-2 rounded-xl text-sm font-bold transition bg-gray-700 text-gray-300 hover:bg-blue-600 hover:text-white">
                🔗 ลิงก์ <span class="ml-1 opacity-70">(<?php echo $count_link; ?>)</span>
            </button>
        </div>

        <!-- Media Cards Grid -->
        <?php if (empty($media_arr)): ?>
            <div class="glass-panel text-center py-16 text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4 block opacity-30"></i>
                <p class="text-xl font-bold mb-2">ยังไม่มีสื่อการเรียนรู้</p>
                <p class="text-sm text-gray-600">ครูยังไม่ได้อัปโหลดสื่อ กรุณารอสักครู่</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="mediaGrid">
                <?php foreach ($media_arr as $m): ?>
                    <?php
                        $typeConfig = [
                            'video' => [
                                'icon' => 'fas fa-play-circle',
                                'color' => 'purple',
                                'label' => '🎬 วิดีโอ',
                                'btnText' => '▶️ ดูวิดีโอ',
                                'btnClass' => 'bg-purple-600 hover:bg-purple-500'
                            ],
                            'pdf' => [
                                'icon' => 'fas fa-file-pdf',
                                'color' => 'red',
                                'label' => '📄 PDF',
                                'btnText' => '📄 ดาวน์โหลด/อ่าน',
                                'btnClass' => 'bg-red-600 hover:bg-red-500'
                            ],
                            'image' => [
                                'icon' => 'fas fa-image',
                                'color' => 'emerald',
                                'label' => '🖼️ รูปภาพ',
                                'btnText' => '🖼️ ดูรูปภาพ',
                                'btnClass' => 'bg-emerald-600 hover:bg-emerald-500'
                            ],
                            'link' => [
                                'icon' => 'fas fa-link',
                                'color' => 'blue',
                                'label' => '🔗 ลิงก์',
                                'btnText' => '🔗 ไปยังเว็บไซต์',
                                'btnClass' => 'bg-blue-600 hover:bg-blue-500'
                            ],
                        ];
                        $tc = $typeConfig[$m['media_type']] ?? $typeConfig['link'];
                        $color = $tc['color'];
                        
                        // Date
                        $ts = strtotime($m['created_at']);
                        $dateDisplay = (int)date('j',$ts).' '.$thai_months[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543);
                        
                        // URL
                        $mediaUrl = in_array($m['media_type'], ['video','link']) ? $m['file_path'] : ('../' . $m['file_path']);
                        
                        // YouTube info
                        $ytId = '';
                        $ytThumb = '';
                        if ($m['media_type'] == 'video' && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $m['file_path'], $match)) {
                            $ytId = $match[1];
                            $ytThumb = "https://img.youtube.com/vi/{$ytId}/hqdefault.jpg";
                        }
                    ?>
                    <div class="media-card glass-panel p-0 overflow-hidden border border-gray-700 hover:border-<?php echo $color; ?>-500/50 group transition-all duration-300 hover:shadow-lg hover:shadow-<?php echo $color; ?>-900/20 hover:-translate-y-1 flex flex-col"
                        data-type="<?php echo $m['media_type']; ?>">
                        
                        <!-- Thumbnail Area -->
                        <div class="h-40 bg-gray-800/80 flex items-center justify-center relative overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 to-transparent z-10"></div>
                            
                            <?php if ($ytThumb): ?>
                                <img src="<?php echo $ytThumb; ?>" alt="thumbnail" class="w-full h-full object-cover transform group-hover:scale-105 transition duration-500">
                                <div class="absolute inset-0 flex items-center justify-center z-20">
                                    <div class="w-14 h-14 bg-purple-600/80 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition">
                                        <i class="fas fa-play text-xl text-white ml-1"></i>
                                    </div>
                                </div>
                            <?php elseif ($m['media_type'] == 'image' && !empty($m['file_path'])): ?>
                                <img src="../<?php echo $m['file_path']; ?>" alt="thumbnail" class="w-full h-full object-cover transform group-hover:scale-105 transition duration-500">
                            <?php else: ?>
                                <div class="relative z-20 transform group-hover:scale-110 transition">
                                    <i class="<?php echo $tc['icon']; ?> text-5xl text-<?php echo $color; ?>-500 drop-shadow-lg"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Type Badge -->
                            <span class="absolute top-3 left-3 z-20 text-xs font-bold px-2.5 py-1 rounded-full bg-<?php echo $color; ?>-500/20 text-<?php echo $color; ?>-400 border border-<?php echo $color; ?>-500/30 backdrop-blur-sm">
                                <?php echo $tc['label']; ?>
                            </span>
                        </div>
                        
                        <!-- Content -->
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="font-bold text-white mb-1 line-clamp-2 text-sm leading-tight"><?php echo htmlspecialchars($m['title']); ?></h3>
                            
                            <?php if (!empty($m['description'])): ?>
                                <p class="text-gray-400 text-xs line-clamp-2 mb-3"><?php echo htmlspecialchars($m['description']); ?></p>
                            <?php else: ?>
                                <div class="mb-3"></div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-3 mt-auto">
                                <span><i class="fas fa-clock mr-1"></i><?php echo $dateDisplay; ?></span>
                                <?php if (!empty($m['teacher_name'])): ?>
                                    <span><i class="fas fa-chalkboard-teacher mr-1"></i><?php echo htmlspecialchars($m['teacher_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Button -->
                            <?php if ($m['media_type'] == 'video' && $ytId): ?>
                                <button onclick="openVideoModal('<?php echo $ytId; ?>', '<?php echo htmlspecialchars($m['title'], ENT_QUOTES); ?>')" 
                                    class="w-full <?php echo $tc['btnClass']; ?> text-white py-2.5 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg">
                                    <?php echo $tc['btnText']; ?>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo $mediaUrl; ?>" target="_blank" 
                                    class="w-full <?php echo $tc['btnClass']; ?> text-white py-2.5 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg text-center block">
                                    <?php echo $tc['btnText']; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- YouTube Video Modal -->
<div id="videoModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.85); backdrop-filter: blur(6px);">
    <div class="w-full max-w-3xl relative">
        <button onclick="closeVideoModal()" class="absolute -top-10 right-0 text-white hover:text-red-400 transition text-xl">
            <i class="fas fa-times"></i> ปิด
        </button>
        <h3 id="videoTitle" class="text-white font-bold mb-3 text-lg truncate"></h3>
        <div class="relative w-full" style="padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
            <iframe id="videoFrame" src="" class="absolute top-0 left-0 w-full h-full" style="border-radius: 16px;"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div id="imageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.9);" onclick="this.classList.add('hidden')">
    <img id="lightboxImg" src="" alt="" class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
</div>

<script>
// Filter
function filterMedia(type) {
    const cards = document.querySelectorAll('.media-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    buttons.forEach(b => {
        b.classList.remove('bg-emerald-600', 'bg-purple-600', 'bg-red-600', 'bg-blue-600', 'text-white');
        b.classList.add('bg-gray-700', 'text-gray-300');
    });
    
    const activeBtn = document.getElementById('filter-' + type);
    activeBtn.classList.remove('bg-gray-700', 'text-gray-300');
    
    const colorMap = { all: 'bg-emerald-600', video: 'bg-purple-600', pdf: 'bg-red-600', image: 'bg-emerald-600', link: 'bg-blue-600' };
    activeBtn.classList.add(colorMap[type], 'text-white');
    
    cards.forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
            card.style.display = '';
            card.style.animation = 'fadeIn 0.3s ease';
        } else {
            card.style.display = 'none';
        }
    });
}

// Video Modal
function openVideoModal(ytId, title) {
    document.getElementById('videoTitle').textContent = title;
    document.getElementById('videoFrame').src = 'https://www.youtube.com/embed/' + ytId + '?autoplay=1';
    document.getElementById('videoModal').classList.remove('hidden');
}

function closeVideoModal() {
    document.getElementById('videoFrame').src = '';
    document.getElementById('videoModal').classList.add('hidden');
}

// Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVideoModal();
        document.getElementById('imageModal').classList.add('hidden');
    }
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once '../includes/footer.php'; ?>
