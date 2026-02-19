<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

$pageTitle = "โพลสดในห้องเรียน";
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
            <a href="admin_dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-home w-8"></i> ภาพรวม</a>
            <a href="tools.php" class="block px-4 py-2 rounded hover:bg-gray-700 text-gray-300 transition"><i class="fas fa-magic w-8"></i> เครื่องมือ</a>
            <a href="polls.php" class="block px-4 py-2 rounded bg-indigo-700 text-white border-l-4 border-yellow-400"><i class="fas fa-poll w-8"></i> โพลสด</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="md:col-span-3 space-y-6">
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-green-400">
                <i class="fas fa-poll mr-2"></i> โพลสดในห้องเรียน
            </h1>
            <button onclick="closePoll()" class="btn btn-secondary text-red-400 border-red-500/50 hover:bg-red-500/20">
                <i class="fas fa-stop-circle mr-2"></i> ปิดโพลที่เปิดอยู่
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Create Poll Form -->
            <div class="glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-plus-circle mr-2"></i> สร้างโพลใหม่</h2>
                <form id="createPollForm" class="space-y-4">
                    <div>
                        <label class="block text-gray-400 mb-1">คำถาม</label>
                        <input type="text" name="question" id="question" class="w-full bg-gray-800 border border-gray-700 rounded p-3 text-white" placeholder="เช่น ใครอยากตอบคำถามนี้?" required>
                    </div>
                    
                    <div id="options-container" class="space-y-2">
                        <label class="block text-gray-400 mb-1">ตัวเลือก</label>
                        <input type="text" name="options[]" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" placeholder="ตัวเลือก ก" required>
                        <input type="text" name="options[]" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white" placeholder="ตัวเลือก ข" required>
                    </div>
                    
                    <button type="button" onclick="addOption()" class="text-sm text-indigo-400 hover:text-white"><i class="fas fa-plus"></i> เพิ่มตัวเลือก</button>

                    <button type="submit" class="w-full btn btn-primary py-3 font-bold mt-4">
                        <i class="fas fa-rocket mr-2"></i> เปิดโพล
                    </button>
                </form>
            </div>

            <!-- Live Results -->
            <div class="glass-panel p-6">
                <h2 class="text-xl font-bold mb-4"><i class="fas fa-chart-bar mr-2"></i> ผลโหวตสด</h2>
                
                <div id="no-poll-msg" class="text-center text-gray-500 py-10">
                    <i class="fas fa-poll-h text-6xl mb-4 opacity-20"></i>
                    <p>ยังไม่มีโพลที่เปิดอยู่ สร้างโพลใหม่เพื่อดูผลลัพธ์</p>
                </div>

                <div id="results-content" class="hidden">
                    <h3 id="result-question" class="text-lg font-bold mb-4 text-center"></h3>
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function addOption() {
        const container = document.getElementById('options-container');
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'options[]';
        input.className = 'w-full bg-gray-800 border border-gray-700 rounded p-2 text-white mt-2';
        input.placeholder = 'ตัวเลือก ' + (container.children.length);
        container.appendChild(input);
    }

    document.getElementById('createPollForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_poll');

        fetch('../api/poll_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                alert('เปิดโพลเรียบร้อยแล้ว!');
                this.reset();
                startPollingResults();
            }
        });
    });

    function closePoll() {
        fetch('../api/poll_api.php?action=close_poll')
        .then(res => res.json())
        .then(data => alert('ปิดโพลเรียบร้อยแล้ว'));
    }

    let resultInterval;
    let myChart;

    function startPollingResults() {
        document.getElementById('no-poll-msg').classList.add('hidden');
        document.getElementById('results-content').classList.remove('hidden');

        if(resultInterval) clearInterval(resultInterval);
        resultInterval = setInterval(fetchResults, 2000);
        fetchResults();
    }

    function fetchResults() {
        fetch('../api/poll_api.php?action=get_results')
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('result-question').innerText = data.poll.question;
                updateChart(data.chart_data);
            }
        });
    }

    function updateChart(data) {
        const ctx = document.getElementById('resultsChart').getContext('2d');
        if (myChart) {
            myChart.data.labels = data.labels;
            myChart.data.datasets[0].data = data.values;
            myChart.update();
        } else {
            myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'จำนวนโหวต',
                        data: data.values,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: 'white',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: 'white' } },
                        x: { ticks: { color: 'white' } }
                    },
                    plugins: {
                        legend: { labels: { color: 'white' } }
                    }
                }
            });
        }
    }
    
    // Check for existing poll on load
    fetchResults();
    if(document.getElementById('result-question').innerText !== '') {
        startPollingResults();
    }
</script>

<?php require_once '../includes/footer.php'; ?>
