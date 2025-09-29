<?php
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$error = '';
$success = '';

// Handle form submission for adding a review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    if (!isset($_POST['content_id']) || !isset($_POST['content_type']) || !isset($_POST['rating']) || empty($_POST['review_text'])) {
        $error = "Please fill in all required fields.";
    } else {
        $content_id = $_POST['content_id'];
        $content_type = $_POST['content_type'];
        $rating = $_POST['rating'];
        $review_text = $_POST['review_text'];
        
        // Check if user has already reviewed this item/facility
        $check_query = "SELECT * FROM reviews WHERE user_id = ? AND content_id = ? AND content_type = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("iis", $user_id, $content_id, $content_type);
        $check_stmt->execute();
        $existing_review = $check_stmt->get_result();
        
        if ($existing_review->num_rows > 0) {
            // Update existing review
            $update_query = "UPDATE reviews SET rating = ?, review_text = ?, created_at = NOW() WHERE user_id = ? AND content_id = ? AND content_type = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("issis", $rating, $review_text, $user_id, $content_id, $content_type);
            
            if ($update_stmt->execute()) {
                $success = "Your review has been updated successfully!";
            } else {
                $error = "Error updating your review. Please try again.";
            }
        } else {
            // Insert new review
            $insert_query = "INSERT INTO reviews (user_id, content_id, content_type, rating, review_text) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iisis", $user_id, $content_id, $content_type, $rating, $review_text);
            
            if ($insert_stmt->execute()) {
                $success = "Your review has been submitted successfully!";
            } else {
                $error = "Error submitting your review. Please try again.";
            }
        }
    }
}

// FIXED QUERY: Get completed rentals for this user (where end date has passed)
// Changed the query to include rentals with 'returned' status OR rentals with end date in the past
$rentals_query = "SELECT ri.*, r.rental_start, r.rental_end 
                  FROM rental_items ri
                  JOIN rentals r ON ri.item_id = r.item_id 
                  WHERE r.user_id = ? AND (r.rental_end < ? OR r.status = 'returned')";

$rentals_stmt = $conn->prepare($rentals_query);
$rentals_stmt->bind_param("is", $user_id, $today);
$rentals_stmt->execute();
$rentals_result = $rentals_stmt->get_result();

// Get completed facility bookings for this user (where booking date has passed)
$bookings_query = "SELECT a.*, b.booking_date, b.start_time, b.end_time 
                   FROM amenities a
                   JOIN bookings b ON a.facility_id = b.facility_id 
                   WHERE b.user_id = ? AND CONCAT(b.booking_date, ' ', b.end_time) < NOW() AND b.status = 'confirmed'";
$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Get user's existing reviews
$reviews_query = "SELECT * FROM reviews WHERE user_id = ?";
$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param("i", $user_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Create an array of existing reviews for easy lookup
$user_reviews = [];
while ($review = $reviews_result->fetch_assoc()) {
    $key = $review['content_type'] . '-' . $review['content_id'];
    $user_reviews[$key] = [
        'rating' => $review['rating'],
        'text' => $review['review_text']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">
                    <i class="bi bi-star-fill"></i> Write a Review
                </h1>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <div class="tabs">
                    <div class="tab active" data-target="rental-items">Rental Items</div>
                    <div class="tab" data-target="facilities">Facilities/Amenities</div>
                </div>
                
                <!-- Rental Items Tab -->
                <div class="tab-content active" id="rental-items">
                    <h2 class="section-title"><i class="bi bi-box-seam"></i> Items You've Rented</h2>
                    
                    <?php if ($rentals_result->num_rows == 0): ?>
                        <div class="no-items-message">
                            <i class="bi bi-emoji-neutral me-2"></i> You haven't rented any items yet or all your rentals are still ongoing.
                        </div>
                    <?php else: ?>
                        <div class="review-items-container">
                            <?php while ($rental = $rentals_result->fetch_assoc()): 
                                $reviewKey = 'rental_item-' . $rental['item_id'];
                                $hasReview = isset($user_reviews[$reviewKey]);
                            ?>
                                <div class="review-item-card <?= $hasReview ? 'already-reviewed' : '' ?>" 
                                     data-id="<?= $rental['item_id'] ?>" 
                                     data-type="rental_item"
                                     data-name="<?= htmlspecialchars($rental['name']) ?>"
                                     data-has-review="<?= $hasReview ? '1' : '0' ?>">
                                    
                                    <img src="<?= !empty($rental['image_path']) ? htmlspecialchars($rental['image_path']) : '/subdisystem/rentals/item_upload/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($rental['name']) ?>" 
                                         class="review-item-img">
                                    
                                    <div class="review-item-details">
                                        <div class="review-item-name"><?= htmlspecialchars($rental['name']) ?></div>
                                        <div class="review-item-date">
                                            <small>Rented: <?= date('M d, Y', strtotime($rental['rental_start'])) ?> - <?= date('M d, Y', strtotime($rental['rental_end'])) ?></small>
                                        </div>
                                        <?php if ($hasReview): ?>
                                            <span class="already-reviewed">
                                                <i class="bi bi-check-circle-fill"></i> Already reviewed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Facilities Tab -->
                <div class="tab-content" id="facilities">
                    <h2 class="section-title"><i class="bi bi-building"></i> Facilities You've Booked</h2>
                    
                    <?php if ($bookings_result->num_rows == 0): ?>
                        <div class="no-items-message">
                            <i class="bi bi-emoji-neutral me-2"></i> You haven't booked any facilities yet or all your bookings are still upcoming.
                        </div>
                    <?php else: ?>
                        <div class="review-items-container">
                            <?php while ($booking = $bookings_result->fetch_assoc()): 
                                $reviewKey = 'facility-' . $booking['facility_id'];
                                $hasReview = isset($user_reviews[$reviewKey]);
                            ?>
                                <div class="review-item-card <?= $hasReview ? 'already-reviewed' : '' ?>" 
                                     data-id="<?= $booking['facility_id'] ?>" 
                                     data-type="facility"
                                     data-name="<?= htmlspecialchars($booking['name']) ?>"
                                     data-has-review="<?= $hasReview ? '1' : '0' ?>">
                                    
                                    <img src="<?= !empty($booking['image_url']) ? htmlspecialchars($booking['image_url']) : '../admin/image_faci/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($booking['name']) ?>" 
                                         class="review-item-img">
                                    
                                    <div class="review-item-details">
                                        <div class="review-item-name"><?= htmlspecialchars($booking['name']) ?></div>
                                        <div class="review-item-date">
                                            <small>Booked: <?= date('M d, Y', strtotime($booking['booking_date'])) ?> (<?= date('h:i A', strtotime($booking['start_time'])) ?> - <?= date('h:i A', strtotime($booking['end_time'])) ?>)</small>
                                        </div>
                                        <?php if ($hasReview): ?>
                                            <span class="already-reviewed">
                                                <i class="bi bi-check-circle-fill"></i> Already reviewed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Review Form -->
                <div class="review-form-container" id="review-form" style="display: none;">
                    <h3 class="section-title"><i class="bi bi-pencil-square"></i> Write Your Review for <span id="review-item-name"></span></h3>
                    
                    <form method="post" id="reviewForm">
                        <input type="hidden" name="content_id" id="content_id">
                        <input type="hidden" name="content_type" id="content_type">
                        
                        <div class="form-group">
                            <label for="rating" class="form-label">Rating</label>
                            <div class="rating-container">
                                <div class="rating-stars">
                                    <button type="button" class="star-btn" data-rating="1"><i class="bi bi-star"></i></button>
                                    <button type="button" class="star-btn" data-rating="2"><i class="bi bi-star"></i></button>
                                    <button type="button" class="star-btn" data-rating="3"><i class="bi bi-star"></i></button>
                                    <button type="button" class="star-btn" data-rating="4"><i class="bi bi-star"></i></button>
                                    <button type="button" class="star-btn" data-rating="5"><i class="bi bi-star"></i></button>
                                </div>
                                <input type="hidden" name="rating" id="rating" value="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="review_text" class="form-label">Your Review</label>
                            <textarea class="form-control" id="review_text" name="review_text" placeholder="Share your experience with this item/facility..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitReview">Submit Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and its content
                    this.classList.add('active');
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).classList.add('active');
                    
                    // Hide review form when switching tabs
                    document.getElementById('review-form').style.display = 'none';
                });
            });
            
            // Item selection logic
            const reviewItems = document.querySelectorAll('.review-item-card');
            reviewItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Reset all item selections
                    reviewItems.forEach(i => i.classList.remove('selected'));
                    
                    // Select clicked item
                    this.classList.add('selected');
                    
                    // Get item details
                    const itemId = this.getAttribute('data-id');
                    const itemType = this.getAttribute('data-type');
                    const itemName = this.getAttribute('data-name');
                    const hasReview = this.getAttribute('data-has-review') === '1';
                    
                    // Update form with selected item details
                    document.getElementById('content_id').value = itemId;
                    document.getElementById('content_type').value = itemType;
                    document.getElementById('review-item-name').textContent = itemName;
                    
                    // Show review form
                    document.getElementById('review-form').style.display = 'block';
                    
                    // Scroll to review form
                    document.getElementById('review-form').scrollIntoView({behavior: 'smooth'});
                    
                    // If item already has a review, load the existing review data
                    if (hasReview) {
                        // This would need a separate AJAX call to get existing review data
                        // For demo purposes, we'll pre-fill with dummy data
                        const reviewKey = itemType + '-' + itemId;
                        <?php
                        // Output user reviews as JavaScript object
                        echo "const userReviews = " . json_encode($user_reviews) . ";\n";
                        ?>
                        
                        if (userReviews[reviewKey]) {
                            // Set rating
                            const rating = userReviews[reviewKey].rating;
                            setRating(rating);
                            
                            // Set review text
                            document.getElementById('review_text').value = userReviews[reviewKey].text;
                        }
                    } else {
                        // Reset form for new review
                        resetReviewForm();
                    }
                });
            });
            
            // Star rating logic
            const starButtons = document.querySelectorAll('.star-btn');
            starButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    setRating(rating);
                });
            });
            
            // Form validation
            document.getElementById('reviewForm').addEventListener('submit', function(e) {
                const rating = document.getElementById('rating').value;
                const reviewText = document.getElementById('review_text').value.trim();
                
                if (rating === '0') {
                    e.preventDefault();
                    alert('Please select a rating');
                }
                
                if (reviewText === '') {
                    e.preventDefault();
                    alert('Please write your review');
                }
            });
            
            // Helper function to set rating
            function setRating(rating) {
                document.getElementById('rating').value = rating;
                
                starButtons.forEach(btn => {
                    const btnRating = parseInt(btn.getAttribute('data-rating'));
                    const icon = btn.querySelector('i');
                    
                    if (btnRating <= rating) {
                        icon.classList.remove('bi-star');
                        icon.classList.add('bi-star-fill');
                        btn.classList.add('selected');
                    } else {
                        icon.classList.remove('bi-star-fill');
                        icon.classList.add('bi-star');
                        btn.classList.remove('selected');
                    }
                });
            }
            
            // Helper function to reset review form
            function resetReviewForm() {
                document.getElementById('rating').value = '0';
                document.getElementById('review_text').value = '';
                
                starButtons.forEach(btn => {
                    const icon = btn.querySelector('i');
                    icon.classList.remove('bi-star-fill');
                    icon.classList.add('bi-star');
                    btn.classList.remove('selected');
                });
            }
        });
    </script>
</body>
</html>

<style>
        :root {
            --primary-color: #3b82f6;
            --primary-light: #dbeafe;
            --secondary-color: #8b5cf6;
            --text-color: #334155;
            --light-text: #94a3b8;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --success-color: #10b981;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            background-color: #f5f7fa;
            padding-bottom: 2rem;
        }
        
        .container {
            max-width: 960px;
            margin: 2rem auto;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-title i {
            color: var(--primary-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .no-items-message {
            color: var(--light-text);
            text-align: center;
            padding: 2rem;
            border: 1px dashed var(--border-color);
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .review-items-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .review-item-card {
            flex: 1;
            min-width: 250px;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .review-item-card:hover {
            border-color: var(--primary-color);
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .review-item-card.selected {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: var(--primary-color);
            border-width: 2px;
        }
        
        .review-item-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
            border: 1px solid var(--border-color);
            background-color: #f8f8f8;
        }
        
        .review-item-details {
            flex: 1;
        }
        
        .review-item-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .review-item-date {
            font-size: 0.85rem;
            color: var (--light-text);
            margin-top: 5px;
        }
        
        .review-form-container {
            padding: 1.5rem;
            border-radius: 8px;
            background-color: var(--light-bg);
            margin-top: 2rem;
        }
        
        .rating-container {
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .star-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 0;
        }
        
        .star-btn:hover, .star-btn.selected {
            color: #f59e0b;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-primary:disabled {
            background-color: var (--light-text);
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .already-reviewed {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .already-reviewed i {
            font-size: 0.7rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: var(--light-text);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .tab:hover {
            color: var(--primary-color);
        }
        
        .tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .review-items-container {
                display: block;
            }
            
            .review-item-card {
                margin-bottom: 1rem;
            }
            
            .rating-stars {
                justify-content: center;
            }
        }
    </style>
