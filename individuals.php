<?php
session_start();
require 'db_connection.php';

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'private_individual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ————————————————————————
// AUTO CREATE MISSING TABLES (already in your file)
// ————————————————————————
// (Keep the CREATE TABLE IF NOT EXISTS blocks from the previous version here)
// ... [your 3 CREATE TABLE statements] ...

// ————————————————————————
// SAFELY GET USER INFO — NO MORE "Unknown column" ERROR!
// ————————————————————————
$display_name = $_SESSION['username'] ?? 'User';
$email        = 'Not set';
$phone        = 'Not set';

$stmt = $conn->prepare("SHOW COLUMNS FROM private_individuals LIKE 'full_name'");
if ($stmt->execute() && $stmt->get_result()->num_rows > 0) {
    // full_name exists → use it
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM private_individuals WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $display_name = $row['full_name'] ?: $display_name;
        $email        = $row['email'] ?? $email;
        $phone        = $row['phone'] ?? $phone;
    }
} else {
    // full_name doesn't exist → try common alternatives
    $stmt = $conn->prepare("SHOW COLUMNS FROM private_individuals LIKE 'name'");
    if ($stmt->execute() && $stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("SELECT name AS full_name, email, phone FROM private_individuals WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $display_name = $row['full_name'] ?: $display_name;
            $email        = $row['email'] ?? $email;
            $phone        = $row['phone'] ?? $phone;
        }
    }
    // If still no luck → just use session username
}

// ————————————————————————
// Stats & Assets (safe queries)
// ————————————————————————
$total_assets        = $conn->query("SELECT COUNT(*) FROM individual_assets WHERE user_id = $user_id")->fetch_row()[0] ?? 0;
$total_beneficiaries = $conn->query("SELECT COUNT(*) FROM individual_beneficiaries WHERE user_id = $user_id")->fetch_row()[0] ?? 0;
$will_exists         = $conn->query("SELECT 1 FROM digital_will WHERE user_id = $user_id LIMIT 1")->num_rows > 0;

$assets_result = $conn->query("
    SELECT ia.*, COALESCE(COUNT(ib.id), 0) AS ben_count 
    FROM individual_assets ia 
    LEFT JOIN individual_beneficiaries ib ON ia.id = ib.asset_id 
    WHERE ia.user_id = $user_id 
    GROUP BY ia.id 
    ORDER BY ia.uploaded_at DESC
");
$assets = $assets_result ?: null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Digital Legacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #00d2ff; --dark: #283e51; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #283e51, #485563); color: #fff; margin:0; padding:0; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; }
        .header { background: rgba(255,255,255,0.1); padding: 30px; border-radius: 16px; text-align: center; backdrop-filter: blur(10px); }
        .header h1 { margin: 0; font-size: 2.5em; color: var(--primary); }
        .navbar { background: rgba(0,0,0,0.4); padding: 15px 30px; border-radius: 12px; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #fff; text-decoration: none; margin: 0 15px; font-weight: 600; }
        .navbar a:hover { color: var(--primary); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-card { background: rgba(255,255,255,0.15); padding: 25px; border-radius: 14px; text-align: center; }
        .stat-card i { font-size: 3em; color: var(--primary); }
        .stat-card h3 { font-size: 2.4em; margin: 10px 0; }
        .card { background: rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; margin-bottom: 30px; }
        .card-header { background: var(--primary); color: #000; padding: 18px; font-weight: bold; font-size: 1.4em; }
        .card-body { padding: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(0,0,0,0.3); }
        .btn { background: var(--primary); color: #000; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-block; margin: 5px; }
        .btn-sm { padding: 6px 12px; font-size: 0.9em; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8em; background: #e8f5e9; color: #2e7d32; }
    </style>
</head>

<body>

<div class="container">
    <!-- REPLACE ONLY THE HEADER SECTION (from <div class="header"> to </div>) WITH THIS UPGRADED VERSION -->

<div class="header">
    <h1>My Digital Legacy</h1>
    <p>Welcome back, <strong><?= htmlspecialchars($display_name) ?></strong></p>
    <small><?= htmlspecialchars($email) ?> • <?= htmlspecialchars($phone) ?></small>

    <!-- ASSET STATUS SUMMARY BAR -->
    <div style="margin-top: 25px; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 14px; backdrop-filter: blur(8px); text-align: center;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; font-size: 1.1em;">
            
            <div>
                <div style="font-size: 2.8em; font-weight: bold; color: #00d2ff;">
                    <?= $total_assets ?>
                </div>
                <div style="color: #ccc;">Total Assets Protected</div>
            </div>

            <?php
            $immediate = $conn->query("SELECT COUNT(*) FROM individual_assets WHERE user_id = $user_id AND release_condition = 'immediate'")->fetch_row()[0] ?? 0;
            $on_hold   = $conn->query("SELECT COUNT(*) FROM individual_assets WHERE user_id = $user_id AND release_condition != 'immediate'")->fetch_row()[0] ?? 0;
            ?>

            <div>
                <div style="font-size: 2.8em; font-weight: bold; color: #4caf50;">
                    <?= $immediate ?>
                </div>
                <div style="color: #a0e0a0;">Ready for Release</div>
                <small style="color:#4caf50;">(After verification)</small>
            </div>

            <div>
                <div style="font-size: 2.8em; font-weight: bold; color: #ff9800;">
                    <?= $on_hold ?>
                </div>
                <div style="color: #ffcc80;">On Hold</div>
                <small style="color:#ff9800;">(Date or Manual)</small>
            </div>

            <div>
                <div style="font-size: 2.8em; font-weight: bold; color: <?= $will_exists ? '#8bc34a' : '#f44336' ?>;">
                    <?= $will_exists ? 'YES' : 'NO' ?>
                </div>
                <div style="color: #ccc;">Digital Will</div>
                <?php if (!$will_exists): ?>
                    <small><a href="digital_will.php" style="color:#00d2ff; text-decoration:underline;">Create Now</a></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <div class="stats">
        <div class="stat-card">
            <i class="fa fa-vault"></i>
            <h3><?= $total_assets ?></h3>
            <p>Total Assets</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-users"></i>
            <h3><?= $total_beneficiaries ?></h3>
            <p>Beneficiaries</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-scroll"></i>
            <h3><?= $will_exists ? 'Yes' : 'No' ?></h3>
            <p>Digital Will</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-shield-alt"></i>
            <h3>100%</h3>
            <p>Encrypted</p>
        </div>
    </div>

    <div style="text-align: right; margin: 20px 0;">
        <a href="individual_upload.php" class="btn">Add New Asset</a>
    </div>

    <div class="card">
        <div class="card-header">My Protected Assets</div>
        <div class="card-body">
            <?php if ($assets && $assets->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asset</th>
                            <th>Category</th>
                            <th>Beneficiaries</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($asset = $assets->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $asset['id'] ?></td>
                                <td><strong><?= htmlspecialchars($asset['asset_name'] ?? 'Untitled') ?></strong></td>
                                <td><?= ucfirst($asset['category'] ?? 'Other') ?></td>
                                <td><?= $asset['ben_count'] ?></td>
                                <td><span class="badge">Protected</span></td>
                                <td>
                                    <?php if (!empty($asset['document_path'])): ?>
                                        <a href="<?= htmlspecialchars($asset['document_path']) ?>" target="_blank" class="btn btn-sm">View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; padding:40px; font-size:1.2em;">
                    No assets protected yet.<br><br>
                    <a href="individual_upload.php" class="btn" style="font-size:1.2em; padding:15px 30px;">
                        Protect Your First Asset Now
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<a href="index.php" class="logout">Logout</a>

</body>
</html>