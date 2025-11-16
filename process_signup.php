<?php
session_start();
require 'db_connection.php';

// AUTO-CREATE MISSING TABLES & COLUMNS (RUNS ONCE, NO ERRORS EVER AGAIN!)
$conn->query("CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    child_name VARCHAR(255),
    child_dob DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS government_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    institution VARCHAR(255),
    position VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS special_gift_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reason TEXT,
    beneficiary_name VARCHAR(255),
    beneficiary_relation VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ADD MISSING COLUMNS IF THEY DON'T EXIST
$conn->query("ALTER TABLE special_gift_users 
    ADD COLUMN IF NOT EXISTS reason TEXT,
    ADD COLUMN IF NOT EXISTS beneficiary_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS beneficiary_relation VARCHAR(100)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All required fields must be filled.";
        header("Location: signup.php?type=$type");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: signup.php?type=$type");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: signup.php?type=$type");
        exit();
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "This email is already registered.";
        header("Location: signup.php?type=$type");
        exit();
    }

    // Handle Profile Picture
    $profile_pic = 'uploads/avatars/default-avatar.png';
    if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0755, true);

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime, $allowed) && $file['size'] <= $max_size) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $dest = 'uploads/avatars/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $profile_pic = $dest;
            }
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, user_type, profile_pic, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $full_name, $email, $phone, $hashed_password, $type, $profile_pic);

    if (!$stmt->execute()) {
        $_SESSION['error'] = "Account creation failed. Please try again.";
        header("Location: signup.php?type=$type");
        exit();
    }

    $user_id = $conn->insert_id;

    // Insert into type-specific tables
    switch ($type) {
        case 'parents':
            $child_name = trim($_POST['child_name'] ?? '');
            $child_dob = $_POST['child_dob'] ?? '';
            if (!empty($child_name)) {
                $stmt2 = $conn->prepare("INSERT INTO parents (user_id, child_name, child_dob) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $user_id, $child_name, $child_dob);
                $stmt2->execute();
            }
            break;

        case 'government':
            $institution = trim($_POST['institution'] ?? '');
            $position = trim($_POST['position'] ?? '');
            if (!empty($institution)) {
                $stmt2 = $conn->prepare("INSERT INTO government_users (user_id, institution, position) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $user_id, $institution, $position);
                $stmt2->execute();
            }
            break;

        case 'special_gift':
            $reason = trim($_POST['gift_reason'] ?? '');
            $beneficiary = trim($_POST['beneficiary_name'] ?? '');
            $relation = trim($_POST['relation'] ?? '');

            if (!empty($reason)) {
                // FIXED: Now uses correct column names: reason, beneficiary_name, beneficiary_relation
                $stmt2 = $conn->prepare("INSERT INTO special_gift_users 
                    (user_id, reason, beneficiary_name, beneficiary_relation) 
                    VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("isss", $user_id, $reason, $beneficiary, $relation);
                $stmt2->execute();
            }
            break;

        case 'private_individual':
            // No extra data
            break;
    }

    $_SESSION['success'] = "Account created successfully! Welcome to Rwanda Digital Inheritance Registry.";
    header("Location: login.php");
    exit();
}

header("Location: signup.php");
exit();
?>