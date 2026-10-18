<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
$user_id = $_SESSION['user_id'];

// Right after logging in, play the welcome sequence once (login.php sets the flag).
$welcome = !empty($_SESSION['welcome']);
unset($_SESSION['welcome']);
$welcome_name = '';
if ($welcome) {
    $w_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $w_stmt->bind_param("i", $user_id);
    $w_stmt->execute();
    $welcome_name = $w_stmt->get_result()->fetch_assoc()['username'] ?? '';
    $w_stmt->close();
}

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

// Get total summary for personal user expenses ONLY (excluding shared)
$stmt1 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS count FROM expenses WHERE user_id = ? AND shared_record_id IS NULL" . $mf['sql']);
$stmt1->bind_param("i" . $mf['types'], $user_id, ...$mf['params']);
$stmt1->execute();
$summary = $stmt1->get_result()->fetch_assoc();

// Get recent personal expenses ONLY (excluding shared)
$stmt2 = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND shared_record_id IS NULL" . $mf['sql'] . " ORDER BY expense_date DESC, id DESC LIMIT 5");
$stmt2->bind_param("i" . $mf['types'], $user_id, ...$mf['params']);
$stmt2->execute();
$recent = $stmt2->get_result();

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
<html lang="en"<?= $welcome ? ' class="welcoming"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECXpense - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <?php if ($welcome): ?>
    <!-- Welcome sequence: runs on its own timeline in CSS, then lifts away. Click or any key skips it. -->
    <div class="welcome" id="welcome" aria-hidden="true">
        <div class="welcome-inner">
            <div class="brand">EC<span>Xpense</span></div>
            <p class="welcome-greeting"><span>Welcome back<?= $welcome_name !== '' ? ', ' . htmlspecialchars($welcome_name) : '' ?></span></p>
            <div class="welcome-rule"></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Dashboard</h1>
                    <p>Your personal spending at a glance.</p>
                </div>
                <div class="topbar-actions">
                    <?php ecx_render_month_filter($mf); ?>
                    <button type="button" class="btn" onclick="document.getElementById('addExpenseModal').style.display='flex'">Add expense</button>
                </div>
            </header>
            
            <?php
                $period_label = $mf['selected'] !== '' ? 'Spent in ' . ecx_month_label($mf['selected']) : 'Total spent, all months';
                $avg_amount   = (int)$summary['count'] > 0 ? (float)$summary['total'] / (int)$summary['count'] : 0;
            ?>
            <section class="masthead" aria-label="Spending summary">
                <div class="masthead-top">
                    <p class="masthead-label"><?= htmlspecialchars($period_label) ?></p>
                    <a href="scan-receipt.php" class="masthead-action">
                        <svg viewBox="0 0 24 24" width="17" height="17" stroke="currentColor" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8V6a2 2 0 0 1 2-2h2"></path><path d="M16 4h2a2 2 0 0 1 2 2v2"></path><path d="M20 16v2a2 2 0 0 1-2 2h-2"></path><path d="M8 20H6a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
                        Scan a receipt
                    </a>
                </div>
                <div class="masthead-bottom">
                    <div class="masthead-figure">
                        <span class="cur">₱</span><span data-countup="<?= (float)$summary['total'] ?>"><?= number_format((float)$summary['total'], 2) ?></span>
                    </div>
                    <dl class="masthead-stats">
                        <div>
                            <dt>Transactions</dt>
                            <dd><?= (int)$summary['count'] ?></dd>
                        </div>
                        <div>
                            <dt>Average</dt>
                            <dd>₱<?= number_format($avg_amount, 2) ?></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="grid">
                <div class="panel">
                    <h2>Spending by category</h2>
                    <div class="chart-wrap"><canvas id="categoryChart" aria-label="Spending by category"></canvas></div>
                    <ul class="legend" id="categoryLegend"></ul>
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <h2>Recent expenses</h2>
                        <a class="link" href="expenses.php<?= $mf['selected'] !== '' ? '?month=' . urlencode($mf['selected']) : '' ?>">View all</a>
                    </div>
                    <?php if ($recent && $recent->num_rows): ?>
                        <div class="table-wrap">
                            <table class="table-compact">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th class="num">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while($row = $recent->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['title']) ?></td>
                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                        <td class="muted"><?= date('M j, Y', strtotime($row['expense_date'])) ?></td>
                                        <td class="num">₱<?= number_format((float)$row['amount'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty"><?= $mf['selected'] !== '' ? 'No expenses in ' . htmlspecialchars(ecx_month_label($mf['selected'])) . '.' : 'No expenses yet. Add your first expense.' ?></div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        // The same ?month= filter drives the chart (validated again on the server).
        const categoryUrl = 'api/category-data.php<?= $mf['selected'] !== '' ? '?month=' . urlencode($mf['selected']) : '' ?>';
        fetch(categoryUrl)
            .then(r => r.json())
            .then(data => {
                const canvas = document.getElementById('categoryChart');
                if (!data.values.length) {
                    const wrap = canvas.closest('.chart-wrap');
                    wrap.insertAdjacentHTML('afterend', '<div class="empty">No spending data for this period.</div>');
                    wrap.remove();
                    document.getElementById('categoryLegend').remove();
                    return;
                }
                ecxDoughnut(canvas, data.labels, data.values, document.getElementById('categoryLegend'));
            });
    </script>

    <?php include __DIR__ . '/includes/modals.php'; ?>
    <?php include __DIR__ . '/includes/add-expense-modal.php'; ?>
    <script src="js/global-ui.js"></script>
</body>
</html>
