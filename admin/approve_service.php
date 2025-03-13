<?php
include '../includes/header.php';


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
    
    public function updateRequestStatus($requestId, $action) {
        // Determine new status and message based on action
        if ($action === 'approve') {
            $status = 'in-progress';
            $message = "Your service request #$requestId is **now in progress**.";
        } elseif ($action === 'reject') {
            $status = 'rejected'; // Changed from 'completed' to 'rejected' for clarity
            $message = "Your service request #$requestId has been **rejected**.";
        } else {
            throw new Exception("Invalid action: $action");
        }
        
        // Get user_id for notification
        $userId = $this->getUserIdFromRequest($requestId);
        if (!$userId) {
            throw new Exception("Request ID not found");
        }
        
        // Start transaction for consistent updates
        $this->conn->begin_transaction();
        
        try {
            // Update request status
            $updateQuery = "UPDATE service_requests SET status = ?, created_at = NOW() WHERE request_id = ?";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param("si", $status, $requestId);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating request: " . $stmt->error);
            }
            
            // Create notification
            $notifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, created_at) 
                          VALUES (?, ?, 'service_request', ?, NOW())";
            $stmt = $this->conn->prepare($notifQuery);
            $stmt->bind_param("iis", $userId, $requestId, $message);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating notification: " . $stmt->error);
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
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
        $query = "SELECT sr.request_id, sr.category, sr.description, sr.status, 
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
}

// Handle form submission
$serviceManager = new ServiceRequestManager($conn);
$statusMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    try {
        $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        
        if (!$requestId) {
            throw new Exception("Invalid request ID");
        }
        
        $serviceManager->updateRequestStatus($requestId, $action);
        
        $statusMessage = "Service request #{$requestId} has been " . 
                         ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
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

// Set page title
$pageTitle = "Admin - Service Request Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>