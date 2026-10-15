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
    <title>Login - ECXpense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/ui-init.js"></script>
</head>
<body class="auth-layout">
    <div class="auth-card">
        <div class="brand auth-brand">EC<span>Xpense</span></div>
        <h1>Welcome Back</h1>
        <p>Log in to access your dashboard.</p>
        
        <?php if ($message): ?>
            <div class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <label style="text-align: left;">Username
                <input type="text" name="username" required placeholder="Enter your username">
            </label>
            <label style="text-align: left;">Password
                <input type="password" name="password" required placeholder="Enter your password">
            </label>
            <button class="btn" type="submit">Log In</button>
        </form>
        
        <div class="auth-links">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
    </div>
    <script src="js/global-ui.js"></script>
</body>
</html>