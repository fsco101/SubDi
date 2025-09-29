<?php
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("Invalid booking ID.");
}

$booking_id = $_GET['id'];

// Fetch existing booking details
$query = "SELECT * FROM bookings WHERE booking_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

// Fetch available facilities
$facilityQuery = "SELECT facility_id, name FROM amenities WHERE availability_status = 'available'";
$facilityResult = $conn->query($facilityQuery);
$facilities = $facilityResult->fetch_all(MYSQLI_ASSOC);

// Function to notify all admins about a booking update
function notifyAdminsForBookingUpdate($conn, $user_id, $booking_id) {
    $adminQuery = "SELECT user_id FROM users WHERE role = 'admin'";
    $admins = $conn->query($adminQuery);
    
    if ($admins->num_rows > 0) {
        while ($admin = $admins->fetch_assoc()) {
            $admin_id = $admin['user_id'];

            $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message) 
                           VALUES (?, ?, 'booking', ?)";
            $notifStmt = $conn->prepare($notifQuery);

            $message = "User #{$user_id} requested an update for Booking ID: {$booking_id}";
            $notifStmt->bind_param("iis", $admin_id, $booking_id, $message);
            $notifStmt->execute();
        }
    }
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $facility_id = $_POST['facility_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = $_POST['purpose'];

    // Check for existing booking in the same time range
    $checkQuery = "SELECT * FROM bookings 
                   WHERE facility_id = ? AND booking_date = ? 
                   AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
                   AND booking_id != ? AND status NOT IN ('cancelled')";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("isssssi", $facility_id, $booking_date, $end_time, $start_time, $start_time, $end_time, $booking_id);
    $stmt->execute();
    $existingBooking = $stmt->get_result()->fetch_assoc();

    if ($existingBooking) {
        $error = "This time slot is already booked. Please choose another.";
    } else {
        // Mark booking as pending approval and notify admin
        $updateQuery = "UPDATE bookings SET facility_id = ?, booking_date = ?, start_time = ?, end_time = ?, purpose = ?, status = 'pending' WHERE booking_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("issssi", $facility_id, $booking_date, $start_time, $end_time, $purpose, $booking_id);

        if ($stmt->execute()) {
            // Notify all admins about the booking update request
            notifyAdminsForBookingUpdate($conn, $_SESSION['user_id'], $booking_id);

            echo "<script>alert('Booking update requested. Waiting for admin approval.'); window.location.href='view_bookings.php';</script>";
            exit();
        } else {
            $error = "Error updating booking.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Booking</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <h2>Edit Booking</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="post">
        <label for="facility_id">Facility:</label>
        <select name="facility_id" required>
            <?php foreach ($facilities as $facility): ?>
                <option value="<?= $facility['facility_id'] ?>" <?= ($facility['facility_id'] == $booking['facility_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($facility['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>

        <label for="booking_date">Date:</label>
        <input type="date" name="booking_date" value="<?= $booking['booking_date'] ?>" required>
        <br>

        <label for="start_time">Start Time:</label>
        <input type="time" name="start_time" value="<?= $booking['start_time'] ?>" required>
        <br>

        <label for="end_time">End Time:</label>
        <input type="time" name="end_time" value="<?= $booking['end_time'] ?>" required>
        <br>

        <label for="purpose">Purpose:</label>
        <input type="text" name="purpose" value="<?= htmlspecialchars($booking['purpose']) ?>" required>
        <br>

        <button type="submit">Request Update</button>
        <a href="view_bookings.php" class = "btn btn-cancel">Cancel</a>
    </form>
</body>
</html>
<?php include '../includes/footer.php'; ?>

