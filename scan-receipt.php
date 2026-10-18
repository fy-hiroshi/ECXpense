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
    <title>Scan receipt - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div>
                    <h1>Scan receipt</h1>
                    <p>Upload a photo of a receipt and we'll fill in the expense for you.</p>
                </div>
            </header>

            <div class="panel panel-narrow">
                <label>Receipt image
                    <input type="file" id="receipt" accept="image/*">
                </label>
                <button class="btn" id="scanBtn" type="button">Scan receipt</button>
                <div id="status" class="status"></div>

                <details>
                    <summary>View raw scanned text</summary>
                    <textarea id="ocrText" rows="6" placeholder="Raw text will appear here..." readonly></textarea>
                </details>

                <!-- Pre-Filled Add Expense Modal (Opens Automatically After Scan) -->
                <div id="parsedExpenseForm" class="modal-overlay" style="display: none;">
                    <div class="modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="parsedTitleHeading">
                        <div class="modal-head">
                            <h2 id="parsedTitleHeading">Review and save expense</h2>
                            <button type="button" class="modal-close" aria-label="Close" onclick="document.getElementById('parsedExpenseForm').style.display='none'">&times;</button>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="add_expense">

                            <label>Title
                                <input id="parsedTitle" type="text" name="title" required placeholder="e.g. Lunch">
                            </label>

                            <label>Amount (₱)
                                <input id="parsedAmount" type="number" step="0.01" name="amount" required placeholder="0.00" inputmode="decimal">
                            </label>

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
                                    <button type="button" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to business categories" aria-label="Switch to business categories">
                                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
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
        </main>
    </div>

    <?php include __DIR__ . '/includes/modals.php'; ?>

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
                btn.title = isBusiness ? "Switch to personal categories" : "Switch to business categories";
                btn.setAttribute("aria-label", btn.title);
            });
        }
    </script>
    <script src="js/global-ui.js"></script>
</body>
</html>