<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'private_individual') {
    header("Location: login.php");
    exit();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_asset'])) {
    $asset_name = $_POST['asset_name'];
    $category   = $_POST['category'];
    $value      = $_POST['value'] ?? '';
    $location   = $_POST['location'] ?? '';
    $description = $_POST['description'];
    $release_condition = $_POST['release_condition'];
    $release_date = $_POST['release_date'] ?? null;

    $upload_dir = 'uploads/individual/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $filePath = null;
    if ($_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $filePath = $upload_dir . time() . "_ind_" . $_SESSION['user_id'] . ".$ext";
        move_uploaded_file($_FILES['document']['tmp_name'], $filePath);
    }

    $stmt = $conn->prepare("INSERT INTO individual_assets 
        (user_id, asset_name, category, estimated_value, location, description, document_path, release_condition, release_date, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssssss", $_SESSION['user_id'], $asset_name, $category, $value, $location, $description, $filePath, $release_condition, $release_date);
    
    if ($stmt->execute()) {
        $asset_id = $conn->insert_id;

        if (!empty($_POST['ben_name'])) {
            $bStmt = $conn->prepare("INSERT INTO individual_beneficiaries 
                (asset_id, user_id, full_name, national_id, phone, email, photo_path, relationship, share_percent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['ben_name'] as $i => $name) {
                if (empty(trim($name))) continue;
                $nid = $_POST['ben_nid'][$i] ?? '';
                $phone = $_POST['ben_phone'][$i] ?? '';
                $email = $_POST['ben_email'][$i] ?? '';
                $relation = $_POST['ben_relation'][$i] ?? '';
                $share = $_POST['ben_share'][$i] ?? 0;

                $photoPath = null;
                if (isset($_FILES['ben_photo']['error'][$i]) && $_FILES['ben_photo']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['ben_photo']['name'][$i], PATHINFO_EXTENSION);
                    $photoPath = $upload_dir . "photo_" . time() . "_$i.$ext";
                    move_uploaded_file($_FILES['ben_photo']['tmp_name'][$i], $photoPath);
                }

                $bStmt->bind_param("iisssssid", $asset_id, $_SESSION['user_id'], $name, $nid, $phone, $email, $photoPath, $relation, $share);
                $bStmt->execute();
            }
        }
        $msg = "Asset successfully protected!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Asset - Private Individual</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #283e51, #485563); color: #fff; margin:0; padding:20px; }
        .container { max-width: 1000px; margin: auto; background: rgba(255,255,255,0.1); padding: 30px; border-radius: 16px; backdrop-filter: blur(12px); }
        h2 { text-align:center; color:#00d2ff; margin-bottom:30px; }
        .tab { overflow:hidden; background:rgba(0,0,0,0.4); border-radius:12px 12px 0 0; }
        .tab button { background:none; border:none; color:white; padding:16px 25px; cursor:pointer; font-size:16px; }
        .tab button.active, .tab button:hover { background:#00d2ff; color:#000; }
        .tabcontent { padding:30px; display:none; }
        .tabcontent.active { display:block; }
        label { display:block; margin:15px 0 8px; font-weight:600; }
        input, select, textarea { width:100%; padding:12px; border:none; border-radius:8px; background:rgba(255,255,255,0.2); color:white; }
        .row { display:flex; gap:20px; }
        .col { flex:1; }
        .benef { background:rgba(0,0,0,0.3); padding:20px; border-radius:12px; margin:20px 0; position:relative; }
        .remove { position:absolute; top:10px; right:10px; color:#ff6b6b; cursor:pointer; font-weight:bold; }
        button { background:#00d2ff; color:#000; padding:15px; border:none; border-radius:50px; font-size:18px; cursor:pointer; width:100%; margin-top:20px; font-weight:bold; }
    </style>
</head>
<body>

<div class="container">
    <h2>Register Your Asset & Protect Your Legacy</h2>
    <?php if($msg) echo "<p style='color:#00ff00; text-align:center; font-weight:bold;'>$msg</p>"; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="tab">
            <button type="button" class="tablinks active" onclick="openTab(event,'Asset')">Asset</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Location')">Location & Value</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Beneficiaries')">Beneficiaries</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Release')">Release Settings</button>
        </div>

        <div id="Asset" class="tabcontent active">
            <label>Asset Name</label>
            <input type="text" name="asset_name" required>
            <label>Category</label>
            <select name="category" required>
                <option>Land</option><option>House</option><option>Vehicle</option><option>Bank Account</option>
                <option>Crypto</option><option>Jewelry</option><option>Business</option><option>Other</option>
            </select>
            <label>Upload Document (Title, Photo, etc.)</label>
            <input type="file" name="document" required accept=".pdf,.jpg,.png">
            <label>Description</label>
            <textarea name="description" rows="4"></textarea>
        </div>

        <div id="Location" class="tabcontent">
            <label>Location (e.g. District, Sector)</label>
            <input type="text" name="location" placeholder="Kacyiru, Gasabo, Kigali">
            <label>Estimated Value (RWF)</label>
            <input type="text" name="value" placeholder="150,000,000">
        </div>

        <div id="Beneficiaries" class="tabcontent">
            <p><button type="button" onclick="addBeneficiary()">+ Add Beneficiary</button></p>
            <div id="beneficiaries"></div>
        </div>

        <div id="Release" class="tabcontent">
            <label>Release Condition</label>
            <select name="release_condition">
                <option value="immediate">Immediate (on death verification)</option>
                <option value="after_date">After Specific Date</option>
                <option value="manual">Manual Release by Trusted Person</option>
            </select>
            <label>Release Date (optional)</label>
            <input type="datetime-local" name="release_date">
            <button type="submit" name="upload_asset">PROTECT THIS ASSET FOREVER</button>
        </div>
    </form>
</div>

<script>
function openTab(evt, tabName) {
    document.querySelectorAll(".tabcontent").forEach(t => t.classList.remove("active"));
    document.querySelectorAll(".tablinks").forEach(b => b.classList.remove("active"));
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
let benCount = 0;
function addBeneficiary() {
    benCount++;
    const div = document.createElement("div");
    div.className = "benef";
    div.innerHTML = `
        <span class="remove" onclick="this.parentElement.remove()">X</span>
        <h4>Beneficiary ${benCount}</h4>
        <div class="row"><div class="col"><label>Full Name</label><input type="text" name="ben_name[]" required></div>
        <div class="col"><label>National ID</label><input type="text" name="ben_nid[]"></div></div>
        <label>Phone (WhatsApp)</label><input type="text" name="ben_phone[]">
        <label>Email</label><input type="email" name="ben_email[]">
        <label>Relationship</label><input type="text" name="ben_relation[]">
        <label>Share %</label><input type="number" name="ben_share[]" min="1" max="100" value="100">
        <label>Photo</label><input type="file" name="ben_photo[]" accept="image/*">
    `;
    document.getElementById("beneficiaries").appendChild(div);
}
addBeneficiary();
</script>

</body>
</html>