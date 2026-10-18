<?php
require_once "config/database.php";
$message = "";
$messageType = "alert";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $verify_password = $_POST["verify_password"] ?? "";

    if ($username && $password && $verify_password) {
        // Check if passwords match
        if ($password !== $verify_password) {
            $message = "Passwords do not match. Please try again.";
        } else {
            // Hash the password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed_password);
            
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $message = "Username already exists. Please choose another.";
            }
            $stmt->close();
        }
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
    <title>Register - ECXpense</title>
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
            <h1>Create your account</h1>
            <p>Start tracking your expenses in a minute.</p>

            <?php if ($message): ?>
                <div class="<?= $messageType ?>" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>Username
                    <input type="text" name="username" required autofocus autocomplete="username" placeholder="Choose a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </label>
                <label>Password
                    <input type="password" name="password" required autocomplete="new-password" placeholder="Create a password">
                </label>
                <label>Confirm password
                    <input type="password" name="verify_password" required autocomplete="new-password" placeholder="Enter the password again">
                </label>
                <button class="btn" type="submit">Create account</button>
            </form>

            <div class="auth-links">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </main>
    <script src="js/global-ui.js"></script>
</body>
</html>