<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    exit();
}

// Get all students with their attendance for the given date
$stmt = $conn->prepare("
    SELECT s.id as student_id, a.status 
    FROM students s 
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    ORDER BY s.student_code
");
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'student_id' => (int)$row['student_id'],
        'status' => $row['status'] ?? ''
    ];
}

echo json_encode(['status' => 'success', 'date' => $date, 'attendance' => $data]);
?>
