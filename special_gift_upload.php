<?php
session_start();
require 'db_connection.php';

// ONLY SPECIAL GIFT USERS — CORRECT ROLE CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'special_gift') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// ————————————————————————
// SAFELY GET USER INFO FROM MAIN 'users' TABLE
// ————————————————————————
$display_name = $_SESSION['username'] ?? 'Gift Bearer';
$profile_pic  = $_SESSION['profile_pic'] ?? 'uploads/avatars/default-avatar.png';

$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND user_type = 'special_gift' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $display_name = $row['full_name'] ?: $display_name;
}

// ————————————————————————
// AUTO CREATE TABLE
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
// PROCESS UPLOAD
// ————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_name   = trim($_POST['asset_name']);
    $asset_type   = $_POST['asset_type'];
    $asset_value  = $_POST['asset_value'];
    $description  = trim($_POST['description']);

    $upload_dir = "uploads/special_gifts/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_path = null;
    if ($_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $filename = "gift_" . time() . "_$user_id." . strtolower($ext);
        $file_path = $upload_dir . $filename;
        move_uploaded_file($_FILES['document']['tmp_name'], $file_path);
    }

    $stmt = $conn->prepare("INSERT INTO special_gifts 
        (giver_id, asset_name, asset_type, asset_value, description, file_path) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdss", $user_id, $asset_name, $asset_type, $asset_value, $description, $file_path);

    if ($stmt->execute()) {
        $msg = "<div style='background:#d4edda; color:#155724; padding:20px; border-radius:16px; text-align:center; font-weight:bold; margin:20px 0; font-size:1.2em;'>
                Gift recorded eternally. Your love will live forever.
                </div>";
    } else {
        $msg = "<div style='background:#f8d7da; color:#721c24; padding:20px; border-radius:16px; text-align:center; font-weight:bold; margin:20px 0;'>
                Error saving gift. Please try again.
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Special Gift • Eternal Legacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root { --pri: #9c27b0; --love: #e91e63; --dark: #1a0033; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #1a0033, #4a148c); color:#fff; margin:0; min-height:100vh; }
        .container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .box { background: rgba(255,255,255,0.12); padding: 50px; border-radius: 28px; backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.15); text-align:center; position:relative; }
        .profile-pic { width: 110px; height: 110px; border-radius: 50%; border: 6px solid var(--pri); object-fit: cover; box-shadow: 0 0 30px rgba(156,39,176,0.7); margin-bottom: 20px; }
        h2 { font-size: 3em; color: var(--pri); margin: 10px 0; }
        .subtitle { font-size: 1.4em; color: #e1bee7; margin: 15px 0 40px; opacity: 0.9; }
        label { display: block; margin: 25px 0 10px; color: #e1bee7; font-weight: 600; text-align: left; font-size: 1.1em; }
        input, select, textarea { width: 100%; padding: 16px; border: none; border-radius: 14px; background: rgba(255,255,255,0.2); color: white; font-size: 16px; }
        input::placeholder, textarea::placeholder { color: #e1bee7; opacity: 0.8; }
        .btn { background: var(--love); color: white; padding: 20px 60px; border: none; border-radius: 50px; font-weight: bold; font-size: 1.5em; cursor: pointer; margin-top: 40px; transition: 0.3s; box-shadow: 0 10px 30px rgba(233,30,99,0.4); }
        .btn:hover { background: #c2185b; transform: translateY(-6px); box-shadow: 0 15px 40px rgba(233,30,99,0.6); }
        .back { position: absolute; top: 25px; left: 30px; color: white; text-decoration: none; font-weight: bold; font-size: 1.1em; }
        .back:hover { color: var(--pri); }
        hr { border: 1px dashed rgba(255,255,255,0.3); margin: 50px 0; }
    </style>
</head>
<body>

<a href="special_gift.php" class="back">Back to Dashboard</a>

<div class="container">
    <div class="box">
        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic">
        <h2>Record a Special Gift</h2>
        <p class="subtitle">Your love becomes eternal the moment you press "Record"</p>

        <?php if($msg) echo $msg; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Gift / Asset Name</label>
            <input type="text" name="asset_name" required placeholder="e.g. Grandmother's Ring, Village Land, Education Fund...">

            <label>Type of Gift</label>
            <select name="asset_type" required>
                <option value="">Choose Type</option>
                <option>Jewelry</option>
                <option>Property</option>
                <option>Cash / Funds</option>
                <option>Vehicle</option>
                <option>Investment</option>
                <option>Personal Item</option>
                <option>Digital Asset</option>
                <option>Other</option>
            </select>

            <label>Estimated Value (RWF)</label>
            <input type="number" name="asset_value" required placeholder="5000000" min="0">

            <label>Upload Proof Document</label>
            <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">

            <hr>

            <label>Message to Future Generations (Optional)</label>
            <textarea name="description" rows="6" placeholder="Why are you giving this gift? Who is it for? Speak from your heart... Your words will live forever."></textarea>

            <button type="submit" class="btn">
                RECORD THIS GIFT FOREVER
            </button>
        </form>
    </div>
</div>

</body>
</html>