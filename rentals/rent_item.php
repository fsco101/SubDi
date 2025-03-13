<?php
include '../includes/header.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /subdisystem/user/login.php");
    exit;
}

// Fetch available rental items
$items = [];
try {
    // Select relevant fields from the rental_items table
    $sql = "SELECT item_id, name, description, rental_price, quantity, image_path, availability_status
            FROM rental_items WHERE availability_status = 'available'";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error fetching available items: " . $conn->error);
    }

    $items = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Error fetching available items: " . $e->getMessage());
}

// Get current date and time for start time
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rental Items</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <style>
        /* Modern Card Design */
        .rental-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }
        .rental-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 123, 255, 0.2);
        }

        /* Rental Image */
        .rental-img {
            height: 220px;
            object-fit: cover;
            border-bottom: 3px solid #0d6efd;
        }

        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Dark Mode Styling */
        body.bg-dark {
            background-color:rgb(255, 255, 255) !important;
        }
        
        .page-header {
            color: black;
        }
        
        /* Modal Styling */
        .modal-content {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .form-control, .form-select {
            background-color: #2c2c2c;
            border: 1px solid #444;
            color: #fff;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #333;
            color: #fff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .rental-img {
                height: 180px;
            }
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="bg-dark text-light">

<div class="container">
    <div class="page-header text-center">
        <h2 class="mb-2"><i class="bi bi-box"></i> Available Rental Items</h2>
        <p class="text-muted">Browse and rent high-quality items for your needs</p>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-warning text-center">
            <i class="bi bi-exclamation-circle me-2"></i> No available rental items at the moment.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card rental-card shadow-lg h-100">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars('/subdisystem/rentals/item_upload/' . basename($item['image_path'])); ?>" 
                                class="card-img-top img-fluid rental-img" 
                                alt="<?= htmlspecialchars($item['name']); ?>">
                            <span class="status-badge bg-success">Available</span>
                        </div>
                        <div class="card-body text-dark">
                            <h5 class="card-title fw-bold"><?= htmlspecialchars($item['name']); ?></h5>
                            <p class="card-text text-muted"><?= htmlspecialchars($item['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-primary fs-5">
                                    <i class="bi bi-tag"></i> ₱<?= number_format($item['rental_price'], 2); ?>/hr
                                </span>
                                <span class="badge bg-info">
                                    <i class="bi bi-box-seam me-1"></i> <?= htmlspecialchars($item['quantity']); ?> units
                                </span>
                            </div>
                            <button class="btn btn-primary btn-lg w-100 mt-2" 
                                    onclick="openRentModal(<?= $item['item_id']; ?>, <?= $item['quantity']; ?>, '<?= htmlspecialchars($item['name']); ?>', <?= $item['rental_price']; ?>)">
                                <i class="bi bi-cart-plus me-2"></i> Rent Now
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Rental Confirmation Modal -->
<div id="rentalModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i> Rent <span id="modalItemName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rentalForm">
                    <input type="hidden" name="item_id" id="modalItemId">
                    <input type="hidden" name="rental_price" id="modalRentalPrice"> <!-- Add rental price to the form -->

                    <div class="mb-3">
                        <label class="form-label fw-bold">Start Date & Time:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-light">
                                <i class="bi bi-calendar-check"></i>
                            </span>
                            <input type="datetime-local" name="rental_start" id="rental_start" 
                                   class="form-control" readonly>
                        </div>
                        <small class="text-muted">Current time (PHT)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">End Date & Time:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-light">
                                <i class="bi bi-calendar-x"></i>
                            </span>
                            <input type="datetime-local" name="rental_end" id="rental_end" required 
                                   class="form-control" onchange="calculateTotalPayment();">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantity:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-light">
                                <i class="bi bi-123"></i>
                            </span>
                            <input type="number" name="quantity" id="rental_quantity" 
                                   class="form-control" min="1" required onchange="calculateTotalPayment();">
                        </div>
                        <small class="text-muted">Available: <span id="availableQuantity"></span> units</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Total Payment:</label>
                        <input type="text" id="totalPayment" class="form-control" readonly>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i> Confirm Rental
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-fill start time with Philippine Time (UTC+8)
function setStartTime() {
    const options = {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    };
    
    const now = new Date();
    const manilaNow = new Date(now.toLocaleString('en-US', options));
    
    const year = manilaNow.getFullYear();
    const month = String(manilaNow.getMonth() + 1).padStart(2, '0');
    const day = String(manilaNow.getDate()).padStart(2, '0');
    const hours = String(manilaNow.getHours()).padStart(2, '0');
    const minutes = String(manilaNow.getMinutes()).padStart(2, '0');
    
    const formatted = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    document.getElementById("rental_start").value = formatted;
    document.getElementById("rental_end").min = formatted;
    
    const defaultEndTime = new Date(manilaNow);
    defaultEndTime.setHours(defaultEndTime.getHours() + 1);
    
    const endHours = String(defaultEndTime.getHours()).padStart(2, '0');
    const endMinutes = String(defaultEndTime.getMinutes()).padStart(2, '0');
    
    const formattedEnd = `${year}-${month}-${day}T${endHours}:${endMinutes}`;
    document.getElementById("rental_end").value = formattedEnd;
}

// Open rental modal with item ID and available quantity
function openRentModal(itemId, availableQuantity, itemName, rentalPrice) {
    setStartTime();
    document.getElementById('modalItemId').value = itemId;
    document.getElementById('modalItemName').textContent = itemName;
    document.getElementById('modalRentalPrice').value = rentalPrice; // Set the rental price in hidden field
    document.getElementById('rental_quantity').setAttribute("max", availableQuantity);
    document.getElementById('rental_quantity').value = 1;
    document.getElementById('availableQuantity').textContent = availableQuantity;
    calculateTotalPayment(); // Calculate total payment when opening modal
    new bootstrap.Modal(document.getElementById('rentalModal')).show();
}

// Calculate total payment based on rental price, quantity, and hours
function calculateTotalPayment() {
    const rentalPrice = parseFloat(document.getElementById('modalRentalPrice').value); // Rental price per hour
    const quantity = parseInt(document.getElementById('rental_quantity').value); // Quantity

    const startTime = new Date(document.getElementById('rental_start').value);
    const endTime = new Date(document.getElementById('rental_end').value);
    
    // Validate if end time is after start time before calculating
    if (endTime > startTime) {
        // Calculate hours
        const hours = Math.ceil((endTime - startTime) / (1000 * 60 * 60)); // Convert milliseconds to hours
        const totalPayment = rentalPrice * quantity * hours;
        document.getElementById('totalPayment').value = "₱" + totalPayment.toFixed(2); // Show total payment
    } else {
        document.getElementById('totalPayment').value = "₱0.00"; // Reset if invalid
    }
}

// Handle rental submission via AJAX
document.getElementById("rentalForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const formData = new FormData(this);

    // Validate end time is after start time
    const startTime = new Date(document.getElementById('rental_start').value);
    const endTime = new Date(document.getElementById('rental_end').value);
    
    if (endTime <= startTime) {
        alert("End time must be after start time");
        return;
    }

    fetch("/subdisystem/rentals/process_rental.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showCustomAlert("Success", data.message, "success");
            setTimeout(() => location.reload(), 2000);
        } else {
            showCustomAlert("Error", data.message, "danger");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        showCustomAlert("Error", "Something went wrong!", "danger");
    });
});

// Custom alert function
function showCustomAlert(title, message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-4`;
    alertDiv.style.zIndex = "9999";
    alertDiv.style.minWidth = "300px";
    alertDiv.style.boxShadow = "0 4px 8px rgba(0,0,0,0.2)";
    
    alertDiv.innerHTML = `
        <strong>${title}: </strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

document.addEventListener("DOMContentLoaded", setStartTime);
</script>
</body>
</html>