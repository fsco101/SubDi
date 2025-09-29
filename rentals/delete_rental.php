<?php
include '../includes/config.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rental_id'])) {
        $rental_id = intval($_POST['rental_id']);

        // Delete the rental item from the database
        $query = "DELETE FROM rentals WHERE rental_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $rental_id);

        if ($stmt->execute()) {
            echo "Rental item deleted successfully.";
        } else {
            echo "Error deleting rental item.";
        }

        $stmt->close();
    } else {
        echo "Rental ID not provided.";
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
