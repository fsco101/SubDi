<?php
include '../includes/config.php';

header('Content-Type: application/json');

// Check if facility_id is provided
if (!isset($_GET['facility_id']) || empty($_GET['facility_id'])) {
    echo json_encode(['error' => 'Facility ID is required']);
    exit();
}

$facility_id = intval($_GET['facility_id']);

// Fetch unavailable dates for the specific facility
$query = "SELECT DISTINCT booking_date 
          FROM bookings 
          WHERE facility_id = ? AND status NOT IN ('cancelled')";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$result = $stmt->get_result();

$unavailableDates = [];
while ($row = $result->fetch_assoc()) {
    $unavailableDates[] = $row['booking_date'];
}

// Format dates for Flatpickr (YYYY-MM-DD)
echo json_encode(['unavailableDates' => $unavailableDates]);
?>