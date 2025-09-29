<?php
ob_start();

include '../includes/header.php';

// Check if email is set in the session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=Please log in to continue.");
    exit();
}

$request_id = $_GET['id'];
$query = "SELECT * FROM service_requests WHERE request_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $updateQuery = "UPDATE service_requests SET status = ? WHERE request_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $status, $request_id);
    if ($stmt->execute()) {
        if ($status == 'completed') {
            // Notify admin via Gmail
            $to = 'subdisystem@gmail.com';
            $subject = 'Maintenance Request Completed';
            $message = 'The maintenance request with ID ' . $request_id . ' has been completed.';
            $headers = 'From: ' . $_SESSION['email'] . "\r\n" .
                       'Reply-To: ' . $_SESSION['email'] . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);

            // Notify admin via system notification
            $admin_id = 1; // Assuming admin has user_id = 1
            $checkAdminQuery = "SELECT user_id FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($checkAdminQuery);
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $notificationQuery = "INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())";
                $notificationMessage = 'The maintenance request with ID ' . $request_id . ' has been completed.';
                $stmt = $conn->prepare($notificationQuery);
                $stmt->bind_param("is", $admin_id, $notificationMessage);
                $stmt->execute();
            } else {
                echo "<p>Error: Admin user does not exist.</p>";
            }
        }
        header("Location: view_requests.php?msg=Request updated successfully!");
        exit();
    } else {
        echo "<p>Error updating request.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Request</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <h2>Update Request</h2>
    <form method="post">
        <label>Status:</label>
        <select name="status" required>
            <option value="pending" <?= ($request['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="in-progress" <?= ($request['status'] == 'in-progress') ? 'selected' : ''; ?>>In Progress</option>
            <option value="completed" <?= ($request['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
        </select>
        <button type="submit">Update</button>
    </form>
</body>
</html>
<?php include '../includes/footer.php'; ?>

