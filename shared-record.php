<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once "config/database.php";

$user_id = $_SESSION['user_id'];
$record_id = (int)($_GET['id'] ?? 0);
$message = "";

// 1. Verify Access and Get Role
$stmt = $conn->prepare("SELECT s.name, s.owner_id, c.role FROM shared_records s JOIN collaborators c ON s.id = c.record_id WHERE s.id = ? AND c.user_id = ? AND c.status = 'accepted'");
$stmt->bind_param("ii", $record_id, $user_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) { die("Record not found or access denied."); }

$is_owner = ($record['role'] === 'owner');
$is_editor = ($record['role'] === 'editor' || $is_owner);

// 2. Handle Actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    // Add Expense
    if ($action === 'add_expense' && $is_editor) {
        $title = trim($_POST['title']); $amount = (float)$_POST['amount']; $category = $_POST['category']; $date = $_POST['expense_date'];
        $ins = $conn->prepare("INSERT INTO expenses (user_id, shared_record_id, title, amount, category, expense_date) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iisdss", $user_id, $record_id, $title, $amount, $category, $date);
        $ins->execute();
        
        // Real-time Notification Trigger: Alert the owner if someone else added this
        if ($user_id !== $record['owner_id']) {
            // Fetch the username of the person adding the expense
            $u_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $current_username = $u_stmt->get_result()->fetch_assoc()['username'] ?? 'A collaborator';
            
            // Prepend the username to the notification message
            $notif_msg = $current_username . " added a new expense: " . htmlspecialchars($title) . " (₱" . number_format($amount, 2) . ")";
            
            $notif_ins = $conn->prepare("INSERT INTO activity_notifications (user_id, workspace_name, message) VALUES (?, ?, ?)");
            $notif_ins->bind_param("iss", $record['owner_id'], $record['name'], $notif_msg);
            $notif_ins->execute();
        }
    }
    // Delete Expense
    elseif ($action === 'delete_expense' && $is_editor) {
        $exp_id = (int)$_POST['expense_id'];
        $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND shared_record_id = ?");
        $del->bind_param("ii", $exp_id, $record_id);
        $del->execute();
    }
    // Invite Collaborator
    elseif ($action === 'invite' && $is_owner) {
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $usr_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $usr_stmt->bind_param("s", $username);
        $usr_stmt->execute();
        $target_user = $usr_stmt->get_result()->fetch_assoc();
        
        if ($target_user) {
            $inv = $conn->prepare("INSERT IGNORE INTO collaborators (record_id, user_id, role, status) VALUES (?, ?, ?, 'pending')");
            $inv->bind_param("iis", $record_id, $target_user['id'], $role);
            $inv->execute();
            $message = "Invitation sent!";
        } else { $message = "User not found."; }
    }
    // Remove or Change Role
    elseif ($action === 'remove_user' && $is_owner) {
        $target = (int)$_POST['target_user_id'];
        $rem = $conn->prepare("DELETE FROM collaborators WHERE record_id = ? AND user_id = ?");
        $rem->bind_param("ii", $record_id, $target);
        $rem->execute();
    }
    elseif ($action === 'update_role' && $is_owner) {
        $target = (int)$_POST['target_user_id']; $new_role = $_POST['new_role'];
        $upd = $conn->prepare("UPDATE collaborators SET role = ? WHERE record_id = ? AND user_id = ?");
        $upd->bind_param("sii", $new_role, $record_id, $target);
        $upd->execute();
    }
    // Rename Record
    elseif ($action === 'rename' && $is_owner) {
        $new_name = trim($_POST['new_name']);
        $ren = $conn->prepare("UPDATE shared_records SET name = ? WHERE id = ?");
        $ren->bind_param("si", $new_name, $record_id);
        $ren->execute();
        $record['name'] = $new_name;
    }
    // Transfer Ownership
    elseif ($action === 'transfer' && $is_owner) {
        $new_owner = (int)$_POST['new_owner_id'];
        if ($new_owner > 0) {
            $conn->begin_transaction();
            $conn->query("UPDATE shared_records SET owner_id = $new_owner WHERE id = $record_id");
            $conn->query("UPDATE collaborators SET role = 'editor' WHERE record_id = $record_id AND user_id = $user_id");
            $conn->query("UPDATE collaborators SET role = 'owner' WHERE record_id = $record_id AND user_id = $new_owner");
            $conn->commit();
            header("Location: shared-record.php?id=$record_id"); exit;
        }
    }
    // Delete Record
    elseif ($action === 'delete_record' && $is_owner) {
        $conn->query("DELETE FROM shared_records WHERE id = $record_id");
        header("Location: shared-records.php"); exit;
    }
}

// Fetch Expenses for List
$exp_stmt =$conn->prepare("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id = u.id WHERE e.shared_record_id = ? ORDER BY e.expense_date DESC, e.id DESC");
$exp_stmt->bind_param("i", $record_id);
$exp_stmt->execute();
$expenses =$exp_stmt->get_result();

// Fetch Dashboard Summary specifically for this Shared Record
$dash_stmt =$conn->prepare("SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS count FROM expenses WHERE shared_record_id = ?");
$dash_stmt->bind_param("i", $record_id);
$dash_stmt->execute();
$summary =$dash_stmt->get_result()->fetch_assoc();

// Fetch Category Data for Chart specifically for this Shared Record
$cat_stmt =$conn->prepare("SELECT category, SUM(amount) total FROM expenses WHERE shared_record_id = ? GROUP BY category ORDER BY total DESC");
$cat_stmt->bind_param("i", $record_id);
$cat_stmt->execute();
$cat_res =$cat_stmt->get_result();
$chart_labels = [];
$chart_values = [];
while($c =$cat_res->fetch_assoc()) {
    $chart_labels[] =$c['category'];
    $chart_values[] = (float)$c['total'];
}

// Fetch Collaborators
$col_stmt =$conn->prepare("SELECT c.*, u.username FROM collaborators c JOIN users u ON c.user_id = u.id WHERE c.record_id = ?");
$col_stmt->bind_param("i", $record_id);
$col_stmt->execute();
$collaborators =$col_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch pending invitations (High Priority)
$inv_stmt =$conn->prepare("SELECT c.id, s.name, c.role FROM collaborators c JOIN shared_records s ON c.record_id = s.id WHERE c.user_id = ? AND c.status = 'pending'");
$inv_stmt->bind_param("i", $user_id);
$inv_stmt->execute();
$invitations =$inv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unread activity (Lower Priority)
$act_stmt =$conn->prepare("SELECT id, workspace_name, message, created_at FROM activity_notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$act_stmt->bind_param("i", $user_id);$act_stmt->execute();
$activities =$act_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pending_count = count($invitations) + count($activities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($record['name']) ?> - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <!-- Include Chart.js & Tesseract.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>
                        <?= htmlspecialchars($record['name']) ?>
                        <span class="role-badge role-<?= strtolower($record['role']) ?>"><?= ucfirst($record['role']) ?></span>
                    </h1>
                    <p>Shared workspace</p>
                </div>
                <a href="shared-records.php" class="btn btn-secondary">All shared records</a>
            </header>

            <?php if($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" onclick="openTab('dashboard', this)">Dashboard</button>
                <button class="tab-btn" onclick="openTab('expenses', this)">Expenses</button>
                <?php if($is_editor): ?><button class="tab-btn" onclick="openTab('scanner', this)">Scan receipt</button><?php endif; ?>
                <button class="tab-btn" onclick="openTab('collaborators', this)">Collaborators</button>
                <?php if($is_owner): ?><button class="tab-btn" onclick="openTab('settings', this)">Settings</button><?php endif; ?>
            </div>

            <!-- DASHBOARD TAB -->
            <div id="dashboard" class="tab-content active">
                <?php $avg_amount = (int)$summary['count'] > 0 ? (float)$summary['total'] / (int)$summary['count'] : 0; ?>
                <section class="masthead" aria-label="Workspace summary">
                    <div class="masthead-top">
                        <p class="masthead-label">Workspace total</p>
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

                <div class="panel">
                    <h2>Spending by category</h2>
                    <?php if($summary['count'] > 0): ?>
                        <div class="breakdown">
                            <div class="chart-wrap"><canvas id="sharedCategoryChart" aria-label="Spending by category"></canvas></div>
                            <ul class="legend" id="sharedCategoryLegend"></ul>
                        </div>
                    <?php else: ?>
                        <div class="empty">No expenses recorded yet. Add the first one to see the breakdown.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- EXPENSES TAB -->
            <div id="expenses" class="tab-content panel">
                <?php if($is_editor): ?>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="add_expense">
                        <label>Title<input name="title" required placeholder="e.g. Server hosting"></label>
                        <label>Amount (₱)<input type="number" step="0.01" name="amount" required placeholder="0.00" inputmode="decimal"></label>
                        <label>Category
                            <div class="category-wrapper">
                                <select name="category" class="dynamic-category-select">
                                    <option>Operations & Office</option>
                                    <option>Cost of Goods Sold (COGS) / Inventory</option>
                                    <option>Marketing & Advertising</option>
                                    <option>Payroll & Contractor Fees</option>
                                    <option>Travel & Client Entertainment</option>
                                    <option>Professional Services & Legal</option>
                                    <option>Business Insurance & Taxes</option>
                                    <option>Other</option>
                                </select>
                                <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to personal categories" aria-label="Switch to personal categories">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                </button>
                            </div>
                        </label>
                        <label>Date<input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></label>
                        <button type="submit" class="btn">Add expense</button>
                    </form>
                <?php endif; ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Title</th><th>Category</th><th>Added by</th><th>Date</th><th class="num">Amount</th><?php if($is_editor): ?><th class="num"><span class="sr-only">Actions</span></th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                        <?php if($expenses->num_rows): while($e =$expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['title']) ?></td>
                                <td><?= htmlspecialchars($e['category']) ?></td>
                                <td><?= htmlspecialchars($e['username']) ?></td>
                                <td class="muted"><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
                                <td class="num">₱<?= number_format($e['amount'], 2) ?></td>
                                <?php if($is_editor): ?>
                                <td class="num">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="delete" onclick="return confirm('Delete this expense?')">Delete</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?= $is_editor ? '6' : '5' ?>" class="empty">No expenses recorded yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SCAN RECEIPT TAB -->
            <?php if($is_editor): ?>
            <div id="scanner" class="tab-content panel">
                <div class="scan-body">
                    <h2>Scan a workspace receipt</h2>
                    <p class="hint">Upload a photo of a receipt and we'll fill in the expense for you.</p>

                    <label>Receipt image
                        <input type="file" id="receipt" accept="image/*">
                    </label>
                    <button class="btn" id="scanBtn" type="button">Scan receipt</button>
                    <div id="status" class="status"></div>

                    <details>
                        <summary>View raw scanned text</summary>
                        <textarea id="ocrText" rows="4" readonly></textarea>
                    </details>

                    <!-- Pre-Filled Workspace Expense Modal (Opens Automatically After Scan) -->
                    <div id="parsedExpenseForm" class="modal-overlay" style="display: none;">
                        <div class="modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="parsedTitleHeading">
                            <div class="modal-head">
                                <h2 id="parsedTitleHeading">Review and save to workspace</h2>
                                <button type="button" class="modal-close" aria-label="Close" onclick="document.getElementById('parsedExpenseForm').style.display='none'">&times;</button>
                            </div>

                            <form method="post">
                                <input type="hidden" name="action" value="add_expense">

                                <label>Title
                                    <input id="parsedTitle" type="text" name="title" required placeholder="e.g. Server hosting">
                                </label>

                                <label>Amount (₱)
                                    <input id="parsedAmount" type="number" step="0.01" name="amount" required placeholder="0.00" inputmode="decimal">
                                </label>

                                <label>Category
                                    <div class="category-wrapper">
                                        <select name="category" class="dynamic-category-select">
                                            <option>Operations & Office</option>
                                            <option>Cost of Goods Sold (COGS) / Inventory</option>
                                            <option>Marketing & Advertising</option>
                                            <option>Payroll & Contractor Fees</option>
                                            <option>Travel & Client Entertainment</option>
                                            <option>Professional Services & Legal</option>
                                            <option>Business Insurance & Taxes</option>
                                            <option>Other</option>
                                        </select>
                                        <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to personal categories" aria-label="Switch to personal categories">
                                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        </button>
                                    </div>
                                </label>

                                <label>Date
                                    <input id="parsedDate" type="date" name="expense_date" required>
                                </label>

                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('parsedExpenseForm').style.display='none'">Cancel</button>
                                    <button type="submit" class="btn">Save expense</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- COLLABORATORS TAB -->
            <div id="collaborators" class="tab-content panel">
                <?php if($is_owner): ?>
                    <form method="post" class="inline-form invite-form">
                        <input type="hidden" name="action" value="invite">
                        <label>Invite by username<input type="text" name="username" required placeholder="Enter the exact username"></label>
                        <label>Role
                            <select name="role"><option value="editor">Editor</option><option value="viewer">Viewer</option></select>
                        </label>
                        <button type="submit" class="btn">Send invite</button>
                    </form>
                <?php endif; ?>

                <div class="collab-list">
                    <?php foreach($collaborators as$c): ?>
                        <div class="collab-row">
                            <div class="collab-info">
                                <strong><?= htmlspecialchars($c['username']) ?></strong>
                                <span class="role-badge sm role-<?= strtolower($c['role']) ?>"><?= ucfirst($c['role']) ?></span>
                                <?php if($c['status'] == 'pending') echo '<span class="pending-badge">Pending</span>'; ?>
                            </div>
                            <?php if($is_owner && $c['user_id'] !=$user_id): ?>
                                <div class="collab-actions">
                                    <form method="post">
                                        <input type="hidden" name="target_user_id" value="<?= $c['user_id'] ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <select name="new_role" aria-label="Role for <?= htmlspecialchars($c['username']) ?>" onchange="this.form.submit()">
                                            <option value="editor" <?= $c['role']=='editor'?'selected':'' ?>>Editor</option>
                                            <option value="viewer" <?= $c['role']=='viewer'?'selected':'' ?>>Viewer</option>
                                        </select>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="target_user_id" value="<?= $c['user_id'] ?>">
                                        <input type="hidden" name="action" value="remove_user">
                                        <button type="submit" class="delete" onclick="return confirm('Remove this user?')">Remove</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SETTINGS TAB -->
            <?php if($is_owner): ?>
            <div id="settings" class="tab-content panel">
                <form method="post" class="inline-form rename-form">
                    <input type="hidden" name="action" value="rename">
                    <label>Workspace name<input type="text" name="new_name" value="<?= htmlspecialchars($record['name']) ?>" required></label>
                    <button type="submit" class="btn">Save name</button>
                </form>

                <h3 class="section-title">Danger zone</h3>

                <div class="danger-grid">
                    <!-- Transfer Ownership Card -->
                    <div class="danger-card">
                        <h4>Transfer ownership</h4>
                        <p>Give complete control of this workspace to another member. You will become an editor.</p>
                        <form id="transferForm" method="post">
                            <input type="hidden" name="action" value="transfer">
                            <select name="new_owner_id" required aria-label="New owner">
                                <option value="">Select a collaborator...</option>
                                <?php foreach($collaborators as $c): if($c['user_id'] != $user_id &&$c['status'] == 'accepted'): ?>
                                    <option value="<?= $c['user_id'] ?>"><?= htmlspecialchars($c['username']) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-danger" onclick="confirmAction('transferForm', '<?= htmlspecialchars(addslashes($record['name'])) ?>', 'transfer ownership')">Transfer ownership</button>
                        </form>
                    </div>

                    <!-- Delete Workspace Card -->
                    <div class="danger-card">
                        <h4>Delete workspace</h4>
                        <p>Permanently delete this workspace and every expense in it. You can't undo this.</p>
                        <form id="deleteForm" method="post">
                            <input type="hidden" name="action" value="delete_record">
                            <button type="button" class="btn btn-danger" onclick="confirmAction('deleteForm', '<?= htmlspecialchars(addslashes($record['name'])) ?>', 'delete this workspace')">Delete workspace</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include __DIR__ . '/includes/modals.php'; ?>

    <?php if($is_owner): ?>
    <!-- Type-the-name confirmation for transfer / delete -->
    <div id="typeConfirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="typeConfirmTitle">
            <h2 id="typeConfirmTitle">Are you sure?</h2>
            <p id="typeConfirmText"></p>
            <label>Workspace name
                <input type="text" id="typeConfirmInput" autocomplete="off" spellcheck="false">
            </label>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('typeConfirmModal').style.display='none'">Cancel</button>
                <button type="button" class="btn btn-danger" id="typeConfirmBtn" disabled>Confirm</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scanner Script Inclusion -->
    <script src="js/receipt-scanner.js"></script>

    <script>
        function openTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        // Transfer / delete: the owner has to type the workspace name before the button unlocks.
        let typeConfirmFormId = null;
        let typeConfirmName = '';
        function confirmAction(formId, recordName, actionText) {
            const modal = document.getElementById('typeConfirmModal');
            const input = document.getElementById('typeConfirmInput');
            const btn = document.getElementById('typeConfirmBtn');
            const isDelete = actionText.indexOf('delete') === 0;
            typeConfirmFormId = formId;
            typeConfirmName = recordName;

            document.getElementById('typeConfirmTitle').textContent = isDelete ? 'Delete this workspace?' : 'Transfer ownership?';
            document.getElementById('typeConfirmText').textContent = 'To ' + actionText + ', type the workspace name exactly: ' + recordName;
            btn.textContent = isDelete ? 'Delete workspace' : 'Transfer ownership';
            input.value = '';
            btn.disabled = true;
            modal.style.display = 'flex';
            setTimeout(() => input.focus(), 50);
        }
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('typeConfirmInput');
            const btn = document.getElementById('typeConfirmBtn');
            if (!input || !btn) return;
            input.addEventListener('input', () => { btn.disabled = input.value !== typeConfirmName; });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); if (!btn.disabled) btn.click(); }
            });
            btn.addEventListener('click', () => {
                if (input.value === typeConfirmName && typeConfirmFormId) {
                    document.getElementById(typeConfirmFormId).submit();
                }
            });
        });

        // Workspace doughnut chart
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('sharedCategoryChart');
            if (ctx && <?= (int)$summary['count'] ?> > 0) {
                ecxDoughnut(
                    ctx,
                    <?= json_encode($chart_labels) ?>,
                    <?= json_encode($chart_values) ?>,
                    document.getElementById('sharedCategoryLegend')
                );
            }
        });
    </script>
    <script>
        const personalCategories = ["Housing & Living", "Utilities", "Food and Dining", "Transportation", "Health & Wellness", "Personal Care & Household", "Entertainment & Lifestyle", "Financial & Obligations", "Other"];
        const businessCategories = ["Operations & Office", "Cost of Goods Sold (COGS) / Inventory", "Marketing & Advertising", "Payroll & Contractor Fees", "Travel & Client Entertainment", "Professional Services & Legal", "Business Insurance & Taxes", "Other"];

        const iconHouse = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>`;
        const iconSuitcase = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>`;

        let isBusiness = true; // Starts in Business mode for collaborative workspaces

        function toggleCategoryType() {
            isBusiness = !isBusiness;
            const selects = document.querySelectorAll('.dynamic-category-select');
            const btns = document.querySelectorAll('.category-toggle-btn');

            const options = isBusiness ? businessCategories : personalCategories;
            const optionsHTML = options.map(opt => `<option>${opt}</option>`).join('');

            selects.forEach(select => select.innerHTML = optionsHTML);
            btns.forEach(btn => {
                btn.innerHTML = isBusiness ? iconSuitcase : iconHouse;
                btn.title = isBusiness ? "Switch to personal categories" : "Switch to business categories";
                btn.setAttribute('aria-label', btn.title);
            });
        }
    </script>
    <script src="js/global-ui.js"></script>
</body>
</html>
