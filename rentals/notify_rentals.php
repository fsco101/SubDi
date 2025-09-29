<?php
session_start();
require '../includes/config.php'; // Ensure this file contains the database connection logic

// Get rentals nearing their return time (e.g., within 1 hour)
$query = "
    SELECT r.rental_id, r.user_id, r.rental_end, u.email
    FROM rentals r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.status = 'approved' AND r.rental_end <= NOW() + INTERVAL 1 HOUR
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $rental_id = $row['rental_id'];
    $user_id = $row['user_id'];
    $email = $row['email'];
    $rental_end = $row['rental_end'];

    // Send notification (email, SMS, etc.)
    $message = "Your rental is nearing its return time. Please return the item by $rental_end.";
    mail($email, "Rental Return Reminder", $message);

    // Insert notification into the database
    $notification_query = "
        INSERT INTO notifications (user_id, related_id, related_type, message)
        VALUES ($user_id, $rental_id, 'rental', '$message')
    ";
    $conn->query($notification_query);
}

$conn->close();
?>
