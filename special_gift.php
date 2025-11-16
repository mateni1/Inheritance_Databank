<?php
session_start();
require 'db_connection.php';

// ONLY SPECIAL GIFT USERS — CORRECT ROLE
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'special_gift') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ————————————————————————
// SAFELY GET USER INFO FROM MAIN 'users' TABLE
// ————————————————————————
$display_name = $_SESSION['username'] ?? 'Gift Bearer';
$email = $phone = 'Not set';
$profile_pic = $_SESSION['profile_pic'] ?? 'uploads/avatars/default-avatar.png';

$stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ? AND user_type = 'special_gift' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $display_name = $row['full_name'] ?: $display_name;
    $email = $row['email'] ?? $email;
    $phone = $row['phone'] ?? $phone;
}

// ————————————————————————
// AUTO CREATE special_gifts TABLE
// ————————————————————————
$conn->query("CREATE TABLE IF NOT EXISTS special_gifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    giver_id INT NOT NULL,
    asset_name VARCHAR(255) NOT NULL,
    asset_type VARCHAR(100),
    asset_value DECIMAL(15,2),
    description TEXT,
    file_path VARCHAR(500),
    status ENUM('pending','delivered','archived') DEFAULT 'pending',
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (giver_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ————————————————————————
// STATS & GIFTS
// ————————————————————————
$total_gifts     = $conn->query("SELECT COUNT(*) FROM special_gifts WHERE giver_id = $user_id")->fetch_row()[0] ?? 0;
$total_value     = $conn->query("SELECT COALESCE(SUM(asset_value), 0) FROM special_gifts WHERE giver_id = $user_id")->fetch_row()[0] ?? 0;
$pending         = $conn->query("SELECT COUNT(*) FROM special_gifts WHERE giver_id = $user_id AND status = 'pending'")->fetch_row()[0] ?? 0;
$delivered       = $conn->query("SELECT COUNT(*) FROM special_gifts WHERE giver_id = $user_id AND status = 'delivered'")->fetch_row()[0] ?? 0;

$gifts = $conn->query("SELECT * FROM special_gifts WHERE giver_id = $user_id ORDER BY uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Gift Bearer • Eternal Legacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root { --pri: #9c27b0; --love: #e91e63; --dark: #1a0033; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #1a0033, #4a148c); color:#fff; margin:0; min-height:100vh; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; }
        .header { background: rgba(255,255,255,0.12); padding: 40px; border-radius: 24px; text-align: center; backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); position:relative; }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; border: 6px solid var(--pri); object-fit: cover; box-shadow: 0 0 30px rgba(156,39,176,0.6); }
        .header h1 { font-size: 3em; color: var(--pri); margin: 15px 0; }
        .stats-bar { margin: 30px 0; padding: 25px; background: rgba(0,0,0,0.3); border-radius: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 25px; text-align: center; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 40px 0; }
        .stat-card { background: rgba(255,255,255,0.15); padding: 30px; border-radius: 20px; text-align: center; }
        .stat-card i { font-size: 3.5em; color: var(--pri); margin-bottom: 15px; }
        .stat-card h3 { font-size: 2.8em; margin: 10px 0; }
        .card { background: rgba(255,255,255,0.1); border-radius: 20px; overflow: hidden; margin: 30px 0; border: 1px solid rgba(255,255,255,0.1); }
        .card-header { background: var(--pri); color: white; padding: 22px; font-size: 1.7em; font-weight: bold; text-align: center; }
        .card-body { padding: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 18px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(0,0,0,0.4); }
        .btn { background: var(--pri); color: white; padding: 16px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-block; margin: 15px; transition: 0.3s; }
        .btn:hover { background: var(--love); transform: translateY(-5px); }
        .btn-upload { background: var(--love); font-size: 1.4em; padding: 20px 50px; }
        .status-pending { background: #ff9800; color:#000; padding:8px 16px; border-radius:30px; font-weight:bold; }
        .status-delivered { background: #4caf50; color:white; }
        .status-archived { background: #607d8b; color:white; }
        .logout { position: absolute; top: 20px; right: 30px; background: #e74c3c; padding: 12px 28px; border-radius: 50px; color: white; text-decoration: none; font-weight: bold; }
        .logout:hover { background: #c0392b; }
        .no-data { text-align:center; padding:80px; font-size:1.5em; opacity:0.8; }
    </style>
</head>
<body>

<a href="index.php" class="logout">Logout</a>

<div class="container">
    <div class="header">
        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic">
        <h1>Special Gift Bearer</h1>
        <p>Preserving love beyond a lifetime</p>
        <p>Welcome, <strong><?= htmlspecialchars($display_name) ?></strong></p>
        <small><?= htmlspecialchars($email) ?> • <?= htmlspecialchars($phone) ?></small>

        <div class="stats-bar">
            <div class="stats-grid">
                <div><div style="font-size:3em; color:var(--pri);"><?= $total_gifts ?></div><div>Gifts Recorded</div></div>
                <div><div style="font-size:3em; color:#e91e63;"><?= number_format($total_value) ?> RWF</div><div>Total Value</div></div>
                <div>< 
<div style="font-size:3em; color:#ff9800;"><?= $pending ?></div><div>Pending</div></div>
                <div><div style="font-size:3em; color:#4caf50;"><?= $delivered ?></div><div>Delivered</div></div>
            </div>
        </div>
    </div>

    <div style="text-align:center; margin:60px 0;">
        <a href="special_gift_upload.php" class="btn btn-upload">Upload New Gift</a>
    </div>

    <div class="card">
        <div class="card-header">Your Eternal Gifts</div>
        <div class="card-body">
            <?php if ($gifts && $gifts->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Gift</th><th>Type</th><th>Value</th><th>Status</th><th>Date</th><th>File</th></tr></thead>
                    <tbody>
                        <?php while($g = $gifts->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($g['asset_name']) ?></strong></td>
                            <td><?= ucfirst($g['asset_type'] ?? 'Gift') ?></td>
                            <td><?= number_format($g['asset_value']) ?> RWF</td>
                            <td><span class="status-<?= $g['status'] ?>"><?= ucfirst($g['status']) ?></span></td>
                            <td><?= date('d M Y', strtotime($g['uploaded_at'])) ?></td>
                            <td><?php if($g['file_path']): ?><a href="<?= $g['file_path'] ?>" target="_blank" style="color:var(--pri);">View</a><?php endif; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    No gifts yet. Your legacy starts with one act of love.<br><br>
                    <a href="special_gift_upload.php" class="btn btn-upload">Record Your First Gift</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>