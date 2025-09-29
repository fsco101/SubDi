<?php
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /subdisystem/user/login.php?message=login_required");
    exit();
}

if (isset($_GET['id'])) {
    $booking_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Check if the booking belongs to the user
    $query = "SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Delete the booking
        $deleteQuery = "DELETE FROM bookings WHERE booking_id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $booking_id);
        if ($deleteStmt->execute()) {
            echo "Booking Deleted Successfully";
        } else {
            echo "Error deleting booking";
        }
    } else {
        echo "Booking not found or you do not have permission to delete this booking";
    }
} else {
    echo "Invalid request";
}
?>
