<?php
try {
    $dsn = "pgsql:host=localhost;port=5432;dbname=distribution1;";
    $user = "postgres";
    $password = "P@ssw0rd";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    exit("DB接続失敗: " . $e->getMessage());
}
