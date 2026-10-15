<?php
session_start();
require_once "../config/database.php";
require_once __DIR__ . "/../includes/month-filter.php";

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(["labels" => [], "values" => []]); 
    exit; 
}

$labels = []; 
$values = [];
$user_id = $_SESSION['user_id'];

// Optional ?month=YYYY-MM filter (invalid values are ignored = all months)
$mf = ecx_month_range();

// The query explicitly excludes shared records
$stmt = $conn->prepare("SELECT category, SUM(amount) total FROM expenses WHERE user_id = ? AND shared_record_id IS NULL" . $mf['sql'] . " GROUP BY category ORDER BY total DESC");
$stmt->bind_param("i" . $mf['types'], $user_id, ...$mf['params']);
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