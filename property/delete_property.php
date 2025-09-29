<?php
include '../includes/config.php';

if (!isset($_GET['id'])) {
    die("Invalid property ID.");
}

$property_id = $_GET['id'];
$query = "DELETE FROM properties WHERE property_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);

if ($stmt->execute()) {
    header("Location: index_properties.php?msg=Property deleted successfully");
    exit();
} else {
    die("Error deleting property.");
}
?>
