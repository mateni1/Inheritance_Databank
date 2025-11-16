<?php
session_start();
require 'db_connection.php'; // Your database connection
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email.";
    } else {
        // Check if email exists in users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $error = "No account found with this email.";
        } else {
            // Generate a secure token
            $token = bin2hex(random_bytes(16));

            // Insert token into password_resets table
            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
            $stmt_insert->bind_param("ss", $email, $token);
            $stmt_insert->execute();

            // Send email with reset link
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Use your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'martinmuganza47@gmail.com'; // Your email
                $mail->Password = 'wjxs iynn eyhi nidg'; // App Passwords
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                // Disable SSL verification for local testing
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                $mail->setFrom('your_email@gmail.com', 'Inheritance System');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $resetLink = "http://localhost/inheritance/reset_password.php?token=$token";
                $mail->Body = "Click the link to reset your password: <a href='$resetLink'>$resetLink</a>";

                $mail->send();
                $success = "Password reset link has been sent to your email.";

            } catch (Exception $e) {
                $error = "Mailer Error: {$mail->ErrorInfo}";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<style>
body { font-family: Arial, sans-serif; background:#f4f4f4; padding:50px; }
form { background:#fff; padding:20px; border-radius:8px; max-width:400px; margin:auto; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
input, button { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:1px solid #ccc; }
button { background:#27ae60; color:#fff; border:none; cursor:pointer; }
button:hover { background:#1e8449; }
.error { color:red; }
.success { color:green; }
</style>
</head>
<body>

<h2 style="text-align:center;">Forgot Password</h2>

<?php if($error) echo "<p class='error'>$error</p>"; ?>
<?php if($success) echo "<p class='success'>$success</p>"; ?>

<form method="POST">
    <label>Email:</label>
    <input type="email" name="email" required>
    <button type="submit">Send Reset Link</button>
</form>

<p style="text-align:center;"><a href="login.php">Back to Login</a></p>

</body>
</html>
