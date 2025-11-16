<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'private_individual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user
$stmt = $conn->prepare("SELECT full_name, email, phone FROM private_individuals WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Stats
$total_assets = $conn->query("SELECT COUNT(*) FROM individual_assets WHERE user_id = $user_id")->fetch_row()[0];
$total_beneficiaries = $conn->query("SELECT COUNT(*) FROM individual_beneficiaries WHERE user_id = $user_id")->fetch_row()[0];
$will_exists = $conn->query("SELECT 1 FROM digital_will WHERE user_id = $user_id")->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inheritance Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #00d2ff; --dark: #283e51; --light: #f8f9fa; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #283e51, #485563); color: #fff; margin:0; padding:0; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; }
        .header { background: rgba(255,255,255,0.1); border-radius: 16px; padding: 30px; text-align: center; backdrop-filter: blur(10px); }
        .header h1 { margin: 0; font-size: 2.5em; }
        .navbar { display: flex; justify-content: space-between; background: rgba(0,0,0,0.4); padding: 15px 30px; border-radius: 12px; margin: 20px 0; }
        .navbar a { color: #fff; text-decoration: none; margin: 0 15px; font-weight: 600; }
        .navbar a:hover { color: var(--primary); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-card { background: rgba(255,255,255,0.15); padding: 25px; border-radius: 14px; text-align: center; backdrop-filter: blur(8px); }
        .stat-card i { font-size: 3em; color: var(--primary); margin-bottom: 15px; }
        .stat-card h3 { margin: 10px 0; font-size: 2.2em; }
        .sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; margin-top: 30px; }
        .section { background: rgba(255,255,255,0.12); padding: 25px; border-radius: 14px; transition: 0.3s; }
        .section:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
        .section h3 { color: var(--primary); margin-top: 0; }
        .btn { background: var(--primary); color: #000; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-block; margin-top: 15px; }
        .btn:hover { background: #5ce1e6; }
        footer { text-align: center; margin-top: 50px; opacity: 0.8; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>My Digital Inheritance</h1>
        <p>Welcome back, <strong><?= htmlspecialchars($user['full_name']); ?></strong></p>
    </div>

    <div class="navbar">
        <span>Secure • Private • Forever</span>
        <div>
            <a href="individual_dashboard.php"><i class="fa fa-home"></i> Home</a>
            <a href="individual_upload.php"><i class="fa fa-plus-circle"></i> Add Asset</a>
            <a href="my_beneficiaries.php"><i class="fa fa-users"></i> Beneficiaries</a>
            <a href="digital_will.php"><i class="fa fa-scroll"></i> My Will</a>
            <a href="logout.php" style="color:#ff6b6b;"><i class="fa fa-sign-out"></i> Logout</a>
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
            <h3><?= $will_exists ? 'Yes' : 'Not yet' ?></h3>
            <p>Digital Will</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-shield-alt"></i>
            <h3>100%</h3>
            <p>Encrypted</p>
        </div>
    </div>

    <div class="sections">
        <div class="section">
            <h3>Add Your Assets</h3>
            <p>Register land, houses, vehicles, crypto, bank accounts, jewelry, and more.</p>
            <ul style="padding-left:20px;">
                <li>Land & Property</li>
                <li>Vehicles</li>
                <li>Crypto Wallets</li>
                <li>Bank Accounts</li>
                <li>Jewelry & Art</li>
            </ul>
            <a href="individual_upload.php" class="btn">Upload Asset Now</a>
        </div>

        <div class="section">
            <h3>Manage Beneficiaries</h3>
            <p>Add your loved ones and decide who gets what.</p>
            <ul style="padding-left:20px;">
                <li>Add photo & ID</li>
                <li>Set inheritance %</li>
                <li>Auto-notify on release</li>
            </ul>
            <a href="my_beneficiaries.php" class="btn">Manage Family</a>
        </div>

        <div class="section">
            <h3>Create Digital Will</h3>
            <p>Record a video message or write your final wishes.</p>
            <ul style="padding-left:20px;">
                <li>Video message</li>
                <li>Written will</li>
                <li>Scheduled release</li>
            </ul>
            <a href="digital_will.php" class="btn">Create Will</a>
        </div>

        <div class="section">
            <h3>Security First</h3>
            <p>Your data is encrypted and only released when conditions are met.</p>
            <ul style="padding-left:20px;">
                <li>End-to-end encryption</li>
                <li>Death verification</li>
                <li>2FA protected</li>
            </ul>
            <a href="security_settings.php" class="btn">Security Settings</a>
        </div>
    </div>

    <footer>
        © <?= date('Y') ?> Digital Inheritance System — Protecting Your Legacy Forever
    </footer>
</div>

</body>
</html>