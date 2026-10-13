<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
$message = "";
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $amount = (float)($_POST["amount"] ?? 0);
    $category = trim($_POST["category"] ?? "Other");
    $date = $_POST["expense_date"] ?? date("Y-m-d");

    if ($title && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, title, amount, category, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $user_id, $title, $amount, $category, $date);
        $stmt->execute(); 
        $stmt->close();
        header("Location: expenses.php?added=1"); 
        exit;
    } else {
        $message = "Please enter a title and valid amount.";
    }
}

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
    <title>Add Expense - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="mobile-header">
                <!-- Hamburger Icon -->
                <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="brand-container">
                    <div class="brand">EC<span>Xpense</span></div>
                    <p class="tagline">Smart Expense Tracker</p>
                </div>
            </div>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="shared-records.php">Shared Records</a>
                <a href="add-expense.php">Add Expense</a>
                <a href="scan-receipt.php">Scan Receipt</a>
                <a href="expenses.php">Expense Records</a>
                
                
                <!-- Mail Notification Icon at Bottom -->
                <div class="sidebar-bottom">
                    <!-- Logout Button (Left) -->
                    <button class="logout-icon-btn" onclick="document.getElementById('logoutModal').style.display='flex'; return false;" title="Log Out">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </button>

                    <!-- Notifications Button (Right) -->
                    <button class="notif-icon-btn" onclick="document.getElementById('notifModal').style.display='flex'; return false;" title="Invitations & Notifications">
                        <svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <?php if($pending_count > 0) echo "<span class='notif-dot'>$pending_count</span>"; ?>
                    </button>
                </div>
            </nav>
        </aside>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Add Expense</h1>
                    <p>Record a new transaction.</p>
                </div>
            </header>
            
            <div class="panel form-panel">
                <?php if($message): ?>
                    <div class="alert"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <label>Expense Title
                        <input name="title" required placeholder="e.g. Lunch">
                    </label>
                    <label>Amount
                        <input type="number" step="0.01" min="0" name="amount" id="amount" required placeholder="0.00">
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
                    <button class="btn" type="submit">Save Expense</button>
                </form>
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
    <script>
        const personalCategories = ["Housing & Living", "Utilities", "Food and Dining", "Transportation", "Health & Wellness", "Personal Care & Household", "Entertainment & Lifestyle", "Financial & Obligations", "Other"];
        const businessCategories = ["Operations & Office", "Cost of Goods Sold (COGS) / Inventory", "Marketing & Advertising", "Payroll & Contractor Fees", "Travel & Client Entertainment", "Professional Services & Legal", "Business Insurance & Taxes", "Other"];
        
        const iconHouse = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>`;
        const iconSuitcase = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>`;
        
        let isBusiness = false; // Starts in Personal mode

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