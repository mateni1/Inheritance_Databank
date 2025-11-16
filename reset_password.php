<?php
session_start();
require 'db_connection.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid or missing token.");
}

// Verify token
$stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($email);

if ($stmt->num_rows === 0) {
    die("Invalid or expired token.");
}

$stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update user's password
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt_update->bind_param("ss", $hashed_password, $email);
        $stmt_update->execute();

        // Delete token
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt_delete->bind_param("s", $token);
        $stmt_delete->execute();

        $success = "✅ Password has been reset successfully. <a href='login.php'>Login here</a>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password | Inheritance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #2c3e50, #3498db);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: "Poppins", sans-serif;
}
.container {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    padding: 40px;
    width: 100%;
    max-width: 420px;
}
h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #2c3e50;
}
.btn-primary {
    background-color: #3498db;
    border: none;
}
.btn-primary:hover {
    background-color: #2980b9;
}
.alert {
    border-radius: 8px;
    font-size: 0.95rem;
}
input[type="password"] {
    border-radius: 8px;
    padding: 10px;
}
a {
    color: #3498db;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
    <h2>🔐 Reset Your Password</h2>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php else: ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter new password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
