<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(["labels" => [], "values" => []]); 
    exit; 
}

$labels = []; 
$values = [];
$user_id = $_SESSION['user_id'];

// The query now explicitly excludes shared records
$stmt = $conn->prepare("SELECT category, SUM(amount) total FROM expenses WHERE user_id = ? AND shared_record_id IS NULL GROUP BY category ORDER BY total DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$q = $stmt->get_result();

if ($q) {
    while ($r = $q->fetch_assoc()) { 
        $labels[] = $r["category"]; 
        $values[] = (float)$r["total"]; 
    }
}

header("Content-Type: application/json");
echo json_encode(["labels" => $labels, "values" => $values]);
?>