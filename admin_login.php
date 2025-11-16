<?php
session_start();
require 'db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = trim($_POST['user_type'] ?? '');

    if (empty($email) || empty($password) || empty($user_type)) {
        $error = "Please fill in all fields.";
    } else {
        if ($user_type === 'admin') {
            // Check in admins table
            $stmt = $conn->prepare("SELECT id, full_name, password, role FROM admins WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
        } else {
            // Check in users table
            $stmt = $conn->prepare("SELECT id, full_name, password, user_type FROM users WHERE email = ? AND user_type = ? LIMIT 1");
            $stmt->bind_param("ss", $email, $user_type);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['full_name'];
                $_SESSION['role'] = $user_type;

                // Redirect based on type
                if ($user_type === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: welcome.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with this email for the selected user type.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Inheritance System</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <h2>Login / Signup</h2>
    <?php if ($error): ?>
        <p class="error" style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <label>User Type:</label>
        <select name="user_type" required>
            <option value="">-- Select Type --</option>
            <option value="admin">Admin</option>
            <option value="parents">Parents</option>
            <option value="private_individual">Private Individual</option>
            <option value="government">Government</option>
            <option value="special_gift">Special Gift</option>
        </select>

        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
</div>
</body>
</html>
