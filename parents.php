<?php
session_start();
require 'db_connection.php';

// ONLY PARENTS ALLOWED
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parents') {
    header("Location: login.php");
    exit();
}

$user_id     = $_SESSION['user_id'];
$username    = $_SESSION['username'] ?? 'Parent';
$profile_pic = $_SESSION['profile_pic'] ?? 'uploads/avatars/default-avatar.png';

// Fetch full name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $username = $row['full_name'];

// Stats
$total_children  = $conn->query("SELECT COUNT(*) FROM parent_children WHERE parent_id = $user_id")->fetch_row()[0] ?? 0;
$total_assets    = $conn->query("SELECT COUNT(*) FROM parent_assets WHERE parent_id = $user_id")->fetch_row()[0] ?? 0;
$pending_assets  = $conn->query("SELECT COUNT(*) FROM parent_assets WHERE parent_id = $user_id AND status = 'pending'")->fetch_row()[0] ?? 0;
$will_exists     = $conn->query("SELECT 1 FROM parent_will WHERE parent_id = $user_id")->num_rows > 0;

// Data
$children = $conn->query("SELECT * FROM parent_children WHERE parent_id = $user_id ORDER BY full_name");
$assets   = $conn->query("SELECT pa.*, pc.full_name as child_name 
                          FROM parent_assets pa 
                          LEFT JOIN parent_children pc ON pa.child_id = pc.id 
                          WHERE pa.parent_id = $user_id 
                          ORDER BY pa.uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children's Future • Parents Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root { --pri: #ff6b9e; --love: #ff8fab; --dark: #2d1b3a; --success: #8bc34a; --warn: #ff9800; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #2d1b3a, #483475); color:#fff; margin:0; min-height:100vh; }
        .container { max-width: 1300px; margin: 20px auto; padding: 20px; }
        .header { background: rgba(255,255,255,0.12); border-radius: 24px; padding: 35px; text-align: center; backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); position:relative; }
        .profile { display: inline-flex; align-items: center; gap: 20px; background: rgba(0,0,0,0.3); padding: 15px 30px; border-radius: 50px; }
        .profile img { width: 90px; height: 90px; border-radius: 50%; border: 5px solid var(--pri); object-fit: cover; }
        .header h1 { font-size: 2.8em; color: var(--pri); margin: 15px 0 5px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 40px 0; }
        .stat { background: rgba(255,255,255,0.15); padding: 25px; border-radius: 20px; text-align: center; }
        .stat i { font-size: 3em; color: var(--pri); margin-bottom: 10px; }
        .stat h3 { font-size: 2.5em; margin: 10px 0; }
        .actions { text-align: center; margin: 50px 0; }
        .btn { background: var(--pri); color: #000; padding: 18px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1.2em; margin: 12px; display: inline-block; transition: 0.3s; }
        .btn:hover { background: #ffb3c6; transform: translateY(-5px); }
        .btn-success { background: var(--success); }
        .section { background: rgba(255,255,255,0.1); border-radius: 20px; padding: 30px; margin: 30px 0; border: 1px solid rgba(255,255,255,0.1); }
        .section h2 { color: var(--pri); text-align: center; margin-bottom: 25px; }
        .children-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .child-card { background: rgba(0,0,0,0.3); padding: 20px; border-radius: 16px; text-align: center; }
        .child-card img { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid var(--pri); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(0,0,0,0.4); }
        .status-pending { background: var(--warn); color:#000; padding:6px 14px; border-radius:30px; font-weight:bold; }
        .status-released { background: var(--success); color:white; }
        .status-transferred { background: #2196f3; color:white; }
        .logout { position: absolute; top: 20px; right: 30px; background: #e74c3c; padding: 12px 25px; border-radius: 50px; color: white; text-decoration: none; font-weight: bold; }
        .logout:hover { background: #c0392b; }
        .no-data { text-align:center; padding:60px; font-size:1.4em; opacity:0.8; }
    </style>
</head>
<body>

<a href="index.php" class="logout">Logout</a>

<div class="container">
    <div class="header">
        <div class="profile">
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Parent">
            <div>
                <h1>For My Beloved Children</h1>
                <p>Securing their future with love and clarity</p>
            </div>
        </div>
        <p>Welcome back, <strong><?= htmlspecialchars($username) ?></strong></p>
    </div>

    <div class="stats">
        <div class="stat"><i class="fas fa-child"></i><h3><?= $total_children ?></h3><p>Children</p></div>
        <div class="stat"><i class="fas fa-home"></i><h3><?= $total_assets ?></h3><p>Assets</p></div>
        <div class="stat"><i class="fas fa-clock"></i><h3><?= $pending_assets ?></h3><p>Pending</p></div>
        <div class="stat"><i class="fas fa-heart"></i><h3><?= $will_exists ? 'Yes' : 'No' ?></h3><p>Will</p></div>
    </div>

    <div class="actions">
        <a href="add_child.php" class="btn">Add Child</a>
        <a href="parents_upload.php" class="btn">Add Asset</a>
        <a href="parent_will.php" class="btn btn-success">Record Will & Video</a>
    </div>

    <?php if ($children->num_rows > 0): ?>
    <div class="section">
        <h2>My Children</h2>
        <div class="children-grid">
            <?php while($c = $children->fetch_assoc()): ?>
            <div class="child-card">
                <img src="<?= $c['photo_path'] ?: 'uploads/avatars/child-default.png' ?>" alt="<?= htmlspecialchars($c['full_name']) ?>">
                <h4><?= htmlspecialchars($c['full_name']) ?></h4>
                <small><?= $c['date_of_birth'] ? date('d M Y', strtotime($c['date_of_birth'])) : 'No DOB' ?></small>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>Protected Assets</h2>
        <?php if ($assets->num_rows > 0): ?>
        <table>
            <thead><tr>
                <th>Asset</th>
                <th>For</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Document</th>
            </tr></thead>
            <tbody>
            <?php while($a = $assets->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['asset_name']) ?></strong></td>
                <td><?= htmlspecialchars($a['child_name'] ?: 'All Children') ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $a['release_condition'])) ?></td>
                <td><span class="status-<?= $a['status'] ?? 'pending' ?>"><?= ucfirst($a['status'] ?? 'pending') ?></span></td>
                <td><?php if($a['document_path']): ?><a href="<?= $a['document_path'] ?>" target="_blank" style="color:var(--pri);">View</a><?php endif; ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">No assets yet. <a href="parents_upload.php" style="color:var(--pri);">Add your first one</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>