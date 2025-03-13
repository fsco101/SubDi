<?php
include '../includes/config.php';

if (!isset($_GET['id'])) {
    die("Invalid booking ID.");
}

$booking_id = $_GET['id'];

// Check if booking exists
$query = "SELECT * FROM bookings WHERE booking_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

// Delete booking
$deleteQuery = "DELETE FROM bookings WHERE booking_id = ?";
$stmt = $conn->prepare($deleteQuery);
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    header("Location: view_bookings.php?msg=Booking deleted successfully");
    exit();
} else {
    die("Error deleting booking.");
}
?>
