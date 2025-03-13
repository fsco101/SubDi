
<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ./user/login.php");
    exit();
}

// If the user confirmed logout, destroy the session and redirect to login page
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_destroy(); // Destroy the session
    header("Location: ./user/login.php"); // Redirect to login page after logging out
    exit();
}
?>


