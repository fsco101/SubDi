<?php
include '../includes/header.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$review = null;

// Check if review ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: /subdisystem/reviews/review.php');
    exit();
}

$review_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Fetch the review details and verify ownership
$review_query = "SELECT r.*, 
                CASE 
                    WHEN r.content_type = 'rental_item' THEN ri.name
                    WHEN r.content_type = 'facility' THEN a.name
                END as item_name
                FROM reviews r
                LEFT JOIN rental_items ri ON r.content_id = ri.item_id AND r.content_type = 'rental_item'
                LEFT JOIN amenities a ON r.content_id = a.facility_id AND r.content_type = 'facility'
                WHERE r.review_id = ? AND r.user_id = ?";

$stmt = $conn->prepare($review_query);
$stmt->bind_param('ii', $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: /subdisystem/reviews/review.php');
    exit();
}

$review = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Review - Subdivision Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .review-display .stars {
            color: #f8ce0b;
            font-size: 18px;
        }
        .review-container {
            margin-bottom: 30px;
        }
        .review-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Delete Review</h1>
    
    <div class="review-container">
        <h2>Are you sure you want to delete this review?</h2>
        
        <div class="review-item">
            <h4><?php echo htmlspecialchars($review['item_name']); ?> 
                <small>(<?php echo $review['content_type'] === 'rental_item' ? 'Rental Item' : 'Facility'; ?>)</small>
            </h4>
            <div class="review-display">
                <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="date">
                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                </span>
            </div>
            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
        </div>
        
        <button id="openModal" class="btn btn-danger">Delete Review</button>
    </div>
</div>

<!-- Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h2>Confirmation</h2>
        <p><strong>Warning:</strong> This action cannot be undone. Are you sure you want to delete this review?</p>
        <div class="action-buttons">
            <button id="confirmDelete" class="btn btn-danger">Yes, Delete Review</button>
            <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var reviewId = <?php echo $review_id; ?>; // Pass review ID to JavaScript

    // Open the modal
    $('#openModal').on('click', function() {
        $('#deleteModal').css('display', 'block');
    });

    // Confirm delete
    $('#confirmDelete').on('click', function() {
        $.post('delete_review.php', { id: reviewId }, function(response) {
            // Handle success or error response
            if (response.success) {
                window.location.href = '/subdisystem/reviews/review.php';
            } else {
                alert(response.error || "Error deleting your review. Please try again.");
            }
        }, 'json');
    });
    
    // Cancel delete
    $('#cancelDelete').on('click', function() {
        $('#deleteModal').css('display', 'none');
    });

    // Close modal if user clicks anywhere outside of it
    $(window).on('click', function(event) {
        if ($(event.target).is('#deleteModal')) {
            $('#deleteModal').css('display', 'none');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>