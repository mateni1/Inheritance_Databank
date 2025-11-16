<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$msg_type = "";

// Ensure settings table exists with all needed columns
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY,
    site_name VARCHAR(255) DEFAULT 'Rwanda Digital Inheritance Registry',
    tagline TEXT,
    logo_path VARCHAR(500),
    primary_color VARCHAR(7) DEFAULT '#0d47a1',
    secondary_color VARCHAR(7) DEFAULT '#00d2ff',
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 1,
    maintenance_mode TINYINT(1) DEFAULT 0,
    registration_open TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("INSERT IGNORE INTO settings (id) VALUES (1)");

// Fetch current settings
$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Update Site Info
    if (isset($_POST['update_info'])) {
        $site_name = trim($_POST['site_name']);
        $tagline = trim($_POST['tagline']);
        
        $stmt = $conn->prepare("UPDATE settings SET site_name = ?, tagline = ? WHERE id = 1");
        $stmt->bind_param("ss", $site_name, $tagline);
        if ($stmt->execute()) {
            $message = "Site information updated successfully!";
            $msg_type = "success";
            $settings['site_name'] = $site_name;
            $settings['tagline'] = $tagline;
        }
    }

    // 2. Update Logo
    if (isset($_POST['update_logo']) && isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $targetDir = "uploads/settings/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $allowed = ['jpg','jpeg','png','gif','webp'];
        $fileName = $_FILES['logo']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newName = "logo_" . time() . ".$ext";
            $targetPath = $targetDir . $newName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                // Delete old logo
                if (!empty($settings['logo_path']) && file_exists($settings['logo_path'])) {
                    unlink($settings['logo_path']);
                }

                $conn->query("UPDATE settings SET logo_path = '$targetPath' WHERE id = 1");
                $settings['logo_path'] = $targetPath;
                $message = "National logo updated successfully!";
                $msg_type = "success";
            }
        } else {
            $message = "Only image files allowed (JPG, PNG, GIF, WebP)";
            $msg_type = "danger";
        }
    }

    // 3. Update Colors
    if (isset($_POST['update_colors'])) {
        $primary = $_POST['primary_color'];
        $secondary = $_POST['secondary_color'];
        
        $stmt = $conn->prepare("UPDATE settings SET primary_color = ?, secondary_color = ? WHERE id = 1");
        $stmt->bind_param("ss", $primary, $secondary);
        if ($stmt->execute()) {
            $message = "Theme colors updated!";
            $msg_type = "success";
            $settings['primary_color'] = $primary;
            $settings['secondary_color'] = $secondary;
        }
    }

    // 4. System Toggles
    if (isset($_POST['update_system'])) {
        $email_on = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_on = isset($_POST['sms_notifications']) ? 1 : 0;
        $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
        $reg_open = isset($_POST['registration_open']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE settings SET 
            email_notifications = ?, sms_notifications = ?, 
            maintenance_mode = ?, registration_open = ? WHERE id = 1");
        $stmt->bind_param("iiii", $email_on, $sms_on, $maintenance, $reg_open);
        
        if ($stmt->execute()) {
            $message = "System settings updated successfully!";
            $msg_type = "success";
            $settings['email_notifications'] = $email_on;
            $settings['sms_notifications'] = $sms_on;
            $settings['maintenance_mode'] = $maintenance;
            $settings['registration_open'] = $reg_open;
        }
    }

    // 5. Database Backup
    if (isset($_POST['backup_db'])) {
        $backup_file = "backups/backup_" . date('Y-m-d_H-i-s') . ".sql";
        if (!is_dir('backups')) mkdir('backups', 0777, true);

        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $sql = "-- Rwanda Digital Inheritance Registry Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
            $sql .= "$create;\n\n";

            $rows = $conn->query("SELECT * FROM `$table`");
            while ($row = $rows->fetch_assoc()) {
                $sql .= "INSERT INTO `$table` VALUES (";
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
                }
                $sql .= implode(",", $values) . ");\n";
            }
            $sql .= "\n";
        }

        if (file_put_contents($backup_file, $sql)) {
            $message = "Database backup created: <strong>$backup_file</strong>";
            $msg_type = "success";
        } else {
            $message = "Failed to create backup.";
            $msg_type = "danger";
        }
    }
}

// Default logo if none set
$logo = !empty($settings['logo_path']) ? $settings['logo_path'] : "assets/rwanda-logo.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National Admin Control Center | Rwanda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= $settings['primary_color'] ?>;
            --secondary: <?= $settings['secondary_color'] ?>;
        }
        body { 
            background: linear-gradient(135deg, #0d47a1, #1a237e); 
            font-family: 'Segoe UI', sans-serif; 
            min-height: 100vh; 
            color: #333;
        }
        .container { max-width: 1000px; margin: 30px auto; }
        .card { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); 
            overflow: hidden; 
            margin-bottom: 30px;
            background: white;
        }
        .card-header { 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            color: white; 
            padding: 25px; 
            text-align: center; 
            font-size: 1.8em; 
            font-weight: bold;
        }
        .card-body { padding: 40px; }
        .logo-preview { 
            width: 180px; 
            height: 180px; 
            object-fit: contain; 
            border: 4px solid #ddd; 
            border-radius: 20px; 
            margin: 20px auto; 
            display: block;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-control, .form-check-input { border-radius: 12px; }
        .btn-primary { 
            background: var(--secondary); 
            border: none; 
            padding: 15px 40px; 
            border-radius: 50px; 
            font-size: 1.2em; 
            font-weight: bold;
        }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-danger { background: #dc3545; }
        .alert { border-radius: 15px; text-align: center; font-size: 1.3em; padding: 25px; }
        .section-title { 
            color: var(--primary); 
            font-weight: bold; 
            font-size: 1.6em; 
            margin: 30px 0 20px; 
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--secondary);
        }
        .maintenance-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: bold;
            z-index: 9999;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>

<?php if ($settings['maintenance_mode']): ?>
    <div class="maintenance-badge">
        MAINTENANCE MODE ACTIVE
    </div>
<?php endif; ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Republic of Rwanda<br>
            <small style="font-size:0.5em; opacity:0.9;">National Digital Inheritance Registry</small>
        </div>
        <div class="card-body">
            <h1 class="text-center mb-4" style="color: var(--primary);">
                ADMIN CONTROL CENTER
            </h1>
            <p class="text-center lead mb-5">
                Logged in as: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></strong>
            </p>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                    <strong><?= $msg_type === 'success' ? 'Success' : 'Error' ?>!</strong> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Site Information -->
            <div class="section-title">
                National Branding
            </div>
            <div class="row">
                <div class="col-md-6">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Site Name</label>
                            <input type="text" name="site_name" class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tagline</label>
                            <input type="text" name="tagline" class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>" placeholder="e.g. Securing Tomorrow, Today">
                        </div>
                        <button type="submit" name="update_info" class="btn btn-primary">
                            Update Information
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" enctype="multipart/form-data">
                        <label class="form-label fw-bold">National Logo</label>
                        <img src="<?= $logo ?>" alt="Logo" class="logo-preview">
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <button type="submit" name="update_logo" class="btn btn-success mt-3">
                            Upload New Logo
                        </button>
                    </form>
                </div>
            </div>

            <!-- Theme Colors -->
            <div class="section-title mt-5">
                Theme & Appearance
            </div>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Primary Color</label>
                    <input type="color" name="primary_color" class="form-control form-control-lg form-control-color" 
                           value="<?= $settings['primary_color'] ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Secondary Color</label>
                    <input type="color" name="secondary_color" class="form-control form-control-lg form-control-color" 
                           value，放$settings['secondary_color'] ?>">
                </div>
                <div class="col-12 text-center">
                    <button type="submit" name="update_colors" class="btn btn-warning">
                        Apply Theme
                    </button>
                </div>
            </form>

            <!-- System Controls -->
            <div class="section-title mt-5">
                System Controls
            </div>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="email_notifications" 
                                   <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold">Email Notifications</label>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                   <?= $settings['sms_notifications'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold">SMS Notifications</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                   <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-danger">Maintenance Mode</label>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="registration_open" 
                                   <?= $settings['registration_open'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold">Allow New Registrations</label>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" name="update_system" class="btn btn-primary btn-lg">
                        Save System Settings
                    </button>
                </div>
            </form>

            <!-- Database Backup -->
            <div class="section-title mt-5">
                Database & Security
            </div>
            <div class="text-center">
                <form method="POST" class="d-inline">
                    <button type="submit" name="backup_db" class="btn btn-danger btn-lg">
                        CREATE FULL BACKUP NOW
                    </button>
                </form>
                <p class="mt-3 text-muted">
                    Last backup: <?= date('d M Y \a\t H:i') ?> | 
                    Total tables: <?= $conn->query("SHOW TABLES")->num_rows ?>
                </p>
            </div>

            <div class="text-center mt-5">
                <a href="admin_dashboard.php" class="btn btn-outline-primary btn-lg">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
