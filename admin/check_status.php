<?php

include '../includes/config.php'; // Adjust path as needed


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied. Only administrators can edit the facilities.");
}
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /subdisystem/user/login.php");
    exit();
}


$user_id = $_SESSION['user_id'];

// Fetch user status from the database
$query = "SELECT status FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Allow only active users
if ($user || $user['status'] !== 'inactive') {
    // Redirect inactive users with an error message
    $_SESSION['error'] = "Your account has been deactivated. Please contact the administrator.";
    header("Location: /subdisystem/user/login.php");
    exit();
}

// If the user is active, allow them to proceed (no action needed)
?>
