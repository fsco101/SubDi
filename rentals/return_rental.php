<?php
session_start();
require '../includes/config.php'; // Ensure this file contains the database connection logic

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rental_id = $_POST['rental_id'];

    // Get rental details
    $query = "SELECT item_id, quantity FROM rentals WHERE rental_id = $rental_id AND status = 'approved'";
    $result = $conn->query($query);
    $rental = $result->fetch_assoc();

    if ($rental) {
        $item_id = $rental['item_id'];
        $quantity = $rental['quantity'];

        // Update rental status to 'returned'
        $update_rental_query = "UPDATE rentals SET status = 'returned' WHERE rental_id = $rental_id";
        $conn->query($update_rental_query);

        // Update item quantity
        $update_item_query = "UPDATE rental_items SET quantity = quantity + $quantity WHERE item_id = $item_id";
        $conn->query($update_item_query);

        // Mark item as available if quantity is greater than 0
        $markAvailable = $conn->prepare("UPDATE rental_items SET availability_status = 'available' WHERE item_id = ? AND quantity > 0");
        $markAvailable->bind_param("i", $item_id);
        $markAvailable->execute();

        echo "Rental returned successfully.";
    } else {
        echo "Invalid rental ID or rental already returned.";
    }
}

$conn->close();
?>

