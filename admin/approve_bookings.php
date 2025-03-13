<?php
include '../includes/header.php';


// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: /subdisystem/user/login.php");
    exit();
}
// Ensure only admin can access
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can approve bookings.");
}

// Handle approval/rejection with AJAX support
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id'], $_POST['action'])) {
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action']; // Approve or Reject

    // Fetch user_id to notify the user
    $query = "SELECT user_id FROM bookings WHERE booking_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $user_id = $booking['user_id'];

    if ($action === 'approve') {
        $status = 'confirmed';
        $payment_status = 'paid';
        $message = "Your booking #$booking_id has been **approved**.";
    } elseif ($action === 'reject') {
        $status = 'canceled';
        $payment_status = 'pending';
        $message = "Your booking #$booking_id has been **rejected**.";
    } else {
        die("Invalid action.");
    }

    // Update booking status
    $updateQuery = "UPDATE bookings SET status = ?, payment_status = ? WHERE booking_id = ?";
    if ($stmt = $conn->prepare($updateQuery)) {
        $stmt->bind_param("ssi", $status, $payment_status, $booking_id);
        if ($stmt->execute()) {
            // Insert notification into the database
            $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message) VALUES (?, ?, 'booking', ?)";
            $stmt = $conn->prepare($notifQuery);
            $stmt->bind_param("iis", $user_id, $booking_id, $message);
            $stmt->execute();

            echo json_encode(["success" => true, "message" => "Booking successfully updated!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error updating booking status: " . $stmt->error]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $conn->error]);
    }
    exit();
}

// Fetch bookings again to reflect updates
$query = "SELECT b.booking_id, b.user_id, u.f_name, u.l_name, u.email, u.phone_number, u.role, u.image_url, 
                 a.name AS facility, a.image_url AS facility_image, b.booking_date, b.start_time, b.end_time, b.purpose, b.status, b.payment_status
          FROM bookings b
          JOIN users u ON b.user_id = u.user_id
          JOIN amenities a ON b.facility_id = a.facility_id
          WHERE b.status = 'pending'
          ORDER BY b.booking_date, b.start_time";

$result = $conn->query($query);
$bookings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin - Approve Bookings</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <style>
        :root {
            --primary: #4285f4;
            --primary-dark: #3367d6;
            --success: #34a853;
            --warning: #fbbc05;
            --danger: #ea4335;
            --light: #f8f9fa;
            --dark: #202124;
            --gray: #5f6368;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-header h2 {
            margin: 0;
            color: var(--dark);
            font-size: 24px;
        }
        
        .booking-count {
            background-color: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .bookings-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .bookings-table th {
            background-color: var(--primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .bookings-table tr {
            border-bottom: 1px solid #eee;
        }
        
        .bookings-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .bookings-table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: black;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .facility-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .facility-info{
            color:black;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        .status-pending {
            background-color: var(--warning);
            color: #333;
        }
        
        .status-confirmed {
            background-color: var(--success);
            color: white;
        }
        
        .status-canceled {
            background-color: var(--danger);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background-color: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #2d9448;
        }
        
        .btn-reject {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-reject:hover {
            background-color: #d23c2f;
        }
        
        .booking-details {
            display: flex;
            flex-direction: column;
            color: black;
        }
        
        .booking-time {
            font-weight: bold;
        }
        
        .booking-date {
            color: var(--gray);
        }
        
        .booking-purpose {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: black;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .payment-paid {
            background-color: #e6f4ea;
            color: var(--success);
        }
        
        .payment-pending {
            background-color: #fef7e0;
            color: #f9ab00;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .notification.success {
            background-color: var(--success);
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification.error {
            background-color: var(--danger);
            opacity: 1;
            transform: translateY(0);
        }
        
        .action-cell {
            min-width: 180px;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h2>Booking Approval Dashboard</h2>
            <span class="booking-count"><?= count($bookings) ?> Pending Bookings</span>
        </div>
        
        <div id="notification" class="notification"></div>
        
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <h3>No Pending Bookings</h3>
                <p>There are currently no bookings waiting for approval.</p>
            </div>
        <?php else: ?>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Facility</th>
                        <th>Booking Info</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr id="row-<?= $booking['booking_id']; ?>">
                            <td>
                                <div class="user-info">
                                    <img class="user-avatar" src="<?= htmlspecialchars($booking['image_url'] ?: '/subdisystem/images/default_avatar.png'); ?>" alt="User">
                                    <div>
                                        <div><strong><?= htmlspecialchars($booking['f_name'] . ' ' . $booking['l_name']); ?></strong></div>
                                        <div><?= htmlspecialchars($booking['email']); ?></div>
                                        <div><?= htmlspecialchars($booking['phone_number']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="facility-info">
                                    <img class="facility-image" src="<?= htmlspecialchars($booking['facility_image'] ?: '/subdisystem/images/default_facility.jpg'); ?>" alt="Facility">
                                    <div><strong><?= htmlspecialchars($booking['facility']); ?></strong></div>
                                </div>
                            </td>
                            <td>
                                <div class="booking-details">
                                    <div class="booking-date"><?= date('F j, Y', strtotime($booking['booking_date'])); ?></div>
                                    <div class="booking-time">
                                        <?= date('g:i A', strtotime($booking['start_time'])); ?> - 
                                        <?= date('g:i A', strtotime($booking['end_time'])); ?>
                                    </div>
                                    <div>Booking #<?= $booking['booking_id']; ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="booking-purpose" title="<?= htmlspecialchars($booking['purpose']); ?>">
                                    <?= htmlspecialchars($booking['purpose']); ?>
                                </div>
                            </td>
                            <td>
                                <div id="status-<?= $booking['booking_id']; ?>">
                                    <span class="status-badge status-<?= $booking['status']; ?>">
                                        <?= ucfirst(htmlspecialchars($booking['status'])); ?>
                                    </span>
                                </div>
                                <div id="payment-<?= $booking['booking_id']; ?>">
                                    <span class="payment-badge payment-<?= $booking['payment_status']; ?>">
                                        <?= ucfirst(htmlspecialchars($booking['payment_status'])); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <button class="btn btn-approve" onclick="updateBooking(<?= $booking['booking_id']; ?>, 'approve')">
                                        Approve
                                    </button>
                                    <button class="btn btn-reject" onclick="updateBooking(<?= $booking['booking_id']; ?>, 'reject')">
                                        Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            
            setTimeout(() => {
                notification.className = 'notification';
            }, 3000);
        }
        
        function updateBooking(bookingId, action) {
            if (confirm(`Are you sure you want to ${action} this booking?`)) {
                fetch("approve_bookings.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `booking_id=${bookingId}&action=${action}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the row styling
                        const statusCell = document.getElementById(`status-${bookingId}`);
                        const paymentCell = document.getElementById(`payment-${bookingId}`);
                        const row = document.getElementById(`row-${bookingId}`);
                        
                        if (action === 'approve') {
                            statusCell.innerHTML = '<span class="status-badge status-confirmed">Confirmed</span>';
                            paymentCell.innerHTML = '<span class="payment-badge payment-paid">Paid</span>';
                        } else {
                            statusCell.innerHTML = '<span class="status-badge status-canceled">Canceled</span>';
                            paymentCell.innerHTML = '<span class="payment-badge payment-pending">Pending</span>';
                        }
                        
                        // Show success notification
                        showNotification(`Booking #${bookingId} ${action === 'approve' ? 'approved' : 'rejected'} successfully!`, 'success');
                        
                        // Fade out and remove the row after a short delay
                        setTimeout(() => {
                            row.style.opacity = '0.5';
                            row.style.transition = 'opacity 0.5s';
                            
                            setTimeout(() => {
                                row.remove();
                                
                                // Update booking count
                                const bookingCount = document.querySelector('.booking-count');
                                const count = parseInt(bookingCount.textContent);
                                bookingCount.textContent = `${count - 1} Pending Bookings`;
                                
                                // Show empty state if no more bookings
                                if (count - 1 === 0) {
                                    const table = document.querySelector('.bookings-table');
                                    table.insertAdjacentHTML('beforebegin', `
                                        <div class="empty-state">
                                            <h3>No Pending Bookings</h3>
                                            <p>There are currently no bookings waiting for approval.</p>
                                        </div>
                                    `);
                                    table.remove();
                                }
                            }, 500);
                        }, 1000);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error("Fetch Error:", error);
                    showNotification("Something went wrong!", 'error');
                });
            }
        }
    </script>
</body>
</html>