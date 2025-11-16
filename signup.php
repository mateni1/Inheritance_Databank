<?php
session_start();
require 'db_connection.php';

$type = $_GET['type'] ?? '';
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signup - Inheritance System</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; margin:0; padding:0; }
    .container { max-width: 550px; margin:50px auto; background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.12);}
    h2 { text-align:center; color:#2c3e50; margin-bottom: 10px; font-size: 26px; }
    h3 { text-align:center; color:#34495e; margin: 25px 0 15px; font-size: 19px; }
    input, select, button { width:100%; padding:12px; margin:10px 0; border-radius:6px; border:1px solid #ccc; font-size:15px; box-sizing:border-box; }
    input[type="file"] { padding:8px; background:#f9f9f9; cursor:pointer; border:1px dashed #aaa; }
    button { background:#27ae60; color:#fff; font-weight:bold; border:none; cursor:pointer; transition:0.3s; font-size:16px; }
    button:hover { background:#1e8449; }
    .error { background:#ffebee; color:#c62828; padding:12px; border-radius:6px; text-align:center; margin:10px 0; font-weight:bold; }
    .success { background:#d4edda; color:#155724; padding:12px; border-radius:6px; text-align:center; margin:10px 0; font-weight:bold; }
    .toggle { text-align:center; margin:20px 0 10px; }
    .toggle a { color:#27ae60; text-decoration:none; font-weight:bold; }
    .profile-preview { text-align:center; margin:20px 0 10px; }
    .profile-preview img { width:120px; height:120px; border-radius:50%; object-fit:cover; border:5px solid #27ae60; box-shadow:0 6px 15px rgba(0,0,0,0.15); }
    .small-text { font-size:13px; color:#666; margin-top:8px; display:block; }
    .back-btn { display:inline-block; margin-top:15px; color:#7f8c8d; font-size:14px; }
</style>
<script>
function showForm() {
    const type = document.getElementById("user_type").value;
    if (type) location.href = "signup.php?type=" + type;
}
function previewImage(e) {
    const preview = document.getElementById('avatarPreview');
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function() {
            preview.src = reader.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>
</head>
<body>

<div class="container">
    <h2>Inheritance System</h2>

    <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if(empty($type)): ?>
        <!-- Step 1: Choose Account Type -->
        <form>
            <label><strong>Select Your Account Type</strong></label>
            <select id="user_type" required>
                <option value="">-- Choose Account Type --</option>
                <option value="parents">Parents</option>
                <option value="government">Government Official</option>
                <option value="special_gift">Special Gift Recipient</option>
                <option value="private_individual">Private Individual</option>
            </select>
            <button type="button" onclick="showForm()">Continue →</button>
        </form>

    <?php else: ?>
        <!-- Step 2: Simple & Fast Registration -->
        <form action="process_signup.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

            <h3><?= ucfirst(str_replace('_', ' ', $type)) ?> Account</h3>

            <!-- Profile Picture -->
            <div class="profile-preview">
                <img id="avatarPreview" src="uploads/avatars/default-avatar.png" alt="Your Photo">
                <span class="small-text">Upload your photo (optional)</span>
            </div>
            <input type="file" name="profile_pic" accept="image/*" onchange="previewImage(event)">

            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="tel" name="phone" placeholder="Phone Number (optional)">

            <input type="password" name="password" placeholder="Create Password" required minlength="6">
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>

            <button type="submit">Create My Account</button>
        </form>

        <div class="toggle">
            <a href="signup.php" class="back-btn">← Back to Account Type</a>
        </div>
    <?php endif; ?>

    <div class="toggle">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

</body>
</html>