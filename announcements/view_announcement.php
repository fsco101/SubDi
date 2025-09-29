<?php

include '../includes/header.php';

// Fetch all announcements with like/dislike counts
$query = "SELECT a.*, u.f_name, u.l_name, 
        (SELECT COUNT(*) FROM likes WHERE content_id = a.announcement_id AND content_type = 'announcement' AND type = 'like') AS likes,
        (SELECT COUNT(*) FROM likes WHERE content_id = a.announcement_id AND content_type = 'announcement' AND type = 'dislike') AS dislikes
        FROM announcements a 
        JOIN users u ON a.posted_by = u.user_id 
        ORDER BY a.created_at DESC";
$result = $conn->query($query);
$announcements = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Announcements</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .announcement-card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            background-color: #ffffff;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .announcement-image {
            max-height: 300px;
            width: 100%;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        .announcement-info {
            padding: 1.5rem;
            color: #212529;
        }
        
        .announcement-title {
            text-align: center;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 0.75rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .replies-container {
            border-radius: 6px;
            margin-top: 1.5rem;
            padding: 1.25rem;
            background-color: #f1f1f1;
            border: 1px solid #e0e0e0;
        }
        
        .reply {
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid #007bff;
            background-color: #ffffff;
            border-radius: 0 4px 4px 0;
        }
        
        .reply-form {
            margin-top: 1rem;
        }
        
        .see-more-btn {
            color: #007bff;
            cursor: pointer;
            text-align: center;
            padding: 8px;
            margin-bottom: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .see-more-btn:hover {
            background-color: #dee2e6;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .btn-outline-success {
            color: #28a745;
            border-color: #28a745;
        }
        
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
        }
        
        .modal-content {
            background-color: #ffffff;
            color: #212529;
        }
        
        .modal-header {
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-control {
            background-color: #ffffff;
            border: 1px solid #ced4da;
            color: #212529;
        }
        
        .form-control:focus {
            background-color: #ffffff;
            color: #212529;
        }
        
        .alert-info {
            background-color: #e9ecef;
            color: #212529;
            border-color: #ced4da;
        }
        
        h2 {
            text-align: center;
            color: #343a40 !important;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2><i class="bi bi-megaphone"></i> Announcements</h2>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="alert alert-info text-center">No announcements available at this time.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-lg-8 mx-auto">
                        <div class="announcement-card">
                            <?php if (!empty($announcement['image_url'])): ?>
                                <img src="<?= htmlspecialchars($announcement['image_url']); ?>" 
                                    class="announcement-image" 
                                    onclick="openModal('<?= htmlspecialchars($announcement['image_url']); ?>')">
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
                                
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="likeContent(<?= $announcement['announcement_id']; ?>, 'announcement', 'like')">
                                        <i class="bi bi-hand-thumbs-up"></i> 
                                        <span id="like-count-<?= $announcement['announcement_id']; ?>"><?= $announcement['likes']; ?></span>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="likeContent(<?= $announcement['announcement_id']; ?>, 'announcement', 'dislike')">
                                        <i class="bi bi-hand-thumbs-down"></i> 
                                        <span id="dislike-count-<?= $announcement['announcement_id']; ?>"><?= $announcement['dislikes']; ?></span>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-outline-primary ms-auto" 
                                            onclick="toggleReplies(<?= $announcement['announcement_id']; ?>)">
                                        <i class="bi bi-chat"></i> Comments
                                    </button>
                                </div>
                                
                                <div id="replies-<?= $announcement['announcement_id']; ?>" class="replies-container d-none">
                                    <?php
                                    // Fetch replies for this announcement
                                    $replyQuery = "SELECT r.reply_id, r.announcement_id, r.user_id, r.reply_text, r.created_at, 
                                                u.f_name, u.l_name, 
                                                (SELECT COUNT(*) FROM likes WHERE content_id = r.reply_id AND content_type = 'announcement_replies' AND type = 'like') AS likes,
                                                (SELECT COUNT(*) FROM likes WHERE content_id = r.reply_id AND content_type = 'announcement_replies' AND type = 'dislike') AS dislikes
                                        FROM announcement_replies r
                                        JOIN users u ON r.user_id = u.user_id
                                        WHERE r.announcement_id = ?
                                        ORDER BY r.created_at DESC";
                                
                                    $stmt = $conn->prepare($replyQuery);
                                    $stmt->bind_param("i", $announcement['announcement_id']);
                                    $stmt->execute();
                                    $replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    ?>
                                    
                                    <!-- Comment Form Shown at the Top -->
                                    <form method="post" action="post_reply.php" class="reply-form mb-3">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['announcement_id']; ?>">
                                        <div class="mb-2">
                                            <textarea name="reply_text" class="form-control" rows="2" placeholder="Write a comment..." required></textarea>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-send"></i> Post
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Comments List -->
                                    <div class="replies-list">
                                        <h6 class="mb-3"><i class="bi bi-chat-text me-1"></i> Comments (<?= count($replies); ?>)</h6>
                                        <?php if (!empty($replies)): ?>
                                            <?php 
                                            $visibleComments = 3; // Number of comments to show initially
                                            $showSeeMore = count($replies) > $visibleComments;
                                            $initialReplies = array_slice($replies, 0, $visibleComments);
                                            $hiddenReplies = array_slice($replies, $visibleComments);
                                            ?>
                                            
                                            <!-- Initial visible comments -->
                                            <div id="initial-replies-<?= $announcement['announcement_id']; ?>">
                                                <?php foreach ($initialReplies as $reply): ?>
                                                    <div class="reply">
                                                        <div class="d-flex justify-content-between align-items-top">
                                                            <div>
                                                                <strong><?= htmlspecialchars($reply['f_name'] . ' ' . $reply['l_name']); ?></strong>
                                                                <small class="text-muted ms-2">
                                                                    <?= date('M d, Y h:i A', strtotime($reply['created_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <p class="my-2"><?= nl2br(htmlspecialchars($reply['reply_text'])); ?></p>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    onclick="likeContent(<?= $reply['reply_id']; ?>, 'announcement_replies', 'like')">
                                                                <i class="bi bi-hand-thumbs-up"></i> 
                                                                <span id="like-count-<?= $reply['reply_id']; ?>"><?= $reply['likes']; ?></span>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="likeContent(<?= $reply['reply_id']; ?>, 'announcement_replies', 'dislike')">
                                                                <i class="bi bi-hand-thumbs-down"></i> 
                                                                <span id="dislike-count-<?= $reply['reply_id']; ?>"><?= $reply['dislikes']; ?></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- "See more" button -->
                                            <?php if ($showSeeMore): ?>
                                                <div class="see-more-btn" 
                                                     id="see-more-<?= $announcement['announcement_id']; ?>"
                                                     onclick="showMoreReplies(<?= $announcement['announcement_id']; ?>)">
                                                    <i class="bi bi-chevron-down"></i> See <?= count($hiddenReplies); ?> more comments
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Hidden comments (initially not displayed) -->
                                            <div id="hidden-replies-<?= $announcement['announcement_id']; ?>" style="display: none;">
                                                <?php foreach ($hiddenReplies as $reply): ?>
                                                    <div class="reply">
                                                        <div class="d-flex justify-content-between align-items-top">
                                                            <div>
                                                                <strong><?= htmlspecialchars($reply['f_name'] . ' ' . $reply['l_name']); ?></strong>
                                                                <small class="text-muted ms-2">
                                                                    <?= date('M d, Y h:i A', strtotime($reply['created_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <p class="my-2"><?= nl2br(htmlspecialchars($reply['reply_text'])); ?></p>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    onclick="likeContent(<?= $reply['reply_id']; ?>, 'announcement_replies', 'like')">
                                                                <i class="bi bi-hand-thumbs-up"></i> 
                                                                <span id="like-count-<?= $reply['reply_id']; ?>"><?= $reply['likes']; ?></span>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="likeContent(<?= $reply['reply_id']; ?>, 'announcement_replies', 'dislike')">
                                                                <i class="bi bi-hand-thumbs-down"></i> 
                                                                <span id="dislike-count-<?= $reply['reply_id']; ?>"><?= $reply['dislikes']; ?></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-center text-muted">No comments yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Announcement Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(imageUrl) {
            document.getElementById("modalImage").src = imageUrl;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        function toggleReplies(id) {
            document.getElementById("replies-" + id).classList.toggle("d-none");
        }

        function showMoreReplies(id) {
            // Show the hidden replies
            document.getElementById("hidden-replies-" + id).style.display = "block";
            // Hide the "See more" button
            document.getElementById("see-more-" + id).style.display = "none";
        }

        function likeContent(contentId, contentType, action) {
            fetch('like_content.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `content_id=${contentId}&content_type=${contentType}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`like-count-${contentId}`).innerText = data.likes;
                    document.getElementById(`dislike-count-${contentId}`).innerText = data.dislikes;
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => console.error("Error:", error));
        }
        
        document.addEventListener("DOMContentLoaded", function () {
            console.log("Initializing Bootstrap Dropdowns...");
            var dropdownElements = document.querySelectorAll('.dropdown-toggle');
            dropdownElements.forEach(function (dropdown) {
                new bootstrap.Dropdown(dropdown);
            });

            console.log("Bootstrap Dropdowns Initialized!");
        });

        document.addEventListener("click", function (event) {
            if (event.target.matches(".dropdown-toggle")) {
                let dropdown = new bootstrap.Dropdown(event.target);
                dropdown.show();
            }
        });

        console.log("Bootstrap version:", bootstrap?.Dropdown ? "Loaded" : "Not Loaded");
    </script>
</body>
</html>
