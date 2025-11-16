<?php
include 'db_connection.php';
$message = "";

// --- Add New Section via POST ---
if (isset($_POST['add_section'])) {
    $key = strtolower(trim($_POST['section_key']));
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $desc = trim($_POST['description']);
    $extra = trim($_POST['extra_info']);
    $link = trim($_POST['link']);
    $status = $_POST['status'] ?? 'active';
    $imagePath = '';

    // Prevent duplicate keys
    $checkStmt = $conn->prepare("SELECT id FROM system_sections WHERE section_key = ?");
    $checkStmt->bind_param("s", $key);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $message = "⚠️ Section key already exists!";
    } else {
        if (!empty($_FILES['image']['name'])) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = "section_" . time() . "_" . basename($_FILES["image"]["name"]);
            $targetFile = $targetDir . $fileName;
            move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);
            $imagePath = $targetFile;
        }

        $stmt = $conn->prepare("INSERT INTO system_sections (section_key, title, subtitle, description, extra_info, link, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $key, $title, $subtitle, $desc, $extra, $link, $status, $imagePath);
        $stmt->execute();
        $message = "✅ New section added successfully!";
    }
}

// --- Fetch all sections ---
$result = $conn->query("SELECT * FROM system_sections ORDER BY id ASC");
$sections = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Sections Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: Arial; background: #f3f6fa; padding: 20px; }
.container { max-width: 900px; margin: auto; background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
h2 { text-align: center; margin-bottom: 20px; }
.message { text-align: center; color: green; font-weight: bold; margin-bottom: 15px; }
label { font-weight: bold; display: block; margin-top: 10px; }
input[type="text"], textarea, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; }
input[type="file"] { margin-top: 8px; }
.btn { padding: 10px 20px; background: #27ae60; color: #fff; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px; }
.btn:hover { background: #219150; }
.section-box { border: 1px solid #ddd; padding: 15px; border-radius: 10px; margin-bottom: 20px; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
.section-info { max-width: 75%; }
.section-actions a { margin-left: 5px; padding: 8px 12px; border-radius: 5px; color: #fff; text-decoration: none; }
.section-actions .edit { background: #3498db; }
.section-actions .delete { background: #e74c3c; }
.section-actions .edit:hover { background: #2980b9; }
.section-actions .delete:hover { background: #c0392b; }
#addSectionModal { display: none; position: fixed; top:0; left:0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 25px; border-radius: 12px; width: 500px; max-width: 90%; position: relative; }
.modal-close { position: absolute; top: 10px; right: 15px; cursor: pointer; font-size: 20px; color: #888; }
.modal-close:hover { color: #333; }
</style>
</head>
<body>

<div class="container">
    <h2>⚙️ System Sections Manager</h2>
    <?php if ($message) echo "<div class='message'>$message</div>"; ?>

    <button class="btn" onclick="openModal()">➕ Add New Section</button>

    <h3 style="margin-top:30px;">Existing Sections</h3>
    <?php foreach ($sections as $sec): ?>
        <div class="section-box">
            <div class="section-info">
                <strong><?php echo htmlspecialchars($sec['title']); ?> (<?php echo $sec['section_key']; ?>)</strong><br>
                <?php echo htmlspecialchars($sec['subtitle']); ?><br>
                <?php echo htmlspecialchars($sec['description']); ?><br>
                <a href="<?php echo htmlspecialchars($sec['link']); ?>" target="_blank"><?php echo htmlspecialchars($sec['link']); ?></a><br>
                Status: <?php echo $sec['status']; ?><br>
                <?php if($sec['image_path']) echo "<img src='{$sec['image_path']}' width='100' style='margin-top:5px;'>"; ?>
            </div>
            <div class="section-actions">
                <a href="edit_section.php?id=<?php echo $sec['id']; ?>" class="edit"><i class="fa fa-edit"></i> Edit</a>
                <a href="delete_section.php?id=<?php echo $sec['id']; ?>" class="delete" onclick="return confirm('Are you sure?')"><i class="fa fa-trash"></i> Delete</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Section Modal -->
<div id="addSectionModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3>➕ Add New Section</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>Section Key</label>
            <input type="text" name="section_key" required>

            <label>Title</label>
            <input type="text" name="title" required>

            <label>Subtitle</label>
            <input type="text" name="subtitle">

            <label>Description</label>
            <textarea name="description" rows="3" required></textarea>

            <label>Extra Info</label>
            <textarea name="extra_info" rows="3"></textarea>

            <label>Link / URL</label>
            <input type="text" name="link">

            <label>Status</label>
            <select name="status">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <label>Image</label>
            <input type="file" name="image">

            <button type="submit" name="add_section" class="btn">➕ Add Section</button>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('addSectionModal').style.display = 'flex'; }
function closeModal() { document.getElementById('addSectionModal').style.display = 'none'; }
window.onclick = function(e) { if(e.target == document.getElementById('addSectionModal')) closeModal(); }
</script>

</body>
</html>
