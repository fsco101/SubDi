<?php
ob_start();
include '../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

if (!isset($_GET['id'])) {
    die("Invalid announcement ID.");
}

$announcement_id = $_GET['id'];
$query = "DELETE FROM announcements WHERE announcement_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $announcement_id);

if ($stmt->execute()) {
    header("Location: view_announcement.php?msg=Announcement deleted successfully");
        exit();
    } else {
        die("Error deleting announcement.");
    }
    ?>
