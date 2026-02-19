<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Login & Role (Teacher only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $date = isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date']) ? $_POST['date'] : date('Y-m-d');

    // Validate
    $allowed = ['present', 'late', 'absent', 'leave'];
    if ($student_id <= 0 || !in_array($status, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit();
    }

    // Check if record exists for this student today
    $check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
    $check->bind_param("is", $student_id, $date);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows > 0) {
        // UPDATE existing record
        $row = $existing->fetch_assoc();
        $stmt = $conn->prepare("UPDATE attendance SET status = ?, check_in_time = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $row['id']);
    } else {
        // INSERT new record
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, date, status, check_in_time) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $student_id, $date, $status);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'attendance_status' => $status]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
}
?>
