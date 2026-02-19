<?php
/**
 * AI Chat API - Connected to Google Gemini
 * This file now redirects to the real Gemini-powered implementation.
 * Kept for backwards compatibility with any old references.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'reply' => 'กรุณาเข้าสู่ระบบก่อนครับ']);
    exit();
}

$userMsg = sanitize($_POST['message'] ?? '');

if (empty($userMsg)) {
    echo json_encode(['status' => 'error', 'reply' => 'กรุณาพิมพ์ข้อความก่อนครับ']);
    exit();
}

// Safety Indicator
$is_risk = false;
$bad_words = ['ฆ่าตัวตาย', 'ตาย', 'suicide', 'kill', 'บูลลี่', 'รังแก'];
foreach($bad_words as $word) {
    if (strpos($userMsg, $word) !== false) {
         $is_risk = true;
         $sender = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'นักเรียนไม่ระบุตัวตน');
         sendLineNotify("\n⚠️ ตรวจพบความเสี่ยงในแชท!\nนักเรียน: $sender\nข้อความ: $userMsg");
         break; 
    }
}

// Get Gemini API Key from database
$apiKey = trim(getSetting('gemini_api_key'));
if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'reply' => '⚙️ ครูยังไม่ได้เชื่อมต่อสมอง AI (API Key) ครับ ฝากบอกครูให้หนูหน่อยนะ ไปตั้งค่าได้ที่หน้า "ตั้งค่าเว็บไซต์"']);
    exit;
}

$systemInstruction = "คุณคือ 'ครูเติ้ล AI' ครูสอนวิทยาการคำนวณที่ใจดีและรอบรู้ หน้าที่ของคุณคือตอบคำถามนักเรียนในทุกประเด็นที่เกี่ยวกับการศึกษา โดยมีเงื่อนไขดังนี้:
บุคลิก: พูดจาสุภาพ ใช้คำแทนตัวว่า 'ครู' และเรียกนักเรียนว่า 'นักเรียน' หรือ 'ลูก'
การตอบคำถาม: ต้องตอบอย่างละเอียด มีเหตุผลประกอบ และเข้าใจง่าย หากเป็นเรื่องยากให้ใช้วิธีเปรียบเทียบ
ห้ามเฉลยการบ้านตรง ๆ: หากนักเรียนถามคำตอบการบ้าน ให้คุณอธิบายวิธีการคิดหรือใบ้แนวทางเพื่อให้เขาลองทำเองก่อน
ความรอบรู้: คุณต้องตอบได้ทั้งเรื่องการเขียนโปรแกรม (Python, HTML, PHP), คณิตศาสตร์, วิทยาศาสตร์ และการใช้ชีวิตในโรงเรียน
ความปลอดภัย: หากพบข้อความที่ส่อถึงความรุนแรง หรือภาวะซึมเศร้า ให้ตอบด้วยความห่วงใยและแนะนำให้มาปรึกษาครูเติ้ลตัวจริงทันที";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

$data = [
    "system_instruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $userMsg]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle curl/network errors
if ($response === false) {
    echo json_encode(['status' => 'error', 'reply' => '🔌 ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ AI ได้ (' . $curlError . ')']);
    exit;
}

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'ครูขอโทษทีนะลูก สมองครูเบลอๆ นิดหน่อย ลองถามใหม่อีกทีนะ';
    
    // Final Safety Check on AI response
    if (strpos($reply, 'ปรึกษาครูเติ้ลตัวจริง') !== false) {
         $sender = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'นักเรียนไม่ระบุตัวตน');
         sendLineNotify("\n⚠️ AI ตรวจพบความเสี่ยงและแนะนำให้ปรึกษาครู\nนักเรียน: $sender\nข้อความนักเรียน: $userMsg");
    }

    // Log Chat
    $student_id = $_SESSION['student_id'] ?? 0;
    if ($student_id > 0) {
        $stmt = $conn->prepare("INSERT INTO ai_chat_logs (student_id, user_message, ai_response, sentiment_score) VALUES (?, ?, ?, ?)");
        $sentiment = $is_risk ? -1.0 : 0.0;
        $stmt->bind_param("issd", $student_id, $userMsg, $reply, $sentiment);
        $stmt->execute();
    }

    echo json_encode(['status' => 'success', 'reply' => $reply]);
} else {
    // Parse API error for clear messaging
    $errorBody = json_decode($response, true);
    $errorMsg = $errorBody['error']['message'] ?? '';
    
    if ($httpCode === 400 && strpos($errorMsg, 'API_KEY') !== false) {
        $replyMsg = '🔑 API Key ไม่ถูกต้อง กรุณาให้ครูตรวจสอบคีย์ในหน้าตั้งค่า';
    } elseif ($httpCode === 429) {
        $replyMsg = '⏳ ใช้งาน AI เยอะเกินไป กรุณารอสักครู่แล้วลองใหม่นะครับ';
    } elseif ($httpCode === 403) {
        $replyMsg = '🚫 API Key ถูกบล็อกหรือหมดอายุ กรุณาให้ครูตรวจสอบ';
    } else {
        $replyMsg = '❌ ระบบ AI ขัดข้องชั่วคราว (HTTP ' . $httpCode . ') กรุณาลองใหม่ภายหลังครับ';
    }
    
    echo json_encode(['status' => 'error', 'reply' => $replyMsg]);
}
exit;
?>
