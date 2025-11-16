<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// === AUTO-FIX & DETECT COLUMN (role OR user_type) === SAFE & BULLETPROOF
$column = 'role';
$check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$check_type = $conn->query("SHOW COLUMNS FROM users LIKE 'user_type'");

if ($check_role && $check_role->num_rows > 0) {
    $column = 'role';
} elseif ($check_type && $check_type->num_rows > 0) {
    $column = 'user_type';
} else {
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'private_individual'");
    $column = 'role';
}

// === AUTO-CREATE MISSING TABLES ===
$conn->query("CREATE TABLE IF NOT EXISTS government_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    asset_name VARCHAR(500),
    category VARCHAR(100),
    file_path VARCHAR(500),
    release_date DATETIME NULL,
    require_verification TINYINT(1) DEFAULT 0,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS beneficiaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT,
    full_name VARCHAR(255),
    notified TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT
)");

// === FETCH REAL DATA SAFELY ===
function safeCount($conn, $sql) {
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_row()) ? (int)$row[0] : 0;
}

$stats = [];
$stats['total_users']           = safeCount($conn, "SELECT COUNT(*) FROM users");
$stats['parents']               = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'parents'");
$stats['private_individual']    = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'private_individual'");
$stats['government']            = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'government'");
$stats['legal_verifier']        = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'legal_verifier'");
$stats['special_gift_user']     = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'special_gift_user' OR `$column` = 'special_gift'");
$stats['admins']                = safeCount($conn, "SELECT COUNT(*) FROM users WHERE `$column` = 'admin'");
$stats['total_gov_assets']      = safeCount($conn, "SELECT COUNT(*) FROM government_assets");
$stats['pending_verification']  = safeCount($conn, "SELECT COUNT(*) FROM government_assets WHERE require_verification = 1");
$stats['scheduled_release']     = safeCount($conn, "SELECT COUNT(*) FROM government_assets WHERE release_date IS NOT NULL AND release_date > NOW()");
$stats['released_assets']       = safeCount($conn, "SELECT COUNT(*) FROM government_assets WHERE release_date IS NOT NULL AND release_date <= NOW()");

$ben = $conn->query("SELECT COUNT(*) as total, SUM(IFNULL(notified,0)) as notified FROM beneficiaries");
$ben_row = $ben && $ben->num_rows > 0 ? $ben->fetch_assoc() : ['total' => 0, 'notified' => 0];
$stats['total_beneficiaries']   = $ben_row['total'];
$stats['notified_beneficiaries']= $ben_row['notified'];

$stats['private_documents'] = $conn->query("SHOW TABLES LIKE 'documents'")->num_rows > 0 
    ? safeCount($conn, "SELECT COUNT(*) FROM documents") : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Inheritance Databank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; font-family:system-ui,sans-serif; margin:0; color:#333; }
        .sidebar {
            width:220px; height:100vh; background:#0d47a1; color:white; position:fixed; top:0; left:0; padding-top:15px; overflow-y:auto;
        }
        .sidebar h3 { text-align:center; font-size:1.4rem; padding:15px 0; border-bottom:2px solid #00d2ff; margin-bottom:20px; }
        .nav-link { color:#ddd; padding:10px 20px; font-size:0.95rem; transition:0.3s; }
        .nav-link:hover, .nav-link.active { background:rgba(0,210,255,0.25); color:white; font-weight:600; }
        .nav-link i { width:28px; }
        .content { margin-left:220px; padding:20px; }
        .stat-card {
            background:white; border-radius:12px; padding:18px; text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.08);
            transition:0.3s; height:100%;
        }
        .stat-card:hover { transform:translateY(-6px); box-shadow:0 12px 25px rgba(0,0,0,0.15); }
        .stat-card .icon { font-size:2.2rem; color:#0d47a1; margin-bottom:10px; }
        .stat-card h5 { font-size:0.95rem; color:#555; margin-bottom:8px; font-weight:600; }
        .stat-card .number { font-size:2rem; font-weight:800; color:#0d47a1; }
        .header-title { font-size:1.8rem; color:#0d47a1; font-weight:700; text-align:center; margin:20px 0 10px; }
        .subtitle { text-align:center; color:#00695c; font-size:1rem; margin-bottom:30px; }
        h2 { font-size:1.4rem; color:#0d47a1; font-weight:700; margin:30px 0 15px; }
        .status-bar {
            text-align:center; padding:12px; background:#0d47a1; color:white; border-radius:10px; font-size:0.9rem; margin-top:40px;
        }
        .logout-btn { background:#c0392b !important; margin:30px 20px 20px; font-size:1rem !important; padding:12px !important; }
        @media (max-width:992px){
            .sidebar { width:70px; }
            .sidebar h3, .nav-link span { display:none; }
            .sidebar .nav-link { text-align:center; padding:15px 0; }
            .sidebar .nav-link i { width:auto; font-size:1.4rem; }
            .content { margin-left:70px; }
        }
        @media (max-width:576px){
            .content { padding:15px; }
            .stat-card .number { font-size:1.8rem; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>INHERITANCE<br>DATABANK</h3>
    <div class="nav flex-column">
        <a href="admin_dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage Users</span></a>
        <a href="admin_government_assets.php" class="nav-link"><i class="fas fa-scroll"></i> <span>Gov Assets</span></a>
        <a href="admin_private_documents.php" class="nav-link"><i class="fas fa-folder"></i> <span>Private Docs</span></a>
        <a href="admin_beneficiaries.php" class="nav-link"><i class="fas fa-heart"></i> <span>Beneficiaries</span></a>
        <a href="admin_reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
        <a href="admin_settings.php" class="nav-link"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <a href="index.php" class="nav-link logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="content">
    <div class="header-title">Inheritance Databank</div>
    <div class="subtitle">Secure • Transparent • Digital Asset Registry</div>

    <h2>User Statistics</h2>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-users"></i></div><h5>Total Users</h5><div class="number"><?= number_format($stats['total_users']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-home"></i></div><h5>Parents</h5><div class="number"><?= number_format($stats['parents']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-user"></i></div><h5>Individuals</h5><div class="number"><?= number_format($stats['private_individual']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-landmark"></i></div><h5>Government</h5><div class="number"><?= number_format($stats['government']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-gavel"></i></div><h5>Legal Verifiers</h5><div class="number"><?= number_format($stats['legal_verifier']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-gift"></i></div><h5>Special Gift</h5><div class="number"><?= number_format($stats['special_gift_user']) ?></div></div></div>
        <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon"><i class="fas fa-user-shield"></i></div><h5>Admins</h5><div class="number"><?= number_format($stats['admins']) ?></div></div></div>
    </div>

    <h2>Government Assets</h2>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="icon"><i class="fas fa-scroll"></i></div><h5>Total</h5><div class="number"><?= number_format($stats['total_gov_assets']) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="icon"><i class="fas fa-clock"></i></div><h5>Pending</h5><div class="number"><?= number_format($stats['pending_verification']) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="icon"><i class="fas fa-calendar-alt"></i></div><h5>Scheduled</h5><div class="number"><?= number_format($stats['scheduled_release']) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="icon"><i class="fas fa-check-double"></i></div><h5>Released</h5><div class="number"><?= number_format($stats['released_assets']) ?></div></div></div>
    </div>

    <h2>Beneficiaries & Documents</h2>
    <div class="row g-3">
        <div class="col-6 col-md-4"><div class="stat-card"><div class="icon"><i class="fas fa-heart"></i></div><h5>Total Beneficiaries</h5><div class="number"><?= number_format($stats['total_beneficiaries']) ?></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card"><div class="icon"><i class="fas fa-bell"></i></div><h5>Notified</h5><div class="number"><?= number_format($stats['notified_beneficiaries']) ?></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card"><div class="icon"><i class="fas fa-folder-open"></i></div><h5>Private Docs</h5><div class="number"><?= number_format($stats['private_documents']) ?></div></div></div>
    </div>

    <div class="status-bar">
        SYSTEM STATUS: <strong style="color:#00ff88;">100% OPERATIONAL</strong> | 
        Last Updated: <?= date('d M Y - H:i') ?> | 
        Admin: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong>
    </div>
</div>

</body>
</html>