<?php
include '../includes/header.php';
include '../send_email.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit("Access Denied. Please log in.");
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: adminDashboard.php?error=' . urlencode('Insufficient permissions'));
    exit("Access Denied. Only administrators can approve service requests.");
}

// Service Request Manager Class
class ServiceRequestManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function updateRequestStatus($requestId, $action, $rejectionReason = null) {
        // Determine new status and message based on action
        if ($action === 'approve') {
            $status = 'in-progress';
            $receiptLink = "http://" . $_SERVER['HTTP_HOST'] . "/subdisystem/maintenance/receipt.php?id=$requestId";
            $message = "Your service request #$requestId is now in progress. <a href='$receiptLink'>View Receipt</a>";
            $receiptContent = $this->createReceipt($requestId); // Create receipt when approved
        } elseif ($action === 'reject') {
            $status = 'rejected';
            $message = "Your service request #$requestId has been rejected.";
            if ($rejectionReason) {
                $message .= " <strong>Reason:</strong> $rejectionReason";
                // Log rejection reason for debugging
                error_log("Service request $requestId rejected with reason: $rejectionReason");
            } else {
                error_log("Service request $requestId rejected without reason");
            }
            $receiptLink = null;
            $receiptContent = null;
        } else {
            throw new Exception("Invalid action: $action");
        }
        
        // Get user_id and email for notification
        $userResult = $this->conn->query("SELECT sr.user_id, u.email, sr.category FROM service_requests sr JOIN users u ON sr.user_id = u.user_id WHERE sr.request_id = $requestId");
        if ($userResult->num_rows === 0) {
            throw new Exception("Request ID not found");
        }
        $user = $userResult->fetch_assoc();
        $userId = $user['user_id'];
        $userEmail = $user['email'];
        $serviceCategory = $user['category'];
        
        // Start transaction for consistent updates
        $this->conn->begin_transaction();
        try {
            // Update request status and rejection reason if provided
            $currentTime = date('Y-m-d H:i:s');
            
            if ($action === 'reject' && $rejectionReason) {
                $updateQuery = "UPDATE service_requests SET status = ?, rejection_reason = ? WHERE request_id = ?";
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->bind_param("ssi", $status, $rejectionReason, $requestId);
                error_log("Executing service request update with rejection reason and timestamp: $currentTime");
            } else {
                $updateQuery = "UPDATE service_requests SET status = ? WHERE request_id = ?";
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->bind_param("si", $status, $requestId);
                error_log("Executing service request update with timestamp: $currentTime");
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating request: " . $stmt->error);
            }
            
            // Verify the update was successful
            if ($stmt->affected_rows === 0) {
                throw new Exception("Request ID not found or no changes made");
            }
            
            // Create notification
            $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) 
                          VALUES (?, ?, 'service_request', ?, NOW())";
            $stmt = $this->conn->prepare($notifQuery);
            $stmt->bind_param("iis", $userId, $requestId, $message);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating notification: " . $stmt->error);
            }
            
            // Send email notification with subject including service category and request ID
            $subject = $action === 'approve' ? 
                "Service Request #$requestId ($serviceCategory) Approved" : 
                "Service Request #$requestId ($serviceCategory) Rejected";
                
            sendEmail($userEmail, $subject, $message);

            // If approved, send receipt copy to admin
            if ($action === 'approve' && $receiptContent) {
                // Send to admin only - user already got the link in their notification
                $adminEmail = "subdisystem@gmail.com"; // Replace with actual admin email
                sendEmail($adminEmail, "Service Request Receipt for Request #$requestId", 
                          "A service request has been approved. <a href='$receiptLink'>View Receipt</a>");
            }
            
            // Commit transaction
            $this->conn->commit();
            return $action === 'approve' ? $receiptContent : true;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function getUserIdFromRequest($requestId) {
        $query = "SELECT user_id FROM service_requests WHERE request_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $request = $result->fetch_assoc();
        return $request['user_id'];
    }
    
    public function getPendingRequests() {
        $query = "SELECT sr.request_id, sr.category, sr.description, sr.status, sr.priority, 
                    DATE_FORMAT(sr.created_at, '%M %d, %Y at %h:%i %p') as formatted_date,
                    sr.created_at, u.f_name, u.l_name, u.email, u.phone_number 
                FROM service_requests sr 
                JOIN users u ON sr.user_id = u.user_id 
                WHERE sr.status = 'pending'
                ORDER BY sr.created_at DESC";
                
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception("Error fetching requests: " . $this->conn->error);
        }
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function createReceipt($requestId) {
        // Get user and request details
        $query = "SELECT sr.request_id, sr.category, sr.description, sr.priority, sr.created_at, u.f_name, u.l_name, u.email, u.phone_number 
                  FROM service_requests sr 
                  JOIN users u ON sr.user_id = u.user_id 
                  WHERE sr.request_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Request ID not found");
        }
        
        $request = $result->fetch_assoc();
        
        // Ensure receipts directory exists
        $receiptsDir = "../receipts";
        if (!is_dir($receiptsDir)) {
            mkdir($receiptsDir, 0777, true);
        }
        
        // Generate HTML receipt content
        $receiptHtml = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Receipt for Service Request #' . $request['request_id'] . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #4CAF50;
                }
                .receipt-header h1 {
                    color: #4CAF50;
                    margin-bottom: 5px;
                }
                .receipt-id {
                    font-size: 18px;
                    color: #666;
                }
                .section {
                    margin-bottom: 25px;
                }
                .section-title {
                    font-weight: bold;
                    background-color: #f5f5f5;
                    padding: 8px;
                    margin-bottom: 10px;
                }
                .row {
                    display: flex;
                    margin-bottom: 10px;
                }
                .label {
                    width: 150px;
                    font-weight: bold;
                }
                .value {
                    flex: 1;
                }
                .description {
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-radius: 5px;
                    white-space: pre-wrap;
                }
                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-style: italic;
                    color: #666;
                    border-top: 1px solid #ddd;
                    padding-top: 20px;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .no-print {
                        display: none;
                    }
                }
                .priority-normal { color: green; }
                .priority-urgent { color: yellow; }
                .priority-emergency { color: orange; }
            </style>
        </head>
        <body>
            <div class="receipt-header">
                <h1>Service Request Receipt</h1>
                <div class="receipt-id">Request #' . $request['request_id'] . '</div>
            </div>
            
            <div class="section">
                <div class="section-title">Request Details</div>
                <div class="row">
                    <div class="label">Category:</div>
                    <div class="value">' . htmlspecialchars($request['category']) . '</div>
                </div>
                <div class="row">
                    <div class="label">Date Submitted:</div>
                    <div class="value">' . date('F d, Y \a\t h:i A', strtotime($request['created_at'])) . '</div>
                </div>
                <div class="row">
                    <div class="label">Date Approved:</div>
                    <div class="value">' . date('F d, Y \a\t h:i A') . '</div>
                </div>
                <div class="row">
                    <div class="label">Priority:</div>
                    <div class="value priority-' . strtolower($request['priority']) . '">' . htmlspecialchars($request['priority']) . '</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Customer Information</div>
                <div class="row">
                    <div class="label">Name:</div>
                    <div class="value">' . htmlspecialchars($request['f_name'] . ' ' . $request['l_name']) . '</div>
                </div>
                <div class="row">
                    <div class="label">Email:</div>
                    <div class="value">' . htmlspecialchars($request['email']) . '</div>
                </div>
                <div class="row">
                    <div class="label">Phone:</div>
                    <div class="value">' . htmlspecialchars($request['phone_number']) . '</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Service Description</div>
                <div class="description">' . nl2br(htmlspecialchars($request['description'])) . '</div>
            </div>
            
            <div class="footer">
                <p>Thank you for using our service.</p>
                <p>This receipt was generated on ' . date('F d, Y \a\t h:i A') . '</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print();" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Print Receipt
                </button>
            </div>
        </body>
        </html>';
        
        // Save HTML to file
        $receiptFile = "$receiptsDir/receipt_{$request['request_id']}.html";
        file_put_contents($receiptFile, $receiptHtml);
        
        // Return the receipt data for display
        return [
            'request_id' => $request['request_id'],
            'category' => $request['category'],
            'description' => $request['description'],
            'name' => $request['f_name'] . ' ' . $request['l_name'],
            'email' => $request['email'],
            'phone' => $request['phone_number'],
            'date_submitted' => $request['created_at'],
            'date_approved' => date('Y-m-d H:i:s'),
            'priority' => $request['priority'],
            'receipt_path' => "receipts/receipt_{$request['request_id']}.html"
        ];
    }
}

// Handle form submission
$serviceManager = new ServiceRequestManager($conn);
$statusMessage = '';
$alertType = '';
$receiptData = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    try {
        $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $rejectionReason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING);
        
        if (!$requestId) {
            throw new Exception("Invalid request ID");
        }
        
        $result = $serviceManager->updateRequestStatus($requestId, $action, $rejectionReason);
        
        if ($action === 'approve' && is_array($result)) {
            $receiptData = $result;
        }
        
        $statusMessage = "Service request #{$requestId} has been " . 
                         ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
        if ($action === 'reject' && $rejectionReason) {
            $statusMessage .= " Rejection reason: " . $rejectionReason;
        }
        $alertType = 'success';
        
    } catch (Exception $e) {
        $statusMessage = "Error: " . $e->getMessage();
        $alertType = 'danger';
    }
}

// Get pending requests
try {
    $requests = $serviceManager->getPendingRequests();
} catch (Exception $e) {
    $statusMessage = "Error fetching requests: " . $e->getMessage();
    $alertType = 'danger';
    $requests = [];
}

// Add this right after handling the form submission where we set $receiptData
if ($receiptData) {
    // Show receipt modal via JavaScript
    echo "<script>var receiptData = " . json_encode($receiptData) . ";</script>";
}

// Set page title
$pageTitle = "Admin - Service Request Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="./subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Add Font Awesome for better icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        
        .badge-in-progress {
            background-color: #0d6efd;
            color: #fff;
        }
        
        .badge-completed {
            background-color: #198754;
            color: #fff;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        .priority-normal { background-color: green; color: white; }
        .priority-urgent { background-color: yellow; color: black; }
        .priority-emergency { background-color: orange; color: white; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-tools"></i> Service Request Management</h2>
                <p class="text-muted">Review and process pending service requests</p>
            </div>
            <div class="col-auto">
                <a href="/subdisystem/adminDashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($statusMessage): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
                <?= $statusMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pending Requests</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($requests) ?></span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <h5>All caught up!</h5>
                        <p class="text-muted">There are no pending service requests to review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Priority</th>
                                    <th>Requester</th>
                                    <th>Contact</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($request['request_id']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($request['category']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="request-description">
                                                <?= htmlspecialchars($request['description']); ?>
                                            </div>

                                        </td>
                                        <td>
                                            <span class="badge priority-<?= strtolower($request['priority']); ?>">
                                                <?= htmlspecialchars($request['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($request['f_name'] . ' ' . $request['l_name']); ?>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-envelope fa-sm text-muted me-1"></i> <?= htmlspecialchars($request['email']); ?></div>
                                            <div><i class="fas fa-phone fa-sm text-muted me-1"></i> <?= htmlspecialchars($request['phone_number']); ?></div>
                                        </td>
                                        <td>
                                            <span title="<?= htmlspecialchars($request['formatted_date']); ?>">
                                                <?= htmlspecialchars($request['formatted_date']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                           <form method="post" class="d-inline-block">
                                                <input type="hidden" name="request_id" value="<?= $request['request_id']; ?>">
                                                <button type="submit" name="action" value="approve" 
                                                        class="btn btn-success btn-sm" 
                                                        data-bs-toggle="tooltip" title="Move to In-Progress"
                                                        onclick="return confirm('Approve request #<?= $request['request_id']; ?>? This will notify the requester.')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                           </form>

                                           <form method="post" class="d-inline-block ms-1">
                                                <input type="hidden" name="request_id" value="<?= $request['request_id']; ?>">
                                                <button type="submit" name="action" value="reject" 
                                                        class="btn btn-outline-danger btn-sm"
                                                        data-bs-toggle="tooltip" title="Mark as Rejected"
                                                        onclick="return confirm('Reject request #<?= $request['request_id']; ?>? This will notify the requester.')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    
                                    <!-- Description Modal -->
                                    <div class="modal fade description-modal" id="descriptionModal<?= $request['request_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Request #<?= htmlspecialchars($request['request_id']); ?> Details
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>Category:</strong> 
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($request['category']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Requested by:</strong> 
                                                        <?= htmlspecialchars($request['f_name'] . ' ' . $request['l_name']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Contact:</strong>
                                                        <div><?= htmlspecialchars($request['email']); ?></div>
                                                        <div><?= htmlspecialchars($request['phone_number']); ?></div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Date Submitted:</strong> 
                                                        <?= htmlspecialchars($request['formatted_date']); ?>
                                                    </div>
                                                    <div>
                                                        <strong>Description:</strong>
                                                        <p class="mt-2 p-3 bg-light rounded">
                                                            <?= nl2br(htmlspecialchars($request['description'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="post" class="me-auto">
                                                        <input type="hidden" name="request_id" value="<?= $request['request_id']; ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <form method="post">
                                                        <input type="hidden" name="request_id" value="<?= $request['request_id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt"></i> Service Request Receipt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <div class="d-flex justify-content-between">
                                <h5 class="mb-0">Request #<span id="receipt-id"></span></h5>
                                <div>
                                    <span class="badge bg-light text-dark" id="receipt-date"></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Customer Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <span id="receipt-name"></span></p>
                                    <p class="mb-1"><strong>Email:</strong> <span id="receipt-email"></span></p>
                                    <p><strong>Phone:</strong> <span id="receipt-phone"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Service Details</h6>
                                    <p class="mb-1"><strong>Category:</strong> <span id="receipt-category"></span></p>
                                    <p class="mb-1"><strong>Date Submitted:</strong> <span id="receipt-date-submitted"></span></p>
                                    <p><strong>Date Approved:</strong> <span id="receipt-date-approved"></span></p>
                                    <p class="mb-1"><strong>Priority:</strong> <span id="receipt-priority"></span></p>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h6 class="text-muted">Description</h6>
                                <p id="receipt-description"></p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="text-center">
                                <a id="view-receipt" href="#" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> View Full Receipt
                                </a>
                                <a id="print-receipt" href="#" class="btn btn-success ms-2" onclick="printReceipt(); return false;">
                                    <i class="fas fa-print"></i> Print Receipt
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionReasonModal" tabindex="-1" aria-labelledby="rejectionReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectionReasonModalLabel">
                        <i class="fas fa-times-circle text-danger"></i> Provide Rejection Reason
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectionForm">
                        <input type="hidden" id="rejection_request_id" name="request_id">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="mb-3">
                            <label class="form-label">Select a reason for rejection:</label>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason1" value="Service not available at this time">
                                <label class="form-check-label" for="reason1">Service not available at this time</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason2" value="Insufficient information provided">
                                <label class="form-check-label" for="reason2">Insufficient information provided</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason3" value="Request out of scope">
                                <label class="form-check-label" for="reason3">Request out of scope</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reason4" value="Duplicate request">
                                <label class="form-check-label" for="reason4">Duplicate request</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rejection_reason" id="reasonOther" value="other">
                                <label class="form-check-label" for="reasonOther">Other reason</label>
                            </div>
                            
                            <div class="mt-3" id="customReasonContainer" style="display: none;">
                                <label for="customReason" class="form-label">Specify reason:</label>
                                <textarea class="form-control" id="customReason" rows="3" placeholder="Please provide details about why this service request is being rejected."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="submitRejection">
                        <i class="fas fa-times"></i> Submit Rejection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Show receipt modal if receipt data exists
            if (typeof receiptData !== 'undefined') {
                // Populate receipt data
                document.getElementById('receipt-id').textContent = receiptData.request_id;
                document.getElementById('receipt-name').textContent = receiptData.name;
                document.getElementById('receipt-email').textContent = receiptData.email;
                document.getElementById('receipt-phone').textContent = receiptData.phone;
                document.getElementById('receipt-category').textContent = receiptData.category;
                document.getElementById('receipt-description').textContent = receiptData.description;
                document.getElementById('receipt-priority').textContent = receiptData.priority;
                document.getElementById('receipt-priority').className = 'priority-' + receiptData.priority.toLowerCase();
                
                // Format dates
                const dateSubmitted = new Date(receiptData.date_submitted);
                const dateApproved = new Date(receiptData.date_approved);
                document.getElementById('receipt-date-submitted').textContent = dateSubmitted.toLocaleString();
                document.getElementById('receipt-date-approved').textContent = dateApproved.toLocaleString();
                document.getElementById('receipt-date').textContent = dateApproved.toLocaleDateString();
                
                // Set view link
                const receiptPath = "../" + receiptData.receipt_path;
                document.getElementById('view-receipt').href = receiptPath;
                
                // Show the modal
                var receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                receiptModal.show();
            }
            
            // Function to print receipt
            window.printReceipt = function() {
                const receiptWindow = window.open(document.getElementById('view-receipt').href, '_blank');
                receiptWindow.onload = function() {
                    receiptWindow.print();
                }
            }
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Replace the direct form submission with a click handler that shows the modal for rejections
            document.querySelectorAll('button[name="action"][value="reject"]').forEach(button => {
                const originalOnClick = button.onclick;
                button.onclick = function(e) {
                    e.preventDefault();
                    const requestId = this.form.querySelector('input[name="request_id"]').value;
                    showRejectionModal(requestId);
                    return false;
                };
            });

            function showRejectionModal(requestId) {
                document.getElementById('rejection_request_id').value = requestId;
                const modal = new bootstrap.Modal(document.getElementById('rejectionReasonModal'));
                modal.show();
            }

            // Handle custom reason toggle
            document.querySelectorAll('input[name="rejection_reason"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const customContainer = document.getElementById('customReasonContainer');
                    customContainer.style.display = this.value === 'other' ? 'block' : 'none';
                });
            });

            // Handle rejection form submission
            document.getElementById('submitRejection').addEventListener('click', function() {
                const requestId = document.getElementById('rejection_request_id').value;
                const selectedReason = document.querySelector('input[name="rejection_reason"]:checked');
                
                if (!selectedReason) {
                    alert("Please select a rejection reason");
                    return;
                }
                
                let rejectionReason = selectedReason.value;
                
                // If "Other" is selected, get the text from textarea
                if (rejectionReason === 'other') {
                    const customReason = document.getElementById('customReason').value.trim();
                    if (!customReason) {
                        alert("Please provide a specific reason for rejection");
                        return;
                    }
                    rejectionReason = customReason;
                }
                
                // Show loading message
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'alert alert-info text-center';
                loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing rejection...';
                document.querySelector('.modal-body').appendChild(loadingDiv);
                
                // Disable the submit button to prevent double submission
                document.getElementById('submitRejection').disabled = true;
                
                // Close the modal
                bootstrap.Modal.getInstance(document.getElementById('rejectionReasonModal')).hide();
                
                // Create and submit the form
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = requestId;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reject';
                
                const reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'rejection_reason';
                reasonInput.value = rejectionReason;
                
                form.appendChild(requestIdInput);
                form.appendChild(actionInput);
                form.appendChild(reasonInput);
                
                document.body.appendChild(form);
                form.submit();
            });
            
            // Show receipt modal if receipt data exists
            if (typeof receiptData !== 'undefined') {
                // ...existing code...
            }
            
            // Function to print receipt
            window.printReceipt = function() {
                // ...existing code...
            }
        });
    </script>
</body>
</html>
