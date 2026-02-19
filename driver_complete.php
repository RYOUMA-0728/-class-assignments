<?php
session_start();
require_once 'showdb.php';

if (!isset($_SESSION['driver_id'])) {
    header('Location: driver_login.html');
    exit;
}

$driverId = (int)($_SESSION['driver_id']);
$requestId = (int)($_POST['request_id'] ?? 0);

/* 配達完了更新 */
$sql = "
UPDATE t_delivery_request
SET status = 'COMPLETED'
WHERE request_id = :request_id
  AND driver_id = :driver_id
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':request_id' => $requestId,
        ':driver_id'  => $driverId
    ]);
} catch (PDOException $e) {
    echo "<pre>SQL Error: " . $e->getMessage() . "</pre>";
    exit;
}

header('Location: driver.php');
exit;
