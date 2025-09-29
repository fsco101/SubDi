<?php
include '../includes/header.php';
include '../send_email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $postedBy = $_SESSION['user_id']; // Assuming admin ID is stored in session

    // Insert announcement into the database
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $content, $postedBy);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Fetch all users
        $usersResult = $conn->query("SELECT email FROM users WHERE status = 'active'");
        while ($user = $usersResult->fetch_assoc()) {
            $to = $user['email'];
            $subject = "New Announcement: $title";
            $message = "<h1>$title</h1><p>$content</p>";
            sendEmail($to, $subject, $message);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post announcement']);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>
