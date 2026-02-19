<?php
session_start();
require_once 'showdb.php';

$loginId  = $_POST['login_id'] ?? '';
$password = $_POST['password'] ?? '';

if (!$loginId || !$password) {
    echo "入力されていません。<a href='driver_login.html'>戻る</a>";
    exit;
}

/* 配達員検索 */
$sql = "
SELECT
    staff_id,
    name,
    password_hash
FROM m_delivery_staff
WHERE login_id = :login_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':login_id' => $loginId]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

/* 簡易認証 */
if ($driver && $password === $driver['password_hash']) {
    // セッション変数を driver.php に合わせて設定
    $_SESSION['driver_id']   = $driver['staff_id'];
    $_SESSION['driver_name'] = $driver['name'];

    header('Location: driver.php');
    exit;
}

/* 認証失敗 */
echo "ログイン失敗 <a href='driver_login.html'>戻る</a>";
