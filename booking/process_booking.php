<?php
include '../includes/header.php';
include '../send_email.php';

// Enable error logging
ini_set('display_errors', 0); // Don't display errors to users
error_log("Process Booking script started");

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

// Helper function to process booking actions
function processBookingAction($bookingId, $action, $adminId) {
    global $conn;
    
    $status = ($action === 'approve') ? 'confirmed' : 'canceled';
    $paymentStatus = ($action === 'approve') ? 'paid' : 'pending';
    
    error_log("Processing booking ID: $bookingId with action: $action");
    
    try {
        $conn->begin_transaction();
        
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE booking_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssi", $status, $paymentStatus, $bookingId);
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
        
        // Retrieve booking date, start time, and end time
        $dataStmt = $conn->prepare("
            SELECT booking_date, start_time, end_time 
            FROM bookings 
            WHERE booking_id = ?
        ");
        $dataStmt->bind_param("i", $bookingId);
        $dataStmt->execute();
        $dataResult = $dataStmt->get_result();

        if ($dataResult->num_rows > 0) {
            $data = $dataResult->fetch_assoc();
            error_log("Retrieved Booking Date: " . $data['booking_date']);
            error_log("Retrieved Start Time: " . $data['start_time']);
            error_log("Retrieved End Time: " . $data['end_time']);
        } else {
            error_log("No booking found for ID: $bookingId");
        }
        
        // Log admin action
        $actionText = $action === 'approve' ? "Approved booking #$bookingId" : "Canceled booking #$bookingId";
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        
        if (!$logStmt || !$logStmt->bind_param("is", $adminId, $actionText) || !$logStmt->execute()) {
            error_log("Warning: Failed to log admin action: " . $conn->error);
            // Continue without throwing exception for log failures
        }
        
        // Create notification message
        $message = $action === 'approve' 
            ? "Your booking request for <strong>$facilityName</strong> has been confirmed and marked as paid. <a href='/subdisystem/booking/receipt.php?booking_id=$bookingId'>View Receipt</a>"
            : "Your booking request for <strong>$facilityName</strong> has been canceled.";
            
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

        // Send receipt to user's Gmail account if booking is approved
        if ($action === 'approve') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $receiptUrl = $protocol . $_SERVER['HTTP_HOST'] . "/subdisystem/booking/receipt.php?booking_id=$bookingId";
            $receiptMessage = "Your booking receipt for <strong>$facilityName</strong> is available. <a href='$receiptUrl'>View Receipt</a>";
            if (!sendEmail($email, "Your Booking Receipt", $receiptMessage)) {
                error_log("Warning: Failed to send receipt email to $email");
                // Continue without throwing exception for email failures
            }
        }
        
        $conn->commit();
        return ['success' => true, 'message' => $action === 'approve' 
            ? "Booking #$bookingId has been approved and marked as paid."
            : "Booking #$bookingId has been canceled, allowing the user to create a new booking."];
        
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

// Handle approval/rejection with AJAX support
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Validate that we have the required parameters
    if (!isset($_POST['booking_id']) || !isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $bookingId = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $adminId = $_SESSION['user_id'];

    // Validate action
    if ($action !== 'approve' && $action !== 'reject') {
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit();
    }

    // Process the booking action
    $result = processBookingAction($bookingId, $action, $adminId);
    echo json_encode($result);
    exit();
}
?>
