<?php
session_start();
require_once 'showdb.php';

// POSTから入力を取得
$loginId  = $_POST['login_id'] ?? '';
$password = $_POST['password'] ?? '';

// 入力チェック
if (!$loginId || !$password) {
    echo "入力されていません。<a href='office_login.html'>戻る</a>";
    exit;
}

try {
    // m_carrier_user テーブルを使用
    $sql = "
        SELECT carrier_user_id, name, branch_id, password_hash
        FROM m_carrier_user
        WHERE login_id = :loginId
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':loginId' => $loginId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // パスワード確認（ハッシュ化対応）
    if ($user && password_verify($password, $user['password_hash'])) {
        // セッションに保存
        $_SESSION['staff_id']   = $user['carrier_user_id'];
        $_SESSION['staff_name'] = $user['name'];
        $_SESSION['branch_id']  = $user['branch_id'];

        // ダッシュボードへリダイレクト
        header('Location: office.php');
        exit;
    }

    // 認証失敗
    echo "ログイン失敗 <a href='office_login.html'>戻る</a>";
} catch (PDOException $e) {
    // SQLエラー表示
    echo "<pre>SQL Error: " . $e->getMessage() . "</pre>";
    exit;
}
