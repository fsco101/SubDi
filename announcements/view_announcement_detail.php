<?php
require "../includes/config.php";
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid announcement ID.");
}

$announcement_id = $_GET['id'];

// Fetch the announcement details
$query = "SELECT a.*, u.f_name, u.l_name 
        FROM announcements a 
        JOIN users u ON a.posted_by = u.user_id 
        WHERE a.announcement_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();

if (!$announcement) {
    die("Announcement not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Announcement Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2><i class="bi bi-megaphone"></i> Announcement Details</h2>
        </div>

        <div class="announcement-card">
            <?php if (!empty($announcement['image_url'])): ?>
                <img src="<?= htmlspecialchars($announcement['image_url']); ?>" class="announcement-image">
            <?php endif; ?>
            
            <div class="announcement-info">
                <h4 class="announcement-title"><?= htmlspecialchars($announcement['title']); ?></h4>
                <div class="d-flex align-items-center mb-2 text-muted">
                    <i class="bi bi-person me-1"></i>
                    <span><?= htmlspecialchars($announcement['f_name'] . ' ' . $announcement['l_name']); ?></span>
                    <span class="mx-2">•</span>
                    <i class="bi bi-clock me-1"></i>
                    <small><?= date('M d, Y h:i A', strtotime($announcement['created_at'])); ?></small>
                </div>
                
                <div class="announcement-content">
                    <?= nl2br(htmlspecialchars($announcement['content'])); ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="/subdisystem/user/login.php" class="btn btn-primary">Login to Comment and Like</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
