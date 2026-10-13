<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once "config/database.php";
$user_id =$_SESSION['user_id'];

// Handle Saving the Parsed Expense
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) &&$_POST['action'] === 'add_expense') {
    $title = trim($_POST['title']); 
    $amount = (float)$_POST['amount']; 
    $category =$_POST['category']; 
    $date =$_POST['expense_date'];
    
    if ($title &&$amount > 0) {
        $ins =$conn->prepare("INSERT INTO expenses (user_id, title, amount, category, expense_date) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("isdss", $user_id, $title,$amount, $category,$date);
        if($ins->execute()) {
            header("Location: expenses.php?added=1"); 
            exit;
        }
    }
}

// Fetch pending invitations for the sidebar
$notif_stmt = $conn->prepare("SELECT c.id, s.name, c.role FROM collaborators c JOIN shared_records s ON c.record_id = s.id WHERE c.user_id = ? AND c.status = 'pending'");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$invitations = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unread activity
$act_stmt =$conn->prepare("SELECT id, workspace_name, message, created_at FROM activity_notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$act_stmt->bind_param("i", $user_id);
$act_stmt->execute();$activities = $act_stmt->get_result()->fetch_all(MYSQLI_ASSOC);$pending_count = count($invitations) + count($activities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Receipt - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
</head>
<body>
    <div class="layout">
       <aside class="sidebar">
            <div class="mobile-header">
                <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="28" height="28"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
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
                <a class="active" href="scan-receipt.php">Scan Receipt</a>
                <a href="expenses.php">Expense Records</a>
               
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
                    <h1>Scan Receipt</h1>
                    <p>Upload a receipt image to auto-fill an expense.</p>
                </div>
            </header>
            
            <div class="panel">
                <label>Receipt Image
                    <input type="file" id="receipt" accept="image/*">
                </label>
                <button class="btn" id="scanBtn" type="button" style="margin-top: 20px;">Scan Receipt</button>
                <div id="status" class="status" style="margin-top: 16px; margin-bottom: 10px;"></div>
                
                <details style="margin-top: 10px; color: #64748b; font-size: 14px; cursor: pointer;">
                    <summary>View Raw Scanned Text</summary>
                    <textarea id="ocrText" rows="6" placeholder="Raw text will appear here..." style="margin-top: 10px; width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;" readonly></textarea>
                </details>

                <!-- Hidden Form that appears after scanning -->
                <form id="parsedExpenseForm" method="post" style="display: none; margin-top: 30px; padding-top: 24px; border-top: 2px solid #e2e8f0;">
                    <h3 style="margin-top: 0; color: #0f172a; margin-bottom: 20px;">Review & Save</h3>
                    <input type="hidden" name="action" value="add_expense">
                    
                    <div class="inline-form">
                        <label>Title<input id="parsedTitle" name="title" required></label>
                        <label>Amount<input id="parsedAmount" type="number" step="0.01" name="amount" required></label>
                        <label>Category
                            <div class="category-wrapper">
                                <select name="category" class="dynamic-category-select">
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
                                <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to Business Categories">
                                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                </button>
                            </div>
                        </label>
                        <label>Date<input id="parsedDate" type="date" name="expense_date" required></label>
                        <button type="submit" class="btn btn-success">Save Expense</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Modals (Logout & Notif) remain unchanged from your existing setup, I am putting placeholder comments to keep it clean -->
    <div id="logoutModal" class="modal-overlay" style="display: none;">
        <div class="modal-box"><h2>Ready to leave?</h2><div class="modal-actions"><button class="btn btn-secondary" onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button><a href="logout.php" class="btn btn-danger">Log Out</a></div></div>
    </div>
    
    <div id="notifModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" style="max-height: 85vh; overflow-y: auto;">
            <h2>Notifications</h2>
            <!-- Place your priority sorted notification HTML loops here just like in the previous step -->
            <button class="btn btn-secondary mt-auto" style="width:100%; margin-top:24px!important;" onclick="document.getElementById('notifModal').style.display='none'">Close</button>
        </div>
    </div>

    <script src="js/receipt-scanner.js"></script>

    <script>
        // Universal Category Toggle Script
        const personalCategories = ["Housing & Living", "Utilities", "Food and Dining", "Transportation", "Health & Wellness", "Personal Care & Household", "Entertainment & Lifestyle", "Financial & Obligations", "Other"];
        const businessCategories = ["Operations & Office", "Cost of Goods Sold (COGS) / Inventory", "Marketing & Advertising", "Payroll & Contractor Fees", "Travel & Client Entertainment", "Professional Services & Legal", "Business Insurance & Taxes", "Other"];
        
        const iconHouse = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>`;
        const iconSuitcase = `<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>`;
        
        let isBusiness = false; // Starts in Personal mode

        function toggleCategoryType() {
            isBusiness = !isBusiness;
            const selects = document.querySelectorAll('.dynamic-category-select');
            const btns = document.querySelectorAll('.category-toggle-btn');
            
            const options = isBusiness ? businessCategories : personalCategories;
            const optionsHTML = options.map(opt => `<option>${opt}</option>`).join('');
            
            selects.forEach(select => select.innerHTML = optionsHTML);
            btns.forEach(btn => {
                btn.innerHTML = isBusiness ? iconSuitcase : iconHouse;
                btn.title = isBusiness ? "Switch to Personal" : "Switch to Business";
            });
        }
    </script>
    <script src="js/global-ui.js"></script>
</body>
</html>