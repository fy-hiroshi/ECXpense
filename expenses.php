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
    <title>Expense Records - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Expense Records</h1>
                    <p>View all saved personal transactions.</p>
                </div>
                <div class="topbar-actions">
                    <?php ecx_render_month_filter($mf); ?>
                    <button type="button" class="btn" onclick="document.getElementById('addExpenseModal').style.display='flex'">+ Add Expense</button>
                </div>
            </header>
            
            <div class="panel">
                <div class="records-summary">
                    <span><strong><?= count($rows) ?></strong> <?= count($rows) === 1 ? 'record' : 'records' ?><?= $mf['selected'] !== '' ? ' in ' . htmlspecialchars(ecx_month_label($mf['selected'])) : '' ?></span>
                    <span>Total <strong>₱<?= number_format((float)$rows_total, 2) ?></strong></span>
                </div>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                        <?php if($rows): ?>
                            <?php foreach($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td><?= htmlspecialchars($r['category']) ?></td>
                                    <td>₱<?= number_format($r['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['expense_date']) ?></td>
                                    <td>
                                        <a class="delete" href="delete-expense.php?id=<?= $r['id'] ?>" onclick="return confirm('Delete this expense?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty"><?= $mf['selected'] !== '' ? 'No expense records found for ' . htmlspecialchars(ecx_month_label($mf['selected'])) . '.' : 'No expense records found.' ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Logout Modal -->
    <div id="logoutModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h2>Ready to leave?</h2>
            <p>Are you sure you want to log out of your account?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
                <a href="logout.php" class="btn btn-danger">Log Out</a>
            </div>
        </div>
    </div>

    <!-- Notifications Modal (Priority Sorted) -->
    <div id="notifModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" style="max-height: 85vh; overflow-y: auto;">
            <h2>Notifications</h2>
            
            <!-- HIGH PRIORITY: Invites -->
            <?php if(count($invitations) > 0): ?>
                <h4 style="margin: 0 0 12px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Pending Invitations</h4>
                <?php foreach($invitations as $n): ?>
                    <div class="notif-item" style="border-left: 4px solid #3b82f6;">
                        <div style="text-align: left;">
                            <strong><?= htmlspecialchars($n['name']) ?></strong><br>
                            <small>Role: <?= ucfirst($n['role']) ?></small>
                        </div>
                        <div class="notif-actions">
                            <a href="notification-action.php?id=<?= $n['id'] ?>&action=accept" class="btn btn-success">Accept</a>
                            <a href="notification-action.php?id=<?= $n['id'] ?>&action=deny" class="btn btn-danger">Deny</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- LOWER PRIORITY: Recent Activity -->
            <?php if(count($activities) > 0): ?>
                <h4 style="margin: <?= count($invitations) > 0 ? '24px' : '0' ?> 0 12px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Recent Activity</h4>
                <?php foreach($activities as $a): ?>
                    <div class="notif-item">
                        <div style="text-align: left;">
                            <strong><?= htmlspecialchars($a['workspace_name']) ?></strong><br>
                            <small><?= htmlspecialchars($a['message']) ?></small>
                        </div>
                        <div class="notif-actions">
                            <a href="notification-action.php?act_id=<?= $a['id'] ?>&action=dismiss" class="btn btn-secondary" style="margin:0!important; padding: 6px 14px!important; font-size:12px!important; min-width:auto;">Dismiss</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($pending_count == 0): ?>
                <div style="text-align: center; padding: 30px 0; color: #94a3b8;">
                    <svg viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="1" fill="none" style="margin-bottom: 12px; opacity: 0.5;">
                        <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <p style="margin:0;">You're all caught up!</p>
                </div>
            <?php endif; ?>

            <button class="btn btn-secondary mt-auto" style="width:100%; margin-top:24px!important;" onclick="document.getElementById('notifModal').style.display='none'">Close</button>
        </div>
    </div>

    <!-- Real-Time Notification Polling script -->
    <script>
        setInterval(() => {
            fetch('api/check-notifications.php')
                .then(res => res.json())
                .then(data => {
                    const iconBtn = document.querySelector('.notif-icon-btn');
                    let dot = document.querySelector('.notif-dot');
                    
                    if (data.count > 0) {
                        if (!dot) {
                            dot = document.createElement('span');
                            dot.className = 'notif-dot';
                            iconBtn.appendChild(dot);
                        }
                        // Update the number inside the red dot
                        dot.textContent = data.count;
                    } else if (dot) {
                        // Remove dot if there are no notifications
                        dot.remove();
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }, 10000); // Quietly checks the database every 10 seconds
    </script>
    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" style="max-width: 480px; width: 100%; text-align: left;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Add Expense</h2>
                <button type="button" onclick="document.getElementById('addExpenseModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 0;">&times;</button>
            </div>

            <form method="post" style="display: flex; flex-direction: column; gap: 16px;">
                <input type="hidden" name="action" value="add_expense">
                
                <label>Expense Title
                    <input type="text" name="title" required placeholder="e.g. Lunch">
                </label>

                <label>Amount
                    <input type="number" step="0.01" name="amount" required placeholder="0.00">
                </label>

                <label>Category
                    <div class="category-wrapper">
                        <select name="category" id="categorySelect">
                            <option>Housing & Living</option>
                            <option>Utilities</option>
                            <option>Food and Dining</option>
                            <option>Transportation</option>
                            <option>Health & Wellness</option>
                            <option>Personal Care & Household</option>
                            <option>Entertainment & Lifestyle</option>
                            <option>Financial & Obligations</option>
                            <option>Other</option>
                        </select>
                        <button type="button" id="categoryToggleBtn" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to Business Categories">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        </button>
                    </div>
                </label>

                <label>Date
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </label>

                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                    <button type="submit" class="btn" style="width: 100%; margin: 0;">Save Expense</button>
                    <button type="button" class="btn btn-secondary" style="width: 100%; margin: 0;" onclick="document.getElementById('addExpenseModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const personalCategories = ["Housing & Living", "Utilities", "Food and Dining", "Transportation", "Health & Wellness", "Personal Care & Household", "Entertainment & Lifestyle", "Financial & Obligations", "Other"];
        const businessCategories = ["Operations & Office", "Cost of Goods Sold (COGS) / Inventory", "Marketing & Advertising", "Payroll & Contractor Fees", "Travel & Client Entertainment", "Professional Services & Legal", "Business Insurance & Taxes", "Other"];
        
        const iconHouse = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>`;
        const iconSuitcase = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>`;
        
        let isBusiness = false;

        function toggleCategoryType() {
            isBusiness = !isBusiness;
            const select = document.getElementById('categorySelect');
            const btn = document.getElementById('categoryToggleBtn');
            
            const options = isBusiness ? businessCategories : personalCategories;
            select.innerHTML = options.map(opt => `<option>${opt}</option>`).join('');
            
            btn.innerHTML = isBusiness ? iconSuitcase : iconHouse;
            btn.title = isBusiness ? "Switch to Personal Categories" : "Switch to Business Categories";
        }
    </script>
    <script src="js/global-ui.js"></script>
</body>
</html>