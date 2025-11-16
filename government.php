<?php
session_start();
require 'db_connection.php'; // Make sure this file connects properly

// Security: Only allow logged-in government users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'government') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// === Fetch user info safely (no error even if 'institution' column is missing) ===
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$full_name   = $user['full_name'] ?? 'Government User';
$email       = $user['email'] ?? 'N/A';
$institution = 'Government Agency'; // You can later add a column and fill it per user

$stmt->close();

// === Statistics ===
$total_assets = $conn->query("SELECT COUNT(*) FROM government_assets WHERE user_id = $user_id")->fetch_row()[0];

$pending_verification = $conn->query("
    SELECT COUNT(*) FROM government_assets 
    WHERE user_id = $user_id AND require_verification = 1
")->fetch_row()[0];

$released_assets = $conn->query("
    SELECT COUNT(DISTINCT asset_id) FROM beneficiaries b
    INNER JOIN government_assets ga ON b.asset_id = ga.id
    WHERE ga.user_id = $user_id AND b.notified = 1
")->fetch_row()[0];

$pending_notifications = $conn->query("
    SELECT COUNT(*) FROM beneficiaries b
    INNER JOIN government_assets ga ON b.asset_id = ga.id
    WHERE ga.user_id = $user_id AND b.notified = 0
")->fetch_row()[0];

// === Fetch all assets with beneficiary summary ===
$assets_sql = "
    SELECT ga.*,
           COUNT(b.id) AS total_beneficiaries,
           SUM(CASE WHEN b.notified = 1 THEN 1 ELSE 0 END) AS notified_count
    FROM government_assets ga
    LEFT JOIN beneficiaries b ON ga.id = b.asset_id
    WHERE ga.user_id = ?
    GROUP BY ga.id
    ORDER BY ga.uploaded_at DESC
";
$stmt = $conn->prepare($assets_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Dashboard - Inheritance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a237e;
            --success: #2e7d32;
            --warning: #ff8f00;
            --danger: #c62828;
            --light: #f8f9fa;
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        .header { background: var(--primary); color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 2.2em; }
        .user-info { margin-top: 12px; font-size: 1.1em; opacity: 0.95; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        .stat-card i { font-size: 3em; margin-bottom: 15px; opacity: 0.8; }
        .stat-card h3 { margin: 10px 0; font-size: 2.4em; color: var(--primary); }
        .stat-card p { margin: 0; color: #555; font-weight: 600; font-size: 1.1em; }

        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 18px 25px; font-size: 1.4em; font-weight: 600; }
        .card-body { padding: 25px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f5f7fa; padding: 16px; text-align: left; font-weight: 600; border-bottom: 3px solid #ddd; }
        td { padding: 16px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9ff; }

        .badge { padding: 7px 14px; border-radius: 30px; font-size: 0.85em; font-weight: bold; }
        .badge-success { background: #e8f5e9; color: var(--success); }
        .badge-warning { background: #fff3e0; color: var(--warning); }
        .badge-danger { background: #ffebee; color: var(--danger); }
        .badge-info { background: #e3f2fd; color: #1565c0; }

        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; margin: 5px 5px 5px 0; font-size: 0.95em; }
        .btn-sm { padding: 6px 12px; font-size: 0.85em; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: #d32f2f; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .text-center { text-align: center; }
        .text-muted { color: #777; font-size: 0.9em; }
        .actions a { margin-right: 8px; }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #ddd; margin-bottom: 15px; border-radius: 10px; overflow: hidden; background: white; }
            td { border: none; position: relative; padding-left: 50%; }
            td:before { content: attr(data-label); position: absolute; left: 15px; width: 45%; font-weight: bold; color: #555; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-shield-alt"></i> Government Inheritance Portal</h1>
        <div class="user-info">
            Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong><br>
            <?php echo htmlspecialchars($email); ?> • <?php echo htmlspecialchars($institution); ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-upload" style="color: var(--primary);"></i>
            <h3><?php echo $total_assets; ?></h3>
            <p>Total Assets Uploaded</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock" style="color: var(--warning);"></i>
            <h3><?php echo $pending_verification; ?></h3>
            <p>Awaiting Verification</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle" style="color: var(--success);"></i>
            <h3><?php echo $released_assets; ?></h3>
            <p>Assets Released</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-bell" style="color: #6a1b9a;"></i>
            <h3><?php echo $pending_notifications; ?></h3>
            <p>Pending Notifications</p>
        </div>
    </div>

    <!-- Upload New Asset Button -->
    <div style="text-align: right; margin-bottom: 20px;">
        <a href="government_asset_upload.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Upload New Asset
        </a>
    </div>

    <!-- Assets Table -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-file-alt"></i> My Uploaded Assets
        </div>
        <div class="card-body">
            <?php if ($assets->num_rows === 0): ?>
                <p class="text-center">No assets uploaded yet. <a href="government_asset_upload.php">Upload your first document</a>.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asset Name</th>
                            <th>Type</th>
                            <th>Beneficiaries</th>
                            <th>Status</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($asset = $assets->fetch_assoc()): ?>
                            <?php
                            // Determine status
                            $status_text = "Pending";
                            $badge_class = "badge-warning";

                            if (!empty($asset['release_date']) && strtotime($asset['release_date']) <= time()) {
                                $status_text = "Released (Date)";
                                $badge_class = "badge-success";
                            } elseif ($asset['require_verification'] == 0 && empty($asset['release_date'])) {
                                $status_text = "Auto Released";
                                $badge_class = "badge-success";
                            } elseif ($asset['notified_count'] == $asset['total_beneficiaries'] && $asset['total_beneficiaries'] > 0) {
                                $status_text = "Fully Notified";
                                $badge_class = "badge-success";
                            } elseif ($asset['require_verification'] == 1) {
                                $status_text = "Awaiting Verification";
                                $badge_class = "badge-danger";
                            }
                            ?>
                            <tr>
                                <td data-label="ID">#<?php echo $asset['id']; ?></td>
                                <td data-label="Name"><strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong></td>
                                <td data-label="Type"><span class="badge badge-info"><?php echo ucfirst($asset['asset_type'] ?? 'document'); ?></span></td>
                                <td data-label="Beneficiaries">
                                    <?php echo $asset['total_beneficiaries']; ?> total<br>
                                    <small class="text-muted"><?php echo $asset['notified_count']; ?> notified</small>
                                </td>
                                <td data-label="Status">
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td data-label="Uploaded"><?php echo date('d M Y H:i', strtotime($asset['uploaded_at'] ?? 'now')); ?></td>
                                <td data-label="Actions" class="actions">
                                    <a href="<?php echo htmlspecialchars($asset['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary" title="View File">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="view_asset_details.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-success" title="View Details">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <?php if ($asset['require_verification'] == 1): ?>
                                        <a href="verify_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-sm btn-warning" title="Verify & Release">
                                            <i class="fas fa-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>

<?php
// Clean up
$conn->close();
?>