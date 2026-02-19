<?php
session_start();
require_once 'showdb.php';

/* ========= 未ログイン対策 ========= */
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.html');
    exit;
}

/* ========= 配送依頼 登録処理 ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 追跡番号生成
        $trackingNumber = 'TRK-' . date('Ymd') . '-' . random_int(1000, 9999);

        /* 配送依頼 */
        $sql = "
            INSERT INTO t_delivery_request
            (tracking_number, customer_id, request_date, pickup_date, pickup_time_slot, status, branch_id)
            VALUES
            (:tracking, :cid, CURRENT_DATE, :pdate, :ptime, 'REQUESTED', :branch_id)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tracking'  => $trackingNumber,
            ':cid'       => $_SESSION['customer_id'],
            ':pdate'     => $_POST['pickup_date'],
            ':ptime'     => $_POST['pickup_time_slot'],
            ':branch_id' => 1   // ← 東京営業所
        ]);

        $requestId = $pdo->lastInsertId();

        /* 集荷元 */
        $sql = "
            INSERT INTO t_package_pickup
            (request_id, postal_code, address, name, phone_number, package_type, quantity)
            VALUES
            (:rid, :zip, :addr, :name, :phone, :ptype, :qty)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rid'   => $requestId,
            ':zip'   => $_POST['pickup_postal_code'],
            ':addr'  => $_POST['pickup_address'],
            ':name'  => $_POST['pickup_name'],
            ':phone' => $_POST['pickup_phone'],
            ':ptype' => $_POST['package_type'],
            ':qty'   => $_POST['quantity']
        ]);

        /* お届け先 */
        $sql = "
            INSERT INTO t_package_delivery
            (request_id, postal_code, address, name, phone_number)
            VALUES
            (:rid, :zip, :addr, :name, :phone)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rid'   => $requestId,
            ':zip'   => $_POST['delivery_postal_code'],
            ':addr'  => $_POST['delivery_address'],
            ':name'  => $_POST['delivery_name'],
            ':phone' => $_POST['delivery_phone']
        ]);

        $pdo->commit();
        header('Location: ./customer_tracking.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = '登録に失敗しました';
    }
}

/* ========= 配送状況一覧 ========= */
$sql = "
SELECT
    r.tracking_number,
    r.status,
    r.request_date,
    p.address AS pickup_address,
    d.address AS delivery_address
FROM t_delivery_request r
JOIN t_package_pickup   p ON r.request_id = p.request_id
JOIN t_package_delivery d ON r.request_id = d.request_id
WHERE r.customer_id = :cid
ORDER BY r.request_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':cid' => $_SESSION['customer_id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusLabel($s)
{
    return match ($s) {
        'REQUESTED'   => '受付済み',
        'IN_DELIVERY' => '配送中',
        'COMPLETED'   => '配達完了',
        default       => '不明'
    };
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>顧客ダッシュボード</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex bg-gray-100">

    <!-- サイドバー -->
    <aside class="w-64 bg-yellow-700 text-white flex flex-col p-4">
        <h1 class="text-2xl font-bold mb-8">顧客サービス</h1>

        <a href="#" id="btn-tracking"
            class="p-3 rounded hover:bg-yellow-600 bg-yellow-600">
            配送状況確認
        </a>

        <a href="#" id="btn-request"
            class="p-3 rounded hover:bg-yellow-600 mt-2">
            配送依頼登録
        </a>

        <div class="mt-auto">
            <a href="customer_logout.php" class="text-red-200">
                ログアウト
            </a>
        </div>
    </aside>

    <!-- メイン -->
    <main class="flex-1 p-8 overflow-y-auto">

        <!-- 配送状況確認 -->
        <section id="tracking">
            <h2 class="text-2xl font-bold mb-4">配送状況確認</h2>

            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3">追跡番号</th>
                            <th class="p-3">お届け先</th>
                            <th class="p-3">依頼日</th>
                            <th class="p-3">状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="4" class="p-6 text-center text-gray-500">
                                    データなし
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rows as $r): ?>
                            <tr class="border-t">
                                <td class="p-3 font-mono">
                                    <?= htmlspecialchars($r['tracking_number']) ?>
                                </td>
                                <td class="p-3">
                                    <?= htmlspecialchars($r['delivery_address']) ?>
                                </td>
                                <td class="p-3">
                                    <?= date('Y/m/d', strtotime($r['request_date'])) ?>
                                </td>
                                <td class="p-3 font-semibold text-orange-600">
                                    <?= statusLabel($r['status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 配送依頼登録 -->
        <section id="request" class="hidden">
            <h2 class="text-2xl font-bold mb-4">配送依頼登録</h2>

            <?php if (!empty($errorMessage)): ?>
                <p class="text-red-600 mb-4">
                    <?= htmlspecialchars($errorMessage) ?>
                </p>
            <?php endif; ?>

            <form method="post" class="bg-white p-6 rounded shadow space-y-4">

                <h3 class="font-bold text-orange-600">集荷元</h3>
                <input name="pickup_name" required placeholder="氏名" class="w-full border p-2 rounded">
                <input name="pickup_phone" required placeholder="電話番号" class="w-full border p-2 rounded">
                <input name="pickup_postal_code" required placeholder="郵便番号" class="w-full border p-2 rounded">
                <input name="pickup_address" required placeholder="住所" class="w-full border p-2 rounded">

                <h3 class="font-bold text-orange-600">お届け先</h3>
                <input name="delivery_name" required placeholder="氏名" class="w-full border p-2 rounded">
                <input name="delivery_phone" required placeholder="電話番号" class="w-full border p-2 rounded">
                <input name="delivery_postal_code" required placeholder="郵便番号" class="w-full border p-2 rounded">
                <input name="delivery_address" required placeholder="住所" class="w-full border p-2 rounded">

                <h3 class="font-bold text-orange-600">荷物</h3>
                <select name="package_type" class="w-full border p-2 rounded">
                    <option value="ダンボール">ダンボール</option>
                    <option value="書類">書類</option>
                </select>
                <input type="number" name="quantity" value="1" min="1" class="w-full border p-2 rounded">

                <h3 class="font-bold text-orange-600">集荷日時</h3>
                <input type="date" name="pickup_date" required class="w-full border p-2 rounded">
                <select name="pickup_time_slot" class="w-full border p-2 rounded">
                    <option value="AM">午前</option>
                    <option value="PM">午後</option>
                </select>

                <button class="w-full bg-orange-600 text-white py-3 rounded font-bold">
                    集荷依頼を登録
                </button>

            </form>
        </section>

    </main>

    <!-- 表示切り替えJS -->
    <script>
        const btnTracking = document.getElementById('btn-tracking');
        const btnRequest = document.getElementById('btn-request');
        const tracking = document.getElementById('tracking');
        const request = document.getElementById('request');

        btnTracking.addEventListener('click', e => {
            e.preventDefault();
            tracking.classList.remove('hidden');
            request.classList.add('hidden');

            btnTracking.classList.add('bg-yellow-600');
            btnRequest.classList.remove('bg-yellow-600');
        });

        btnRequest.addEventListener('click', e => {
            e.preventDefault();
            request.classList.remove('hidden');
            tracking.classList.add('hidden');

            btnRequest.classList.add('bg-yellow-600');
            btnTracking.classList.remove('bg-yellow-600');
        });
    </script>

</body>

</html>