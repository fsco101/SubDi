<?php

include '../includes/header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can edit the facilities.");
}

if (isset($_GET['id'])) {
    $facility_id = $_GET['id'];

    // First, fetch the current facility to get its image URL
    $query = "SELECT image_url FROM amenities WHERE facility_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $facility = $stmt->get_result()->fetch_assoc();

    // Check if the facility was found
    if (!$facility) {
        die("Facility not found.");
    }

    // Delete the facility from the database
    $query = "DELETE FROM amenities WHERE facility_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $facility_id);

    if ($stmt->execute()) {
        // Delete the image from the server if it exists
        if (file_exists($facility['image_url'])) {
            unlink($facility['image_url']);
        }

        // Redirect to edit_faci.php after deletion
        header("Location: edit_faci.php");
        exit();
    } else {
        echo "Error deleting facility.";
    }
} else {
    echo "No facility ID provided.";
}
?>