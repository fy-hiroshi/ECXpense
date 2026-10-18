<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
$user_id = $_SESSION['user_id'];

// Handle Adding a New Expense from the Modal
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $category = $_POST['category'] ?? 'Other';
    $date = $_POST['expense_date'] ?? date('Y-m-d');

    if ($title !== "" && $amount > 0) {
        $ins = $conn->prepare("INSERT INTO expenses (user_id, title, amount, category, expense_date) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("isdss", $user_id, $title, $amount, $category, $date);
        $ins->execute();
        header("Location: expenses.php");
        exit;
    }
}

// Month filter (?month=YYYY-MM, empty = all months)
require_once __DIR__ . "/includes/month-filter.php";
$mf = ecx_month_filter($conn, $user_id);

// Fetch only personal expenses (excluding shared), optionally limited to one month
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND shared_record_id IS NULL" . $mf['sql'] . " ORDER BY expense_date DESC, id DESC");
$stmt->bind_param("i" . $mf['types'], $user_id, ...$mf['params']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rows_total = array_sum(array_column($rows, 'amount'));

// Fetch pending invitations (High Priority)
$inv_stmt = $conn->prepare("SELECT c.id, s.name, c.role FROM collaborators c JOIN shared_records s ON c.record_id = s.id WHERE c.user_id = ? AND c.status = 'pending'");
$inv_stmt->bind_param("i", $user_id);
$inv_stmt->execute();
$invitations = $inv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unread activity (Lower Priority)
$act_stmt = $conn->prepare("SELECT id, workspace_name, message, created_at FROM activity_notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$act_stmt->bind_param("i", $user_id);
$act_stmt->execute();
$activities = $act_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pending_count = count($invitations) + count($activities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense records - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Expense records</h1>
                    <p>Every personal transaction you have saved.</p>
                </div>
                <div class="topbar-actions">
                    <?php ecx_render_month_filter($mf); ?>
                    <button type="button" class="btn" onclick="document.getElementById('addExpenseModal').style.display='flex'">Add expense</button>
                </div>
            </header>
            
            <div class="panel">
                <div class="records-summary">
                    <span><strong><?= count($rows) ?></strong> <?= count($rows) === 1 ? 'record' : 'records' ?><?= $mf['selected'] !== '' ? ' in ' . htmlspecialchars(ecx_month_label($mf['selected'])) : '' ?></span>
                    <span>Total <strong>₱<?= number_format((float)$rows_total, 2) ?></strong></span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th class="num">Amount</th>
                                <th class="num"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($rows): ?>
                            <?php foreach($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td><?= htmlspecialchars($r['category']) ?></td>
                                    <td class="muted"><?= date('M j, Y', strtotime($r['expense_date'])) ?></td>
                                    <td class="num">₱<?= number_format($r['amount'], 2) ?></td>
                                    <td class="num">
                                        <a class="delete" href="delete-expense.php?id=<?= $r['id'] ?>" onclick="return confirm('Delete this expense?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty"><?= $mf['selected'] !== '' ? 'No expense records found for ' . htmlspecialchars(ecx_month_label($mf['selected'])) . '.' : 'No expense records found.' ?></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <?php include __DIR__ . '/includes/modals.php'; ?>
    <?php include __DIR__ . '/includes/add-expense-modal.php'; ?>
    <script src="js/global-ui.js"></script>
</body>
</html>
