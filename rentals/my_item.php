<?php
include '../includes/header.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch rentals for the logged-in user
$query = "SELECT r.rental_id, r.rental_start, r.rental_end, r.status, r.payment_status, r.quantity, 
                 r.total_payment, i.name AS item_name, i.description, i.rental_price, i.image_path 
          FROM rentals r
          JOIN rental_items i ON r.item_id = i.item_id
          WHERE r.user_id = ?
          ORDER BY r.rental_start DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rentals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to format dates
function formatDateTime($dateTimeStr) {
    $date = new DateTime($dateTimeStr);
    return $date->format('M d, Y - h:i A');
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'pending': return 'bg-warning text-dark';
        case 'approved': return 'bg-success';
        case 'returned': return 'bg-secondary';
        case 'cancelled': return 'bg-danger';
        case 'paid': return 'bg-success';
        default: return 'bg-info';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rented Items</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <style>
        /* Main Container Styling */
        body.bg-dark {
            background-color:rgb(255, 255, 255) !important;
        }
        .page-header {
            color:black;
        }
        
        /* Card Styling */
        .rental-card {
            background: #1e1e1e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .rental-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(13, 110, 253, 0.25);
            border-color: rgba(13, 110, 253, 0.5);
        }
        
        /* Image Styling */
        .rental-img-container {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .rental-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .rental-card:hover .rental-img {
            transform: scale(1.05);
        }
        
        /* Card Content */
        .card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #fff;
        }
        
        .card-text {
            color: rgba(255,255,255,0.7);
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        
        /* Info List */
        .rental-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .rental-info-list li {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: rgba(255,255,255,0.8);
        }
        
        .rental-info-list .icon {
            width: 24px;
            margin-right: 10px;
            color: #0d6efd;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.35em 0.65em;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        
        /* Date Time Display */
        .datetime {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Price Display */
        .price-tag {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 0.75rem;
        }
        
        /* Total Payment Display */
        .total-payment {
            font-size: 1rem;
            font-weight: 700;
            color: #dc3545; /* Using a danger color to indicate payment */
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            margin-top: 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: rgba(255,255,255,0.3);
            margin-bottom: 1.5rem;
            display: block;
        }
        
        /* Status Indicator in Card Header */
        .status-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-title {
                font-size: 1.2rem;
            }
            
            .price-tag {
                font-size: 1.1rem;
            }
            
            .rental-info-list li {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-dark text-light">

<div class="container py-5">
    <div class="page-header">
        <h2 class="text-center mb-2"><i class="bi bi-box-seam me-2"></i> My Rented Items</h2>
        <p class="text-center text-muted">View and manage your current and past rentals</p>
    </div>

    <?php if (!empty($rentals)): ?>
        <button class="btn btn-danger w-100 mb-3" onclick="deleteAllItems()">
            <i class="bi bi-trash me-2"></i> Delete All Returned and Paid Items
        </button>
    <?php endif; ?>

    <?php if (empty($rentals)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h4>No Rentals Found</h4>
            <p class="text-muted mb-4">You haven't rented any items yet.</p>
            <a href="rent_item.php" class="btn btn-primary px-4 py-2">
                <i class="bi bi-plus-circle me-2"></i> Rent Items Now
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($rentals as $rental): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="rental-card shadow">
                        <!-- Status Badge -->
                        <div class="status-indicator">
                            <span class="status-badge <?= getStatusBadgeClass($rental['status']); ?>">
                                <?= ucfirst(htmlspecialchars($rental['status'])); ?>
                            </span>
                        </div>
                        
                        <!-- Item Image -->
                        <div class="rental-img-container">
                            <img src="<?= htmlspecialchars('/subdisystem/rentals/item_upload/' . basename($rental['image_path'])); ?>" 
                                 class="rental-img" 
                                 alt="<?= htmlspecialchars($rental['item_name']); ?>">
                        </div>
                        
                        <!-- Card Content -->
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($rental['item_name']); ?></h5>
                            <p class="card-text"><?= htmlspecialchars($rental['description']); ?></p>
                            
                            <div class="price-tag">
                                <i class="bi bi-tag-fill me-2"></i>₱<?= number_format($rental['rental_price'], 2); ?> per hour
                            </div>
                            
                            <!-- Total Payment Display -->
                            <div class="total-payment">
                                Total Payment: ₱<?= number_format($rental['total_payment'], 2); ?>
                            </div>
                            
                            <!-- Rental Information -->
                            <ul class="rental-info-list mt-2">
                                <li>
                                    <i class="bi bi-calendar-check icon"></i>
                                    <div>
                                        <small class="d-block text-muted">Start Time:</small>
                                        <span class="datetime"><?= formatDateTime($rental['rental_start']); ?></span>
                                    </div>
                                </li>
                                <li>
                                    <i class="bi bi-calendar-x icon"></i>
                                    <div>
                                        <small class="d-block text-muted">End Time:</small>
                                        <span class="datetime"><?= formatDateTime($rental['rental_end']); ?></span>
                                    </div>
                                </li>
                                <li>
                                    <i class="bi bi-box icon"></i>
                                    <span><strong>Quantity:</strong> <?= htmlspecialchars($rental['quantity']); ?></span>
                                </li>
                                <li>
                                    <i class="bi bi-cash-stack icon"></i>
                                    <div class="d-flex align-items-center">
                                        <span class="me-2"><strong>Payment Status:</strong></span>
                                        <span class="status-badge <?= getStatusBadgeClass($rental['payment_status']); ?>">
                                            <?= ucfirst(htmlspecialchars($rental['payment_status'])); ?>
                                        </span>
                                    </div>
                                </li>
                            </ul>
                            
                            <!-- Action Button based on status -->
                            <?php if (strtolower($rental['status']) === 'approved' && strtolower($rental['payment_status']) === 'paid'): ?>
                                <button class="btn btn-danger w-100 mt-3" onclick="returnItem(<?= $rental['rental_id']; ?>)">
                                    <i class="bi bi-arrow-return-left me-2"></i> Return Item
                                </button>
                            <?php elseif (strtolower($rental['status']) === 'approved' && strtolower($rental['payment_status']) === 'pending'): ?>
                                <a href="payment.php?rental_id=<?= $rental['rental_id']; ?>" class="btn btn-primary w-100 mt-3">
                                    <i class="bi bi-credit-card me-2"></i> Pay Now
                                </a>
                            <?php elseif (strtolower($rental['status']) === 'pending'): ?>
                                <button class="btn btn-outline-secondary w-100 mt-3" disabled>
                                    <i class="bi bi-hourglass me-2"></i> Awaiting Approval
                                </button>
                            <?php endif; ?>

                            <!-- Delete Button for Returned and Paid Items -->
                            <?php if (strtolower($rental['status']) === 'returned' || strtolower($rental['status']) === 'paid'): ?>
                                <button class="btn btn-outline-danger w-100 mt-3" onclick="deleteItem(<?= $rental['rental_id']; ?>, '<?= strtolower($rental['status']); ?>')">
                                    <i class="bi bi-trash me-2"></i> Delete Item
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
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

function returnItem(rentalId) {
    if (confirm("Are you sure you want to return this item?")) {
        fetch("/subdisystem/rentals/return_rental.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `rental_id=${rentalId}`
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Something went wrong!");
        });
    }
}

function deleteItem(rentalId, status) {
    if (status !== 'returned' && status !== 'paid') {
        alert("Only returned or paid items can be deleted.");
        return;
    }
    if (confirm("Are you sure you want to delete this item?")) {
        fetch("/subdisystem/rentals/delete_rental.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `rental_id=${rentalId}`
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Something went wrong!");
        });
    }
}

function deleteAllItems() {
    if (confirm("Are you sure you want to delete all returned and paid items?")) {
        fetch("/subdisystem/rentals/delete_all_rentals.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `action=delete_all`
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Something went wrong!");
        });
    }
}
</script>

</body>
</html>
<?php include '../includes/footer.php'; ?>