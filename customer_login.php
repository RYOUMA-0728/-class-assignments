<?php
session_start();
require_once 'showdb.php';

/* POST以外は拒否（405対策） */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customer_login.html');
    exit;
}

$loginId  = $_POST['loginId'] ?? '';
$password = $_POST['password'] ?? '';

$sql = "
SELECT customer_id, name, password_hash
FROM m_customer
WHERE login_id = :loginId OR email = :loginId
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':loginId' => $loginId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer && $password === $customer['password_hash']) {
    $_SESSION['customer_id']   = $customer['customer_id'];
    $_SESSION['customer_name'] = $customer['name'];
    header('Location: customer_tracking.php');
    exit;
}

echo "ログイン失敗 <a href='customer_login.html'>戻る</a>";
