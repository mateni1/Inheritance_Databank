<?php 
session_start();

// 🔒 Only allow logged-in legal verifiers
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'legal_verifier') {
    header("Location: login.php");
    exit();
}

require 'db_connection.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Legal Verifier Dashboard - Inheritance System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #2c3e50, #34495e);
  color: #fff;
  margin: 0;
  padding: 0;
}
.dashboard {
  max-width: 1000px;
  margin: 50px auto;
  background: rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 30px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.25);
}
h1 {
  text-align: center;
  font-size: 28px;
  margin-bottom: 15px;
}
.tagline {
  text-align: center;
  color: #dcdcdc;
  font-size: 16px;
  margin-bottom: 30px;
}
.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(0,0,0,0.25);
  padding: 10px 20px;
  border-radius: 10px;
}
.navbar a {
  color: #fff;
  text-decoration: none;
  font-weight: bold;
  margin: 0 10px;
}
.navbar a:hover {
  text-decoration: underline;
}
.section {
  background: rgba(255,255,255,0.15);
  padding: 20px;
  border-radius: 10px;
  margin-top: 20px;
}
.section h3 {
  margin-bottom: 10px;
  font-size: 18px;
}
.section ul {
  margin: 0;
  padding-left: 20px;
}
.section ul li {
  margin: 10px 0;
}
</style>
</head>
<body>

<div class="dashboard">
  <div class="navbar">
    <span>⚖️ Legal Verifier: <?= htmlspecialchars($_SESSION['username']); ?></span>
    <div>
      <a href="legal_verifier.php">Dashboard</a>
      <a href="verify_wills.php">Verify Wills</a>
      <a href="documents.php">Documents</a>
      <a href="logout.php" style="color:#ff6b6b;">Logout</a>
    </div>
  </div>

  <h1>Legal Verification Center</h1>
  <p class="tagline">
    Empowering law and justice in inheritance transparency.<br>
    Every estate must be verified under the law.
  </p>

  <div class="section">
    <h3>👤 Legal Officer Information</h3>
    <p><strong>Name:</strong> <?= htmlspecialchars($user['full_name']); ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
    <p><strong>Role:</strong> Legal Verifier</p>
  </div>

  <div class="section">
    <h3>📜 Legal Responsibilities</h3>
    <ul>
      <li>✔️ Review and verify uploaded inheritance documents.</li>
      <li>📁 Confirm authenticity of wills and signatures.</li>
      <li>⚖️ Approve legal clearance for estate transfer.</li>
      <li>🕵️ Investigate fraud or duplicate inheritance claims.</li>
    </ul>
  </div>

  <div class="section">
    <h3>🧾 Quick Access Tools</h3>
    <ul>
      <li><a href="verify_wills.php" style="color:#fff;">Start Will Verification</a></li>
      <li><a href="case_reports.php" style="color:#fff;">Generate Case Report</a></li>
      <li><a href="upload_documents.php" style="color:#fff;">Upload Legal Documents</a></li>
      <li><a href="law_updates.php" style="color:#fff;">Review Legal Updates</a></li>
    </ul>
  </div>

  <div class="section">
    <h3>📊 Legal Insight</h3>
    <ul>
      <li>Track the number of verified estates this month.</li>
      <li>Analyze pending vs completed verifications.</li>
      <li>Monitor estates under legal dispute.</li>
    </ul>
  </div>

</div>

</body>
</html>
