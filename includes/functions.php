<?php
// includes/functions.php

/**
 * Sanitize input data
 */
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

/**
 * Check if username already exists
 */
function usernameExists($username) {
    global $conn;
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Check if student code already exists
 */
function studentCodeExists($code) {
    global $conn;
    $sql = "SELECT id FROM students WHERE student_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Check if seat is already taken (Room + Number)
 */
function seatTaken($level, $room, $number) {
    global $conn;
    $sql = "SELECT id FROM students WHERE class_level = ? AND room = ? AND number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $level, $room, $number);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Flash Message Helper
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'error', 'info'
        'text' => $message
    ];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $class = $msg['type'] == 'success' ? 'bg-green-500' : ($msg['type'] == 'error' ? 'bg-red-500' : 'bg-blue-500');
        echo "<div class='p-4 mb-4 text-white rounded $class'>{$msg['text']}</div>";
        unset($_SESSION['flash_message']);
    }
}

/**
 * Get Site Setting
 */
function getSetting($key) {
    global $conn;
    $sql = "SELECT setting_value FROM site_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

/**
 * Get Active Announcements
 */
function getAnnouncements() {
    global $conn;
    $sql = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY category DESC, created_at DESC";
    $result = $conn->query($sql);
    return $result;
}

/**
 * Send LINE Risk Alert (Messaging API - Broadcast)
 */
function sendLineRiskAlert($message) {
    // New Channel Access Token (Long-lived)
    $accessToken = 'uFTUUvcYesi3BshZ+TM+5gUzNesaDVGRPQ9V7DFwJF0AZsogt60yQu8QnpCm1QpIGjoeSRycmGn+iGcsyHFlWXswYAzbSrjBOrHVPom4s7SR7dWYP1TZXolIVaw2y0qtpVS3bZmX01rInglv+2h46AdB04t89/1O/w1cDnyilFU=';

    // Broadcast Message
    $broadcastUrl = "https://api.line.me/v2/bot/message/broadcast";
    $msgData = [
        'messages' => [
            [
                'type' => 'text',
                'text' => "🚨 แจ้งเตือนความเสี่ยง (Risk Alert) 🚨\n\n" . $message
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $broadcastUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($msgData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $accessToken"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

// Existing sendLineNotify kept for backward compatibility if needed, 
// but risk alerts will use the new function above.
function sendLineNotify($message) {
    global $conn;
    $sql = "SELECT setting_value FROM site_settings WHERE setting_key = 'line_notify_token'";
    $result = $conn->query($sql);
    $token = '';
    if($result && $result->num_rows > 0) {
        $token = $result->fetch_assoc()['setting_value'];
    }
    if (empty($token)) return false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=$message");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-type: application/x-www-form-urlencoded",
        "Authorization: Bearer $token",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * Send LINE Broadcast (Messaging API)
 */
function sendLineBroadcast($message) {
    $accessToken = 'uFTUUvcYesi3BshZ+TM+5gUzNesaDVGRPQ9V7DFwJF0AZsogt60yQu8QnpCm1QpIGjoeSRycmGn+iGcsyHFlWXswYAzbSrjBOrHVPom4s7SR7dWYP1TZXolIVaw2y0qtpVS3bZmX01rInglv+2h46AdB04t89/1O/w1cDnyilFU=';
    
    $content = [
        'messages' => [
            ['type' => 'text', 'text' => $message]
        ]
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/broadcast');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_close($ch);
    return $result;
}
?>
