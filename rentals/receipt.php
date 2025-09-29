<?php
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /subdisystem/user/login.php");
    exit();
}

// Check if rental_id is provided
if (!isset($_GET['rental_id'])) {
    echo "Rental ID is required";
    exit();
}

$rentalId = intval($_GET['rental_id']);

// Fetch rental details
$query = "SELECT r.rental_id, r.user_id, u.f_name, u.l_name, u.email, u.phone_number, 
           u.role, u.image_url, i.name AS item_name, i.description, 
           i.rental_price, i.image_path, r.rental_start, r.rental_end, 
           r.quantity, r.status, r.payment_status, r.total_payment 
    FROM rentals r 
    JOIN users u ON r.user_id = u.user_id 
    JOIN rental_items i ON r.item_id = i.item_id 
    WHERE r.rental_id = $rentalId";

// Allow admin to access any rental receipt
if ($_SESSION['role'] !== 'admin') {
    $query .= " AND r.user_id = " . intval($_SESSION['user_id']);
}

$result = $conn->query($query);

// Check for query error
if ($result === false) {
    error_log("Error in rental receipt query: " . $conn->error);
    echo "Error fetching rental details";
    exit();
}

$rental = $result->fetch_assoc();
if (!$rental) {
    echo "Rental not found";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rental Receipt</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h2><i class="fas fa-receipt"></i> Rental Receipt</h2>
            <div class="receipt-actions">
                <button id="printReceiptBtn" class="action-btn print-btn">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button id="downloadPdfBtn" class="action-btn pdf-btn">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="rent_item.php" class="action-btn">
                    <i class="fas fa-arrow-left"></i> Back to Rentals
                </a>
            </div>
        </div>
        
        <div class="receipt-content" id="receipt-printable">
            <div class="receipt-branding">
                <h1>RENTAL RECEIPT</h1>
                <p class="receipt-subtitle">SubdiSystem Rental Management</p>
            </div>
            
            <div class="receipt-details">
                <div class="receipt-info">
                    <table class="receipt-info-table">
                        <tr>
                            <th>Rental ID:</th>
                            <td><?= htmlspecialchars($rental['rental_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="receipt-status <?= strtolower($rental['status']); ?>">
                                <?= ucfirst(htmlspecialchars($rental['status'])); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Payment Status:</th>
                            <td><span class="receipt-status <?= strtolower($rental['payment_status']); ?>">
                                <?= ucfirst(htmlspecialchars($rental['payment_status'])); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="receipt-user">
                    <h3>Renter Information</h3>
                    <table class="receipt-user-table">
                        <tr>
                            <th>Name:</th>
                            <td><?= htmlspecialchars($rental['f_name'] . ' ' . $rental['l_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?= htmlspecialchars($rental['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?= htmlspecialchars($rental['phone_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td><?= ucfirst(htmlspecialchars($rental['role'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="receipt-booking">
                <h3>Item Information</h3>
                <div class="booking-flex">
                    <div class="booking-facility print-hide">
                        <h4><?= htmlspecialchars($rental['item_name']); ?></h4>
                    </div>
                    
                    <div class="booking-info">
                        <table class="booking-info-table">
                            <tr>
                                <th>Item Name:</th>
                                <td><?= htmlspecialchars($rental['item_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td><?= htmlspecialchars($rental['description']); ?></td>
                            </tr>
                            <tr>
                                <th>Rental Price:</th>
                                <td>₱<?= htmlspecialchars(number_format($rental['rental_price'], 2)); ?> per item</td>
                            </tr>
                            <tr>
                                <th>Quantity:</th>
                                <td><?= htmlspecialchars($rental['quantity']); ?></td>
                            </tr>
                            <tr>
                                <th>Total Payment:</th>
                                <td>₱<?= htmlspecialchars(number_format($rental['total_payment'], 2)); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="receipt-payment">
                <h3>Rental Period</h3>
                <table class="payment-table">
                    <tr>
                        <th>Start Date:</th>
                        <td><?= date('M j, Y', strtotime($rental['rental_start'])); ?></td>
                    </tr>
                    <tr>
                        <th>End Date:</th>
                        <td><?= date('M j, Y', strtotime($rental['rental_end'])); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="receipt-footer">
                <p>Thank you for using our rental system!</p>
                <p>For any inquiries, please contact the administration office.</p>
                <p class="receipt-id">Rental ID: <?= htmlspecialchars($rental['rental_id']); ?></p>
            </div>
        </div>
    </div>

    <!-- Include jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        function attachEventListeners() {
            // Print functionality
            document.getElementById('printReceiptBtn').addEventListener('click', function() {
                const printContents = document.getElementById('receipt-printable').innerHTML;
                const originalContents = document.body.innerHTML;
                
                document.body.innerHTML = `
                    <div class="print-only">
                        ${printContents}
                    </div>
                `;
                
                window.print();
                document.body.innerHTML = originalContents;
                
                // Reattach event listeners after content is restored
                attachEventListeners();
            });

            // PDF export functionality
            document.getElementById('downloadPdfBtn').addEventListener('click', function() {
                const { jsPDF } = window.jspdf;
                
                // Create a clone of the receipt to modify for PDF export
                const element = document.getElementById('receipt-printable').cloneNode(true);
                const pdfContainer = document.createElement('div');
                pdfContainer.appendChild(element);
                pdfContainer.style.width = '700px';
                document.body.appendChild(pdfContainer);
                
                // Hide elements that shouldn't be in the PDF
                const hideElements = pdfContainer.querySelectorAll('.print-hide');
                hideElements.forEach(el => {
                    el.style.display = 'none';
                });
                
                html2canvas(pdfContainer, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                }).then(canvas => {
                    document.body.removeChild(pdfContainer);
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgWidth = 210;
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;

                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= 295;

                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= 295;
                    }

                    pdf.save(`rental_receipt_${<?= htmlspecialchars($rental['rental_id']); ?>}.pdf`);
                });
            });
        }

        // Attach event listeners on page load
        attachEventListeners();
    </script>

    <style>
        /* Receipt Page Styling */
        .receipt-container {
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
            border: 1px solid #e9ecef;
            background-color: #ffffff;
            margin: 30px auto;
            max-width: 600px;
        }
        .receipt-header {
            margin-bottom: 20px;
            text-align: center;
        }
        .receipt-header h2 {
            font-weight: bold;
            font-size: 20px;
            margin: 0;
        }
        .receipt-actions {
            margin-bottom: 20px;
            justify-content: space-between;
            display: flex;
        }
        .action-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .action-btn:hover {
            background-color: #2980b9;
        }
        .receipt-content {
            font-size: 14px;
        }
        .receipt-branding {
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-branding h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        .receipt-subtitle {
            margin: 0;
            font-size: 12px;
        }
        .receipt-details, .receipt-booking, .receipt-payment {
            margin-bottom: 20px;
        }
        .receipt-info-table, .receipt-user-table, .booking-info-table, .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-info-table th, .receipt-user-table th, .booking-info-table th, .payment-table th {
            text-align: left;
            padding: 5px;
            font-weight: bold;
            border-bottom: 1px dashed #000;
            color: black;
            background-color: white;
        }
        .receipt-info-table td, .receipt-user-table td, .booking-info-table td, .payment-table td {
            text-align: left;
            padding: 5px;
            border-bottom: 1px dashed #000;
            color: black;
            background-color: white;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
        }
        .print-hide {
            display: none;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .print-only, .print-only * {
                visibility: visible;
            }
            .print-only {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
    </style>
</body>
</html>