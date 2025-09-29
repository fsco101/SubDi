<?php
session_start();
include '../includes/config.php';

// Set response header to return JSON
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "You must be logged in to rent an item."]);
    exit;
}

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : null;
    $rental_start = isset($_POST['rental_start']) ? $_POST['rental_start'] : null; // Ensure rental_start is captured
    $rental_end = isset($_POST['rental_end']) ? $_POST['rental_end'] : null;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    // Validate input
    if (!$item_id || !$rental_start || !$rental_end || $quantity < 1) {
        throw new Exception("Invalid input data.");
    }

    // Get current date (Philippine Time UTC+8)
    date_default_timezone_set('Asia/Manila');

    // Validate rental end date
    if (strtotime($rental_end) <= strtotime($rental_start)) {
        throw new Exception("Invalid rental duration. The end time must be later than the start time.");
    }

    // Check if the item is available and has sufficient quantity
    $checkItem = $conn->prepare("SELECT rental_price, quantity FROM rental_items WHERE item_id = ? AND availability_status = 'available'");
    $checkItem->bind_param("i", $item_id);
    $checkItem->execute();
    $itemResult = $checkItem->get_result();
    $itemData = $itemResult->fetch_assoc();

    if (!$itemData || $itemData['quantity'] < $quantity) {
        throw new Exception("Not enough stock available for this item.");
    }

    // Calculate rental duration in hours
    $startTime = new DateTime($rental_start);
    $endTime = new DateTime($rental_end);
    $interval = $startTime->diff($endTime);
    $hours = ($interval->days * 24) + $interval->h;

    // Calculate total payment
    $rental_price = $itemData['rental_price'];
    $total_payment = $rental_price * $quantity * $hours;

    // Insert rental request with quantity and total payment
    $insertQuery = "INSERT INTO rentals (user_id, item_id, rental_start, rental_end, quantity, total_payment, status, payment_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iissid", $user_id, $item_id, $rental_start, $rental_end, $quantity, $total_payment);

    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }

    // Get last inserted rental ID
    $rental_id = $stmt->insert_id;

    // Reduce quantity based on user request
    $updateQuantity = $conn->prepare("UPDATE rental_items SET quantity = quantity - ? WHERE item_id = ? AND quantity >= ?");
    $updateQuantity->bind_param("iii", $quantity, $item_id, $quantity);
    $updateQuantity->execute();

    // If quantity reaches 0, mark item as unavailable
    $markUnavailable = $conn->prepare("UPDATE rental_items SET availability_status = 'unavailable' WHERE item_id = ? AND quantity = 0");
    $markUnavailable->bind_param("i", $item_id);
    $markUnavailable->execute();

    // Notify all admins about the new rental request
    $adminQuery = "SELECT user_id FROM users WHERE role = 'admin'";
    $admins = $conn->query($adminQuery);
    
    while ($admin = $admins->fetch_assoc()) {
        $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message) 
                       VALUES (?, ?, 'rental', 'New rental request from User #{$user_id}')";
        $notifStmt = $conn->prepare($notifQuery);
        $notifStmt->bind_param("ii", $admin['user_id'], $rental_id);
        $notifStmt->execute();
    }

    // Return success JSON response with total payment
    echo json_encode(["success" => true, "message" => "Rental confirmed successfully!", "total_payment" => "₱" . number_format($total_payment, 2)]);
    exit;
} catch (Exception $e) {
    // Return error JSON response
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
?>
