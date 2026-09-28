<?php
error_reporting(0);
ini_set('display_errors', 0);

$host = 'fdb1029.awardspace.net';
$dbname = '4617465_arabfleex';
$user = '4617465_arabfleex';
$pass = 'alifalah9090';

$secret_key = "ArabFleex_2024_SecRet";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: Method not allowed.");
}

if (!isset($_POST['secret_key']) || $_POST['secret_key'] !== $secret_key) {
    die("Error: Unauthorized Access.");
}

$action = $_POST['action'] ?? '';
$series_id = intval($_POST['series_id'] ?? 0);

if ($series_id === 0) {
    die("Error: Invalid series_id.");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($action === 'get_latest') {
        $stmt = $pdo->prepare("SELECT episode_number, watch_link FROM episodes WHERE series_id = :sid ORDER BY episode_number DESC LIMIT 1");
        $stmt->execute([':sid' => $series_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo json_encode([
                "last_ep" => intval($row['episode_number']),
                "last_link" => $row['watch_link']
            ]);
        } else {
            echo json_encode([
                "last_ep" => 0,
                "last_link" => ""
            ]);
        }
        exit;
    }
    
    if ($action === 'insert') {
        $episode_number = intval($_POST['episode_number'] ?? 0);
        if ($episode_number === 0) die("Error: Episode 0 not allowed.");

        $title = $_POST['title'] ?? ''; 
        $watch_link = $_POST['watch_link'] ?? '';
        $watch_link_2 = $_POST['watch_link_2'] ?? '';
        $watch_link_3 = $_POST['watch_link_3'] ?? '';
        $watch_link_4 = $_POST['watch_link_4'] ?? '';
        $download_link = $_POST['download_link'] ?? '';
        $download_link_2 = $_POST['download_link_2'] ?? '';

        $check_sql = "SELECT id FROM episodes WHERE series_id = :series_id AND episode_number = :episode_number LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':series_id' => $series_id, ':episode_number' => $episode_number]);
        
        if ($check_stmt->rowCount() > 0) {
            die("Error: Episode already exists.");
        }

        $insert_sql = "INSERT INTO episodes 
                        (series_id, title, episode_number, watch_link, watch_link_2, watch_link_3, watch_link_4, download_link, download_link_2) 
                       VALUES 
                        (:series_id, :title, :episode_number, :w1, :w2, :w3, :w4, :d1, :d2)";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([
            ':series_id' => $series_id, 
            ':title' => $title, 
            ':episode_number' => $episode_number,
            ':w1' => $watch_link, ':w2' => $watch_link_2, ':w3' => $watch_link_3, ':w4' => $watch_link_4,
            ':d1' => $download_link, ':d2' => $download_link_2
        ]);
        
        echo "INSERTED";
        exit;
    }

    if ($action === 'update') {
        $episode_number = intval($_POST['episode_number'] ?? 0);
        if ($episode_number === 0) die("Error: Episode 0 not allowed.");

        $watch_link   = $_POST['watch_link']   ?? '';
        $watch_link_2 = $_POST['watch_link_2'] ?? '';
        $watch_link_3 = $_POST['watch_link_3'] ?? '';
        $watch_link_4 = $_POST['watch_link_4'] ?? '';
        $download_link   = $_POST['download_link']   ?? '';
        $download_link_2 = $_POST['download_link_2'] ?? '';

        $check_stmt = $pdo->prepare("SELECT id FROM episodes WHERE series_id = :series_id AND episode_number = :episode_number LIMIT 1");
        $check_stmt->execute([':series_id' => $series_id, ':episode_number' => $episode_number]);

        if ($check_stmt->rowCount() === 0) {
            die("Error: Episode not found.");
        }

        $update_sql = "UPDATE episodes SET 
                        watch_link = :w1, watch_link_2 = :w2, watch_link_3 = :w3, watch_link_4 = :w4,
                        download_link = :d1, download_link_2 = :d2
                       WHERE series_id = :series_id AND episode_number = :episode_number";
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([
            ':series_id' => $series_id,
            ':episode_number' => $episode_number,
            ':w1' => $watch_link, ':w2' => $watch_link_2, ':w3' => $watch_link_3, ':w4' => $watch_link_4,
            ':d1' => $download_link, ':d2' => $download_link_2
        ]);

        echo "UPDATED";
        exit;
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>