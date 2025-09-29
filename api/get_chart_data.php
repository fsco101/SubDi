<?php
// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../includes/config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data_type = $_GET['type'] ?? '';
$result = [];

switch ($data_type) {
    case 'user_rentals':
        // Get rental data for the current user - Using proper column aliasing
        $query = "SELECT ri.name AS item_name, COUNT(r.rental_id) as count 
                 FROM rentals r 
                 JOIN rental_items ri ON r.item_id = ri.item_id 
                 WHERE r.user_id = ? 
                 GROUP BY r.item_id, ri.name
                 ORDER BY count DESC 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'bookings':
        // Get booking data grouped by facility for ALL users
        $query = "SELECT a.name as facility_name, COUNT(b.booking_id) as count 
                 FROM bookings b 
                 JOIN amenities a ON b.facility_id = a.facility_id 
                 GROUP BY b.facility_id 
                 ORDER BY count DESC 
                 LIMIT 10";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    
    case 'service_requests':
        // Get service request data grouped by category for ALL users
        $query = "SELECT category, COUNT(request_id) as count 
                 FROM service_requests 
                 GROUP BY category 
                 ORDER BY count DESC 
                 LIMIT 10";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;

        case 'rentals':
            // Get rental data grouped by item for ALL users - Using proper column aliasing
            $query = "SELECT ri.name AS item_name, COUNT(r.rental_id) as count, SUM(COALESCE(r.total_payment, 0)) as revenue 
                     FROM rentals r 
                     JOIN rental_items ri ON r.item_id = ri.item_id 
                     GROUP BY r.item_id, ri.name
                     ORDER BY count DESC 
                     LIMIT 10";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // If there's no data yet, provide some default items to avoid "undefined" issues
            if (empty($result)) {
                // Get available rental items even if they haven't been rented yet
                $query = "SELECT name AS item_name FROM rental_items LIMIT 5";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $items_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (!empty($items_result)) {
                    foreach ($items_result as $item) {
                        $result[] = [
                            'item_name' => $item['item_name'],
                            'count' => 0,
                            'revenue' => 0
                        ];
                    }
                }
            }
            break;
        
    case 'user_bookings':
        // Get booking data for the current user
        $query = "SELECT a.name as facility_name, COUNT(b.booking_id) as count 
                 FROM bookings b 
                 JOIN amenities a ON b.facility_id = a.facility_id 
                 WHERE b.user_id = ? 
                 GROUP BY b.facility_id 
                 ORDER BY count DESC 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'user_service_requests':
        // Get service request data for the current user
        $query = "SELECT category, COUNT(request_id) as count 
                 FROM service_requests 
                 WHERE user_id = ? 
                 GROUP BY category 
                 ORDER BY count DESC 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    default:
        $result = ['error' => 'Invalid data type requested'];
        break;
}

echo json_encode($result);
?>
