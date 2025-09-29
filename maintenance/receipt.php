<?php
include '../includes/header.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if request ID is provided
if (!isset($_GET['id'])) {
    die("Request ID is required.");
}

$request_id = $_GET['id'];

// Fetch request details
$query = "SELECT sr.request_id, sr.category, sr.description, sr.status, sr.created_at, 
                 u.f_name, u.l_name, u.email, u.phone_number 
          FROM service_requests sr 
          JOIN users u ON sr.user_id = u.user_id 
          WHERE sr.request_id = ?";

// Allow admin to access any service request receipt
if ($_SESSION['role'] !== 'admin') {
    $query .= " AND sr.user_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($_SESSION['role'] === 'admin' ? "i" : "ii", $request_id, $_SESSION['user_id']);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    die("Request not found.");
}

// Generate receipt link for notifications and emails
$receipt_link = "http://" . $_SERVER['HTTP_HOST'] . "/subdisystem/maintenance/receipt.php?id=" . $request_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Request Receipt</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .receipt-details {
            margin-bottom: 20px;
        }

        .receipt-info-table, .receipt-user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-info-table th, .receipt-user-table th {
            text-align: left;
            padding: 5px;
            font-weight: bold;
            border-bottom: 1px dashed #000;
            color: black;
            background-color: white;
        }

        .receipt-info-table td, .receipt-user-table td {
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
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h2><i class="fas fa-receipt"></i> Service Request Receipt</h2>
            <div class="receipt-actions">
                <button id="printReceiptBtn" class="action-btn print-btn">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button id="downloadPdfBtn" class="action-btn pdf-btn">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
                <a href="/subdisystem/maintenance/requests.php" class="action-btn">
                    <i class="fas fa-arrow-left"></i> Back to Requests
                </a>
            </div>
        </div>

        <div class="receipt-content" id="receipt-printable">
            <div class="receipt-branding">
                <h1>SUBDISYSTEM SERVICE RECEIPT</h1>
                <p class="receipt-subtitle">SubdiSystem Maintenance Management</p>
            </div>

            <div class="receipt-details">
                <h3>Request Details</h3>
                <table class="receipt-info-table">
                    <tr>
                        <th>Request ID:</th>
                        <td><?= htmlspecialchars($request['request_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?= htmlspecialchars($request['category']); ?></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?= htmlspecialchars($request['description']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><?= htmlspecialchars($request['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td><?= date('F d, Y - h:i A', strtotime($request['created_at'])); ?></td>
                    </tr>
                </table>
            </div>

            <div class="receipt-details">
                <h3>User Details</h3>
                <table class="receipt-user-table">
                    <tr>
                        <th>Name:</th>
                        <td><?= htmlspecialchars($request['f_name'] . ' ' . $request['l_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= htmlspecialchars($request['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td><?= htmlspecialchars($request['phone_number']); ?></td>
                    </tr>
                </table>
            </div>

            <div class="receipt-footer">
                <p>Thank you for using our maintenance request system!</p>
                <p>For any inquiries, please contact the administration office.</p>
                <p class="receipt-id">Request ID: <?= htmlspecialchars($request['request_id']); ?></p>
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

                pdf.save(`service_request_receipt_${<?= $request_id; ?>}.pdf`);
            });
        });
    </script>
</body>
</html>
