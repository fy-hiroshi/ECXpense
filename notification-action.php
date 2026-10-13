<?php
session_start();
require_once "config/database.php";
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
$act_id = (int)($_GET['act_id'] ?? 0);
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Handle Collab Invites
if ($id > 0 && in_array($action, ['accept', 'deny'])) {
    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE collaborators SET status = 'accepted' WHERE id = ? AND user_id = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM collaborators WHERE id = ? AND user_id = ?");
    }
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
}

// Handle Activity Dismissals
if ($act_id > 0 && $action === 'dismiss') {
    $stmt = $conn->prepare("UPDATE activity_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $act_id, $user_id);
    $stmt->execute();
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>