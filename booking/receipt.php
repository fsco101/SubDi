<?php
include '../includes/header.php';

// Check if the booking ID is provided
if (!isset($_GET['booking_id'])) {
    header("Location: /subdisystem/view_faci.php");
    exit();
}

$booking_id = $_GET['booking_id'];

// First, get the user columns to handle different schemas
$userColumnsQuery = "SHOW COLUMNS FROM users";
$userColumns = $conn->query($userColumnsQuery);
$hasFirstName = false;
$hasLastName = false;
$nameColumn = "";

if ($userColumns) {
    while ($column = $userColumns->fetch_assoc()) {
        if ($column['Field'] == 'first_name') {
            $hasFirstName = true;
        }
        if ($column['Field'] == 'last_name') {
            $hasLastName = true;
        }
        if ($column['Field'] == 'name' || $column['Field'] == 'full_name' || $column['Field'] == 'username') {
            $nameColumn = $column['Field'];
        }
    }
}

// Adjust the query based on available columns
if ($hasFirstName && $hasLastName) {
    $query = "SELECT b.*, a.name as facility_name, a.image_url, u.first_name, u.last_name, u.email
              FROM bookings b
              JOIN amenities a ON b.facility_id = a.facility_id
              JOIN users u ON b.user_id = u.user_id
              WHERE b.booking_id = ? AND b.user_id = ?";
} else if (!empty($nameColumn)) {
    $query = "SELECT b.*, a.name as facility_name, a.image_url, u.{$nameColumn} as user_name, u.email
              FROM bookings b
              JOIN amenities a ON b.facility_id = a.facility_id
              JOIN users u ON b.user_id = u.user_id
              WHERE b.booking_id = ? AND b.user_id = ?";
} else {
    // Fallback query with minimal user information
    $query = "SELECT b.*, a.name as facility_name, a.image_url, u.user_id, u.email
              FROM bookings b
              JOIN amenities a ON b.facility_id = a.facility_id
              JOIN users u ON b.user_id = u.user_id
              WHERE b.booking_id = ? AND b.user_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// If booking not found or doesn't belong to current user
if ($result->num_rows == 0) {
    header("Location: /subdisystem/view_faci.php.php?error=Booking not found");
    exit();
}

$booking = $result->fetch_assoc();
$receipt_number = "BK-" . str_pad($booking_id, 6, '0', STR_PAD_LEFT);

// Format date and time
$formatted_date = date('F d, Y', strtotime($booking['booking_date']));
$start_time = date('h:i A', strtotime($booking['start_time']));
$end_time = date('h:i A', strtotime($booking['end_time']));

// Calculate duration in hours
$start = new DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
$end = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
$duration = $start->diff($end);
$hours = $duration->h;
$minutes = $duration->i;
$total_minutes = ($hours * 60) + $minutes;
$duration_text = '';

if ($hours > 0) {
    $duration_text .= $hours . ' hour' . ($hours > 1 ? 's' : '');
}
if ($minutes > 0) {
    $duration_text .= ($hours > 0 ? ' and ' : '') . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
}

// Generate the receipt date
$receipt_date = date('F d, Y - h:i A');

// Determine how to display user name based on available columns
if (isset($booking['first_name']) && isset($booking['last_name'])) {
    $user_name = $booking['first_name'] . ' ' . $booking['last_name'];
} else if (isset($booking['user_name'])) {
    $user_name = $booking['user_name'];
} else {
    $user_name = 'User #' . $booking['user_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h2><i class="fas fa-receipt"></i> Booking Receipt</h2>
            <div class="receipt-actions">
                <button id="printReceiptBtn" class="action-btn print-btn">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button id="downloadPdfBtn" class="action-btn pdf-btn">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="/subdisystem/view_faci.php" class="action-btn">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        </div>
        
        <div class="receipt-content" id="receipt-printable">
            <div class="receipt-branding">
                <h1>FACILITY BOOKING RECEIPT</h1>
                <p class="receipt-subtitle">SubdiSystem Booking Management</p>
            </div>
            
            <div class="receipt-details">
                <div class="receipt-info">
                    <table class="receipt-info-table">
                        <tr>
                            <th>Receipt Number:</th>
                            <td><?php echo $receipt_number; ?></td>
                        </tr>
                        <tr>
                            <th>Date Issued:</th>
                            <td><?php echo $receipt_date; ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="receipt-status <?php echo strtolower($booking['status']); ?>">
                                <?php echo ucfirst($booking['status']); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="receipt-user">
                    <h3>Customer Information</h3>
                    <table class="receipt-user-table">
                        <tr>
                            <th>Name:</th>
                            <td><?php echo $user_name; ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo $booking['email']; ?></td>
                        </tr>
                        <tr>
                            <th>User ID:</th>
                            <td><?php echo $booking['user_id']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="receipt-booking">
                <h3>Booking Details</h3>
                <div class="booking-flex">
                    <div class="booking-facility print-hide">
                    <?php echo htmlspecialchars($booking['facility_name']); ?>
                        <h4><?php echo htmlspecialchars($booking['facility_name']); ?></h4>
                    </div>
                    
                    <div class="booking-info">
                        <table class="booking-info-table">
                            <tr>
                                <th>Facility:</th>
                                <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Booking Date:</th>
                                <td><?php echo $formatted_date; ?></td>
                            </tr>
                            <tr>
                                <th>Time Slot:</th>
                                <td><?php echo $start_time . ' - ' . $end_time; ?></td>
                            </tr>
                            <tr>
                                <th>Duration:</th>
                                <td><?php echo $duration_text; ?></td>
                            </tr>
                            <tr>
                                <th>Purpose:</th>
                                <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="receipt-payment">
                <h3>Payment Information</h3>
                <table class="payment-table">
                    <tr>
                        <th>Payment Status:</th>
                        <td><span class="payment-status <?php echo strtolower($booking['payment_status']); ?>">
                            <?php echo ucfirst($booking['payment_status']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Booking Status:</th>
                        <td><span class="booking-status <?php echo strtolower($booking['status']); ?>">
                            <?php echo ucfirst($booking['status']); ?></span>
                        </td>
                    </tr>
                </table>
                <p class="payment-note">Payment details will be provided after booking approval.</p>
            </div>
            
            <div class="receipt-footer">
                <p>Thank you for using our facility booking system!</p>
                <p>For any inquiries, please contact the administration office.</p>
                <p class="receipt-id">Booking ID: <?php echo $booking_id; ?></p>
            </div>
        </div>
    </div>

    <!-- Include jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
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
                const pageHeight = 295;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save(`booking_receipt_<?php echo $receipt_number; ?>.pdf`);
            });
        });
        
        function attachEventListeners() {
            document.getElementById('printReceiptBtn').addEventListener('click', function() {
                window.location.reload();
            });
            
            document.getElementById('downloadPdfBtn').addEventListener('click', function() {
                // The PDF functionality will be reattached
                window.location.reload();
            });
        }
    </script>

    <style>
        /* Receipt Page Styling */
        .receipt-container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            padding: 20px;
            font-family: 'Courier New', Courier, monospace;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .receipt-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .action-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
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
            margin: 0;
            font-size: 18px;
            font-weight: bold;
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
            color:black;
            background-color: white;

        }

        .receipt-info-table td, .receipt-user-table td, .booking-info-table td, .payment-table td {
            padding: 5px;
            border-bottom: 1px dashed #000;
            color:black;
            background-color: white;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
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

            .receipt-actions {
                display: none;
            }

            .receipt-container {
                border: none;
                box-shadow: none;
            }

            .print-hide {
                display: none !important;
            }
        }
    </style>
</body>
</html>
