<?php
// === login.php - FINAL WORKING VERSION ===
ob_start(); // Prevent "headers already sent" errors
session_start();
require 'db_connection.php';

// Only process login on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');

    // Debug: Remove after testing (uncomment to see values)
    // echo "<pre>Email: $email\nType: $user_type\nPassword: [hidden]</pre>"; exit();

    if (empty($email) || empty($password) || empty($user_type)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: login.php");
        exit();
    }

    // MAIN FIX: Query the 'users' table with exact match
    $stmt = $conn->prepare("
        SELECT id, full_name, password, user_type, phone, profile_pic 
        FROM users 
        WHERE email = ? AND user_type = ? 
        LIMIT 1
    ");

    if (!$stmt) {
        $_SESSION['error'] = "Database error. Please try again.";
        header("Location: login.php");
        exit();
    }

    $stmt->bind_param("ss", $email, $user_type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {

            // SUCCESS: Set session
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['full_name'];
            $_SESSION['email']       = $email;
            $_SESSION['role']        = $user['user_type'];
            $_SESSION['phone']       = $user['phone'] ?? '';
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'uploads/avatars/default-avatar.png';

            // FINAL FIX: Redirect with full exit
            $redirect = match ($user['user_type']) {
                'admin'             => 'admin_dashboard.php',
                'parents'           => 'parents.php',
                'private_individual'=> 'individuals.php',
                'government'        => 'government.php',
                'special_gift'      => 'special_gift.php',
                default             => 'welcome.php'
            };

            header("Location: $redirect");
            exit();

        } else {
            $_SESSION['error'] = "Incorrect password.";
        }
    } else {
        // This is the most common issue: user_type mismatch or wrong email
        $_SESSION['error'] = "No account found. Check email and user type.";
    }

    // Always redirect back on error
    header("Location: login.php");
    exit();
}

// If not POST, show login form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Inheritance System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; margin:0; padding:0; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .box { background:#fff; padding:40px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.15); width:100%; max-width:420px; text-align:center; }
        h2 { color:#2c3e50; margin-bottom:10px; }
        .subtitle { color:#7f8c8d; margin-bottom:30px; }
        input, select { width:100%; padding:14px; margin:12px 0; border:1px solid #ddd; border-radius:10px; font-size:16px; box-sizing:border-box; }
        button { background:#27ae60; color:white; padding:16px; border:none; border-radius:10px; font-weight:bold; font-size:17px; cursor:pointer; width:100%; margin-top:10px; }
        button:hover { background:#1e8449; }
        .error { background:#ffebee; color:#c62828; padding:14px; border-radius:10px; margin:15px 0; font-weight:bold; font-size:15px; }
        .links a { color:#27ae60; text-decoration:none; font-weight:bold; }
        .links a:hover { text-decoration:underline; }
    </style>
</head>
<body>

<div class="box">
    <h2>Inheritance System</h2>
    <p class="subtitle">Login to your account</p>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="">
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

        <button type="submit">Login Now</button>
    </form>

    <div class="links" style="margin-top:25px;">
        <p><a href="signup.php">Create new account</a></p>
    </div>
</div>

</body>
</html>
<?php ob_end_flush(); ?>