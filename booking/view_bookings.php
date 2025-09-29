<?php
include '../includes/header.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: /subdisystem/user/login.php?message=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT b.booking_id, a.name AS facility, b.booking_date, b.purpose, b.status, b.payment_status, b.start_time, b.end_time
          FROM bookings b
          JOIN amenities a ON b.facility_id = a.facility_id
          WHERE b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Bookings</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>


.bookings-container {
    max-width: 1200px;
    margin: 40px auto;
}

.card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid #e3e6f0;
    background-color: #ffffff;
}

.card-header {
    background-color: #5a67d8;
    color: white;
    padding: 1.25rem;
    font-weight: 600;
    text-transform: uppercase;
    border-bottom: 1px solid #d1d5db;
}

.table th, .table td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #e3e6f0;
}

.badge-pending {
    background-color: #ffcc00;
    color: #333;
    border-radius: 8px;
    padding: 5px 10px;
}
.badge-confirmed {
    background-color: #28a745;
    color: white;
    border-radius: 8px;
    padding: 5px 10px;
}
.badge-cancelled {
    background-color: #e63946;
    color: white;
    border-radius: 8px;
    padding: 5px 10px;
}
.badge-paid {
    background-color: #1e7e34;
    color: white;
    border-radius: 8px;
    padding: 5px 10px;
}
.badge-unpaid {
    background-color: #dc3545;
    color: white;
    border-radius: 8px;
    padding: 5px 10px;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    border: none;
    border-radius: 6px;
    padding: 10px 20px;
    width: 140px; /* Ensures both buttons have the same width */
    height: 42px; /* Ensures both buttons have the same height */
    transition: background-color 0.2s, transform 0.1s;
    cursor: pointer;
}

.btn-edit {
    background-color: #5a67d8;
    color: white;
}

.btn-edit:hover {
    background-color: #4c51bf;
}

.btn-cancel {
    background-color: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background-color: #5a6268;
}

.btn:active {
    transform: scale(0.98);
}



.empty-bookings {
    padding: 50px 20px;
    text-align: center;
    color: #6c757d;
    font-size: 1.1rem;
}

    </style>
</head>
<body>
    <div class="container bookings-container">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>My Bookings
                </h2>
            </div>
            
            <div class="table-responsive">
                <?php if (count($bookings) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingTable">
                            <?php foreach ($bookings as $booking): ?>
                                <tr id="row-<?= $booking['booking_id']; ?>">
                                    <td data-label="Facility">
                                        <i class="fas fa-building me-2"></i><?= htmlspecialchars($booking['facility']); ?>
                                    </td>
                                    <td data-label="Date">
                                        <i class="fas fa-calendar-day me-2"></i><?= date('M d, Y', strtotime($booking['booking_date'])); ?>
                                    </td>
                                    <td data-label="Time">
                                        <i class="far fa-clock me-2"></i>
                                        <?= date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])); ?>
                                    </td>
                                    <td data-label="Purpose">
                                        <i class="fas fa-tasks me-2"></i><?= htmlspecialchars($booking['purpose']); ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php 
                                        $statusClass = 'badge-pending';
                                        if ($booking['status'] === 'confirmed') {
                                            $statusClass = 'badge-confirmed';
                                        } elseif ($booking['status'] === 'cancelled') {
                                            $statusClass = 'badge-cancelled';
                                        }
                                        ?>
                                        <span id="status-<?= $booking['booking_id']; ?>" class="badge <?= $statusClass; ?>">
                                            <?= ucfirst(htmlspecialchars($booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Payment">
                                        <?php 
                                        $paymentClass = ($booking['payment_status'] === 'paid') ? 'badge-paid' : 'badge-unpaid';
                                        ?>
                                        <span id="payment-<?= $booking['booking_id']; ?>" class="badge <?= $paymentClass; ?>">
                                            <?= ucfirst(htmlspecialchars($booking['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-btns">
                                            <?php
                                            $currentDateTime = new DateTime();
                                            $bookingEndDateTime = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
                                            if ($bookingEndDateTime < $currentDateTime && $booking['status'] === 'confirmed' && $booking['payment_status'] === 'paid'): ?>
                                                <button onclick="deleteBooking(<?= $booking['booking_id']; ?>)" class="btn btn-action btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <a href="edit_booking.php?id=<?= $booking['booking_id']; ?>" class="btn btn-action btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button onclick="cancelBooking(<?= $booking['booking_id']; ?>)" class="btn btn-action btn-cancel">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-bookings">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Bookings Found</h4>
                        <p class="text-muted">You haven't made any facility bookings yet.</p>
                        <a href="/subdisystem/booking/create_booking.php" class="btn btn-primary mt-3">Book a Facility</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function cancelBooking(bookingId) {
            if (confirm("Are you sure you want to cancel this booking?")) {
                fetch('delete_booking.php?id=' + bookingId, { method: 'GET' })
                .then(response => response.text())
                .then(data => {
                    document.getElementById("status-" + bookingId).innerText = "Cancelled";
                    document.getElementById("status-" + bookingId).className = "badge badge-cancelled";
                    alert("Booking Cancelled Successfully");
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Error cancelling booking. Please try again.");
                });
            }
        }
        function deleteBooking(bookingId) {
            if (confirm("Are you sure you want to delete this booking?")) {
                fetch('delete_booking.php?id=' + bookingId, { method: 'GET' })
                .then(response => response.text())
                .then(data => {
                    document.getElementById("row-" + bookingId).remove();
                    alert("Booking Deleted Successfully");
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Error deleting booking. Please try again.");
                });
            }
        }
        // Function to refresh the booking status dynamically
        function refreshBookings() {
            fetch('fetch_bookings.php')
            .then(response => response.json())
            .then(data => {
                data.forEach(booking => {
                    const statusElement = document.getElementById("status-" + booking.booking_id);
                    const paymentElement = document.getElementById("payment-" + booking.booking_id);
                    
                    if (statusElement) {
                        statusElement.innerText = booking.status;
                        
                        // Update status badge class
                        statusElement.className = "badge";
                        if (booking.status.toLowerCase() === "confirmed") {
                            statusElement.classList.add("badge-confirmed");
                        } else if (booking.status.toLowerCase() === "cancelled") {
                            statusElement.classList.add("badge-cancelled");
                        } else {
                            statusElement.classList.add("badge-pending");
                        }
                    }
                    
                    if (paymentElement) {
                        paymentElement.innerText = booking.payment_status;
                        
                        // Update payment badge class
                        paymentElement.className = "badge";
                        if (booking.payment_status.toLowerCase() === "paid") {
                            paymentElement.classList.add("badge-paid");
                        } else {
                            paymentElement.classList.add("badge-unpaid");
                        }
                    }
                });
            })
            .catch(error => console.error("Error:", error));
        }

        // Refresh bookings every 5 seconds
        setInterval(refreshBookings, 5000);

    document.addEventListener("DOMContentLoaded", function () {
        console.log("Initializing Bootstrap Dropdowns...");
        var dropdownElements = document.querySelectorAll('.dropdown-toggle');
        dropdownElements.forEach(function (dropdown) {
            new bootstrap.Dropdown(dropdown);
        });

        console.log("Bootstrap Dropdowns Initialized!");
    });

    document.addEventListener("click", function (event) {
        if (event.target.matches(".dropdown-toggle")) {
            let dropdown = new bootstrap.Dropdown(event.target);
            dropdown.show();
        }
    });

    console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");

    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>