<?php
include '../includes/config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["error" => "User not logged in"]);
    exit();
}

// Update all unread notifications to read
$query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo json_encode(["success" => true]);
?>
