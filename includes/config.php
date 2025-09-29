<?php
$servername = "localhost";  // Your server name or IP
$username = "root";         // Your database username
$password = "";        // Your database password
$dbname = "subdisystem";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
