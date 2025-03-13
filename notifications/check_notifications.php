<?php
include '../includes/config.php';

session_start();
$user_id = $_SESSION['user_id'];

$query = "SELECT COUNT(*) AS unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(["unread" => $result['unread']]);
?>
