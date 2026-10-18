<?php
/**
 * Logout and notification modals, plus the live badge poller.
 * Shared by every logged-in page.
 *
 * The including page must already have:
 *   $invitations   - pending invitations (rows with id, name, role)
 *   $activities    - unread activity (rows with id, workspace_name, message)
 *   $pending_count - count($invitations) + count($activities)
 */
$invitations   = $invitations ?? [];
$activities    = $activities ?? [];
$pending_count = $pending_count ?? (count($invitations) + count($activities));
?>
    <!-- Logout Modal -->
    <div id="logoutModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
            <h2 id="logoutTitle">Ready to leave?</h2>
            <p>You'll need to log in again to see your expenses.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
                <a href="logout.php" class="btn btn-danger">Log out</a>
            </div>
        </div>
    </div>

    <!-- Notifications Modal (invitations first, then activity) -->
    <div id="notifModal" class="modal-overlay" style="display: none;">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
            <h2 id="notifTitle">Notifications</h2>

            <?php if (count($invitations) > 0): ?>
                <h4 class="modal-section">Pending invitations</h4>
                <?php foreach ($invitations as $n): ?>
                    <div class="notif-item is-invite">
                        <div>
                            <strong><?= htmlspecialchars($n['name']) ?></strong>
                            <small>Role: <?= ucfirst($n['role']) ?></small>
                        </div>
                        <div class="notif-actions">
                            <a href="notification-action.php?id=<?= $n['id'] ?>&action=accept" class="btn btn-success">Accept</a>
                            <a href="notification-action.php?id=<?= $n['id'] ?>&action=deny" class="btn btn-danger">Deny</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($activities) > 0): ?>
                <h4 class="modal-section<?= count($invitations) > 0 ? ' spaced' : '' ?>">Recent activity</h4>
                <?php foreach ($activities as $a): ?>
                    <div class="notif-item">
                        <div>
                            <strong><?= htmlspecialchars($a['workspace_name']) ?></strong>
                            <small><?= htmlspecialchars($a['message']) ?></small>
                        </div>
                        <div class="notif-actions">
                            <a href="notification-action.php?act_id=<?= $a['id'] ?>&action=dismiss" class="btn btn-secondary">Dismiss</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($pending_count == 0): ?>
                <div class="notif-empty">
                    <svg viewBox="0 0 24 24" width="40" height="40" stroke="currentColor" stroke-width="1.25" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <p>You're all caught up.</p>
                </div>
            <?php endif; ?>

            <button type="button" class="btn btn-secondary btn-block" onclick="document.getElementById('notifModal').style.display='none'">Close</button>
        </div>
    </div>

    <!-- Keeps the notification badge current without a page reload -->
    <script>
        setInterval(() => {
            fetch('api/check-notification.php')
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
                        dot.textContent = data.count;
                    } else if (dot) {
                        dot.remove();
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }, 10000);
    </script>
