<?php
// Include database connection
ob_start();
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$review = null;

// Check if review ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location:/subdisystem/reviews/review.php');
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
    // Review not found or doesn't belong to the user
    header('Location: reviews.php');
    exit();
}

$review = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    // Validate and sanitize input
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $review_text = filter_input(INPUT_POST, 'review_text', FILTER_SANITIZE_STRING);
    
    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5.";
    } else {
        // Update the review
        $update_query = "UPDATE reviews 
                         SET rating = ?, review_text = ? 
                         WHERE review_id = ? AND user_id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('isii', $rating, $review_text, $review_id, $user_id);
        
        if ($update_stmt->execute()) {
            // Redirect after successful update
            header("Location: /subdisystem/reviews/review.php?msg=Review Updated Successfully");
           
        } else {
            $error = "Error updating your review. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Review - Subdivision Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Include fontawesome or other icon library for star ratings -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>


        .star-rating {
            display: inline-block;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            float: right;
            cursor: pointer;
            color: #ccc;
            font-size: 24px;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f8ce0b;
        }
        .review-container {
            margin-bottom: 30px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

    </style>
</head>
<body>
    
    <div class="container">
        <h1>Edit Review</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="review-container">
            <h2>Editing Review for: <?php echo htmlspecialchars($review['item_name']); ?></h2>
            <p class="text-muted">
                Type: <?php echo $review['content_type'] === 'rental_item' ? 'Rental Item' : 'Facility'; ?>
            </p>
            <p class="text-muted">
                Originally submitted on: <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
            </p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Rating:</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" <?php echo ($review['rating'] == 5) ? 'checked' : ''; ?> required />
                        <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star4" name="rating" value="4" <?php echo ($review['rating'] == 4) ? 'checked' : ''; ?> />
                        <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star3" name="rating" value="3" <?php echo ($review['rating'] == 3) ? 'checked' : ''; ?> />
                        <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star2" name="rating" value="2" <?php echo ($review['rating'] == 2) ? 'checked' : ''; ?> />
                        <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star1" name="rating" value="1" <?php echo ($review['rating'] == 1) ? 'checked' : ''; ?> />
                        <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review_text">Your Review:</label>
                    <textarea id="review_text" name="review_text" class="form-control" rows="4" required><?php echo htmlspecialchars($review['review_text']); ?></textarea>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" name="update_review" class="btn btn-primary">Update Review</button>
                    <a href="/subdisystem/reviews/review.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>