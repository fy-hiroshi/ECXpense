<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
$id = (int)($_GET["id"] ?? 0);
$user_id = $_SESSION['user_id'];

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: expenses.php"); 
exit;
?>