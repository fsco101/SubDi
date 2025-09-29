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
            FROM rental_items";
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
                            <span class="status-badge <?= $item['availability_status'] == 'available' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($item['availability_status']); ?>
                            </span>
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
                            <?php if ($item['availability_status'] == 'available'): ?>
                                <button class="btn btn-primary btn-lg w-100 mt-2" 
                                        onclick="openRentModal(<?= $item['item_id']; ?>, <?= $item['quantity']; ?>, '<?= htmlspecialchars($item['name']); ?>', <?= $item['rental_price']; ?>)">
                                    <i class="bi bi-cart-plus me-2"></i> Rent Now
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg w-100 mt-2" disabled>
                                    <i class="bi bi-x-circle me-2"></i> Not Available
                                </button>
                            <?php endif; ?>
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

<!-- Receipt Modal -->
<div id="receiptModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-white text-dark">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i> Rental Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <div class="receipt-container">
                    <div class="text-center mb-3">
                        <h3 class="receipt-title">RENTAL RECEIPT</h3>
                        <p class="receipt-subtitle">SubdiSystem Rental Service</p>
                    </div>
                    
                    <div class="receipt-details">
                        <p><strong>Receipt #:</strong> <span id="receipt-id"></span></p>
                        <p><strong>Date:</strong> <span id="receipt-date"></span></p>
                        <p><strong>Customer:</strong> <span id="receipt-customer"></span></p>
                        
                        <hr>
                        
                        <p><strong>Item:</strong> <span id="receipt-item-name"></span></p>
                        <p><strong>Quantity:</strong> <span id="receipt-quantity"></span></p>
                        <p><strong>Start Date/Time:</strong> <span id="receipt-start-time"></span></p>
                        <p><strong>End Date/Time:</strong> <span id="receipt-end-time"></span></p>
                        <p><strong>Duration:</strong> <span id="receipt-duration"></span></p>
                        
                        <hr>
                        
                        <p><strong>Price per Hour:</strong> <span id="receipt-price"></span></p>
                        <p class="receipt-total"><strong>TOTAL AMOUNT:</strong> <span id="receipt-total"></span></p>
                        
                        <div class="receipt-footer text-center mt-4">
                            <p>Thank you for using our rental service!</p>
                            <p class="small text-muted">This is an electronic receipt.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="bi bi-printer me-2"></i> Print Receipt
                </button>
                <button type="button" class="btn btn-success" onclick="downloadReceipt()">
                    <i class="bi bi-download me-2"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Include html2canvas and jsPDF for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

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
    
    const endYear = defaultEndTime.getFullYear();
    const endMonth = String(defaultEndTime.getMonth() + 1).padStart(2, '0');
    const endDay = String(defaultEndTime.getDate()).padStart(2, '0');
    const endHours = String(defaultEndTime.getHours()).padStart(2, '0');
    const endMinutes = String(defaultEndTime.getMinutes()).padStart(2, '0');
    
    const formattedEnd = `${endYear}-${endMonth}-${endDay}T${endHours}:${endMinutes}`;
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
            // Close rental modal
            bootstrap.Modal.getInstance(document.getElementById('rentalModal')).hide();
            
            // Generate receipt
            generateReceipt(data);
            
            // Show success message
            showCustomAlert("Success", data.message, "success");
            
            // Show receipt modal
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
            
            // Reload page after modal is closed
            document.getElementById('receiptModal').addEventListener('hidden.bs.modal', function() {
                location.reload();
            });
        } else {
            showCustomAlert("Error", data.message, "danger");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        showCustomAlert("Error", "Something went wrong!", "danger");
    });
});

// Generate receipt with rental data
function generateReceipt(data) {
    // Format dates
    const startTimeObj = new Date(document.getElementById('rental_start').value);
    const endTimeObj = new Date(document.getElementById('rental_end').value);
    
    const startTimeFormatted = formatDateTime(startTimeObj);
    const endTimeFormatted = formatDateTime(endTimeObj);
    
    // Calculate duration in hours
    const durationHours = Math.ceil((endTimeObj - startTimeObj) / (1000 * 60 * 60));
    
    // Get rental details
    const itemName = document.getElementById('modalItemName').textContent;
    const quantity = document.getElementById('rental_quantity').value;
    const pricePerHour = "₱" + parseFloat(document.getElementById('modalRentalPrice').value).toFixed(2);
    const totalPayment = document.getElementById('totalPayment').value;
    
    // Set receipt values
    document.getElementById('receipt-id').textContent = data.rental_id || "RNT-" + Math.floor(Math.random() * 10000);
    document.getElementById('receipt-date').textContent = formatDate(new Date());
    document.getElementById('receipt-customer').textContent = data.user_name || "Customer";
    document.getElementById('receipt-item-name').textContent = itemName;
    document.getElementById('receipt-quantity').textContent = quantity;
    document.getElementById('receipt-start-time').textContent = startTimeFormatted;
    document.getElementById('receipt-end-time').textContent = endTimeFormatted;
    document.getElementById('receipt-duration').textContent = durationHours + " hour(s)";
    document.getElementById('receipt-price').textContent = pricePerHour;
    document.getElementById('receipt-total').textContent = totalPayment;
}

// Format date for receipt
function formatDate(date) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    return date.toLocaleDateString('en-US', options);
}

// Format date and time for receipt
function formatDateTime(date) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
}

// Print receipt function
function printReceipt() {
    const content = document.getElementById('receiptContent');
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div class="container p-4">
            ${content.innerHTML}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContents;
    
    // Reinitialize the modals after restoring content
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

// Download receipt as PDF
function downloadReceipt() {
    const { jsPDF } = window.jspdf;
    const receiptContent = document.querySelector('.receipt-container');
    
    html2canvas(receiptContent).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
        const imgHeight = canvas.height * imgWidth / canvas.width;
        
        pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
        pdf.save('rental-receipt.pdf');
    });
}

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
    
    /* Receipt Styling */
    .receipt-container {
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
    .receipt-title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .receipt-subtitle {
        color: #666;
        margin-bottom: 20px;
    }
    
    .receipt-details {
        font-size: 14px;
    }
    
    .receipt-details hr {
        border-top: 1px dashed #ccc;
        margin: 15px 0;
    }
    
    .receipt-total {
        font-size: 18px;
        font-weight: bold;
        margin-top: 10px;
    }
    
    .receipt-footer {
        border-top: 1px dashed #ccc;
        padding-top: 15px;
        margin-top: 20px;
    }
    
    @media print {
        .receipt-container {
            box-shadow: none;
        }
    }
</style><?php include '../includes/footer.php'; ?>