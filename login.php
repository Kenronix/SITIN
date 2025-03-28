<?php
session_start();
require_once 'conn.php'; // Ensure this connects to the 'admin_user' database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Select user from the admin_user database
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            if ($password === $user['password']) { // Plain text password check
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php"); // Redirect admin users
                    exit();
                } else {
                    header("Location: index.php"); // Redirect student users
                    exit();
                }
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <title>Sitin Monitoring System</title>
</head>
<body>
    <div id="container">
        <form method="POST" action="login.php">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div id="image">
                <img src="logo.jpg" alt="UC CSS" class="logo">
            </div>
            <h2>CCS SITIN MONITORING SYSTEM</h2>
            <label>Username:</label>
            <input type="text" id="username" name="username">
            <label>Password:</label>
            <input type="password" id="password" name="password">
            <input type="submit" value="Login" id="button">
            <br>
            <br>
            <h3>Don't have an account?</h3>
            <a href="register.php" class="register">Sign Up</a>
        </form>
    </div>
</body>
</html>



