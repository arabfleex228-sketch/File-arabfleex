<?php
error_reporting(0);
ini_set('display_errors', 0);

// بيانات الاتصال بقاعدة بيانات AwardSpace
$host = 'fdb1029.awardspace.net';
$dbname = '4617465_arabfleex';
$user = '4617465_arabfleex';
$pass = 'alifalah9090';

// المفتاح السري الخاص بالبوت
$secret_key = "ArabFleex_2024_SecRet";

// منع أي طلب غير POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: Method not allowed.");
}

// التحقق من المفتاح السري
if (!isset($_POST['secret_key']) || $_POST['secret_key'] !== $secret_key) {
    die("Error: Unauthorized Access.");
}

$action = $_POST['action'] ?? '';
$series_id = intval($_POST['series_id'] ?? 0);

if ($series_id === 0) {
    die("Error: Invalid series_id.");
}

try {
    // الاتصال بقاعدة البيانات
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($action === 'insert') {
        $episode_number = intval($_POST['episode_number'] ?? 0);
        if ($episode_number === 0) die("Error: Episode 0 not allowed.");

        $title = $_POST['title'] ?? ''; 
        // استقبال الروابط المدمجة من البوت
        $links_string = $_POST['links_string'] ?? '';

        // 1. التحقق من عدم تكرار الحلقة في جدول episodes
        $check_sql = "SELECT id FROM episodes WHERE series_id = :series_id AND episode_number = :episode_number LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':series_id' => $series_id, ':episode_number' => $episode_number]);
        
        if ($check_stmt->rowCount() > 0) {
            die("Error: Episode already exists.");
        }

        // 2. إدخال البيانات في جدول episodes
        $insert_sql = "INSERT INTO episodes 
                        (series_id, title, episode_number, watch_link, download_link) 
                       VALUES 
                        (:series_id, :title, :episode_number, :watch_link, :download_link)";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([
            ':series_id' => $series_id, 
            ':title' => $title, 
            ':episode_number' => $episode_number,
            ':watch_link' => $links_string,
            ':download_link' => $links_string
        ]);
        
        // إرسال رسالة النجاح للبوت
        echo "INSERTED";
        exit;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>