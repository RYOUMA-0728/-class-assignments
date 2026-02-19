<?php
session_start();
require_once 'showdb.php';

/* ========= 未ログイン対策 ========= */
if (!isset($_SESSION['driver_id'])) {
    header('Location: driver_login.html');
    exit;
}

$driverId   = (int)$_SESSION['driver_id'];
$driverName = $_SESSION['driver_name'] ?? '不明';

/* ========= 担当タスク取得 ========= */
$sql = "
SELECT
    r.request_id,
    r.tracking_number,
    r.status,
    r.pickup_time_slot,
    p.address AS pickup_address,
    d.address AS delivery_address,
    c.name AS customer_name,
    c.phone_number AS customer_phone
FROM t_delivery_request r
LEFT JOIN t_package_pickup p   ON r.request_id = p.request_id
LEFT JOIN t_package_delivery d ON r.request_id = d.request_id
JOIN m_customer c ON r.customer_id = c.customer_id
WHERE r.driver_id = :driver_id
ORDER BY r.request_id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':driver_id' => $driverId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'COMPLETED'));
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>配達員ダッシュボード</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex bg-gray-100">

    <!-- サイドバー -->
    <aside class="w-20 bg-gray-800 text-white flex flex-col items-center p-4">
        <span class="text-xs text-gray-400">配達員</span>
        <a href="driver_logout.php" class="mt-auto text-red-400 text-sm">ログアウト</a>
    </aside>

    <!-- メイン -->
    <main class="flex-grow p-6">

        <header class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800">本日のタスク</h1>
                <p class="text-sm text-gray-600">配達員：<?= htmlspecialchars($driverName) ?></p>
            </div>
            <div class="text-lg font-bold text-blue-600">
                完了 <?= $completedTasks ?> / <?= $totalTasks ?>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Google Map -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow p-4 h-96">
                <iframe
                    src="https://www.google.com/maps?q=静岡県&output=embed"
                    class="w-full h-full rounded"
                    loading="lazy">
                </iframe>
            </div>

            <!-- タスク一覧 -->
            <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <?php foreach ($tasks as $t):
                    $isCompleted = $t['status'] === 'COMPLETED';
                ?>
                    <div
                        class="task-card bg-white rounded-xl shadow p-4 cursor-pointer <?= $isCompleted ? 'opacity-60' : '' ?>"
                        onclick="openModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-mono text-gray-400"><?= $t['tracking_number'] ?></span>
                            <span class="text-xs px-2 py-1 rounded <?= $isCompleted ? 'bg-green-500' : 'bg-blue-500' ?> text-white">
                                <?= $isCompleted ? '完了' : '未完了' ?>
                            </span>
                        </div>
                        <p class="font-bold"><?= htmlspecialchars($t['customer_name']) ?> 様</p>
                        <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($t['delivery_address']) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if (!$tasks): ?>
                    <p class="text-gray-500 text-center">タスクはありません</p>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- モーダル -->
    <div id="modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg">
            <h3 class="text-xl font-bold mb-3" id="m_tracking"></h3>
            <p><b>お客様:</b> <span id="m_name"></span></p>
            <p><b>電話:</b> <span id="m_phone"></span></p>
            <p><b>集荷元:</b> <span id="m_pickup"></span></p>
            <p><b>配達先:</b> <span id="m_delivery"></span></p>
            <p><b>時間帯:</b> <span id="m_time"></span></p>

            <form action="driver_complete.php" method="post" class="mt-4">
                <input type="hidden" name="request_id" id="m_request_id">
                <button class="w-full bg-green-600 text-white py-2 rounded font-bold">
                    配達完了
                </button>
            </form>

            <button onclick="closeModal()" class="mt-3 w-full text-gray-500">閉じる</button>
        </div>
    </div>

    <script>
        function openModal(task) {
            document.getElementById('m_tracking').textContent = task.tracking_number;
            document.getElementById('m_name').textContent = task.customer_name;
            document.getElementById('m_phone').textContent = task.customer_phone;
            document.getElementById('m_pickup').textContent = task.pickup_address ?? '―';
            document.getElementById('m_delivery').textContent = task.delivery_address;
            document.getElementById('m_time').textContent = task.pickup_time_slot ?? '指定なし';
            document.getElementById('m_request_id').value = task.request_id;
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }
    </script>

</body>

</html>