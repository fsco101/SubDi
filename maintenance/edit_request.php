<?php
ob_start();

include '../includes/header.php';


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
    <link rel="stylesheet" href="../style/style.css">
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