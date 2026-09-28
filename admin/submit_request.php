<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'unknown';

    if ($type === 'request') {
        $name = $_POST['name'] ?? null;
        $email = $_POST['email'] ?? null;
        $work_name = $_POST['work_name'] ?? null;
        $work_link = $_POST['work_link'] ?? null;

        $stmt = $conn->prepare("INSERT INTO requests (type, name, email, work_name, work_link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $type, $name, $email, $work_name, $work_link);
        if ($stmt->execute()) {
            $response['success'] = true;
        }
        $stmt->close();
    } elseif ($type === 'feedback') {
        $name = $_POST['fb_name'] ?? null;
        $email = $_POST['fb_email'] ?? null;
        $description = $_POST['description'] ?? null;
        
        $stmt = $conn->prepare("INSERT INTO requests (type, name, email, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $type, $name, $email, $description);
        if ($stmt->execute()) {
            $response['success'] = true;
        }
        $stmt->close();
    }
}

$conn->close();
echo json_encode($response);
?>