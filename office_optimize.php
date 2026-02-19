<?php
session_start();
require_once 'showdb.php';

header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'], $_SESSION['branch_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'not logged in']);
    exit;
}

$branchId = $_SESSION['branch_id'];

try {
    $pdo->beginTransaction();

    /* ① 未割当タスクをロック */
    $taskSql = "
        SELECT request_id
        FROM t_delivery_request
        WHERE status = 'REQUESTED'
          AND driver_id IS NULL
          AND branch_id = :branch_id
        ORDER BY request_id
        FOR UPDATE
    ";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([':branch_id' => $branchId]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$tasks) {
        throw new Exception('未割当タスクがありません');
    }

    /* ② 配達員をロック（集計なし） */
    $driverSql = "
        SELECT staff_id
        FROM m_delivery_staff
        WHERE branch_id = :branch_id
        ORDER BY staff_id
        FOR UPDATE
    ";
    $driverStmt = $pdo->prepare($driverSql);
    $driverStmt->execute([':branch_id' => $branchId]);
    $drivers = $driverStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$drivers) {
        throw new Exception('配達員がいません');
    }

    /* ③ 現在タスク数をPHP側で集計 */
    $countSql = "
        SELECT COUNT(*) FROM t_delivery_request
        WHERE driver_id = :driver_id
    ";
    $countStmt = $pdo->prepare($countSql);

    $driverLoad = [];
    foreach ($drivers as $driverId) {
        $countStmt->execute([':driver_id' => $driverId]);
        $driverLoad[$driverId] = (int)$countStmt->fetchColumn();
    }

    /* ④ タスク割当（負荷が少ない順） */
    asort($driverLoad); // タスク数が少ない順

    $updateSql = "
        UPDATE t_delivery_request
        SET driver_id = :driver_id,
            status = 'ASSIGNED'
        WHERE request_id = :request_id
    ";
    $updateStmt = $pdo->prepare($updateSql);

    $assigned = 0;
    $driverIds = array_keys($driverLoad);
    $driverCount = count($driverIds);
    $i = 0;

    foreach ($tasks as $requestId) {
        $driverId = $driverIds[$i % $driverCount];

        $updateStmt->execute([
            ':driver_id'   => $driverId,
            ':request_id' => $requestId
        ]);

        $assigned++;
        $i++;
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'assigned' => $assigned
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
