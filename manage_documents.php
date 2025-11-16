<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle approve/reject actions
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $conn->query("UPDATE documents SET status='approved' WHERE id=$id");
}

if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $conn->query("UPDATE documents SET status='rejected' WHERE id=$id");
}

// Fetch all documents
$result = $conn->query("SELECT d.*, u.full_name AS uploader FROM documents d JOIN users u ON d.user_id=u.id ORDER BY d.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Documents | Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Manage Documents</h2>
    <table class="table table-hover mt-3">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Uploader</th>
                <th>Document Name</th>
                <th>Uploaded At</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($doc = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $doc['id'] ?></td>
                <td><?= htmlspecialchars($doc['uploader']) ?></td>
                <td><a href="uploads/<?= $doc['file_name'] ?>" target="_blank"><?= htmlspecialchars($doc['file_name']) ?></a></td>
                <td><?= $doc['created_at'] ?></td>
                <td><?= ucfirst($doc['status']) ?></td>
                <td>
                    <?php if($doc['status'] != 'approved'): ?>
                        <a href="?approve=<?= $doc['id'] ?>" class="btn btn-sm btn-success">Approve</a>
                    <?php endif; ?>
                    <?php if($doc['status'] != 'rejected'): ?>
                        <a href="?reject=<?= $doc['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>
</body>
</html>
