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
                   AND booking_id != ?";
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
        <a href="view_bookings.php">Cancel</a>
    </form>
</body>
</html>


<style> /* General Page Styling */
body {
    background-color: #121212;
    color: #ffffff;
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 0;
}

/* Main Container - Wider Layout */
.booking-container {
    max-width: 1400px; /* Increased width for more space */
    margin: 50px auto;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 30px;
    padding: 30px;
}

/* Header Styling */
.booking-header {
    width: 100%;
    text-align: center;
    margin-bottom: 2rem;
    border-bottom: 3px solid #ffffff;
    padding-bottom: 1.5rem;
    font-size: 28px;
    font-weight: bold;
}

/* Booking Form Section */
.booking-form {
    flex: 1;
    background-color: #1c1c1c;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0px 6px 12px rgba(255, 255, 255, 0.1);
    min-width: 500px;
}

/* Form Field Styling */
.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 8px;
}

select, input, textarea {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 8px;
    background: #2c2c2c;
    color: #ffffff;
    font-size: 18px;
}

textarea {
    resize: none;
    height: 120px;
}

select:focus, input:focus, textarea:focus {
    border: 2px solid #17a2b8;
    outline: none;
}

/* Large Button */
button {
    width: 100%;
    background-color: #17a2b8;
    color: white;
    padding: 15px;
    font-size: 20px;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s, transform 0.2s;
}

button:hover {
    background-color: #138496;
    transform: scale(1.05);
}

/* Facility List - Displaying Options */
.facility-list {
    flex: 1.5;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}

/* Facility Card Design */
.facility-card {
    flex: 1 1 calc(33% - 20px);
    min-width: 300px;
    background: #222;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(255, 255, 255, 0.15);
    text-align: center;
    transition: transform 0.3s ease;
}

.facility-card:hover {
    transform: translateY(-5px);
}

.facility-card.maintenance {
    opacity: 0.6;
    pointer-events: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .booking-container {
        flex-direction: column;
        align-items: center;
    }

    .booking-form, .facility-list {
        width: 100%;
    }

    .facility-card {
        flex: 1 1 calc(50% - 20px);
    }
}

@media (max-width: 768px) {
    .facility-card {
        flex: 1 1 100%;
    }

    .booking-form {
        min-width: unset;
    }
}
</style>
</html>
