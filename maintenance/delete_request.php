<?php
include '../includes/config.php';

$request_id = $_GET['id'];
$query = "DELETE FROM service_requests WHERE request_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
if ($stmt->execute()) {
    header("Location: view_requests.php?msg=Request deleted successfully!");
    exit();
} else {
    echo "<p>Error deleting request.</p>";
}
?>