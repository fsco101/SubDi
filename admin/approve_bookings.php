<?php
// Start output buffering at the very beginning
ob_start();

include '../includes/header.php';
include '../send_email.php';

// Enable error logging
ini_set('display_errors', 0); // Don't display errors to users
error_log("Approve Bookings script started");

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

// Handler for AJAX requests - process first before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any buffered output
    ob_clean();
    
    // Set content type to JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Validate that we have the required parameters
    if (!isset($_POST['booking_id']) || !isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $bookingId = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $adminId = $_SESSION['user_id'];
    $rejectionReason = null;

    // Get rejection reason if action is reject
    if ($action === 'reject' && isset($_POST['rejection_reason'])) {
        $rejectionReason = $_POST['rejection_reason'];
        // Log the rejection reason for debugging
        error_log("Rejection reason received: $rejectionReason");
    }

    // Validate action
    if ($action !== 'approve' && $action !== 'reject') {
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit();
    }

    // Process the booking action
    $result = processBookingAction($bookingId, $action, $adminId, $rejectionReason);
    echo json_encode($result);
    exit();
}

// Helper function to process booking actions
function processBookingAction($bookingId, $action, $adminId, $rejectionReason = null) {
    global $conn;
    
    $status = ($action === 'approve') ? 'confirmed' : 'canceled';
    $paymentStatus = ($action === 'approve') ? 'paid' : 'pending';
    
    error_log("Processing booking ID: $bookingId with action: $action");
    if ($action === 'reject' && $rejectionReason) {
        error_log("Rejection reason: $rejectionReason");
    }
    
    try {
        $conn->begin_transaction();
        
        // Update booking status and rejection reason if provided
        if ($action === 'reject' && $rejectionReason) {
            $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ?, rejection_reason = ? WHERE booking_id = ?");
            if ($stmt === false) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            
            $stmt->bind_param("sssi", $status, $paymentStatus, $rejectionReason, $bookingId);
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE booking_id = ?");
            if ($stmt === false) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssi", $status, $paymentStatus, $bookingId);
        }
        
        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new Exception("Error updating booking or no booking found with ID: $bookingId");
        }
        
        // Get user email, user_id and facility info in a single query
        $dataStmt = $conn->prepare("
            SELECT u.email, u.user_id, a.name AS facility_name 
            FROM bookings b 
            JOIN users u ON b.user_id = u.user_id 
            JOIN amenities a ON b.facility_id = a.facility_id 
            WHERE b.booking_id = ?
        ");
        
        if (!$dataStmt || !$dataStmt->bind_param("i", $bookingId) || !$dataStmt->execute()) {
            throw new Exception("Error fetching booking data: " . $conn->error);
        }
        
        $dataResult = $dataStmt->get_result();
        if ($dataResult->num_rows === 0) {
            throw new Exception("No data found for booking ID: $bookingId");
        }
        
        $data = $dataResult->fetch_assoc();
        $email = $data['email'];
        $userId = $data['user_id'];
        $facilityName = $data['facility_name'];
        
        // Log admin action
        $actionText = $action === 'approve' ? "Approved booking #$bookingId" : "Canceled booking #$bookingId";
        if ($action === 'reject' && $rejectionReason) {
            $actionText .= " - Reason: $rejectionReason";
        }
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        
        if (!$logStmt || !$logStmt->bind_param("is", $adminId, $actionText) || !$logStmt->execute()) {
            error_log("Warning: Failed to log admin action: " . $conn->error);
            // Continue without throwing exception for log failures
        }
        
        // Create notification message
        $message = $action === 'approve' 
            ? "Your booking request for <strong>$facilityName</strong> has been confirmed and marked as paid."
            : "Your booking request for <strong>$facilityName</strong> has been canceled." . 
              ($rejectionReason ? " <strong>Reason:</strong> $rejectionReason" : "");
            
        // Add receipt link only for approved bookings
        if ($action === 'approve') {
            $message .= " <a href='/subdisystem/booking/receipt.php?booking_id=$bookingId'>View Receipt</a>";
        }
            
        // Add notification to database
        $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) 
                      VALUES (?, ?, 'booking', ?, NOW())";
        $notifStmt = $conn->prepare($notifQuery);
        
        if ($notifStmt && $notifStmt->bind_param("iis", $userId, $bookingId, $message) && $notifStmt->execute()) {
            error_log("Notification created for user $userId");
        } else {
            error_log("Warning: Failed to create notification: " . $conn->error);
            // Continue without throwing exception for notification failures
        }
        
        // Send email notification
        $subject = $action === 'approve' ? "Booking Confirmed: $facilityName" : "Booking Canceled: $facilityName";
        
        if (!sendEmail($email, $subject, $message)) {
            error_log("Warning: Failed to send email to $email");
            // Continue without throwing exception for email failures
        }

        $conn->commit();
        return ['success' => true, 'message' => $action === 'approve' 
            ? "Booking #$bookingId has been approved and marked as paid."
            : "Booking #$bookingId has been rejected." . ($rejectionReason ? " Reason: $rejectionReason" : "")];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    } finally {
        // Close statements
        if (isset($stmt)) $stmt->close();
        if (isset($dataStmt)) $dataStmt->close();
        if (isset($logStmt)) $logStmt->close();
        if (isset($notifStmt)) $notifStmt->close();
    }
}

// Fetch bookings again to reflect updates
$query = "SELECT b.booking_id, b.user_id, u.f_name, u.l_name, u.email, u.phone_number, u.role, u.image_url, 
                 a.name AS facility, a.image_url AS facility_image, b.booking_date, b.start_time, b.end_time, b.purpose, b.status, b.payment_status
          FROM bookings b
          JOIN users u ON b.user_id = u.user_id
          JOIN amenities a ON b.facility_id = a.facility_id
          WHERE b.status = 'pending'
          ORDER BY b.booking_date, b.start_time";

// Check for query error
$result = $conn->query($query);
if ($result === false) {
    error_log("Error in bookings query: " . $conn->error);
    $bookings = [];
} else {
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin - Approve Bookings</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h2><i class="bi bi-calendar-check"></i> Booking Approval Dashboard</h2>
            <span class="booking-count"><?= count($bookings) ?> Pending Bookings</span>
        </div>
        
        <div id="notification" class="notification"></div>
        
        <!-- Show initial notification if there are bookings -->
        <?php if (!empty($bookings)): ?>
        <script>
            // Display welcome notification when page loads
            window.addEventListener('DOMContentLoaded', (event) => {
                setTimeout(() => {
                    showNotification("Welcome to booking approval dashboard. You have <?= count($bookings) ?> pending bookings to review.", "info");
                }, 500);
            });
        </script>
        <?php endif; ?>
        
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h3>No Pending Bookings</h3>
                <p>There are currently no bookings waiting for approval.</p>
                <button class="btn btn-primary mt-3" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Page
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="bookings-table table table-striped table-hover">
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
                                            <div><small><?= htmlspecialchars($booking['email']); ?></small></div>
                                            <div><small><?= htmlspecialchars($booking['phone_number']); ?></small></div>
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
                                        <div><small>Booking #<?= $booking['booking_id']; ?></small></div>
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
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                        <button class="btn btn-reject" onclick="updateBooking(<?= $booking['booking_id']; ?>, 'reject')">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectionModalLabel">Provide Rejection Reason</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectionForm">
                        <input type="hidden" id="rejection_booking_id" name="booking_id">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="mb-3">
                            <label class="form-label">Select a reason:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason1" value="Facility unavailable due to maintenance">
                                <label class="form-check-label" for="reason1">Facility unavailable due to maintenance</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason2" value="Double booking conflict">
                                <label class="form-check-label" for="reason2">Double booking conflict</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason3" value="Incomplete booking information">
                                <label class="form-check-label" for="reason3">Incomplete booking information</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason4" value="Policy violation">
                                <label class="form-check-label" for="reason4">Policy violation</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reasonOther" value="other">
                                <label class="form-check-label" for="reasonOther">Other reason</label>
                            </div>
                            
                            <div class="mt-3" id="otherReasonContainer" style="display: none;">
                                <label for="otherReason" class="form-label">Specify reason:</label>
                                <textarea class="form-control" id="otherReason" rows="3" placeholder="Please provide specific details about why this booking is being rejected."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="submitRejection">Submit Rejection</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Improved notification function
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            if (!notification) return;
            
            // Clear any existing notifications
            clearTimeout(window.notificationTimeout);
            
            // Set icon based on notification type
            let icon;
            switch(type) {
                case 'success':
                    icon = '<i class="bi bi-check-circle-fill"></i>';
                    break;
                case 'error':
                    icon = '<i class="bi bi-exclamation-triangle-fill"></i>';
                    break;
                case 'info':
                    icon = '<i class="bi bi-info-circle-fill"></i>';
                    break;
                case 'loading':
                    icon = '<i class="bi bi-hourglass-split spinning"></i>';
                    break;
                default:
                    icon = '<i class="bi bi-bell-fill"></i>';
            }
            
            // Update notification content and style
            notification.innerHTML = `${icon} ${message}`;
            
            // Reset all classes first
            notification.className = 'notification';
            
            // Force a browser reflow to ensure animation works
            void notification.offsetWidth;
            
            // Add the appropriate class
            notification.classList.add(type);
            notification.classList.add('show');
            
            // Auto-hide notification after delay (except for loading)
            if (type !== 'loading') {
                const delay = type === 'error' ? 5000 : 3000;
                window.notificationTimeout = setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.classList.remove('success', 'error', 'info', 'loading');
                    }, 300);
                }, delay);
            }
        }

        function updateBooking(bookingId, action) {
            if (action === 'approve') {
                const confirmMessage = "Are you sure you want to approve this booking request? This will mark payment as paid.";
                if (confirm(confirmMessage)) {
                    // Show loading notification
                    showNotification("Processing approval...", 'loading');
                    
                    // Add loading state to the row
                    const row = document.getElementById('row-' + bookingId);
                    if (row) row.classList.add('loading-row');
                    
                    // Disable action buttons
                    const buttons = row.querySelectorAll('.action-buttons button');
                    buttons.forEach(btn => {
                        btn.disabled = true;
                        btn.classList.add('btn-loading');
                    });
                    
                    processBookingAction(bookingId, action);
                }
            } else if (action === 'reject') {
                // Show rejection modal
                const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
                document.getElementById('rejection_booking_id').value = bookingId;
                modal.show();
            }
        }
        
        // Function to process booking actions
        function processBookingAction(bookingId, action, rejectionReason = null) {
            // Build form data
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('action', action);
            if (rejectionReason) {
                formData.append('rejection_reason', rejectionReason);
            }

            // Send to current page instead of separate endpoint for faster processing
            fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(response => {
                // Check if the response is valid JSON
                const contentType = response.headers.get("content-type");
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    throw new Error("Received non-JSON response from server");
                }
            })
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showNotification(data.message, 'success');
                    
                    // Update UI to reflect the change
                    updateBookingUI(bookingId, action);
                    
                    // Update booking count
                    updateBookingCount();
                } else {
                    showNotification(data.message || "Error updating booking status", 'error');
                    
                    // Remove loading state
                    resetLoadingState(bookingId);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showNotification('Error: ' + error.message, 'error');
                
                // Remove loading state
                resetLoadingState(bookingId);
            });
        }

        // Helper function to reset loading state
        function resetLoadingState(bookingId) {
            const row = document.getElementById('row-' + bookingId);
            if (row) {
                row.classList.remove('loading-row');
                const buttons = row.querySelectorAll('.action-buttons button');
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.classList.remove('btn-loading');
                });
            }
        }

        // Function to update UI after action
        function updateBookingUI(bookingId, action) {
            const row = document.getElementById('row-' + bookingId);
            if (row) {
                // Add fade out animation
                row.style.transition = 'opacity 0.5s ease-out';
                row.style.opacity = '0.5';
                
                // Update status badges
                const statusElem = document.getElementById('status-' + bookingId);
                const paymentElem = document.getElementById('payment-' + bookingId);
                
                if (statusElem) {
                    statusElem.innerHTML = `<span class="status-badge status-${action === 'approve' ? 'confirmed' : 'canceled'}">${action === 'approve' ? 'Confirmed' : 'Canceled'}</span>`;
                }
                
                if (paymentElem) {
                    paymentElem.innerHTML = `<span class="payment-badge payment-${action === 'approve' ? 'paid' : 'pending'}">${action === 'approve' ? 'Paid' : 'Pending'}</span>`;
                }
                
                // Remove row after animation completes
                setTimeout(() => {
                    row.remove();
                }, 500);
            }
        }

        // Handle rejection form submission
        document.getElementById('submitRejection').addEventListener('click', function() {
            const bookingId = document.getElementById('rejection_booking_id').value;
            const selectedReason = document.querySelector('input[name="rejection_reason"]:checked');
            
            if (!selectedReason) {
                alert("Please select a rejection reason");
                return;
            }
            
            let rejectionReason = selectedReason.value;
            
            // If "Other" is selected, get the text from textarea
            if (rejectionReason === 'other') {
                const otherReason = document.getElementById('otherReason').value.trim();
                if (!otherReason) {
                    alert("Please provide a specific reason for rejection");
                    return;
                }
                rejectionReason = otherReason;
            }
            
            // Disable the submit button to prevent double submission
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass spinning"></i> Processing...';
            
            // Close the modal
            bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
            
            // Show loading notification
            showNotification("Processing rejection...", 'loading');
            
            // Add loading state to the row
            const row = document.getElementById('row-' + bookingId);
            if (row) row.classList.add('loading-row');
            
            // Disable action buttons
            const buttons = row.querySelectorAll('.action-buttons button');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('btn-loading');
            });
            
            // Process the rejection
            processBookingAction(bookingId, 'reject', rejectionReason);
        });

        // Handle other reason selection
        document.querySelectorAll('input[name="rejection_reason"]').forEach(input => {
            input.addEventListener('change', function() {
                const otherContainer = document.getElementById('otherReasonContainer');
                if (this.value === 'other') {
                    otherContainer.style.display = 'block';
                } else {
                    otherContainer.style.display = 'none';
                }
            });
        });

        // Update booking count and check if all are processed
        function updateBookingCount() {
            const bookingCount = document.querySelector('.booking-count');
            if (bookingCount) {
                const countText = bookingCount.textContent;
                const countMatch = countText.match(/(\d+)/);
                
                if (countMatch && countMatch[1]) {
                    const count = parseInt(countMatch[1]);
                    const newCount = Math.max(0, count - 1); // Ensure count doesn't go below 0
                    bookingCount.textContent = `${newCount} Pending Bookings`;
                    
                    // Show empty state if no more bookings
                    if (newCount === 0) {
                        handleEmptyState();
                    }
                }
            }
        }

        // Function to handle empty state display
        function handleEmptyState() {
            const container = document.querySelector('.container');
            const dashboardHeader = document.querySelector('.dashboard-header');
            const bookingsTable = document.querySelector('.table-responsive');
            
            // Check if empty state already exists
            if (!document.querySelector('.empty-state')) {
                // Remove bookings table if it exists
                if (bookingsTable) bookingsTable.remove();
                
                // Create empty state element
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <i class="bi bi-calendar-x"></i>
                    <h3>No Pending Bookings</h3>
                    <p>There are currently no bookings waiting for approval.</p>
                    <button class="btn btn-primary mt-3" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Page
                    </button>
                `;
                
                // Insert after dashboard header
                if (dashboardHeader) {
                    dashboardHeader.insertAdjacentElement('afterend', emptyState);
                } else if (container) {
                    container.appendChild(emptyState);
                }
                
                // Show notification
                showNotification("All bookings processed. You can now refresh the page.", 'success');
            }
        }
    </script>
</body>
</html>

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
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    margin-top: 30px;
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

.facility-info {
    color: black;
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
    top: 80px; /* Adjusted to be below the header */
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

/* Add these new styles for loading indicators */
.spinning {
    animation: spin 1s linear infinite;
    display: inline-block;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-row {
    position: relative;
    pointer-events: none;
}

.loading-row::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7);
    z-index: 5;
}

.btn-loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.notification.loading {
    background-color: #17a2b8;
    opacity: 1;
    transform: translateY(0);
}
</style>
