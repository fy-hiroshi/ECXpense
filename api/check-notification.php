<?php
session_start();
require_once "../config/database.php";
if (!isset($_SESSION['user_id'])) { echo json_encode(['count' => 0]); exit; }

$user_id = $_SESSION['user_id'];

// Get pending invites
$inv_res = $conn->query("SELECT COUNT(*) as c FROM collaborators WHERE user_id = $user_id AND status = 'pending'");
$inv_count = $inv_res ? (int)$inv_res->fetch_assoc()['c'] : 0;

// Get unread activities
$act_res = $conn->query("SELECT COUNT(*) as c FROM activity_notifications WHERE user_id = $user_id AND is_read = 0");
$act_count = $act_res ? (int)$act_res->fetch_assoc()['c'] : 0;

echo json_encode(['count' => $inv_count + $act_count]);
?>