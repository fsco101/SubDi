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

// Function to generate receipt
function generateReceipt($conn, $booking_id) {
    // First, get the user columns to handle different schemas
    $userColumnsQuery = "SHOW COLUMNS FROM users";
    $userColumns = $conn->query($userColumnsQuery);
    $hasFirstName = false;
    $hasLastName = false;
    $nameColumn = "";
    
    if ($userColumns) {
        while ($column = $userColumns->fetch_assoc()) {
            if ($column['Field'] == 'first_name') {
                $hasFirstName = true;
            }
            if ($column['Field'] == 'last_name') {
                $hasLastName = true;
            }
            if ($column['Field'] == 'name' || $column['Field'] == 'full_name' || $column['Field'] == 'username') {
                $nameColumn = $column['Field'];
            }
        }
    }
    
    // Adjust the query based on available columns
    if ($hasFirstName && $hasLastName) {
        $query = "SELECT b.*, a.name as facility_name, u.first_name, u.last_name, u.email
                  FROM bookings b
                  JOIN amenities a ON b.facility_id = a.facility_id
                  JOIN users u ON b.user_id = u.user_id
                  WHERE b.booking_id = ?";
    } else if (!empty($nameColumn)) {
        $query = "SELECT b.*, a.name as facility_name, u.{$nameColumn} as user_name, u.email
                  FROM bookings b
                  JOIN amenities a ON b.facility_id = a.facility_id
                  JOIN users u ON b.user_id = u.user_id
                  WHERE b.booking_id = ?";
    } else {
        // Fallback query with minimal user information
        $query = "SELECT b.*, a.name as facility_name, u.user_id, u.email
                  FROM bookings b
                  JOIN amenities a ON b.facility_id = a.facility_id
                  JOIN users u ON b.user_id = u.user_id
                  WHERE b.booking_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debugging: Log the submitted form data
    error_log("Form Data: " . print_r($_POST, true));

    $facility_id = isset($_POST['facility_id']) ? $_POST['facility_id'] : null;
    $booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : null;
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : null;
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : null;

    // Validate required fields
    if (empty($facility_id) || empty($booking_date) || empty($start_time) || empty($end_time) || empty($purpose)) {
        $error = "All fields are required. Please fill in all the details.";
    } else {
        // Check if the user already has an active booking
        $activeBookingQuery = "SELECT COUNT(*) AS count FROM bookings WHERE user_id = ? AND status IN ('pending', 'confirmed')";
        $stmt = $conn->prepare($activeBookingQuery);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $activeBooking = $result->fetch_assoc();

        if ($activeBooking['count'] > 0) {
            $error = "You already have an active booking. Please complete or cancel it before creating a new one.";
        } else {
            // Validate date and time formats
            $dateValid = DateTime::createFromFormat('Y-m-d', $booking_date) !== false;
            $startTimeValid = DateTime::createFromFormat('H:i', $start_time) !== false;
            $endTimeValid = DateTime::createFromFormat('H:i', $end_time) !== false;

            if (!$dateValid || !$startTimeValid || !$endTimeValid) {
                $error = "Invalid date or time format. Please try again.";
            } else {
                $user_id = $_SESSION['user_id'];

                // Debugging: Log the values
                error_log("Booking Date: $booking_date, Start Time: $start_time, End Time: $end_time");

                if (empty($booking_date) || empty($start_time) || empty($end_time)) {
                    $error = "Please provide a valid date and time.";
                } else {
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
                        // Check if the user has exceeded the booking limit (3 times per week)
                        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($booking_date)));
                        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($booking_date)));

                        $limitQuery = "SELECT COUNT(*) AS count 
                                       FROM bookings 
                                       WHERE user_id = ? AND booking_date BETWEEN ? AND ?";
                        $stmt = $conn->prepare($limitQuery);
                        $stmt->bind_param("iss", $user_id, $weekStart, $weekEnd);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $userBookings = $result->fetch_assoc();

                        if ($userBookings['count'] >= 3) {
                            $error = "You have reached the maximum booking limit of 3 times per week.";
                        } else {
                            // Check for time slot conflict for the selected facility
                            $checkQuery = "SELECT COUNT(*) AS count 
                                           FROM bookings 
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
                                // Check for duplicate booking by the same user for the same facility, date, and time
                                $duplicateQuery = "SELECT COUNT(*) AS count 
                                                   FROM bookings 
                                                   WHERE user_id = ? AND facility_id = ? AND booking_date = ? 
                                                   AND start_time = ? AND end_time = ?";
                                $stmt = $conn->prepare($duplicateQuery);
                                $stmt->bind_param("iisss", $user_id, $facility_id, $booking_date, $start_time, $end_time);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $duplicateBooking = $result->fetch_assoc();

                                if ($duplicateBooking['count'] > 0) {
                                    $error = "You have already booked this facility for the selected date and time.";
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
                                        
                                        // Generate receipt data
                                        $receipt_data = generateReceipt($conn, $booking_id);
                                        
                                        // Store receipt data in session for display
                                        if ($receipt_data) {
                                            $_SESSION['receipt_data'] = $receipt_data;
                                            header("Location: receipt.php?booking_id=" . $booking_id);
                                            exit();
                                        } else {
                                            header("Location: view_bookings.php?msg=Booking successfully created");
                                            exit();
                                        }
                                    } else {
                                        $error = "Error creating booking.";
                                    }
                                }
                            }
                        }
                    }
                }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
                        <input type="text" id="booking_date" class="form-control" placeholder="Select a date" readonly required>
                        <input type="hidden" name="booking_date" id="hidden_booking_date">
                    </div>

                    <!-- Modal -->
                    <div id="datePickerModal" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <h3>Select a Date</h3>
                            <div id="calendar"></div>
                            <button id="confirmDate" class="btn btn-primary">Confirm Date</button>
                        </div>
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
                    <li><i class="fas fa-check"></i> A receipt will be generated after successful booking submission.</li>
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
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const purpose = document.getElementById('purpose').value;
        
        if (!facility || !date || !startTime || !endTime || !purpose.trim()) {
            event.preventDefault();
            alert('Please fill in all required fields.');
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

document.addEventListener('DOMContentLoaded', function () {
    const bookingDateInput = document.getElementById('booking_date');
    const hiddenBookingDateInput = document.getElementById('hidden_booking_date');
    const facilitySelect = document.getElementById('facility_id');
    const datePickerModal = document.getElementById('datePickerModal');
    const closeModal = document.querySelector('.modal .close');
    const confirmDateButton = document.getElementById('confirmDate');
    let selectedDate = null;

    // Initialize Flatpickr
    const flatpickrInstance = flatpickr('#calendar', {
        inline: true,
        minDate: new Date().fp_incr(1), // Set minimum date to 1 day in the future
        disable: [], // Unavailable dates will be added here dynamically
        onChange: function (selectedDates) {
            selectedDate = selectedDates[0];
        }
    });

    // Fetch unavailable dates for the selected facility
    function fetchUnavailableDates(facilityId) {
        if (!facilityId) {
            flatpickrInstance.set('disable', []); // Reset if no facility is selected
            return;
        }

        fetch(`/subdisystem/booking/get_unavailable_dates.php?facility_id=${facilityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.unavailableDates) {
                    flatpickrInstance.set('disable', data.unavailableDates);
                } else {
                    flatpickrInstance.set('disable', []); // Reset if no unavailable dates
                }
            })
            .catch(error => console.error('Error fetching unavailable dates:', error));
    }

    // Update unavailable dates when facility changes
    facilitySelect.addEventListener('change', function () {
        const facilityId = this.value;
        fetchUnavailableDates(facilityId);
    });

    // Open modal on input click
    bookingDateInput.addEventListener('click', function () {
        if (!facilitySelect.value) {
            alert('Please select a facility first.');
            return;
        }
        datePickerModal.style.display = 'block';
    });

    // Close modal
    closeModal.addEventListener('click', function () {
        datePickerModal.style.display = 'none';
    });

    // Confirm selected date
    confirmDateButton.addEventListener('click', function () {
        if (selectedDate) {
            const formattedDate = flatpickr.formatDate(selectedDate, 'Y-m-d');
            bookingDateInput.value = formattedDate; // Update visible input
            hiddenBookingDateInput.value = formattedDate; // Update hidden input
            datePickerModal.style.display = 'none';
        } else {
            alert('Please select a date.');
        }
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function (event) {
        if (event.target === datePickerModal) {
            datePickerModal.style.display = 'none';
        }
    });
});
</script>
</body>
</html>

<style>
    /* Simplified Booking System */

/* Main Container */
.booking-container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 20px;
}

/* Header Styling */
.booking-header {
    text-align: center;
    margin: 50px 0 30px 0;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}



.booking-header h2 {
    font-size: 32px;
    margin-bottom: 10px;
    color: black;
    font-weight: bold;
    text-align: center;
}

.current-datetime {
    font-size: 16px;
    color: #7f8c8d;
    font-weight: 500;
    text-align: center;
}

/* Booking Content Layout */
.booking-content {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    margin-bottom: 30px;
}

.booking-form {
    flex: 1;
    min-width: 400px;
    background-color: #ffffff;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-top: 4px solid #3498db;
}

.form-header {
    margin-bottom: 25px;
}

.form-header h3 {
    font-size: 22px;
    margin-bottom: 15px;
    color: #2c3e50;
}

.alert {
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.alert-danger {
    background-color: #ffebee;
    border-left: 4px solid #e74c3c;
    color: #c0392b;
}

.form-group label {
    color: black;
    font-weight: bold; /* Optional: Makes the text bold */
}


/* Override form element styles for the booking context */
.booking-form select, 
.booking-form input, 
.booking-form textarea {
    padding: 14px;
    border: 1px solid #dcdfe6;
    transition: all 0.3s;
}

.booking-form select:focus, 
.booking-form input:focus, 
.booking-form textarea:focus {
    background: #ffffff;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.booking-form textarea {
    min-height: 120px;
    color: #333333;
}

.form-text {
    display: block;
    margin-top: 6px;
    font-size: 13px;
    color: rgb(0, 0, 0) !important;
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
    background-color: #3498db;
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
    background: #3498db;
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
    background: #2980b9;
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
    color: #2c3e50;
    text-align: center;
}

.facility-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.facility-card {
    background-color: #ffffff;
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
    border: 2px solid #3498db;
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
    background-color: #e74c3c;
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
    color: #2c3e50;
}

.facility-details p {
    font-size: 14px;
    color: #7f8c8d;
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
    background-color: #e3f7e5;
    color: #27ae60;
}

.facility-status.under.maintenance {
    background-color: #fff8e1;
    color: #f39c12;
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
    background-color: #ffffff;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #3498db;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
    color: #2c3e50;
    font-size: 20px;
    margin-top: 0;
    margin-bottom: 15px;
}

.info-card h3 i {
    margin-right: 10px;
    color: #3498db;
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
    color: #3498db;
    position: absolute;
    left: 0;
    top: 3px;
}

/* Fix for form elements in the original dark theme */
.form-group label {
    color: black;
}

.form-group small {
    color: #7f8c8d !important;
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

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    width: 50%;
    text-align: center;
    position: relative;
}

.modal .close {
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}

#calendar {
    margin: 20px 0;
}
</style>
<?php include '../includes/footer.php'; ?>