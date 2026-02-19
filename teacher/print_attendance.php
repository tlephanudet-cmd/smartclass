<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkLogin();
if ($_SESSION['role'] != 'teacher') { header("Location: ../index.php"); exit(); }

// Get date parameter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    die('รูปแบบวันที่ไม่ถูกต้อง');
}

// Thai months
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$thai_days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

$date_ts = strtotime($date);
$day_num = (int)date('w', $date_ts);
$day_of_month = (int)date('j', $date_ts);
$month_num = (int)date('n', $date_ts);
$year_thai = (int)date('Y', $date_ts) + 543;
$thai_date_str = "วัน{$thai_days[$day_num]}ที่ {$day_of_month} {$thai_months[$month_num]} {$year_thai}";

// Get site settings
$site_title = getSetting('site_title') ?: 'Smart Classroom Management System';
$school_logo = getSetting('school_logo');

// Get teacher name
$teacher_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';

// Get students with attendance for the date
$stmt = $conn->prepare("
    SELECT s.id, s.student_code, s.full_name, s.class_level, s.room, s.number,
           a.status, a.check_in_time
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    ORDER BY s.student_code
");
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
$count_present = 0;
$count_late = 0;
$count_absent = 0;
$count_leave = 0;
$count_unchecked = 0;
$class_info = '';

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
    if (empty($class_info) && !empty($row['class_level']) && !empty($row['room'])) {
        $class_info = $row['class_level'] . '/' . $row['room'];
    }
    switch ($row['status']) {
        case 'present': $count_present++; break;
        case 'late': $count_late++; break;
        case 'absent': $count_absent++; break;
        case 'leave': $count_leave++; break;
        default: $count_unchecked++; break;
    }
}
$total = count($students);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการเช็คชื่อ - <?php echo $thai_date_str; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== Base Reset ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1a1a2e;
            background: #e8e8e8;
        }

        /* ===== A4 Paper ===== */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 20mm 18mm 25mm 18mm;
            background: #fff;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            position: relative;
        }

        /* ===== Header ===== */
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px double #2c3e50;
        }
        .report-header .logo-area {
            margin-bottom: 8px;
        }
        .report-header .logo-area img {
            height: 60px;
            margin-bottom: 5px;
        }
        .report-header .logo-area .system-icon {
            font-size: 42px;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .report-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .report-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #4f46e5;
            margin-bottom: 6px;
        }
        .report-header .meta-info {
            font-size: 14px;
            color: #475569;
        }
        .report-header .meta-info span {
            display: inline-block;
            margin: 0 10px;
        }

        /* ===== Table ===== */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .attendance-table thead th {
            background: #1e293b;
            color: #fff;
            padding: 8px 6px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
        }
        .attendance-table thead th:first-child { border-radius: 6px 0 0 0; }
        .attendance-table thead th:last-child { border-radius: 0 6px 0 0; }
        .attendance-table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        .attendance-table tbody td:nth-child(3) {
            text-align: left;
        }
        .attendance-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .attendance-table tbody tr:hover {
            background: #eef2ff;
        }

        /* Status styles */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 60px;
        }
        .status-present { background: #dcfce7; color: #166534; }
        .status-late { background: #fef9c3; color: #854d0e; }
        .status-absent { background: #fecaca; color: #991b1b; }
        .status-leave { background: #dbeafe; color: #1e40af; }
        .status-unchecked { background: #f1f5f9; color: #64748b; }

        /* ===== Summary ===== */
        .summary-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .summary-section h3 {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        .summary-card {
            text-align: center;
            padding: 10px 6px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
        }
        .summary-card .num {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
        }
        .summary-card .label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        .card-total .num { color: #1e293b; }
        .card-present .num { color: #16a34a; }
        .card-present { border-color: #bbf7d0; }
        .card-late .num { color: #ca8a04; }
        .card-late { border-color: #fde68a; }
        .card-absent .num { color: #dc2626; }
        .card-absent { border-color: #fecaca; }
        .card-leave .num { color: #2563eb; }
        .card-leave { border-color: #bfdbfe; }

        /* ===== Signature ===== */
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
            padding-right: 40px;
        }
        .signature-box {
            text-align: center;
            min-width: 200px;
        }
        .signature-box .sign-line {
            width: 200px;
            border-bottom: 1px dotted #64748b;
            margin: 0 auto 4px;
            height: 50px;
        }
        .signature-box .sign-label {
            font-size: 13px;
            color: #475569;
        }
        .signature-box .sign-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .signature-box .sign-position {
            font-size: 12px;
            color: #64748b;
        }

        /* ===== Toolbar (screen only) ===== */
        .no-print-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.3);
        }
        .no-print-toolbar button {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-print {
            background: #4f46e5;
            color: #fff;
        }
        .btn-print:hover { background: #4338ca; }
        .btn-back {
            background: #475569;
            color: #fff;
        }
        .btn-back:hover { background: #334155; }

        /* ===== Footer ===== */
        .page-footer {
            position: absolute;
            bottom: 10mm;
            left: 18mm;
            right: 18mm;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        /* ===== Print Styles ===== */
        @media print {
            body {
                background: #fff;
            }
            .page {
                margin: 0;
                padding: 15mm 15mm 20mm 15mm;
                box-shadow: none;
                width: 100%;
                min-height: auto;
            }
            .no-print-toolbar {
                display: none !important;
            }
            .attendance-table thead th {
                background: #1e293b !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .attendance-table tbody tr:nth-child(even) {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .status-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .summary-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .page-footer {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 15px;
            }
            @page {
                size: A4;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <!-- Screen-only toolbar -->
    <div class="no-print-toolbar">
        <button class="btn-back" onclick="window.close()">
            <i class="fas fa-arrow-left"></i> กลับ
        </button>
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> พิมพ์ / บันทึก PDF
        </button>
    </div>

    <div class="page">
        <!-- Header -->
        <div class="report-header">
            <div class="logo-area">
                <?php if (!empty($school_logo)): ?>
                    <img src="../<?php echo $school_logo; ?>" alt="Logo"><br>
                <?php else: ?>
                    <div class="system-icon"><i class="fas fa-graduation-cap"></i></div>
                <?php endif; ?>
            </div>
            <h1>โรงเรียนหลักเมืองมหาสารคาม</h1>
            <h2><i class="fas fa-clipboard-list"></i> รายงานการเช็คชื่อประจำวัน</h2>
            <div class="meta-info">
                <?php if (!empty($class_info)): ?>
                    <span><i class="fas fa-school"></i> ชั้นเรียน: <strong><?php echo htmlspecialchars($class_info); ?></strong></span>
                    <span>|</span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-day"></i> <?php echo $thai_date_str; ?></span>
                <?php if (!empty($teacher_name)): ?>
                    <span>|</span>
                    <span><i class="fas fa-user-tie"></i> ครูผู้สอน: <strong><?php echo htmlspecialchars($teacher_name); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Table -->
        <table class="attendance-table">
            <thead>
                <tr>
                    <th style="width:6%">ลำดับ</th>
                    <th style="width:14%">รหัสนักเรียน</th>
                    <th style="width:30%">ชื่อ-นามสกุล</th>
                    <th style="width:12%">เลขที่</th>
                    <th style="width:16%">สถานะ</th>
                    <th style="width:12%">เวลาเข้า</th>
                    <th style="width:10%">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">
                            <i class="fas fa-info-circle"></i> ไม่พบข้อมูลนักเรียน
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($students as $s): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($s['student_code']); ?></td>
                            <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                            <td><?php echo isset($s['number']) ? $s['number'] : '-'; ?></td>
                            <td>
                                <?php
                                    switch ($s['status']) {
                                        case 'present':
                                            echo '<span class="status-badge status-present">✅ มา</span>';
                                            break;
                                        case 'late':
                                            echo '<span class="status-badge status-late">⏰ สาย</span>';
                                            break;
                                        case 'absent':
                                            echo '<span class="status-badge status-absent">❌ ขาด</span>';
                                            break;
                                        case 'leave':
                                            echo '<span class="status-badge status-leave">📋 ลา</span>';
                                            break;
                                        default:
                                            echo '<span class="status-badge status-unchecked">— ยังไม่เช็ค</span>';
                                            break;
                                    }
                                ?>
                            </td>
                            <td>
                                <?php
                                    if (!empty($s['check_in_time'])) {
                                        echo date('H:i', strtotime($s['check_in_time']));
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-section">
            <h3><i class="fas fa-chart-pie"></i> สรุปผลการเช็คชื่อ</h3>
            <div class="summary-grid">
                <div class="summary-card card-total">
                    <div class="num"><?php echo $total; ?></div>
                    <div class="label">ทั้งหมด (คน)</div>
                </div>
                <div class="summary-card card-present">
                    <div class="num"><?php echo $count_present; ?></div>
                    <div class="label">✅ มาเรียน</div>
                </div>
                <div class="summary-card card-late">
                    <div class="num"><?php echo $count_late; ?></div>
                    <div class="label">⏰ สาย</div>
                </div>
                <div class="summary-card card-absent">
                    <div class="num"><?php echo $count_absent; ?></div>
                    <div class="label">❌ ขาด</div>
                </div>
                <div class="summary-card card-leave">
                    <div class="num"><?php echo $count_leave; ?></div>
                    <div class="label">📋 ลา</div>
                </div>
            </div>
        </div>

        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="sign-line"></div>
                <div class="sign-label">ลงชื่อ</div>
                <div class="sign-name">(<?php echo !empty($teacher_name) ? htmlspecialchars($teacher_name) : '........................................'; ?>)</div>
                <div class="sign-position">ครูผู้สอน</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="page-footer">
            พิมพ์จากระบบ <?php echo htmlspecialchars($site_title); ?> &bull; <?php echo $thai_date_str; ?> &bull; เวลาพิมพ์: <?php echo date('H:i น.'); ?>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
