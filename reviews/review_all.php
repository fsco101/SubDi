<?php
ob_start();
include '../includes/header.php';

// Check if user is admin, redirect if not
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

// Default filters
$contentType = isset($_GET['type']) ? $_GET['type'] : 'all';
$ratingFilter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query based on filters
$reviewsQuery = "
    SELECT r.*, 
           u.f_name, u.l_name, u.image_url as user_image,
           CASE 
               WHEN r.content_type = 'rental_item' THEN ri.name
               WHEN r.content_type = 'facility' THEN a.name
           END as item_name,
           CASE 
               WHEN r.content_type = 'rental_item' THEN ri.image_path
               WHEN r.content_type = 'facility' THEN a.image_url
           END as item_image,
           CASE 
               WHEN r.content_type = 'rental_item' THEN 'Rental Item'
               WHEN r.content_type = 'facility' THEN 'Facility'
           END as type_label
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN rental_items ri ON r.content_id = ri.item_id AND r.content_type = 'rental_item'
    LEFT JOIN amenities a ON r.content_id = a.facility_id AND r.content_type = 'facility'
    WHERE 1=1
";

// Apply filters
if ($contentType != 'all') {
    $reviewsQuery .= " AND r.content_type = '$contentType'";
}

if ($ratingFilter > 0) {
    $reviewsQuery .= " AND r.rating = $ratingFilter";
}

if (!empty($searchQuery)) {
    $reviewsQuery .= " AND (
        ri.name LIKE '%$searchQuery%' OR 
        a.name LIKE '%$searchQuery%' OR
        u.f_name LIKE '%$searchQuery%' OR
        u.l_name LIKE '%$searchQuery%' OR
        r.review_text LIKE '%$searchQuery%'
    )";
}

// Apply sorting
switch ($sortBy) {
    case 'oldest':
        $reviewsQuery .= " ORDER BY r.created_at ASC";
        break;
    case 'highest':
        $reviewsQuery .= " ORDER BY r.rating DESC, r.created_at DESC";
        break;
    case 'lowest':
        $reviewsQuery .= " ORDER BY r.rating ASC, r.created_at DESC";
        break;
    case 'item_name_asc':
        $reviewsQuery .= " ORDER BY item_name ASC, r.created_at DESC";
        break;
    case 'item_name_desc':
        $reviewsQuery .= " ORDER BY item_name DESC, r.created_at DESC";
        break;
    default:
        $reviewsQuery .= " ORDER BY r.created_at DESC";
}

// Execute the query
$reviewsResult = $conn->query($reviewsQuery);

// Get counts for summary
$countQuery = "SELECT 
    content_type, COUNT(*) as total,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
    AVG(rating) as avg_rating
    FROM reviews
    GROUP BY content_type";
$countResult = $conn->query($countQuery);

$stats = [
    'rental_item' => [
        'total' => 0,
        'avg' => 0,
        'stars' => [0, 0, 0, 0, 0]
    ],
    'facility' => [
        'total' => 0,
        'avg' => 0,
        'stars' => [0, 0, 0, 0, 0]
    ]
];

if ($countResult && $countResult->num_rows > 0) {
    while ($row = $countResult->fetch_assoc()) {
        $stats[$row['content_type']]['total'] = $row['total'];
        $stats[$row['content_type']]['avg'] = round($row['avg_rating'], 1);
        $stats[$row['content_type']]['stars'] = [
            $row['one_star'],
            $row['two_star'],
            $row['three_star'],
            $row['four_star'],
            $row['five_star']
        ];
    }
}

// Helper function to render star ratings
function renderStars($rating) {
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<i class="bi bi-star-fill text-warning"></i>';
        } else {
            $output .= '<i class="bi bi-star text-muted"></i>';
        }
    }
    return $output;
}

// Extract unique items/facilities for filter dropdown
$itemsQuery = "
    SELECT 'rental_item' as type, item_id as id, name 
    FROM rental_items
    UNION
    SELECT 'facility' as type, facility_id as id, name
    FROM amenities
    ORDER BY name";
$itemsResult = $conn->query($itemsQuery);
$filterItems = [];

if ($itemsResult && $itemsResult->num_rows > 0) {
    while ($row = $itemsResult->fetch_assoc()) {
        $filterItems[$row['type']][] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - Admin Dashboard</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="page-container">
        <h1 class="page-title">
            <i class="bi bi-star"></i> Review Management
        </h1>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number"><?= $stats['rental_item']['total'] + $stats['facility']['total'] ?></div>
                    <div class="label">Total Reviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number"><?= $stats['rental_item']['total'] ?></div>
                    <div class="label">Item Reviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number"><?= $stats['facility']['total'] ?></div>
                    <div class="label">Facility Reviews</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number">
                        <?php 
                        $totalReviews = $stats['rental_item']['total'] + $stats['facility']['total'];
                        $totalRating = 0;
                        if ($totalReviews > 0) {
                            $totalRating = round(($stats['rental_item']['avg'] * $stats['rental_item']['total'] + 
                                           $stats['facility']['avg'] * $stats['facility']['total']) / $totalReviews, 1);
                        }
                        echo $totalRating;
                        ?>
                        <small class="fs-6">/5</small>
                    </div>
                    <div class="label">Average Rating</div>
                    <div class="mt-2">
                        <?= renderStars($totalRating) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rating Summary -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">Item Reviews Rating</h5>
                    <?php if ($stats['rental_item']['total'] > 0): ?>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="rating-summary">
                                <div style="width: 25px;"><?= $i ?> <i class="bi bi-star-fill text-warning small"></i></div>
                                <div class="progress">
                                    <?php 
                                    $percentage = $stats['rental_item']['total'] > 0 ? 
                                        ($stats['rental_item']['stars'][$i-1] / $stats['rental_item']['total']) * 100 : 0; 
                                    ?>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $percentage ?>%" 
                                         aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div style="width: 40px;" class="rating-count"><?= $stats['rental_item']['stars'][$i-1] ?></div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <p class="text-muted">No item reviews available</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">Facility Reviews Rating</h5>
                    <?php if ($stats['facility']['total'] > 0): ?>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="rating-summary">
                                <div style="width: 25px;"><?= $i ?> <i class="bi bi-star-fill text-warning small"></i></div>
                                <div class="progress">
                                    <?php 
                                    $percentage = $stats['facility']['total'] > 0 ? 
                                        ($stats['facility']['stars'][$i-1] / $stats['facility']['total']) * 100 : 0; 
                                    ?>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $percentage ?>%" 
                                         aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div style="width: 40px;" class="rating-count"><?= $stats['facility']['stars'][$i-1] ?></div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <p class="text-muted">No facility reviews available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form action="" method="GET" id="filterForm">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="type" class="filter-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?= $contentType == 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="rental_item" <?= $contentType == 'rental_item' ? 'selected' : '' ?>>Rental Items</option>
                            <option value="facility" <?= $contentType == 'facility' ? 'selected' : '' ?>>Facilities</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="rating" class="filter-label">Rating</label>
                        <select class="form-select" id="rating" name="rating">
                            <option value="0" <?= $ratingFilter == 0 ? 'selected' : '' ?>>All Ratings</option>
                            <option value="5" <?= $ratingFilter == 5 ? 'selected' : '' ?>>5 Stars</option>
                            <option value="4" <?= $ratingFilter == 4 ? 'selected' : '' ?>>4 Stars</option>
                            <option value="3" <?= $ratingFilter == 3 ? 'selected' : '' ?>>3 Stars</option>
                            <option value="2" <?= $ratingFilter == 2 ? 'selected' : '' ?>>2 Stars</option>
                            <option value="1" <?= $ratingFilter == 1 ? 'selected' : '' ?>>1 Star</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="sort" class="filter-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sortBy == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="highest" <?= $sortBy == 'highest' ? 'selected' : '' ?>>Highest Rating</option>
                            <option value="lowest" <?= $sortBy == 'lowest' ? 'selected' : '' ?>>Lowest Rating</option>
                            <option value="item_name_asc" <?= $sortBy == 'item_name_asc' ? 'selected' : '' ?>>Item Name (A-Z)</option>
                            <option value="item_name_desc" <?= $sortBy == 'item_name_desc' ? 'selected' : '' ?>>Item Name (Z-A)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search reviews..." 
                                   name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                            <button class="btn btn-filter" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-end">
                    <button type="button" class="btn btn-reset" id="resetFilters">
                        <i class="bi bi-x-circle"></i> Reset Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Reviews List -->
        <?php if ($reviewsResult && $reviewsResult->num_rows > 0): ?>
            <div class="reviews-container">
                <?php while ($review = $reviewsResult->fetch_assoc()): ?>
                    <div class="review-card <?= $review['content_type'] == 'rental_item' ? 'rental-item' : 'facility' ?>">
                        <div class="review-header">
                            <?php if (!empty($review['user_image'])): ?>
                                <img src="<?= htmlspecialchars($review['user_image']) ?>" alt="<?= htmlspecialchars($review['f_name']) ?>" class="review-user-img">
                            <?php else: ?>
                                <div class="review-user-placeholder">
                                    <?= strtoupper(substr($review['f_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="review-meta">
                                <h5 class="review-user-name">
                                    <?= htmlspecialchars($review['f_name'] . ' ' . $review['l_name']) ?>
                                </h5>
                                <div class="review-date">
                                    <?= date('F j, Y g:i A', strtotime($review['created_at'])) ?>
                                </div>
                            </div>
                            
                            <span class="review-badge <?= $review['content_type'] == 'rental_item' ? 'badge-rental-item' : 'badge-facility' ?>">
                                <?= htmlspecialchars($review['type_label']) ?>
                            </span>
                        </div>
                        
                        <div class="review-item-info">
                            <?php if (!empty($review['item_image'])): ?>
                                <img src="<?= htmlspecialchars($review['item_image']) ?>" alt="<?= htmlspecialchars($review['item_name']) ?>" class="review-item-img">
                            <?php else: ?>
                                <div class="review-item-placeholder">
                                    <i class="bi <?= $review['content_type'] == 'rental_item' ? 'bi-box-seam' : 'bi-building' ?>"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <h6 class="review-item-name"><?= htmlspecialchars($review['item_name']) ?></h6>
                                <div class="review-item-type"><?= htmlspecialchars($review['type_label']) ?></div>
                            </div>
                        </div>
                        
                        <div class="review-stars">
                            <?= renderStars($review['rating']) ?>
                            <span class="ms-2 text-muted"><?= $review['rating'] ?>/5</span>
                        </div>
                        
                        <?php if (!empty($review['review_text'])): ?>
                            <p class="review-text mt-3">
                                "<?= htmlspecialchars($review['review_text']) ?>"
                            </p>
                        <?php endif; ?>
                        
                        <div class="review-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger delete-review" data-review-id="<?= $review['review_id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-emoji-neutral"></i>
                <h3>No reviews found</h3>
                <p>There are no reviews matching your current filter criteria. Try adjusting your filters or check back later.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Delete Review Modal -->
    <div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this review? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_review.php" method="post" id="deleteReviewForm">
                        <input type="hidden" name="review_id" id="reviewIdToDelete">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reset filter form
            document.getElementById('resetFilters').addEventListener('click', function() {
                window.location.href = 'review_all.php';
            });
            
            // Handle delete review action
            document.querySelectorAll('.delete-review').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    document.getElementById('reviewIdToDelete').value = reviewId;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteReviewModal'));
                    deleteModal.show();
                });
            });
            
            // Auto-submit form when filters change
            const filterSelects = document.querySelectorAll('#type, #rating, #sort');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            });
        });
    </script>
</body>
</html>
<style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
            --text-color: #1e293b;
            --light-text: #94a3b8;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --bg-dark: #1e293b;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--text-color);
        }
        
        .page-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-title i {
            color: var(--primary-color);
        }
        
        .stats-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stats-card .label {
            font-size: 0.9rem;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filters-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .review-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        
        .review-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .review-card.rental-item {
            border-left-color: var(--primary-color);
        }
        
        .review-card.facility {
            border-left-color: var(--warning);
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .review-user-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid var(--border-color);
        }
        
        .review-user-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--light-text);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 1rem;
        }
        
        .review-meta {
            flex: 1;
        }
        
        .review-user-name {
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .review-date {
            font-size: 0.8rem;
            color: var(--light-text);
        }
        
        .review-stars {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .review-item-info {
            display: flex;
            align-items: center;
            background-color: var(--bg-light);
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .review-item-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.25rem;
            margin-right: 1rem;
        }
        
        .review-item-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 0.25rem;
            background-color: #e2e8f0;
            color: var(--light-text);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .review-item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .review-item-type {
            font-size: 0.8rem;
            color: var (--light-text);
        }
        
        .review-text {
            line-height: 1.6;
            margin-bottom: 0;
            color: var(--text-color);
        }
        
        .review-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        .review-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-rental-item {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .badge-facility {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .filter-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .form-select, .form-control {
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        
        .rating-count {
            font-size: 0.85rem;
            color: var(--light-text);
        }
        
        .btn-filter {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        .btn-filter:hover {
            background-color: #2563eb;
        }
        
        .btn-reset {
            background-color: #f1f5f9;
            color: var(--text-color);
            border: none;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        .btn-reset:hover {
            background-color: #e2e8f0;
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .rating-summary .progress {
            flex: 1;
            height: 0.5rem;
            margin: 0 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-text);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--light-text);
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
    <?php include '../includes/footer.php'; ?>