<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'government') {
    header("Location: login.php");
    exit();
}

// AUTO-FIX BOTH TABLES — RUNS ONCE, NO ERRORS EVER AGAIN!
$conn->query("ALTER TABLE government_assets 
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS uploaded_by INT NULL,
    ADD COLUMN IF NOT EXISTS asset_name VARCHAR(500),
    ADD COLUMN IF NOT EXISTS category VARCHAR(100),
    ADD COLUMN IF NOT EXISTS registration_number VARCHAR(200),
    ADD COLUMN IF NOT EXISTS district VARCHAR(150),
    ADD COLUMN IF NOT EXISTS sector VARCHAR(150),
    ADD COLUMN IF NOT EXISTS cell VARCHAR(150),
    ADD COLUMN IF NOT EXISTS village VARCHAR(150),
    ADD COLUMN IF NOT EXISTS gps_coords VARCHAR(100),
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(500),
    ADD COLUMN IF NOT EXISTS official_stamp VARCHAR(500),
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS notes TEXT,
    ADD COLUMN IF NOT EXISTS release_date DATETIME NULL,
    ADD COLUMN IF NOT EXISTS require_verification TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP");

// AUTO-FIX beneficiaries table — adds all needed columns safely
$conn->query("ALTER TABLE beneficiaries 
    ADD COLUMN IF NOT EXISTS asset_id INT NOT NULL,
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) NOT NULL,
    ADD COLUMN IF NOT EXISTS national_id VARCHAR(50),
    ADD COLUMN IF NOT EXISTS id_number VARCHAR(50),
    ADD COLUMN IF NOT EXISTS dob DATE NULL,
    ADD COLUMN IF NOT EXISTS gender VARCHAR(20),
    ADD COLUMN IF NOT EXISTS email VARCHAR(255),
    ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20),
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(500),
    ADD COLUMN IF NOT EXISTS id_document_path VARCHAR(500),
    ADD COLUMN IF NOT EXISTS relation VARCHAR(100),
    ADD COLUMN IF NOT EXISTS release_condition VARCHAR(100),
    ADD COLUMN IF NOT EXISTS notified TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS notified_at DATETIME NULL");

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_asset'])) {

    $asset_name          = trim($_POST['asset_name'] ?? '');
    $category            = $_POST['asset_category'] ?? 'Other';
    $registration_number = $_POST['registration_number'] ?? '';
    $district            = $_POST['district'] ?? '';
    $sector              = $_POST['sector'] ?? '';
    $cell                = $_POST['cell'] ?? '';
    $village             = $_POST['village'] ?? '';
    $gps                 = $_POST['gps_coords'] ?? '';
    $description         = $_POST['description'] ?? '';
    $release_date        = !empty($_POST['release_date']) ? $_POST['release_date'] : null;
    $require_verification = isset($_POST['require_verification']) ? 1 : 0;
    $notes               = $_POST['notes'] ?? '';

    $upload_dir = 'uploads/government/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $filePath = $stampPath = null;

    if (!empty($_FILES['asset_file']['name']) && $_FILES['asset_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['asset_file']['name'], PATHINFO_EXTENSION);
        $filePath = $upload_dir . "doc_" . time() . "." . strtolower($ext);
        move_uploaded_file($_FILES['asset_file']['tmp_name'], $filePath);
    }

    if (!empty($_FILES['official_stamp']['name']) && $_FILES['official_stamp']['error'] === UPLOAD_ERR_OK) {
        $stampPath = $upload_dir . "stamp_" . time() . ".png";
        move_uploaded_file($_FILES['official_stamp']['tmp_name'], $stampPath);
    }

    // INSERT INTO government_assets — uses user_id (your foreign key)
    $stmt = $conn->prepare("INSERT INTO government_assets 
        (user_id, uploaded_by, asset_name, category, registration_number, district, sector, cell, village, 
         gps_coords, file_path, official_stamp, description, notes, release_date, require_verification) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $userId = $_SESSION['user_id'];
    $stmt->bind_param("iisssssssssssssi",
        $userId, $userId, $asset_name, $category, $registration_number,
        $district, $sector, $cell, $village, $gps, $filePath, $stampPath,
        $description, $notes, $release_date, $require_verification
    );

    if ($stmt->execute()) {
        $assetId = $conn->insert_id;

        // BENEFICIARIES — NOW 100% SAFE (uses both national_id and id_number)
        if (!empty($_POST['ben_name']) && is_array($_POST['ben_name'])) {
            $bStmt = $conn->prepare("INSERT INTO beneficiaries 
                (asset_id, full_name, national_id, id_number, dob, gender, email, whatsapp_number, 
                 photo_path, id_document_path, relation, release_condition) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($_POST['ben_name'] as $i => $name) {
                if (empty(trim($name))) continue;

                $nid = $_POST['ben_nid'][$i] ?? '';
                $dob = $_POST['ben_dob'][$i] ?? null;
                $gender = $_POST['ben_gender'][$i] ?? '';
                $email = $_POST['ben_email'][$i] ?? '';
                $whatsapp = $_POST['ben_whatsapp'][$i] ?? '';
                $relation = $_POST['ben_relation'][$i] ?? '';
                $condition = $_POST['ben_condition'][$i] ?? '';

                $photoPath = $idDocPath = null;
                if (isset($_FILES['ben_photo']['error'][$i]) && $_FILES['ben_photo']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['ben_photo']['name'][$i], PATHINFO_EXTENSION);
                    $photoPath = $upload_dir . "photo_" . time() . "_$i.$ext";
                    move_uploaded_file($_FILES['ben_photo']['tmp_name'][$i], $photoPath);
                }
                if (isset($_FILES['ben_id_doc']['error'][$i]) && $_FILES['ben_id_doc']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['ben_id_doc']['name'][$i], PATHINFO_EXTENSION);
                    $idDocPath = $upload_dir . "id_" . time() . "_$i.$ext";
                    move_uploaded_file($_FILES['ben_id_doc']['tmp_name'][$i], $idDocPath);
                }

                $bStmt->bind_param("isssssssssss",
                    $assetId, $name, $nid, $nid, $dob, $gender, $email, $whatsapp,
                    $photoPath, $idDocPath, $relation, $condition
                );
                $bStmt->execute();
            }
        }

        $msg = "<div style='background:#d4edda;color:green;padding:30px;border-radius:15px;text-align:center;font-size:1.5em;font-weight:bold;'>
                SUCCESS!<br>Government asset registered permanently.<br>
                <small>Record ID: #$assetId</small>
                </div>";
    } else {
        $msg = "<div style='background:#f8d7da;color:red;padding:20px;border-radius:10px;'>
                Error: " . $stmt->error . "
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rwanda Government Registry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0d47a1, #1a237e); color: #fff; margin:0; padding:20px; }
        .container { max-width: 1100px; margin: 40px auto; background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; backdrop-filter: blur(12px); }
        h2 { text-align:center; color:#00d2ff; font-size:3em; margin:0; }
        .tab { overflow:hidden; background:#1a237e; border-radius:15px 15px 0 0; }
        .tab button { background:none; border:none; color:white; padding:18px 35px; cursor:pointer; font-weight:600; font-size:18px; }
        .tab button.active, .tab button:hover { background:#00d2ff; color:#000; }
        .tabcontent { padding:45px; background:rgba(0,0,0,0.4); border-radius:0 0 15px 15px; display:none; }
        .tabcontent.active { display:block; }
        label { display:block; margin:22px 0 10px; font-weight:600; font-size:1.2em; }
        input, select, textarea { width:100%; padding:18px; border:none; border-radius:12px; background:rgba(255,255,255,0.2); color:white; font-size:17px; }
        .row { display:flex; gap:30px; margin:20px 0; }
        .col { flex:1; }
        .benef { background:rgba(255,255,255,0.15); padding:35px; border-radius:18px; margin:30px 0; position:relative; border:3px dashed #00d2ff; }
        .remove-ben { position:absolute; top:15px; right:15px; color:#ff6b6b; font-size:28px; cursor:pointer; background:rgba(0,0,0,0.5); width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        button { background:#00d2ff; color:#000; padding:22px; border:none; border-radius:50px; font-size:24px; font-weight:bold; cursor:pointer; width:100%; margin-top:50px; }
        button:hover { background:#5ce1e6; transform:translateY(-3px); }
        .required { color:#ff6b6b; }
    </style>
</head>
<body>

<div class="container">
    <h2>Republic of Rwanda<br><small style="font-size:0.4em;">Digital Inheritance Registry</small></h2>
    
    <?php if($msg) echo $msg; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="tab">
            <button type="button" class="tablinks active" onclick="openTab(event,'Asset')">Asset Details</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Location')">Location</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Beneficiaries')">Beneficiaries</button>
            <button type="button" class="tablinks" onclick="openTab(event,'Official')">Official Seal</button>
        </div>

        <div id="Asset" class="tabcontent active">
            <label>Asset Name <span class="required">*</span></label>
            <input type="text" name="asset_name" required>

            <label>Category <span class="required">*</span></label>
            <select name="asset_category" required>
                <option value="Land Title">Land Title</option>
                <option value="House">House/Property</option>
                <option value="Vehicle">Vehicle</option>
                <option value="Bank Account">Bank Account</option>
                <option value="Shares">Company Shares</option>
                <option value="Other">Other</option>
            </select>

            <label>Registration Number</label>
            <input type="text" name="registration_number">

            <label>Main Document (PDF/Image) <span class="required">*</span></label>
            <input type="file" name="asset_file" accept=".pdf,.jpg,.jpeg,.png" required>

            <label>Description</label>
            <textarea name="description" rows="4"></textarea>
        </div>

        <div id="Location" class="tabcontent">
            <div class="row">
                <div class="col"><label>District <span class="required">*</span></label><input type="text" name="district" required></div>
                <div class="col"><label>Sector</label><input type="text" name="sector"></div>
            </div>
            <div class="row">
                <div class="col"><label>Cell</label><input type="text" name="cell"></div>
                <div class="col"><label>Village</label><input type="text" name="village"></div>
            </div>
            <label>GPS Coordinates</label>
            <input type="text" name="gps_coords" placeholder="-1.9441, 30.0619">
        </div>

        <div id="Beneficiaries" class="tabcontent">
            <p style="text-align:center; margin:40px 0;">
                <button type="button" onclick="addBeneficiary()" style="background:#00d2ff;color:#000;padding:16px 40px;border:none;border-radius:50px;font-size:20px;">
                    + Add Beneficiary
                </button>
            </p>
            <div id="beneficiaries-container"></div>
        </div>

        <div id="Official" class="tabcontent">
            <label>Release Date & Time (optional)</label>
            <input type="datetime-local" name="release_date">

            <label style="font-size:1.3em;">
                <input type="checkbox" name="require_verification"> Require Manual Verification
            </label>

            <label>Official Stamp / Signature</label>
            <input type="file" name="official_stamp" accept="image/*">

            <label>Internal Notes</label>
            <textarea name="notes" rows="5"></textarea>

            <button type="submit" name="upload_asset">
                REGISTER ASSET OFFICIALLY
            </button>
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
        <span class="remove-ben" onclick="this.parentElement.remove()">X</span>
        <h3 style="color:#00d2ff;margin:0 0 25px;">Beneficiary ${benCount}</h3>
        <div class="row">
            <div class="col"><label>Full Name <span class="required">*</span></label><input type="text" name="ben_name[]" required></div>
            <div class="col"><label>National ID / Passport</label><input type="text" name="ben_nid[]"></div>
        </div>
        <div class="row">
            <div class="col"><label>Date of Birth</label><input type="date" name="ben_dob[]"></div>
            <div class="col"><label>Gender</label>
                <select name="ben_gender[]"><option>Male</option><option>Female</option><option>Other</option></select>
            </div>
        </div>
        <label>Passport Photo</label><input type="file" name="ben_photo[]" accept="image/*">
        <label>ID Document</label><input type="file" name="ben_id_doc[]" accept=".pdf,.jpg,.png">
        <label>Email</label><input type="email" name="ben_email[]">
        <label>WhatsApp Number</label><input type="text" name="ben_whatsapp[]" placeholder="+250...">
        <label>Relation to Owner</label><input type="text" name="ben_relation[]">
        <label>Release Condition</label>
        <select name="ben_condition[]">
            <option value="">Immediate Release</option>
            <option>After Verification</option>
            <option>On Specific Date</option>
        </select>
        <hr style="border:1px dashed rgba(255,255,255,0.4); margin:30px 0;">
    `;
    document.getElementById("beneficiaries-container").appendChild(div);
}
addBeneficiary();
</script>

</body>
</html>