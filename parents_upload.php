<?php
session_start();
require 'db_connection.php';

// ONLY PARENTS ALLOWED — THIS IS THE FIX!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parents') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Fetch children
$children = $conn->query("SELECT id, full_name FROM parent_children WHERE parent_id = $user_id ORDER BY full_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_name        = trim($_POST['asset_name']);
    $category          = $_POST['category'];
    $value_estimate    = trim($_POST['value_estimate'] ?? '');
    $location          = trim($_POST['location'] ?? '');
    $legal_notes       = trim($_POST['legal_notes'] ?? '');
    $release_condition= $_POST['release_condition'];
    $specific_date     = $_POST['specific_date'] ?? null;
    $child_id          = $_POST['child_id'] ?? null;

    $upload_dir = "uploads/parents/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $document_path = null;
    if ($_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $filename = "asset_" . time() . "_$user_id." . $ext;
        $document_path = $upload_dir . $filename;
        move_uploaded_file($_FILES['document']['tmp_name'], $document_path);
    }

    $stmt = $conn->prepare("INSERT INTO parent_assets 
        (parent_id, asset_name, category, value_estimate, location, legal_notes, document_path, 
         release_condition, specific_date, child_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    $stmt->bind_param("issssssssi", $user_id, $asset_name, $category, $value_estimate, 
                      $location, $legal_notes, $document_path, $release_condition, $specific_date, $child_id);

    if ($stmt->execute()) {
        $msg = "<div style='background:#d4edda;color:#155724;padding:20px;border-radius:16px;text-align:center;font-weight:bold;'>
                Asset protected successfully! Your child's future is safer now.
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Asset • Parents Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root { --pri: #ff6b9e; --love: #ff8fab; --dark: #2d1b3a; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #2d1b3a, #483475); color:#fff; margin:0; min-height:100vh; }
        .container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .box { background: rgba(255,255,255,0.12); padding: 40px; border-radius: 24px; backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        h2 { text-align: center; color: var(--pri); font-size: 2.5em; }
        .subtitle { text-align: center; color: #ffccdd; margin: 15px 0 40px; }
        label { display: block; margin: 20px 0 8px; color: #ffccdd; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 14px; border: none; border-radius: 12px; background: rgba(255,255,255,0.2); color: white; font-size: 16px; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        .btn { background: var(--pri); color: #000; padding: 18px 40px; border: none; border-radius: 50px; font-weight: bold; font-size: 18px; cursor: pointer; width: 100%; margin-top: 30px; transition: 0.3s; }
        .btn:hover { background: #ffb3c6; transform: translateY(-5px); }
        .back { position: absolute; top: 20px; left: 30px; color: white; text-decoration: none; font-weight: bold; }
        .back:hover { color: var(--pri); }
    </style>
</head>
<body>

<a href="parents.php" class="back">Back to Dashboard</a>

<div class="container">
    <div class="box">
        <h2>Protect Asset for My Child</h2>
        <p class="subtitle">Add property, money, or gift with clear inheritance rules</p>

        <?php if($msg) echo $msg; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col"><label>Asset Name</label><input type="text" name="asset_name" required></div>
                <div class="col"><label>Category</label>
                    <select name="category" required>
                        <option value="">Choose...</option>
                        <option>House</option><option>Land</option><option>Car</option><option>Bank Account</option>
                        <option>Business</option><option>Jewelry</option><option>Education Fund</option><option>Other</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col"><label>Estimated Value (Optional)</label><input type="text" name="value_estimate" placeholder="e.g. 250,000,000 RWF"></div>
                <div class="col"><label>Location</label><input type="text" name="location" placeholder="e.g. Kigali, Gasabo"></div>
            </div>

            <label>Upload Document (Deed, Photo, etc.)</label>
            <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png">

            <label>Legal Notes / Special Message</label>
            <textarea name="legal_notes" rows="3" placeholder="Any special instructions for your child..."></textarea>

            <hr style="border:1px dashed rgba(255,255,255,0.3); margin:40px 0;">

            <div class="row">
                <div class="col"><label>For Which Child?</label>
                    <select name="child_id">
                        <option value="">All Children Equally</option>
                        <?php while($c = $children->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                        <?php endwhile; $children->data_seek(0); ?>
                    </select>
                </div>
                <div class="col"><label>Release Condition</label>
                    <select name="release_condition" required>
                        <option value="on_death">Upon My Death</option>
                        <option value="child_18">When Child Turns 18</option>
                        <option value="graduation">After Graduation</option>
                        <option value="marriage">Upon Marriage</option>
                        <option value="manual">Manual Release Only</option>
                    </select>
                </div>
            </div>

            <label>Specific Date (Optional)</label>
            <input type="date" name="specific_date">

            <button type="submit" class="btn">PROTECT THIS ASSET NOW</button>
        </form>
    </div>
</div>
</body>
</html>