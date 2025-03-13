<?php
ob_start();
include '../includes/header.php';

// Fetch available facilities
$query = "SELECT facility_id, name, availability_status, description, image_url 
          FROM amenities WHERE availability_status != 'unavailable'";
$result = $conn->query($query);
$facilities = $result->fetch_all(MYSQLI_ASSOC);

// Function to notify admins about a new booking
function notifyAdminsForBooking($conn, $user_id, $booking_id) {
    $adminQuery = "SELECT user_id FROM users WHERE role = 'admin'";
    $admins = $conn->query($adminQuery);

    if ($admins->num_rows > 0) {
        while ($admin = $admins->fetch_assoc()) {
            $admin_id = $admin['user_id'];

            $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message) 
                           VALUES (?, ?, 'booking', ?)";
            $notifStmt = $conn->prepare($notifQuery);

            $message = "New booking request from User #{$user_id} (Booking ID: {$booking_id})";
            $notifStmt->bind_param("iis", $admin_id, $booking_id, $message);
            $notifStmt->execute();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $facility_id = $_POST['facility_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = trim($_POST['purpose']);

    // Check if the facility is under maintenance
    $statusQuery = "SELECT availability_status FROM amenities WHERE facility_id = ?";
    $stmt = $conn->prepare($statusQuery);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $facility = $result->fetch_assoc();

    if ($facility['availability_status'] == 'under maintenance') {
        $error = "This facility is under maintenance and cannot be booked.";
    } else {
// Check for time slot conflict
$checkQuery = "SELECT COUNT(*) AS count FROM bookings 
               WHERE facility_id = ? AND booking_date = ? 
               AND (
                   (start_time < ? AND end_time > ?)  -- Overlapping start
                   OR (start_time >= ? AND start_time < ?) -- Starts inside another booking
                   OR (end_time > ? AND end_time <= ?) -- Ends inside another booking
                   OR (start_time <= ? AND end_time >= ?) -- Completely overlaps
               )";

$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("isssssssss", 
                  $facility_id, $booking_date, 
                  $end_time, $start_time,  
                  $start_time, $end_time,  
                  $start_time, $end_time,  
                  $start_time, $end_time); 

$stmt->execute();
$result = $stmt->get_result();
$existingBooking = $result->fetch_assoc();


        if ($existingBooking['count'] > 0) {
            $error = "This facility is already booked on {$booking_date} from {$start_time} to {$end_time}. Please choose another time.";
        } else {
            // Insert the booking
            $insertQuery = "INSERT INTO bookings (user_id, facility_id, booking_date, start_time, end_time, purpose, status, payment_status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iissss", $user_id, $facility_id, $booking_date, $start_time, $end_time, $purpose);

            if ($stmt->execute()) {
                $booking_id = $stmt->insert_id;

                // Notify admins
                notifyAdminsForBooking($conn, $user_id, $booking_id);

                header("Location: view_bookings.php?msg=Booking successfully created");
                exit();
            } else {
                $error = "Error creating booking.";
            }
        }
    }
}

// Set the timezone for the Philippines
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Booking</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="booking-container">
        <div class="booking-header">
            <h2><i class="fas fa-calendar-check"></i> Create Booking</h2>
            <p class="current-datetime">Philippine Time: <?= date('F d, Y - h:i A') ?></p>
        </div>
        
        <div class="booking-content">
            <div class="booking-form">
                <div class="form-header">
                    <h3>Book a Facility</h3>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" action="create_booking.php" id="bookingForm">
                    <div class="form-group">
                        <label for="facility_id"><i class="fas fa-building"></i> Select Facility:</label>
                        <select name="facility_id" id="facility_id" required>
                            <option value="">-- Select a Facility --</option>
                            <?php foreach ($facilities as $facility): ?>
                                <option value="<?= $facility['facility_id'] ?>" 
                                    <?= ($facility['availability_status'] == 'under maintenance') ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($facility['name']) ?> 
                                    <?= ($facility['availability_status'] == 'under maintenance') ? '(Under Maintenance)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_date"><i class="fas fa-calendar-alt"></i> Select Date:</label>
                        <input type="date" name="booking_date" id="booking_date" min="<?= $today ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time"><i class="fas fa-clock"></i> Start Time:</label>
                        <input type="time" name="start_time" id="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time"><i class="fas fa-clock"></i> End Time:</label>
                        <input type="time" name="end_time" id="end_time" required>
                    </div>


                    
                    <div class="form-group">
                        <label for="purpose"><i class="fas fa-align-left" style:color = "white"></i> Purpose of Booking:</label>
                        <textarea name="purpose" id="purpose" placeholder="Please provide details about your booking purpose..." required><?= isset($purpose) ? htmlspecialchars($purpose) : '' ?></textarea>
                        <small class="form-text text-muted" style:color="white";>Please provide specific details about how you plan to use the facility.</small>
                    </div>
                    
                    <div class="form-group booking-terms">
                        <label class="checkbox-container">
                            <input type="checkbox" id="terms" required>
                            <span class="checkmark"></span>
                            I agree to the booking terms and conditions
                        </label>
                    </div>

                    <button type="submit" id="bookBtn"><i class="fas fa-check-circle"></i> Book Now</button>
                </form>
            </div>
            
            <div class="facility-list">
                <h3 class="facility-list-header">Available Facilities</h3>
                <div class="facility-cards">
                    <?php foreach ($facilities as $facility): ?>
                        <div class="facility-card <?= ($facility['availability_status'] == 'under maintenance') ? 'maintenance' : '' ?>">
                            <div class="facility-image">
                                <img src="<?= !empty($facility['image_url']) ? htmlspecialchars($facility['image_url']) : '/subdisystem/images/default-facility.jpg' ?>" alt="<?= htmlspecialchars($facility['name']) ?>">
                                <?php if ($facility['availability_status'] == 'under maintenance'): ?>
                                    <div class="maintenance-badge">Under Maintenance</div>
                                <?php endif; ?>
                            </div>
                            <div class="facility-details">
                                <h4><?= htmlspecialchars($facility['name']) ?></h4>
                                <p><?= !empty($facility['description']) ? htmlspecialchars($facility['description']) : 'No description available.' ?></p>
                                <div class="facility-status <?= $facility['availability_status'] ?>">
                                    <i class="fas fa-circle"></i> <?= ucfirst($facility['availability_status']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="booking-info">
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Booking Information</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Bookings are available from Monday to Saturday, 8:00 AM to 7:00 PM.</li>
                    <li><i class="fas fa-check"></i> All bookings are subject to approval by the administration.</li>
                    <li><i class="fas fa-check"></i> Booking requests must be made at least 24 hours in advance.</li>
                    <li><i class="fas fa-check"></i> Payment details will be provided after booking approval.</li>
                    <li><i class="fas fa-check"></i> Please check your notification panel for booking status updates.</li>
                </ul>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Highlight selected facility in the facility list when chosen in dropdown
    const facilitySelect = document.getElementById('facility_id');
    facilitySelect.addEventListener('change', function() {
        const selectedFacilityId = this.value;
        const facilityCards = document.querySelectorAll('.facility-card');
        
        facilityCards.forEach(card => {
            card.classList.remove('selected');
        });
        
        if (selectedFacilityId) {
            const selectedCard = document.querySelector(`.facility-card[data-id="${selectedFacilityId}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
    
    // Form validation
    const bookingForm = document.getElementById('bookingForm');
    const bookBtn = document.getElementById('bookBtn');
    
    bookingForm.addEventListener('submit', function(event) {
        const facility = document.getElementById('facility_id').value;
        const date = document.getElementById('booking_date').value;
        const timeSlot = document.getElementById('time_slot').value;
        const purpose = document.getElementById('purpose').value;
        const terms = document.getElementById('terms').checked;
        
        if (!facility || !date || !timeSlot || !purpose.trim() || !terms) {
            event.preventDefault();
            alert('Please fill in all required fields and accept the terms and conditions.');
        } else {
            bookBtn.disabled = true;
            bookBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
    });
    
    // Add data-id attribute to facility cards for selection
    const facilityCards = document.querySelectorAll('.facility-card');
    <?php foreach ($facilities as $facility): ?>
        document.querySelector(`.facility-card:nth-child(<?= array_search($facility, $facilities) + 1 ?>)`).setAttribute('data-id', '<?= $facility['facility_id'] ?>');
    <?php endforeach; ?>
    
    // Allow clicking on facility cards to select them in the dropdown
    facilityCards.forEach(card => {
        if (!card.classList.contains('maintenance')) {
            card.addEventListener('click', function() {
                const facilityId = this.getAttribute('data-id');
                document.getElementById('facility_id').value = facilityId;
                
                facilityCards.forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        }
    });
});
</script>

<style>

/* Simplified Booking System */
body {
    background-color: #f5f5f5; /* Light gray background */
    color: #333333;
    font-family: 'Roboto', 'Arial', sans-serif;
    margin: 0;
    padding: 0;
    line-height: 1.6;
}

/* Main Container */
.booking-container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 20px;
}

/* Header Styling */
.booking-header {
    text-align: center;
    margin-bottom: 30px;
    position: relative;

}

.booking-header h2 {
    font-size: 32px;
    margin-bottom: 10px;
    color:rgb(255, 255, 255); /* Dark blue-gray */
    font-weight: bold;
    color:black;
}

.current-datetime {
    font-size: 16px;
    color: #7f8c8d; /* Medium gray */
    font-weight: 500;
}

/* Booking Content Layout */
.booking-content {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    margin-bottom: 30px;
}

/* Form Styling */
.booking-form {
    flex: 1;
    min-width: 400px;
    background-color: #ffffff; /* White background */
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-top: 4px solid #3498db; /* Blue accent */
}

.form-header {
    margin-bottom: 25px;
}

.form-header h3 {
    font-size: 22px;
    margin-bottom: 15px;
    color: #2c3e50; /* Dark blue-gray */
}

.alert {
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.alert-danger {
    background-color: #ffebee; /* Light red */
    border-left: 4px solid #e74c3c; /* Red */
    color: #c0392b; /* Dark red */
}

.form-group-label{
    margin-bottom: 20px;
    color:rgb(255, 255, 255);
}

.label {
    display: block;
    font-weight: 500;
    font-size: 16px;
    margin-bottom: 8px;
    color:rgb(255, 255, 255);
}

label i {
    margin-right: 8px;
    color:rgb(255, 255, 255);
}

select, input, textarea {
    width: 100%;
    padding: 14px;
    border: 1px solid #dcdfe6;
    border-radius: 6px;
    background: #ffffff;
    color: #333333;
    font-size: 16px;
    transition: all 0.3s;
}

select:focus, input:focus, textarea:focus {
    background: #ffffff;
    border-color: #3498db; /* Blue */
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
}

textarea {
    resize: vertical;
    min-height: 120px;
    color: #333333;
}

.form-text {
    display: block;
    margin-top: 6px;
    font-size: 13px;
    color:rgb(255, 255, 255);
}

/* Checkbox styling */
.booking-terms {
    margin: 25px 0;
}

.checkbox-container {
    display: block;
    position: relative;
    padding-left: 35px;
    cursor: pointer;
    font-size: 16px;
    color: #333333;
}

.checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 22px;
    width: 22px;
    background-color: #ffffff;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
}

.checkbox-container:hover input ~ .checkmark {
    background-color: #f3f4f6;
}

.checkbox-container input:checked ~ .checkmark {
    background-color: #3498db; /* Blue */
    border-color: #3498db;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

.checkbox-container input:checked ~ .checkmark:after {
    display: block;
}

.checkbox-container .checkmark:after {
    left: 8px;
    top: 4px;
    width: 5px;
    height: 10px;
    border: solid #ffffff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Button styling */
button {
    width: 100%;
    background: #3498db; /* Blue */
    color: white;
    padding: 16px;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

button:hover {
    background: #2980b9; /* Darker blue */
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

button:active {
    transform: translateY(0);
}

button:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}

button i {
    font-size: 20px;
}

/* Facility List Styling */
.facility-list {
    flex: 1.2;
    min-width: 400px;
}

.facility-list-header {
    font-size: 22px;
    margin-bottom: 20px;
    color: #2c3e50; /* Dark blue-gray */
    text-align: center;
}

.facility-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.facility-card {
    background-color: #ffffff; /* White */
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.facility-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.facility-card.selected {
    border: 2px solid #3498db; /* Blue border */
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
}

.facility-card.maintenance {
    opacity: 0.7;
    filter: grayscale(0.7);
    cursor: not-allowed;
}

.facility-image {
    height: 180px;
    overflow: hidden;
    position: relative;
}

.facility-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.facility-card:hover .facility-image img {
    transform: scale(1.05);
}

.maintenance-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #e74c3c; /* Red */
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.facility-details {
    padding: 15px;
}

.facility-details h4 {
    font-size: 18px;
    margin: 0 0 10px;
    color: #2c3e50; /* Dark blue-gray */
}

.facility-details p {
    font-size: 14px;
    color: #7f8c8d; /* Medium gray */
    margin-bottom: 15px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.facility-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.facility-status.available {
    background-color: #e3f7e5; /* Light green */
    color: #27ae60; /* Green */
}

.facility-status.under.maintenance {
    background-color: #fff8e1; /* Light yellow */
    color: #f39c12; /* Yellow-orange */
}

.facility-status i {
    font-size: 10px;
    margin-right: 5px;
}

/* Booking Info Section */
.booking-info {
    margin-top: 30px;
}

.info-card {
    background-color: #ffffff; /* White */
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #3498db; /* Blue accent */
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
    color: #2c3e50; /* Dark blue-gray */
    font-size: 20px;
    margin-top: 0;
    margin-bottom: 15px;
}

.info-card h3 i {
    margin-right: 10px;
    color: #3498db; /* Blue */
}

.info-card ul {
    padding-left: 20px;
}

.info-card li {
    margin-bottom: 10px;
    position: relative;
    padding-left: 30px;
    color: #333333;
}

.info-card li i {
    color: #3498db; /* Blue */
    position: absolute;
    left: 0;
    top: 3px;
}

/* Fix for form elements in the original dark theme */
.form-group label {
    color: white;
}

.form-group small {
    color: #7f8c8d !important; /* Medium gray */
}

/* Responsive Design */
@media (max-width: 1100px) {
    .booking-content {
        flex-direction: column;
    }
    
    .booking-form, .facility-list {
        width: 100%;
        min-width: auto;
    }
}

@media (max-width: 768px) {
    .booking-container {
        padding: 15px;
        margin: 15px auto;
    }
    
    .facility-cards {
        grid-template-columns: 1fr;
    }
    
    .booking-form {
        padding: 20px 15px;
    }
    
    .booking-header h2 {
        font-size: 26px;
    }
    
    select, input, textarea {
        padding: 12px;
    }
    
    button {
        padding: 14px;
        font-size: 16px;
    }
}
</style>

</body>
</html>