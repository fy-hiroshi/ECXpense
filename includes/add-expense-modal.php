<?php
/**
 * "Add expense" modal for personal expenses (dashboard and expense records).
 * Posts back to the including page, which handles action=add_expense.
 */
?>
    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal-overlay" style="display: none;">
        <div class="modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="addExpenseTitle">
            <div class="modal-head">
                <h2 id="addExpenseTitle">Add expense</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="document.getElementById('addExpenseModal').style.display='none'">&times;</button>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="add_expense">

                <label>Title
                    <input type="text" name="title" required placeholder="e.g. Lunch">
                </label>

                <label>Amount (₱)
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" inputmode="decimal">
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
                        <button type="button" id="categoryToggleBtn" onclick="toggleCategoryType()" class="category-toggle-btn" title="Switch to business categories" aria-label="Switch to business categories">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        </button>
                    </div>
                </label>

                <label>Date
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </label>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addExpenseModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn">Save expense</button>
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
            const label = isBusiness ? "Switch to personal categories" : "Switch to business categories";
            btn.title = label;
            btn.setAttribute('aria-label', label);
        }
    </script>
