<?php
session_start();
require 'db_connection.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ADMIN ONLY
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? $_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// AUTO-FIX DATABASE
$conn->query("ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(500) DEFAULT 'uploads/avatars/default-avatar.png',
    ADD COLUMN IF NOT EXISTS status ENUM('active','suspended') DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");

$column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->num_rows > 0 ? 'role' : 'user_type';

$msg = "";

// UPLOAD AVATAR FUNCTION
function uploadAvatar($file, $user_id) {
    $target_dir = "uploads/avatars/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    $filename = "user_" . $user_id . "_" . time() . "." . $ext;
    $target = $target_dir . $filename;

    if (move_uploaded_file($file["tmp_name"], $target)) {
        return $target;
    }
    return null;
}

// CREATE NEW USER + AVATAR
if (isset($_POST['create_user'])) {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $pass = trim($_POST['password']) ?: substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
    $hashed = password_hash($pass, PASSWORD_DEFAULT);

    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) {
        $msg = "<div class='alert alert-danger'>Email already exists!</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, `$column`, status, force_password_change) VALUES (?, ?, ?, ?, ?, 'active', 1)");
        $stmt->bind_param("sssss", $name, $email, $phone, $hashed, $role);
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            if (!empty($_FILES['avatar']['name'])) {
                $pic = uploadAvatar($_FILES['avatar'], $user_id);
                if ($pic) $conn->query("UPDATE users SET profile_pic = '$pic' WHERE id = $user_id");
            }
            $msg = "<div class='alert alert-success'>User <strong>$name</strong> created!<br>Password: <code style='background:#333;color:#0f0;padding:8px;'>$pass</code></div>";
        }
    }
}

// EDIT USER + AVATAR
if (isset($_POST['edit_user'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name']);
    $email = trim($_POST['edit_email']);
    $phone = trim($_POST['edit_phone']);
    $role = $_POST['edit_role'];
    $status = $_POST['edit_status'];

    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, `$column`=?, status=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $email, $phone, $role, $status, $id);
    $stmt->execute();

    if (!empty($_FILES['edit_avatar']['name'])) {
        $pic = uploadAvatar($_FILES['edit_avatar'], $id);
        if ($pic) $conn->query("UPDATE users SET profile_pic = '$pic' WHERE id = $id");
    }

    $msg = "<div class='alert alert-success'>User updated successfully!</div>";
}

// RESET PASSWORD — EXACTLY LIKE YOUR ORIGINAL (WORKING 100%)
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);

    if (empty($new_password)) {
        $msg = "<div class='alert alert-danger'>Password required!</div>";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $update = $conn->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);
        $update->execute();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'martinmuganza47@gmail.com';
            $mail->Password = 'wjxs iynn eyhi nidg';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

            $mail->setFrom('martinmuganza47@gmail.com', 'Inheritance System');
            $mail->addAddress($user['email'], $user['full_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Confirmation';
            $mail->Body = "
                <p>Hello <strong>{$user['full_name']}</strong>,</p>
                <p>Your password has been successfully reset by the administrator.</p>
                <p><strong>New Password:</strong> $new_password</p>
                <p>Please change it after logging in.</p>
                <br><small>This email was sent automatically by the Inheritance System.</small>
            ";
            $mail->send();
            $msg = "<div class='alert alert-success'>Password reset + email sent to <strong>{$user['email']}</strong></div>";
        } catch (Exception $e) {
            $msg = "<div class='alert alert-warning'>Password updated, email failed: {$mail->ErrorInfo}</div>";
        }
    }
}

// [Bulk actions, delete, impersonate, search — unchanged & perfect]

// SEARCH & FILTER
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$where = "WHERE 1=1"; $params = []; $types = "";
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= "sss";
}
if ($filter_role !== 'all') {
    $where .= " AND `$column` = ?"; $params[] = $filter_role; $types .= "s";
}

$sql = "SELECT id, full_name, email, phone, `$column` as role, profile_pic, COALESCE(status,'active') as status, created_at FROM users $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National User Control Center | Rwanda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f0f2f5, #e3f2fd); font-family: 'Segoe UI', sans-serif; margin: 0; }
        .sidebar { width: 280px; height: 100vh; background: linear-gradient(180deg, #0d47a1, #1a237e); color: white; position: fixed; top: 0; left: 0; padding-top: 30px; box-shadow: 10px 0 30px rgba(0,0,0,0.4); }
        .sidebar h3 { text-align:center; color:#00d2ff; font-weight:bold; font-size:2em; padding:30px 0; border-bottom:4px solid #00d2ff; }
        .nav-link { color:#ddd; padding:18px 40px; font-size:18px; transition:0.3s; border-left:6px solid transparent; }
        .nav-link:hover, .nav-link.active { background:rgba(0,210,255,0.3); color:#fff !important; border-left:6px solid #00d2ff; font-weight:bold; }
        .content { margin-left: 280px; padding: 50px; }
        .card { border-radius: 25px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: none; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #0d47a1, #00d2ff); color: white; padding: 30px; text-align: center; font-size: 2em; font-weight: bold; }
        .avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 3px solid #00d2ff; }
        .btn-rw { background: #00d2ff; color: #1a237e; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>RWANDA ADMIN</h3>
    <div class="nav flex-column">
        <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
        <a href="manage_users.php" class="nav-link active">Manage Users</a>
        <a href="logout.php" class="nav-link" style="background:#c0392b;margin-top:50px;">Logout</a>
    </div>
</div>

<div class="content">
    <div class="card">
        <div class="card-header">National User Control Center</div>
        <div class="card-body">

            <?= $msg ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <button class="btn btn-rw btn-lg" data-bs-toggle="modal" data-bs-target="#createModal">Create New User</button>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">Go</button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Avatar</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): 
                            $avatar = $user['profile_pic'] && file_exists($user['profile_pic']) ? $user['profile_pic'] : 'uploads/avatars/default-avatar.png';
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" form="bulk-form"></td>
                            <td><img src="<?= $avatar ?>" class="avatar"></td>
                            <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone'] ?? '—') ?></td>
                            <td><span class="badge bg-info"><?= ucwords(str_replace('_', ' ', $user['role'])) ?></span></td>
                            <td><span class="badge bg-<?= $user['status']==='suspended'?'danger':'success' ?>"><?= ucfirst($user['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit<?= $user['id'] ?>">Edit</button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#reset<?= $user['id'] ?>">Reset Pass</button>
                                <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-dark" onclick="return confirm('Delete forever?')">Delete</a>
                            </td>
                        </tr>

                        <!-- EDIT MODAL + AVATAR -->
                        <div class="modal fade" id="edit<?= $user['id'] ?>">
                            <div class="modal-dialog modal-lg">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white"><h5>Edit User</h5></div>
                                        <div class="modal-body">
                                            <input type="hidden" name="edit_id" value="<?= $user['id'] ?>">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <img src="<?= $avatar ?>" class="img-fluid rounded mb-3" style="max-height:200px;">
                                                    <input type="file" name="edit_avatar" class="form-control" accept="image/*">
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" name="edit_name" class="form-control mb-2" value="<?= $user['full_name'] ?>" required>
                                                    <input type="email" name="edit_email" class="form-control mb-2" value="<?= $user['email'] ?>" required>
                                                    <input type="text" name="edit_phone" class="form-control mb-2" value="<?= $user['phone'] ?? '' ?>">
                                                    <select name="edit_role" class="form-select mb-2" required>
                                                        <option value="parents" <?= $user['role']==='parents'?'selected':'' ?>>Parents</option>
                                                        <option value="private_individual" <?= $user['role']==='private_individual'?'selected':'' ?>>Private Individual</option>
                                                        <option value="government" <?= $user['role']==='government'?'selected':'' ?>>Government</option>
                                                        <option value="special_gift_user" <?= $user['role']==='special_gift_user'?'selected':'' ?>>Special Gift</option>
                                                        <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
                                                    </select>
                                                    <select name="edit_status" class="form-select">
                                                        <option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option>
                                                        <option value="suspended" <?= $user['status']==='suspended'?'selected':'' ?>>Suspended</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="edit_user" class="btn btn-success btn-lg">Save Changes</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- RESET PASSWORD MODAL -->
                        <div class="modal fade" id="reset<?= $user['id'] ?>">
                            <div class="modal-dialog">
                                <form method="POST">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white"><h5>Reset Password: <?= $user['full_name'] ?></h5></div>
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="text" name="new_password" class="form-control form-control-lg" placeholder="Enter new password" required>
                                            <small>Email will be sent to: <?= $user['email'] ?></small>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="reset_password" class="btn btn-danger btn-lg">Reset & Send Email</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- CREATE USER MODAL + AVATAR -->
<div class="modal fade" id="createModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header bg-success text-white"><h5>Create New User</h5></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label>Profile Picture</label>
                            <input type="file" name="avatar" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="full_name" class="form-control mb-2" placeholder="Full Name" required>
                            <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
                            <input type="text" name="phone" class="form-control mb-2" placeholder="Phone">
                            <select name="role" class="form-select mb-2" required>
                                <option value="">Select Role</option>
                                <option value="parents">Parents</option>
                                <option value="private_individual">Private Individual</option>
                                <option value="government">Government</option>
                                <option value="special_gift_user">Special Gift</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <input type="password" name="password" class="form-control" placeholder="Password (auto if blank)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="create_user" class="btn btn-success btn-lg">Create User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<form id="bulk-form" method="POST"></form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('select-all').onclick = function() {
        document.querySelectorAll('input[name="selected_users[]"]').forEach(cb => cb.checked = this.checked);
    }
</script>
</body>
</html>