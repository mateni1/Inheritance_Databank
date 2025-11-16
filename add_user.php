<?php
session_start();
require 'db_connection.php';

// Restrict access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $user_type = trim($_POST['user_type']);

    if (empty($full_name) || empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, user_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $user_type);
            if ($stmt->execute()) {
                $msg = "User added successfully!";
            } else {
                $error = "Error adding user.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add User | Admin Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body {
    background-color: #f4f6f8;
    font-family: "Poppins", sans-serif;
}
.container {
    margin-left: 240px;
    padding: 40px;
}
.sidebar {
    width: 230px;
    height: 100vh;
    background-color: #2c3e50;
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
}
.sidebar h2 {
    text-align: center;
    padding: 20px;
    background-color: #1a252f;
    margin: 0;
}
.sidebar a {
    color: white;
    text-decoration: none;
    padding: 15px 20px;
    display: block;
    transition: background 0.3s;
}
.sidebar a:hover {
    background-color: #34495e;
}
.logout {
    margin-top: auto;
    background-color: #e74c3c;
    text-align: center;
    padding: 15px;
}
.logout a {
    color: white;
    text-decoration: none;
    font-weight: bold;
}
form {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>
</head>

<body>
<div class="sidebar">
    <h2>Inheritance DB</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="manage_users.php">Manage Users</a>
    <a href="manage_documents.php">Documents</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
    <div class="logout"><a href="dashboard.php">Logout</a></div>
</div>

<div class="container">
    <h2 class="mb-4">Add New User</h2>

    <?php if (!empty($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">User Type</label>
            <select name="user_type" class="form-select" required>
                <option value="">-- Select Type --</option>
                <option value="admin">Admin</option>
                <option value="parents">Parents</option>
                <option value="private_individual">Private Individual</option>
                <option value="government">Government</option>
                <option value="special_gift">Special Gift</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Save User</button>
        <a href="manage_users.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
