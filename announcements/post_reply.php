<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in");
}

$user_id = $_SESSION['user_id'];
$announcement_id = $_POST['announcement_id'] ?? null;
$reply_text = $_POST['reply_text'] ?? null;

if (!$announcement_id || !$reply_text) {
    die("Error: Invalid request");
}

// Insert the reply into the database
$insertQuery = "INSERT INTO announcement_replies (announcement_id, user_id, reply_text) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insertQuery);
$stmt->bind_param("iis", $announcement_id, $user_id, $reply_text);

if ($stmt->execute()) {
    header("Location: view_announcement.php"); // Redirect back to view
    exit();
} else {
    echo "Error: Could not post reply.";
}
?>
