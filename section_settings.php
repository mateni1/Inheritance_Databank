<?php
include 'db_connection.php';
$message = "";

// --- Add New Section ---
if (isset($_POST['add_section'])) {
    $key = strtolower(trim($_POST['new_section_key']));
    $title = trim($_POST['new_section_title']);
    $desc = trim($_POST['new_section_description']);
    $imagePath = '';

    // Check if section key already exists
    $checkStmt = $conn->prepare("SELECT id FROM system_sections WHERE section_key = ?");
    $checkStmt->bind_param("s", $key);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $message = "⚠️ Section key already exists! Choose a unique key.";
    } else {
        // Handle image upload
        if (!empty($_FILES['new_section_image']['name'])) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

            $fileName = "section_" . time() . "_" . basename($_FILES["new_section_image"]["name"]);
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($_FILES["new_section_image"]["tmp_name"], $targetFile)) {
                $imagePath = $targetFile;
            } else {
                $message = "⚠️ Failed to upload image.";
            }
        }

        // Insert new section
        $stmt = $conn->prepare("INSERT INTO system_sections (section_key, title, description, image_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $key, $title, $desc, $imagePath);
        if ($stmt->execute()) {
            $message = "✅ New section added successfully!";
        } else {
            $message = "⚠️ Failed to add section. Please try again.";
        }
    }
}

// --- Update Existing Sections ---
if (isset($_POST['update_sections'])) {
    foreach ($_POST['title'] as $id => $title) {
        $desc = $_POST['description'][$id];
        $key = $_POST['section_key'][$id];
        $imagePath = $_POST['existing_image'][$id];

        if (isset($_FILES['image']['name'][$id]) && $_FILES['image']['error'][$id] == 0) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = "section_" . time() . "_" . basename($_FILES["image"]["name"][$id]);
            $targetFile = $targetDir . $fileName;
            move_uploaded_file($_FILES["image"]["tmp_name"][$id], $targetFile);
            $imagePath = $targetFile;
        }

        $stmt = $conn->prepare("UPDATE system_sections SET section_key=?, title=?, description=?, image_path=? WHERE id=?");
        $stmt->bind_param("ssssi", $key, $title, $desc, $imagePath, $id);
        $stmt->execute();
    }

    $message = "✅ Sections updated successfully!";
}

// --- Delete Section ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM system_sections WHERE id = $id");
    $message = "❌ Section deleted successfully!";
}

// --- Fetch all sections ---
$result = $conn->query("SELECT * FROM system_sections ORDER BY id ASC");
$sections = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Sections | Inheritance Databank</title>
<style>
    body { font-family: Arial, sans-serif; background: #f3f6fa; margin: 0; padding: 20px; }
    .container { max-width: 1000px; margin: auto; background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    h2 { text-align: center; color: #333; margin-bottom: 20px; }
    .message { text-align: center; color: green; font-weight: bold; margin-bottom: 15px; }
    .section-box { border: 1px solid #ddd; padding: 15px; border-radius: 10px; margin-bottom: 20px; background: #fafafa; }
    label { font-weight: bold; margin-top: 10px; display: block; }
    input[type="text"], textarea { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; }
    input[type="file"] { margin-top: 8px; }
    img { width: 120px; border-radius: 10px; display: block; margin-top: 10px; }
    .btn { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
    .btn:hover { background: #0056b3; }
    .btn-danger { background: #e74c3c; }
    .btn-danger:hover { background: #c0392b; }
    .add-section { border: 3px dashed #27ae60; background: #eafaf1; padding: 20px; margin-top: 40px; border-radius: 12px; }
    .flex-end { display: flex; justify-content: flex-end; margin-top: 20px; }
    /* Different styles per section key */
    .section-box.students { border-left: 5px solid #3498db; }
    .section-box.investors { border-left: 5px solid #2ecc71; }
    .section-box.partners { border-left: 5px solid #f39c12; }
</style>
</head>
<body>

<div class="container">
    <h2>⚙️ Manage Sections</h2>
    <?php if ($message) echo "<div class='message'>$message</div>"; ?>

    <!-- Existing Sections -->
    <form method="POST" enctype="multipart/form-data">
        <?php foreach ($sections as $sec): ?>
            <div class="section-box <?php echo htmlspecialchars($sec['section_key']); ?>">
                <label>Section Key:</label>
                <input type="text" name="section_key[<?php echo $sec['id']; ?>]" value="<?php echo htmlspecialchars($sec['section_key']); ?>">

                <label>Title:</label>
                <input type="text" name="title[<?php echo $sec['id']; ?>]" value="<?php echo htmlspecialchars($sec['title']); ?>">

                <label>Description:</label>
                <textarea name="description[<?php echo $sec['id']; ?>]" rows="3"><?php echo htmlspecialchars($sec['description']); ?></textarea>

                <label>Image:</label>
                <img src="<?php echo $sec['image_path']; ?>" alt="Preview">
                <input type="file" name="image[<?php echo $sec['id']; ?>]">
                <input type="hidden" name="existing_image[<?php echo $sec['id']; ?>]" value="<?php echo $sec['image_path']; ?>">

                <div class="flex-end">
                    <a href="?delete=<?php echo $sec['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this section?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="flex-end">
            <button type="submit" name="update_sections" class="btn">💾 Save All Changes</button>
        </div>
    </form>

    <!-- Add New Section -->
    <div class="add-section">
        <h3>➕ Add a Brand-New Independent Section</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>Section Key (e.g. students, investors)</label>
            <input type="text" name="new_section_key" required>

            <label>Title</label>
            <input type="text" name="new_section_title" required>

            <label>Description</label>
            <textarea name="new_section_description" rows="3" required></textarea>

            <label>Image</label>
            <input type="file" name="new_section_image" accept="image/*">

            <div class="flex-end">
                <button type="submit" name="add_section" class="btn">➕ Add Independent Section</button>
                 <a href="manage_users.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
