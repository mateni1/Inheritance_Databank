<?php
session_start();
require 'db_connection.php';
require_once 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? $_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: login.php"); exit();
}

$column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->num_rows > 0 ? 'role' : 'user_type';
function c($sql){ 
    global $conn; 
    $r = $conn->query($sql); 
    return ($r && $row = $r->fetch_row()) ? (int)$row[0] : 0; 
}

// === CORE STATS (100% SAFE - only real tables/columns) ===
$stats = [
    'users'        => c("SELECT COUNT(*) FROM users"),
    'parents'      => c("SELECT COUNT(*) FROM users WHERE `$column`='parents'"),
    'individuals'  => c("SELECT COUNT(*) FROM users WHERE `$column`='private_individual'"),
    'government'   => c("SELECT COUNT(*) FROM users WHERE `$column`='government'"),
    'special'      => c("SELECT COUNT(*) FROM users WHERE `$column` IN('special_gift_user','special_gift')"),
    'admins'       => c("SELECT COUNT(*) FROM users WHERE `$column`='admin'"),
    'parent_a'     => c("SELECT COUNT(*) FROM parent_assets"),
    'indiv_a'      => c("SELECT COUNT(*) FROM individual_assets"),
    'gov_a'        => c("SELECT COUNT(*) FROM government_assets"),
    'special_a'    => ($conn->query("SHOW TABLES LIKE 'special_gift_assets'")->num_rows > 0) 
                     ? c("SELECT COUNT(*) FROM special_gift_assets") : 0,
];
$stats['total_assets'] = $stats['parent_a'] + $stats['indiv_a'] + $stats['gov_a'] + $stats['special_a'];

// === SMART ANALYSIS & SUGGESTIONS (SAFE & ACCURATE) ===
$suggestions = [];

// 1. Low user base
if ($stats['users'] < 50) {
    $suggestions[] = "User registration is very low. Promote the platform through community leaders and radio.";
}
if ($stats['users'] == 0) {
    $suggestions[] = "No users registered yet. Start with a pilot group (e.g. 10 families).";
}

// 2. User type distribution
if ($stats['parents'] == 0 && $stats['users'] > 0) {
    $suggestions[] = "No parents registered. This is the core user group — target family offices and churches.";
}
if ($stats['individuals'] > $stats['parents'] * 3) {
    $suggestions[] = "Too many individual users compared to parents. Focus outreach on families with children.";
}

// 3. Asset registration health
if ($stats['total_assets'] == 0) {
    $suggestions[] = "No assets registered yet. Guide first users to upload documents (land titles, wills, etc.).";
}
if ($stats['total_assets'] > 0 && $stats['users'] > 0 && ($stats['total_assets'] / $stats['users']) < 1) {
    $suggestions[] = "Only " . round($stats['total_assets']/$stats['users'], 2) . " assets per user. Encourage users to register all their properties.";
}

// 4. Feature usage
if ($stats['special_a'] == 0) {
    $suggestions[] = "Special Gift feature not used. Promote it for charity donations and conditional wills.";
}
if ($stats['gov_a'] == 0) {
    $suggestions[] = "No government assets registered. Engage with ministries for official documents.";
}

// 5. Positive feedback
if ($stats['users'] >= 100 && $stats['total_assets'] >= 200) {
    $suggestions[] = "Excellent growth! System adoption is strong. Consider adding mobile app next.";
}

if (empty($suggestions)) {
    $suggestions[] = "System is healthy and growing steadily. Keep monitoring weekly.";
}

// === PDF EXPORT WITH SUGGESTIONS ===
if(isset($_POST['export_pdf'])){
    $dompdf = new Dompdf(['isRemoteEnabled'=>true]);
    $sug = "<h3>Improvement Suggestions</h3><ul>";
    foreach($suggestions as $s) $sug .= "<li>$s</li>";
    $sug .= "</ul>";

    $html = "<h2 style='text-align:center;color:#0d47a1;'>INHERITANCE DATABANK – ANALYTICS REPORT</h2>
             <p style='text-align:center;'><strong>Generated:</strong> ".date('d M Y H:i')." | Total Users: ".number_format($stats['users'])." | Total Assets: ".number_format($stats['total_assets'])."</p><hr>
             <table border=1 width=100% cellpadding=10 cellspacing=0><tr style='background:#0d47a1;color:white;'><th>User Type</th><th>Count</th></tr>
             <tr><td>Parents</td><td align=center><b>".number_format($stats['parents'])."</b></td></tr>
             <tr><td>Individuals</td><td align=center><b>".number_format($stats['individuals'])."</b></td></tr>
             <tr><td>Government Users</td><td align=center><b>".number_format($stats['government'])."</b></td></tr>
             <tr><td>Special Gift Users</td><td align=center><b>".number_format($stats['special'])."</b></td></tr>
             <tr><td>Administrators</td><td align=center><b>".number_format($stats['admins'])."</b></td></tr></table><br>
             <table border=1 width=100% cellpadding=10 cellspacing=0><tr style='background:#00695c;color:white;'><th>Asset Type</th><th>Total</th></tr>
             <tr><td>Parent Assets</td><td align=center><b>".number_format($stats['parent_a'])."</b></td></tr>
             <tr><td>Individual Assets</td><td align=center><b>".number_format($stats['indiv_a'])."</b></td></tr>
             <tr><td>Government Assets</td><td align=center><b>".number_format($stats['gov_a'])."</b></td></tr>
             <tr><td>Special Gift Assets</td><td align=center><b>".number_format($stats['special_a'])."</b></td></tr>
             <tr style='background:#1b5e20;color:white;'><td><b>TOTAL</b></td><td align=center><b>".number_format($stats['total_assets'])."</b></td></tr></table><br>
             $sug";
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream("Inheritance_Databank_Analysis_".date('Y-m-d').".pdf",["Attachment"=>true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inheritance Databank – Analytics</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f5f7fa; font-family:system-ui,sans-serif; padding:15px 0; color:#333; }
    .card { border:none; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:1rem; }
    .header { background:#0d47a1; color:white; padding:1.2rem; text-align:center; font-size:1.3rem; font-weight:600; }
    .num { font-size:2.2rem; font-weight:800; color:#0d47a1; }
    .total { background:#1b5e20; color:white; padding:0.8rem 1.5rem; border-radius:8px; font-size:1.8rem; font-weight:800; display:inline-block; }
    .box { background:#fff; border-radius:10px; padding:1rem; text-align:center; margin-bottom:0.8rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .box h6 { margin:0 0 0.4rem; color:#555; font-size:0.95rem; }
    .box .val { font-size:1.8rem; font-weight:700; color:#0d47a1; }
    .alert { border-radius:10px; padding:1rem; margin-bottom:1rem; font-size:0.95rem; }
    .btn { border-radius:30px; padding:0.6rem 1.8rem; font-weight:600; }
    @media (min-width:576px){
        .header{font-size:1.5rem;padding:1.5rem;}
        .num{font-size:3rem;}
        .total{font-size:2.4rem;}
        .box .val{font-size:2.2rem;}
    }
</style>
</head>
<body>

<div class="container">

    <div class="card mb-3">
        <div class="header">INHERITANCE DATABANK – SYSTEM ANALYTICS</div>
        <div class="card-body text-center py-4">
            <h5 class="text-muted mb-2">Total Users</h5>
            <div class="num"><?=number_format($stats['users'])?></div>
            <div class="total mt-3"><?=number_format($stats['total_assets'])?></div>
            <small class="d-block text-white mt-1 opacity-80">Total Assets</small>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="text-primary text-center mb-3 fw-bold">User Categories</h6>
            <div class="row g-2 text-center">
                <div class="col-6"><div class="box"><h6>Parents</h6><div class="val"><?=number_format($stats['parents'])?></div></div></div>
                <div class="col-6"><div class="box"><h6>Individuals</h6><div class="val"><?=number_format($stats['individuals'])?></div></div></div>
                <div class="col-6"><div class="box"><h6>Government</h6><div class="val"><?=number_format($stats['government'])?></div></div></div>
                <div class="col-6"><div class="box"><h6>Special Gift</h6><div class="val"><?=number_format($stats['special'])?></div></div></div>
                <div class="col-12"><div class="box bg-dark text-white"><h6>Admins</h6><div class="val"><?=number_format($stats['admins'])?></div></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="text-primary text-center mb-3 fw-bold">Registered Assets</h6>
            <div class="row g-2 text-center">
                <div class="col-6"><div class="box bg-primary text-white"><h6>Parent</h6><div class="val"><?=number_format($stats['parent_a'])?></div></div></div>
                <div class="col-6"><div class="box bg-info text-white"><h6>Individual</h6><div class="val"><?=number_format($stats['indiv_a'])?></div></div></div>
                <div class="col-6"><div class="box bg-success text-white"><h6>Government</h6><div class="val"><?=number_format($stats['gov_a'])?></div></div></div>
                <div class="col-6"><div class="box bg-warning text-dark"><h6>Special Gift</h6><div class="val"><?=number_format($stats['special_a'])?></div></div></div>
            </div>
        </div>
    </div>

    <!-- SMART SUGGESTIONS -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="text-success text-center mb-3 fw-bold">AI Improvement Suggestions</h6>
            <?php foreach($suggestions as $s): ?>
                <div class="alert alert-info border-0 mb-2 py-2">
                    <strong>Suggestion:</strong> <?=htmlspecialchars($s)?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="text-center">
        <form method="POST" class="d-inline">
            <button name="export_pdf" class="btn btn-danger">Export Full Analysis PDF</button>
        </form>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
    </div>
</div>

</body>
</html>