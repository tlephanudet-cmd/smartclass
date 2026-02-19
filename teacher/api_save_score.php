<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check login & role
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$score = isset($_POST['score']) ? $_POST['score'] : '';

if ($student_id <= 0 || $assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing student_id or assignment_id']);
    exit();
}

// Verify assignment exists and get max_score
$asgn = $conn->query("SELECT max_score FROM gradebook_assignments WHERE id = $assignment_id")->fetch_assoc();
if (!$asgn) {
    echo json_encode(['success' => false, 'error' => 'Assignment not found']);
    exit();
}

// If score is empty, delete the record
if ($score === '' || $score === null) {
    $conn->query("DELETE FROM gradebook_scores WHERE assignment_id = $assignment_id AND student_id = $student_id");
    echo json_encode(['success' => true, 'action' => 'deleted']);
    exit();
}

$score = floatval($score);
$max = floatval($asgn['max_score']);

// Clamp score
if ($score < 0) $score = 0;
if ($score > $max) $score = $max;

// Upsert score
$stmt = $conn->prepare("INSERT INTO gradebook_scores (assignment_id, student_id, score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE score = ?");
$stmt->bind_param("iidd", $assignment_id, $student_id, $score, $score);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'score' => $score]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
