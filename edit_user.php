<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = $msg = "";

// Get user ID from GET
if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Handle form submission
if (isset($_POST['update_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $user_type = $_POST['user_type'];

    if (empty($full_name) || empty($email)) {
        $error = "Full Name and Email are required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, user_type=? WHERE id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $user_type, $user_id);
        if ($stmt->execute()) {
            $msg = "User updated successfully.";
        } else {
            $error = "Error updating user.";
        }
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: manage_users.php");
    exit();
}
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User | Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body { background-color: #f8f9fa; font-family: "Poppins", sans-serif; padding: 40px; }
.container { max-width: 600px; margin:auto; background:white; padding:20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
</style>
</head>
<body>

<div class="container">
    <h3>Edit User</h3>
    <?php if($msg) echo "<div class='alert alert-success'>$msg</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
        </div>
        <div class="mb-3">
            <label>User Type</label>
            <select name="user_type" class="form-select">
                <option value="parents" <?= $user['user_type']=='parents'?'selected':'' ?>>Parents</option>
                <option value="government" <?= $user['user_type']=='government'?'selected':'' ?>>Government</option>
                <option value="private_individual" <?= $user['user_type']=='private_individual'?'selected':'' ?>>Private Individual</option>
                <option value="special_gift" <?= $user['user_type']=='special_gift'?'selected':'' ?>>Special Gift</option>
            </select>
        </div>

        <button type="submit" name="update_user" class="btn btn-success">Save Changes</button>
        <a href="manage_users.php" class="btn btn-secondary">Back</a>
    </form>
</div>

</body>
</html>

