<?php
session_start();
// Database connection (Keep your existing connection logic here)
$host = 'localhost';
$db   = 'dbm_bluedale';
$user = 'root'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
    $stmt->execute([$email, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: summary.php");
        exit();
    } else {
        $error_msg = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Bluedale Group</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <!-- Ensure this file name is correct in your folder -->
            <img src="logo.png" alt="Bluedale Logo">
        </div>

        <?php if($error_msg): ?>
            <div class="error-msg"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>User*</label>
                <input type="email" name="email"  required>
            </div>

            <div class="input-group">
                <label>pasword*</label> 
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="login-btn">LOGIN</button>
        </form>
    </div>

</body>
</html>