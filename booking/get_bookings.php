<?php
include "../includes/config.php"; // Ensure this connects to your database

header('Content-Type: application/json');

// Fetch all facility bookings
$query = "SELECT b.booking_id, b.booking_date, b.start_time, b.end_time, b.status, a.name as facility_name 
          FROM bookings b 
          JOIN amenities a ON b.facility_id = a.facility_id";

$result = $conn->query($query);
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        "booking_id" => $row['booking_id'],
        "facility_name" => $row['facility_name'],
        "booking_date" => $row['booking_date'],
        "start_time" => $row['start_time'],
        "end_time" => $row['end_time'],
        "status" => $row['status']
    ];
}

echo json_encode($bookings);
$conn->close();
?>
