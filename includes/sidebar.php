<?php
/**
 * Shared sidebar for every logged-in page.
 *
 * The including page must already have:
 *   $conn          - mysqli connection
 *   $user_id       - id of the logged-in user
 *   $pending_count - number of pending invitations + unread activity (notification badge)
 *
 * The "Shared Records" item is expandable: the chevron reveals a scrollable list
 * of every record the user has accepted access to. Open/closed state is remembered
 * in localStorage (see js/ui-init.js and js/global-ui.js).
 */
$sb_page = basename($_SERVER['SCRIPT_NAME']);
$sb_current_record = ($sb_page === 'shared-record.php') ? (int)($_GET['id'] ?? 0) : 0;
$pending_count = $pending_count ?? 0;

// Shared records the user belongs to (newest first, same order as the Shared Records page)
$sb_records = [];
$sb_stmt = $conn->prepare(
    "SELECT s.id, s.name
       FROM shared_records s
       JOIN collaborators c ON s.id = c.record_id
      WHERE c.user_id = ? AND c.status = 'accepted'
   ORDER BY s.created_at DESC, s.id DESC"
);
if ($sb_stmt) {
    $sb_stmt->bind_param("i", $user_id);
    $sb_stmt->execute();
    $sb_records = $sb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sb_stmt->close();
}
?>
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
                <a href="index.php"<?= $sb_page === 'index.php' ? ' class="active"' : '' ?>>Dashboard</a>
                <a href="scan-receipt.php"<?= $sb_page === 'scan-receipt.php' ? ' class="active"' : '' ?>>Scan Receipt</a>
                <a href="expenses.php"<?= $sb_page === 'expenses.php' ? ' class="active"' : '' ?>>Expense Records</a>

                <!-- Shared Records (expandable) -->
                <div class="nav-group">
                    <div class="nav-row">
                        <a href="shared-records.php" class="nav-main<?= $sb_page === 'shared-records.php' ? ' active' : '' ?>">Shared Records</a>
                        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="sharedNavList"
                                title="Show or hide your shared records" aria-label="Show or hide your shared records">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                    </div>
                    <div class="nav-sub" id="sharedNavList">
                        <?php if ($sb_records): ?>
                            <?php foreach ($sb_records as $sb_r): ?>
                                <a href="shared-record.php?id=<?= (int)$sb_r['id'] ?>"
                                   class="nav-sub-link<?= (int)$sb_r['id'] === $sb_current_record ? ' active' : '' ?>"
                                   title="<?= htmlspecialchars($sb_r['name']) ?>"><?= htmlspecialchars($sb_r['name']) ?></a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="nav-sub-empty">No shared records yet</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Logout / Theme / Notifications at Bottom -->
                <div class="sidebar-bottom">
                    <!-- Logout Button (Left) -->
                    <button class="logout-icon-btn" onclick="document.getElementById('logoutModal').style.display='flex'; return false;" title="Log Out">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </button>

                    <!-- Light / Dark Mode Toggle (Middle) -->
                    <button type="button" class="theme-toggle" id="themeToggle" title="Switch to dark mode" aria-label="Switch to dark mode">
                        <svg class="icon-moon" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                        <svg class="icon-sun" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
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
