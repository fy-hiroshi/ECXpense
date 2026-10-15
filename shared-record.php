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
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1 style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <?= htmlspecialchars($record['name']) ?> 
                        <span class="role-badge role-<?= strtolower($record['role']) ?>" style="font-size: 13px; margin: 0; padding: 4px 12px; line-height: 1.2;">
                            <?= ucfirst($record['role']) ?>
                        </span>
                    </h1>
                    <p>Collaborative Workspace</p>
                </div>
                <a href="shared-records.php" class="btn btn-secondary" style="border: 1px solid var(--border-strong);">Back to Spaces</a>
            </header>
            
            <?php if($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" onclick="openTab('dashboard', this)">Dashboard</button>
                <button class="tab-btn" onclick="openTab('expenses', this)">Expenses</button>
                <?php if($is_editor): ?><button class="tab-btn" onclick="openTab('scanner', this)">Scan Receipt</button><?php endif; ?>
                <button class="tab-btn" onclick="openTab('collaborators', this)">Collaborators</button>
                <?php if($is_owner): ?><button class="tab-btn" onclick="openTab('settings', this)">Settings</button><?php endif; ?>
            </div>

            <!-- DASHBOARD TAB -->
            <div id="dashboard" class="tab-content active panel">
                <section class="cards" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 30px;">
                    <div class="card" style="box-shadow: none; background: var(--surface-2);">
                        <div class="label">Total Workspace Expenses</div>
                        <div class="value">₱<?= number_format((float)$summary['total'], 2) ?></div>
                    </div>
                    <div class="card" style="box-shadow: none; background: var(--surface-2);">
                        <div class="label">Total Transactions</div>
                        <div class="value"><?= (int)$summary['count'] ?></div>
                    </div>
                </section>
                
                <?php if($summary['count'] > 0): ?>
                <div style="max-width: 450px; margin: 0 auto; padding: 20px;">
                    <h3 style="text-align: center; color: var(--heading); margin-bottom: 24px;">Spending by Category</h3>
                    <canvas id="sharedCategoryChart"></canvas>
                </div>
                <?php else: ?>
                    <div class="empty">No expenses recorded yet. Add your first expense to see the chart!</div>
                <?php endif; ?>
            </div>

            <!-- EXPENSES TAB -->
            <div id="expenses" class="tab-content panel">
                <?php if($is_editor): ?>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="add_expense">
                        <label>Title<input name="title" required placeholder="e.g. Server Hosting"></label>
                        <label>Amount<input type="number" step="0.01" name="amount" required placeholder="0.00"></label>
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
                                <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to Personal Categories">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                </button>
                            </div>
                        </label>
                        <label>Date<input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></label>
                        <button type="submit" class="btn">Add Expense</button>
                    </form>
                <?php endif; ?>

                <div class="table-wrap">
                    <table>
                        <tr><th>Added By</th><th>Title</th><th>Category</th><th>Amount</th><th>Date</th><?php if($is_editor): ?><th>Action</th><?php endif; ?></tr>
                        <?php if($expenses->num_rows): while($e =$expenses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($e['username']) ?></strong></td>
                                <td><?= htmlspecialchars($e['title']) ?></td>
                                <td><?= htmlspecialchars($e['category']) ?></td>
                                <td>₱<?= number_format($e['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($e['expense_date']) ?></td>
                                <?php if($is_editor): ?>
                                <td>
                                    <form method="post" style="margin:0; display:inline;">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                                        <button type="submit" class="delete" style="border:none; background:transparent; cursor:pointer;" onclick="return confirm('Delete this?')">Delete</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?= $is_editor ? '6' : '5' ?>" class="empty">No expenses recorded yet.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- SCAN RECEIPT TAB -->
            <?php if($is_editor): ?>
            <div id="scanner" class="tab-content panel">
                <div class="form-panel" style="max-width: 100%; margin: 0; padding: 10px; border:none; box-shadow:none;">
                    <h2 style="margin-top: 0; margin-bottom: 8px;">Scan Workspace Receipt</h2>
                    <p class="hint" style="margin-bottom: 24px;">Upload a receipt to automatically parse its details.</p>
                    
                    <label>Receipt Image
                        <input type="file" id="receipt" accept="image/*">
                    </label>
                    <button class="btn" id="scanBtn" type="button" style="margin-top: 20px;">Extract Details</button>
                    <div id="status" class="status" style="margin-top: 16px; margin-bottom: 10px;"></div>
                    
                    <details style="margin-top: 10px; color: var(--muted); font-size: 14px; cursor: pointer;">
                        <summary>View Raw Scanned Text</summary>
                        <textarea id="ocrText" rows="4" style="margin-top: 10px; width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px;" readonly></textarea>
                    </details>

                    <!-- Pre-Filled Workspace Expense Modal (Opens Automatically After Scan) -->
                    <div id="parsedExpenseForm" class="modal-overlay" style="display: none;">
                        <div class="modal-box" style="max-width: 480px; width: 100%; text-align: left;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h2 style="margin: 0;">Review & Save to Workspace</h2>
                                <button type="button" onclick="document.getElementById('parsedExpenseForm').style.display='none'" style="background: transparent; border: none; font-size: 24px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 0;">&times;</button>
                            </div>

                            <form method="post" style="display: flex; flex-direction: column; gap: 16px;">
                                <input type="hidden" name="action" value="add_expense">
                                
                                <label>Expense Title
                                    <input id="parsedTitle" type="text" name="title" required placeholder="e.g. Server Hosting">
                                </label>

                                <label>Amount
                                    <input id="parsedAmount" type="number" step="0.01" name="amount" required placeholder="0.00">
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
                                        <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to Personal Categories">
                                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        </button>
                                    </div>
                                </label>

                                <label>Date
                                    <input id="parsedDate" type="date" name="expense_date" required>
                                </label>

                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                                    <button type="submit" class="btn" style="width: 100%; margin: 0;">Save Expense</button>
                                    <button type="button" class="btn btn-secondary" style="width: 100%; margin: 0;" onclick="document.getElementById('parsedExpenseForm').style.display='none'">Cancel</button>
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
                        <label>Invite by Username<input type="text" name="username" required placeholder="Enter exact username"></label>
                        <label>Role
                            <select name="role"><option value="editor">Editor</option><option value="viewer">Viewer</option></select>
                        </label>
                        <button type="submit" class="btn">Send Invite</button>
                    </form>
                <?php endif; ?>

                <div class="collab-list">
                    <?php foreach($collaborators as$c): ?>
                        <div class="collab-row">
                            <div class="collab-info">
                                <strong><?= htmlspecialchars($c['username']) ?></strong> 
                                <span class="role-badge role-<?= strtolower($c['role']) ?>" style="font-size:10px; padding:2px 8px; margin-left:8px;"><?= ucfirst($c['role']) ?></span>
                                <?php if($c['status'] == 'pending') echo "<span style='background:#fef08a; color:#854d0e; font-size:10px; padding:2px 8px; border-radius:12px; margin-left:8px;'>Pending</span>"; ?>
                            </div>
                            <?php if($is_owner && $c['user_id'] !=$user_id): ?>
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <form method="post" style="display:flex; flex-direction:row; margin:0;">
                                        <input type="hidden" name="target_user_id" value="<?= $c['user_id'] ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <select name="new_role" onchange="this.form.submit()" style="padding: 6px 12px; height:auto;">
                                            <option value="editor" <?= $c['role']=='editor'?'selected':'' ?>>Editor</option>
                                            <option value="viewer" <?= $c['role']=='viewer'?'selected':'' ?>>Viewer</option>
                                        </select>
                                    </form>
                                    <form method="post" style="margin:0; display:flex;">
                                        <input type="hidden" name="target_user_id" value="<?= $c['user_id'] ?>">
                                        <input type="hidden" name="action" value="remove_user">
                                        <button type="submit" class="delete" style="border:none; background:transparent; cursor:pointer;" onclick="return confirm('Remove this user?')">Remove</button>
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
                    <label>Workspace Name<input type="text" name="new_name" value="<?= htmlspecialchars($record['name']) ?>" required></label>
                    <button type="submit" class="btn">Save Name</button>
                </form>

                <hr style="border:0; border-top:1px solid var(--border); margin: 30px 0;">
                
                <h3 style="color: #dc2626; margin-top: 0; margin-bottom: 20px;">Danger Zone</h3>
                
                <div class="danger-grid">
                    <!-- Transfer Ownership Card -->
                    <div class="danger-card">
                        <h4>Transfer Ownership</h4>
                        <p>Give complete control of this workspace to another member. You will be demoted to an Editor.</p>
                        <form id="transferForm" method="post" style="display:flex; flex-direction:column; gap:12px; margin:0;">
                            <input type="hidden" name="action" value="transfer">
                            <select name="new_owner_id" required style="border-color:#fca5a5;">
                                <option value="">Select current collaborator...</option>
                                <?php foreach($collaborators as $c): if($c['user_id'] != $user_id &&$c['status'] == 'accepted'): ?>
                                    <option value="<?= $c['user_id'] ?>"><?= htmlspecialchars($c['username']) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-danger" onclick="confirmAction('transferForm', '<?= htmlspecialchars(addslashes($record['name'])) ?>', 'transfer ownership')">Transfer Ownership</button>
                        </form>
                    </div>

                    <!-- Delete Workspace Card -->
                    <div class="danger-card">
                        <h4>Delete Workspace</h4>
                        <p>Permanently delete this workspace and all associated expense data. This action cannot be undone.</p>
                        <form id="deleteForm" method="post" style="margin:0; margin-top:auto;">
                            <input type="hidden" name="action" value="delete_record">
                            <button type="button" class="btn btn-danger" style="width:100%;" onclick="confirmAction('deleteForm', '<?= htmlspecialchars(addslashes($record['name'])) ?>', 'delete this workspace')">Delete Entire Workspace</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
                <?php foreach($invitations as$n): ?>
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
                <?php foreach($activities as$a): ?>
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

    <!-- Scanner Script Inclusion -->
    <script src="js/receipt-scanner.js"></script>

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

    <script>
        function openTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        function confirmAction(formId, recordName, actionText) {
            let input = prompt(`To ${actionText}, please type the workspace name exactly: "${recordName}"`);
            if (input === recordName) {
                document.getElementById(formId).submit();
            } else if (input !== null) {
                alert("The name did not match. Action cancelled.");
            }
        }

        // Initialize Workspace Doughnut Chart
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('sharedCategoryChart');
            if (ctx && <?= $summary['count'] ?> > 0) {
                const colors = ecxThemeColors();
                const chart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($chart_labels) ?>,
                        datasets: [{
                            data: <?= json_encode($chart_values) ?>,
                            backgroundColor: ecxChartColors(<?= count($chart_values) ?>),
                            borderColor: colors.surface
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom', labels: { color: colors.text } } }
                    }
                });
                // Re-color the chart when the light/dark toggle is used.
                window.addEventListener('themechange', () => {
                    const c = ecxThemeColors();
                    chart.data.datasets[0].borderColor = c.surface;
                    chart.options.plugins.legend.labels.color = c.text;
                    chart.update();
                });
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
                btn.title = isBusiness ? "Switch to Personal Categories" : "Switch to Business Categories";
            });
        }
    </script>
    <script src="js/global-ui.js"></script>
</body>
</html>