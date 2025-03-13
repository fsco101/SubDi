<?php
session_start();
include '../includes/config.php';

header('Content-Type: application/json'); // Ensure JSON response

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$content_id = $_POST['content_id'] ?? null;
$content_type = $_POST['content_type'] ?? null;
$type = $_POST['action'] ?? null; // Ensure "action" is received correctly

if (!$content_id || !$content_type || !$type) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

// Check if the user already reacted
$query = "SELECT * FROM likes WHERE user_id = ? AND content_id = ? AND content_type = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $user_id, $content_id, $content_type);
$stmt->execute();
$result = $stmt->get_result();
$existing_like = $result->fetch_assoc();

if ($existing_like) {
    // If the same reaction exists, remove it
    if ($existing_like['type'] === $type) {
        $deleteQuery = "DELETE FROM likes WHERE user_id = ? AND content_id = ? AND content_type = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("iis", $user_id, $content_id, $content_type);
        $stmt->execute();
    } else {
        // If switching reactions (like -> dislike or vice versa)
        $updateQuery = "UPDATE likes SET type = ? WHERE user_id = ? AND content_id = ? AND content_type = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("siis", $type, $user_id, $content_id, $content_type);
        $stmt->execute();
    }
} else {
    // Insert new reaction
    $insertQuery = "INSERT INTO likes (user_id, content_id, content_type, type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iiss", $user_id, $content_id, $content_type, $type);
    $stmt->execute();
}

// Get updated counts
$likesQuery = "SELECT COUNT(*) AS total_likes FROM likes WHERE content_id = ? AND content_type = ? AND type = 'like'";
$stmt = $conn->prepare($likesQuery);
$stmt->bind_param("is", $content_id, $content_type);
$stmt->execute();
$likesResult = $stmt->get_result()->fetch_assoc();
$totalLikes = $likesResult['total_likes'];

$dislikesQuery = "SELECT COUNT(*) AS total_dislikes FROM likes WHERE content_id = ? AND content_type = ? AND type = 'dislike'";
$stmt = $conn->prepare($dislikesQuery);
$stmt->bind_param("is", $content_id, $content_type);
$stmt->execute();
$dislikesResult = $stmt->get_result()->fetch_assoc();
$totalDislikes = $dislikesResult['total_dislikes'];

// Return updated counts as JSON
echo json_encode([
    "success" => true,
    "likes" => $totalLikes,
    "dislikes" => $totalDislikes
]);
?>
