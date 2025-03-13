<?php
include '../includes/config.php';
session_start();

$userId = $_SESSION['user_id'];

$query = "SELECT COUNT(*) AS unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$unreadCount = $result['unread'];

echo json_encode(["unread" => $unreadCount]);
?>
