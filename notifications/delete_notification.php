<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if notification_id is provided
if (!isset($data['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit();
}

$notification_id = filter_var($data['notification_id'], FILTER_SANITIZE_NUMBER_INT);

// Validate notification exists and belongs to the user
$verifyQuery = "SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param("ii", $notification_id, $user_id);
$verifyStmt->execute();
$result = $verifyStmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Notification not found or not authorized']);
    exit();
}

// Delete the notification
$deleteQuery = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
$deleteStmt = $conn->prepare($deleteQuery);
$deleteStmt->bind_param("ii", $notification_id, $user_id);
$success = $deleteStmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete notification', 'error' => $conn->error]);
}

// Close statements
$deleteStmt->close();
$verifyStmt->close();
$conn->close();
?>
