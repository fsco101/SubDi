<?php
// Start output buffering at the very beginning
ob_start();

// Before including any files or doing anything that might output content
// Only include essential files for the processing
require_once '../includes/config.php'; // Include only database connection
require_once '../send_email.php'; // Include email functionality

// Session handling - don't output anything while starting session
@session_start();

// Enable error logging
ini_set('display_errors', 0); // Don't display errors to users
error_log("Booking Process script started");

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Ensure only admin can access
if ($_SESSION['role'] !== 'admin') {
    error_log("Access Denied. Only administrators can approve bookings.");
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access Denied. Only administrators can approve bookings.']);
    exit();
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
        
        // Log admin action
        $actionText = $action === 'approve' ? "Approved booking #$bookingId" : "Canceled booking #$bookingId";
        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        
        if (!$logStmt || !$logStmt->bind_param("is", $adminId, $actionText) || !$logStmt->execute()) {
            error_log("Warning: Failed to log admin action: " . $conn->error);
            // Continue without throwing exception for log failures
        }
        
        // Create notification message - Store HTML in database, but DO NOT return in JSON response
        $htmlMessage = $action === 'approve' 
            ? "Your booking request for <strong>$facilityName</strong> has been confirmed and marked as paid."
            : "Your booking request for <strong>$facilityName</strong> has been canceled.";
            
        // For email - safe to use HTML
        $emailMessage = $htmlMessage;
        
        // For JSON response - use plain text version
        $plainMessage = $action === 'approve' 
            ? "Your booking request for $facilityName has been confirmed and marked as paid."
            : "Your booking request for $facilityName has been canceled.";
            
        // Add notification to database
        $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) 
                      VALUES (?, ?, 'booking', ?, NOW())";
        $notifStmt = $conn->prepare($notifQuery);
        
        if ($notifStmt && $notifStmt->bind_param("iis", $userId, $bookingId, $htmlMessage) && $notifStmt->execute()) {
            error_log("Notification created for user $userId");
        } else {
            error_log("Warning: Failed to create notification: " . $conn->error);
            // Continue without throwing exception for notification failures
        }
        
        // Send email notification
        $subject = $action === 'approve' ? "Booking Confirmed: $facilityName" : "Booking Canceled: $facilityName";
        
        if (!sendEmail($email, $subject, $emailMessage)) {
            error_log("Warning: Failed to send email to $email");
            // Continue without throwing exception for email failures
        }
        
        $conn->commit();
        return ['success' => true, 'message' => $plainMessage];
        
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
    // Clear any previous output before sending JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set content type to JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Validate that we have the required parameters
    if (!isset($_POST['booking_id']) || !isset($_POST['action'])) {
        error_log("Missing required parameters");
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $bookingId = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $adminId = $_SESSION['user_id'];

    // Validate action
    if ($action !== 'approve' && $action !== 'reject') {
        error_log("Invalid action parameter");
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit();
    }

    // Process the booking action
    $result = processBookingAction($bookingId, $action, $adminId);
    echo json_encode($result);
    exit();
} else {
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    error_log("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
?>
