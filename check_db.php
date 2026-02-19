<?php
require_once 'includes/config.php';

echo "<h2>consultation_replies table structure:</h2><pre>";

$result = $conn->query("DESCRIBE consultation_replies");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . " | " . $row['Default'] . "\n";
    }
} else {
    echo "Table does not exist: " . $conn->error;
}

echo "</pre>";

echo "<h2>Sample data (last 5):</h2><pre>";
$result2 = $conn->query("SELECT * FROM consultation_replies ORDER BY id DESC LIMIT 5");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";
?>
