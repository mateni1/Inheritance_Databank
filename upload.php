<?php
include "db.php";
session_start();

// Using test user id = 1
$user_id = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!is_dir('uploads')) mkdir('uploads', 0755, true);

    $tmp = $_FILES['file']['tmp_name'];
    $originalName = basename($_FILES['file']['name']);
    $uniq = uniqid('', true);
    $encryptedName = $uniq . ".enc";

    // READ file and perform simple encoding (placeholder)
    $content = file_get_contents($tmp);
    $encryptedContent = base64_encode($content);

    $saved = file_put_contents(__DIR__ . "/uploads/" . $encryptedName, $encryptedContent);

    if ($saved === false) {
        die("Failed to save file on server.");
    }

    // Insert DB record
    $stmt = $conn->prepare("INSERT INTO inheritance_files (user_id, file_name, encrypted_name) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $originalName, $encryptedName);
    $stmt->execute();

    header("Location: index.php");
    exit;
}
?>
