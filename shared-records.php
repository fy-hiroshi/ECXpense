<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once "config/database.php";
$user_id = $_SESSION['user_id'];
$message = "";

// Create New Shared Record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_record'])) {
    $name = trim($_POST['record_name']);
    if ($name) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO shared_records (name, owner_id) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $user_id);
            $stmt->execute();
            $new_record_id = $conn->insert_id;
            
            $stmt2 = $conn->prepare("INSERT INTO collaborators (record_id, user_id, role, status) VALUES (?, ?, 'owner', 'accepted')");
            $stmt2->bind_param("ii", $new_record_id, $user_id);
            $stmt2->execute();
            
            $conn->commit();
            header("Location: shared-record.php?id=" . $new_record_id); exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating record.";
        }
    }
}

// Fetch user's shared records
$stmt = $conn->prepare("SELECT s.id, s.name, c.role FROM shared_records s JOIN collaborators c ON s.id = c.record_id WHERE c.user_id = ? AND c.status = 'accepted' ORDER BY s.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$records = $stmt->get_result();

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
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared records - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main">
            <header class="topbar">
                <div><h1>Shared records</h1><p>Keep track of expenses with your team.</p></div>
            </header>
            
            <?php if($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

            <section class="grid">
                <div class="panel">
                    <h2>Your shared records</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Your role</th><th class="num"><span class="sr-only">Open</span></th></tr>
                            </thead>
                            <tbody>
                            <?php if($records->num_rows): while($row = $records->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><span class="role-badge role-<?= strtolower($row['role']) ?>"><?= ucfirst($row['role']) ?></span></td>
                                    <td class="num"><a href="shared-record.php?id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm">Open</a></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" class="empty">No shared records yet. Create one to start splitting the work.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <h2>Create a shared record</h2>
                    <form method="post">
                        <label>Name
                            <input type="text" name="record_name" required placeholder="e.g. Project Alpha expenses">
                        </label>
                        <p class="hint">You can invite people by username once it's created.</p>
                        <button type="submit" name="create_record" class="btn">Create record</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/includes/modals.php'; ?>
    <script src="js/global-ui.js"></script>
</body>
</html>
