<?php
session_start();
require_once 'conn.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    session_destroy();
}

header("Location: login.php");
exit();
?>
