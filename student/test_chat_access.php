<?php
require_once '../includes/config.php';

echo "<h1>Subdirectory API Test</h1>";
echo "Base URL: " . BASE_URL . "<br>";
$apiUrl = BASE_URL . '/api_chat.php';
echo "Target API URL: " . $apiUrl . "<br>";

// Test connection using cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => 'test']));
// Need cookie for session? 
// For this test, api_chat might return "login required" which is a success (means it reached the file).

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "<br>";
echo "Response: " . htmlspecialchars($response) . "<br>";
?>
