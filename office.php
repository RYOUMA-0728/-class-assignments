<?php
session_start();
require_once 'showdb.php';

/* ログインチェック */
if (!isset($_SESSION['staff_id'])) {
    header('Location: office_login.html');
    exit;
}

$staffName = $_SESSION['staff_name'];
$branchId  = $_SESSION['branch_id'];

/* 配達スタッフごとのタスク数 */
$sql = "
SELECT
    s.staff_id,
    s.name,
    COUNT(r.request_id) AS current_tasks,
    10 AS capacity,
    '担当エリア未設定' AS area
FROM m_delivery_staff s
LEFT JOIN t_delivery_request r
    ON s.staff_id = r.driver_id
WHERE s.branch_id = :branch_id
GROUP BY s.staff_id, s.name
ORDER BY s.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':branch_id' => $branchId]);
$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 未割当タスク数 */
$sql = "
SELECT COUNT(*) FROM t_delivery_request
WHERE status = 'REQUESTED'
AND branch_id = :branch_id
AND driver_id IS NULL
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':branch_id' => $branchId]);
$unassignedCount = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>営業所 配送最適化ダッシュボード</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex bg-gray-100">

    <!-- サイドバー -->
    <aside class="w-20 bg-gray-800 text-white flex flex-col items-center p-4">
        <span class="text-xs text-gray-400">営業所</span>
        <a href="office_logout.php" class="mt-auto text-red-400 hover:text-red-300 text-sm">ログアウト</a>
    </aside>

    <!-- メイン -->
    <main class="flex-grow p-8">

        <!-- ヘッダー -->
        <header class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800">営業所ダッシュボード</h1>
                <p class="text-sm text-gray-600">
                    ログイン中：<?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="text-lg font-bold text-red-600">
                未割当タスク <?= (int)$unassignedCount ?> 件
            </div>
        </header>

        <!-- 2カラム -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- 左：配達員状況 -->
            <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if ($drivers): ?>
                    <?php foreach ($drivers as $d): ?>
                        <div class="bg-white rounded-xl shadow p-5">
                            <p class="text-lg font-bold mb-1">
                                <?= htmlspecialchars($d['name']) ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                担当エリア：<?= htmlspecialchars($d['area']) ?>
                            </p>
                            <p class="text-sm mt-2">
                                タスク数：
                                <span class="font-bold">
                                    <?= (int)$d['current_tasks'] ?> / <?= (int)$d['capacity'] ?>
                                </span>
                            </p>

                            <!-- 簡易進捗バー -->
                            <?php
                            $rate = min(100, ($d['current_tasks'] / $d['capacity']) * 100);
                            ?>
                            <div class="w-full bg-gray-200 rounded-full h-3 mt-2">
                                <div class="bg-blue-600 h-3 rounded-full"
                                    style="width: <?= (int)$rate ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 col-span-full text-center">
                        配達員が登録されていません
                    </p>
                <?php endif; ?>
            </div>

            <!-- 右：最適化パネル -->
            <div class="bg-white rounded-xl shadow p-6 border-t-4 border-red-500">
                <h2 class="text-xl font-bold text-red-700 mb-4">
                    ルート最適化 & タスク割り当て
                </h2>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-red-600">未割り当て件数</p>
                    <p class="text-4xl font-extrabold text-red-800">
                        <?= (int)$unassignedCount ?>
                    </p>
                </div>

                <p class="text-sm text-gray-600 mb-6">
                    未割り当ての配送依頼を、配達員の現在タスク数を考慮して
                    自動的に割り当てます。
                </p>

                <button
                    onclick="optimizeTasks()"
                    class="w-full py-4 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition">
                    最適化を実行
                </button>

                <p id="result-message" class="text-center text-sm mt-4 hidden"></p>
            </div>
        </div>
    </main>

    <script>
        function optimizeTasks() {
            if (!confirm('未割当タスクを自動割り当てします。よろしいですか？')) {
                return;
            }

            fetch('office_optimize.php', {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    alert(`最適化完了：${data.assigned} 件を割り当てました`);
                    location.reload();
                })
                .catch(() => {
                    alert('最適化処理に失敗しました');
                });
        }
    </script>

</body>

</html>