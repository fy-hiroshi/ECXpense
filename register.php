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
</head>
<body class="auth-layout">
    <div class="auth-card">
        <div class="brand auth-brand">EC<span>Xpense</span></div>
        <h1>Create an Account</h1>
        <p>Start tracking your expenses securely.</p>
        
        <?php if ($message): ?>
            <div class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <label style="text-align: left;">Username
                <input type="text" name="username" required placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </label>
            <label style="text-align: left;">Password
                <input type="password" name="password" required placeholder="Create a password">
            </label>
            <label style="text-align: left;">Verify Password
                <input type="password" name="verify_password" required placeholder="Confirm your password">
            </label>
            <button class="btn" type="submit">Sign Up</button>
        </form>
        
        <div class="auth-links">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>
    <script src="js/global-ui.js"></script>
</body>
</html>