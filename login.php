<?php
session_start();
require 'db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');

    // Validate
    if (empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } else {

        // THIS IS THE FIX: Query the main 'users' table only
        $stmt = $conn->prepare("
            SELECT id, full_name, password, user_type, phone, profile_pic 
            FROM users 
            WHERE email = ? AND user_type = ? 
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Database error. Try again later.";
        } else {
            $stmt->bind_param("ss", $email, $user_type);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check password
                if (password_verify($password, $user['password'])) {

                    // SUCCESS: Save session data
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['username']    = $user['full_name'];
                    $_SESSION['email']       = $email;
                    $_SESSION['role']        = $user['user_type'];
                    $_SESSION['phone']       = $user['phone'] ?? '';
                    $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'uploads/avatars/default-avatar.png';

                    // Redirect correctly
                    switch ($user['user_type']) {
                        case 'admin':
                            header("Location: admin_dashboard.php"); exit();
                        case 'parents':
                            header("Location: parents.php"); exit();
                        case 'private_individual':
                            header("Location: individuals.php"); exit();
                        case 'government':
                            header("Location: government.php"); exit();
                        case 'special_gift':
                            header("Location: special_gift.php"); exit();
                        default:
                            header("Location: welcome.php"); exit();
                    }

                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "No account found with this email and user type.";
            }
        }
    }

    // If login failed, show error on login page
    if ($error) {
        $_SESSION['login_error'] = $error;
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Inheritance System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin:0; padding:0; }
        .container {
            max-width: 420px; margin: 100px auto; background:#fff; padding:40px; border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.15); text-align:center;
        }
        h2 { color:#2c3e50; margin-bottom:25px; }
        input, select { width:100%; padding:14px; margin:12px 0; border:1px solid #ddd; border-radius:8px; font-size:16px; }
        button { background:#27ae60; color:white; padding:14px; border:none; border-radius:8px; font-weight:bold; cursor:pointer; font-size:16px; }
        button:hover { background:#1e8449; }
        .error { background:#ffebee; color:#c62828; padding:12px; border-radius:8px; margin:15px 0; font-weight:bold; }
        .links a { color:#27ae60; text-decoration:none; font-weight:bold; }
        .links a:hover { text-decoration:underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>Login to Your Account</h2>

    <?php if (isset($_SESSION['login_error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['login_error']) ?></div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email Address" required autofocus>
        <input type="password" name="password" placeholder="Password" required>

        <select name="user_type" required>
            <option value="">-- Select Account Type --</option>
            <option value="parents">Parents</option>
            <option value="government">Government Official</option>
            <option value="special_gift">Special Gift Recipient</option>
            <option value="private_individual">Private Individual</option>
            <option value="admin">Admin</option>
        </select>

        <button type="submit">Login</button>
    </form>

    <div class="links" style="margin-top:20px;">
        <p><a href="signup.php">Don't have an account? Sign up</a></p>
    </div>
</div>

</body>
</html>