<?php
include '../includes/config.php'; // Ensure database connection

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Item ID not provided.");
}

$id = intval($_GET['id']); // Ensure ID is an integer

// Fetch the item to get the image path
try {
    $stmt = $conn->prepare("SELECT image_path FROM rental_items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        die("Error: Item not found.");
    }

    $imagePath = $item['image_path']; // Get the image path before deleting

    // Delete item from the database
    $stmt = $conn->prepare("DELETE FROM rental_items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Delete the image file if it exists
    if (!empty($imagePath) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $imagePath)) {
        unlink(__DIR__ . DIRECTORY_SEPARATOR . $imagePath);
    }

    // Redirect to index.php after successful deletion
    header("Location: index_item.php");
    exit;

} catch (mysqli_sql_exception $e) {
    die("Error deleting item: " . $e->getMessage());
}
?>
