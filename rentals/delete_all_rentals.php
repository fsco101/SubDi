<?php
include '../includes/config.php'; // Adjust the path as necessary

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to perform this action.";
    exit();
}

$user_id = $_SESSION['user_id'];

// Delete all returned and paid rentals for the logged-in user
$query = "DELETE FROM rentals 
          WHERE user_id = ? AND status = 'returned' AND payment_status = 'paid'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo "All returned and paid items have been deleted successfully.";
} else {
    echo "Error deleting items: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
