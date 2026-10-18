<?php
session_start();
require_once "config/database.php";
$message = "";
$messageType = "alert";

if (isset($_GET['registered'])) {
    $message = "Registration successful! Please log in.";
    $messageType = "alert success";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verify the entered password matches the stored hash
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['welcome'] = 1; // the dashboard plays its welcome sequence once
                header("Location: index.php");
                exit;
            } else {
                $message = "Invalid password.";
            }
        } else {
            $message = "User not found.";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body class="auth-layout">
    <aside class="auth-visual" aria-hidden="true">
        <div class="brand">EC<span>Xpense</span></div>
        <div class="auth-visual-copy">
            <p class="auth-statement">Know where every peso went.</p>
            <p class="auth-sub">Track personal spending, share records with your team, and let receipts fill in the details for you.</p>
        </div>
    </aside>
    <main class="auth-panel">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p>Log in to see your expenses.</p>

            <?php if ($message): ?>
                <div class="<?= $messageType ?>" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>Username
                    <input type="text" name="username" required autofocus autocomplete="username" placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </label>
                <label>Password
                    <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                </label>
                <button class="btn" type="submit">Log in</button>
            </form>

            <div class="auth-links">
                New to ECXpense? <a href="register.php">Create an account</a>
            </div>
        </div>
    </main>
    <script src="js/global-ui.js"></script>
</body>
</html>