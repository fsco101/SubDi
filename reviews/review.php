<?php
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

// Fetch user's completed rentals with images
$rentals_query = "SELECT r.rental_id, r.item_id, ri.name, ri.image_path 
                 FROM rentals r
                 JOIN rental_items ri ON r.item_id = ri.item_id
                 WHERE r.user_id = ? AND r.status = 'returned' AND r.rental_end <= CURRENT_DATE
                 AND NOT EXISTS (
                    SELECT 1 FROM reviews 
                    WHERE user_id = ? AND content_id = r.item_id AND content_type = 'rental_item'
                 )";

$rentals_stmt = $conn->prepare($rentals_query);
$rentals_stmt->bind_param('ii', $user_id, $user_id);
$rentals_stmt->execute();
$rentals_result = $rentals_stmt->get_result();

// Fetch user's confirmed bookings with images
$bookings_query = "SELECT b.booking_id, b.facility_id, a.name, a.image_url
                  FROM bookings b
                  JOIN amenities a ON b.facility_id = a.facility_id
                  WHERE b.user_id = ? AND b.status = 'confirmed' AND b.booking_date <= CURRENT_DATE
                  AND NOT EXISTS (
                    SELECT 1 FROM reviews 
                    WHERE user_id = ? AND content_id = b.facility_id AND content_type = 'facility'
                  )";

$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->bind_param('ii', $user_id, $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

// Fetch user's previous reviews with images
$reviews_query = "SELECT r.review_id, r.content_id, r.content_type, r.rating, r.review_text, r.created_at,
                 CASE 
                    WHEN r.content_type = 'rental_item' THEN ri.name
                    WHEN r.content_type = 'facility' THEN a.name
                 END as item_name,
                 CASE 
                    WHEN r.content_type = 'rental_item' THEN ri.image_path
                    WHEN r.content_type = 'facility' THEN a.image_url
                 END as item_image
                 FROM reviews r
                 LEFT JOIN rental_items ri ON r.content_id = ri.item_id AND r.content_type = 'rental_item'
                 LEFT JOIN amenities a ON r.content_id = a.facility_id AND r.content_type = 'facility'
                 WHERE r.user_id = ?
                 ORDER BY r.created_at DESC";

$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param('i', $user_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - Subdivision Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .star-rating label { color: #ccc; font-size: 24px; cursor: pointer; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f8ce0b; }
        .review-item { padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #fff; }
        .review-container img { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; }
        .no-reviews { text-align: center; padding: 20px; background: #fff; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h1 class="text-center">My Reviews</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="review-container my-4">
        <h2>Write a Review</h2>

        <?php if ($rentals_result->num_rows == 0 && $bookings_result->num_rows == 0): ?>
            <div class="no-reviews">
                <p>You don't have any completed rentals or bookings to review at this time.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="content_select" class="form-label">Select what you want to review:</label>
                    <select id="content_select" name="content_select" class="form-control" required onchange="updateHiddenFields(this)">
                        <option value="">-- Select an item or facility --</option>
                        <?php while ($row = $rentals_result->fetch_assoc()): ?>
                            <option value="rental_item_<?php echo $row['item_id']; ?>" data-image="<?php echo $row['image_path']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                        <?php endwhile; ?>
                        <?php while ($row = $bookings_result->fetch_assoc()): ?>
                            <option value="facility_<?php echo $row['facility_id']; ?>" data-image="<?php echo $row['image_url']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div id="selected-image" class="mb-3 text-center" style="display: none;">
                    <img src="" id="preview-image" class="rounded img-thumbnail" alt="Selected Item">
                </div>

                <input type="hidden" name="content_id" id="content_id">
                <input type="hidden" name="content_type" id="content_type">

                <div class="mb-3">
                    <label>Rating:</label>
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required />
                            <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="review_text">Your Review:</label>
                    <textarea id="review_text" name="review_text" class="form-control" rows="4" required></textarea>
                </div>

                <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="review-container my-4">
        <h2>My Previous Reviews</h2>

        <?php while ($review = $reviews_result->fetch_assoc()): ?>
            <div class="review-item mb-3 d-flex align-items-center">
                <img src="<?php echo $review['item_image']; ?>" class="me-3" alt="Review Image">
                <div>
                    <h4><?php echo htmlspecialchars($review['item_name']); ?></h4>
                    <div class="text-warning">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                    <a href="edit_review.php?id=<?php echo $review['review_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm" data-id="<?php echo $review['review_id']; ?>" onclick="openDeleteModal(this)">Delete</button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Modal for Deleting Review -->
<div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-labelledby="deleteReviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteReviewModalLabel">Delete Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this review? This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function updateHiddenFields(select) {
        let option = select.options[select.selectedIndex];
        let value = option.value.split('_');
        document.getElementById('content_type').value = value[0];
        document.getElementById('content_id').value = value[1];
        document.getElementById('preview-image').src = option.getAttribute('data-image');
        document.getElementById('selected-image').style.display = 'block';
    }

    function openDeleteModal(button) {
        const reviewId = $(button).data('id'); // Get review ID from data attribute
        $('#confirmDelete').data('id', reviewId); // Store review ID in confirm button
        $('#deleteReviewModal').modal('show'); // Show the modal
    }

    $('#confirmDelete').on('click', function() {
        const reviewId = $(this).data('id'); // Get review ID from confirm button
        $.ajax({
            url: 'delete_review.php',
            type: 'POST',
            data: { id: reviewId },
            success: function(response) {
                // Handle success or failure here.
                if (response.success) {
                    window.location.reload(); // Reload the page to see updated reviews
                } else {
                    alert(response.error || "Error deleting the review. Please try again.");
                }
            },
            error: function() {
                alert("There was an error processing your request. Please try again.");
            }
        });
    });
</script>

</body>
</html>