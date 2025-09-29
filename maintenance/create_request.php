<?php
ob_start();
include '../includes/header.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $preferred_date = !empty($_POST['preferred_date']) ? $_POST['preferred_date'] : NULL;
    
    // Validate inputs
    if (empty($category) || empty($description)) {
        $error_message = "All required fields must be filled out.";
    } else {
        // Check request limits
        $current_date = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));

        // Check active requests
        $active_request_query = "SELECT COUNT(*) AS active_count FROM service_requests WHERE user_id = ? AND status IN ('pending', 'in_progress')";
        $active_request_stmt = $conn->prepare($active_request_query);
        $active_request_stmt->bind_param("i", $user_id);
        $active_request_stmt->execute();
        $active_request_result = $active_request_stmt->get_result();
        $active_request_data = $active_request_result->fetch_assoc();

        if ($active_request_data['active_count'] > 0) {
            $error_message = "You already have an active request. Please wait until it is resolved before submitting a new one.";
        } else {
            // Check weekly request count
            $weekly_request_query = "SELECT COUNT(*) AS weekly_count FROM service_requests WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?";
            $weekly_request_stmt = $conn->prepare($weekly_request_query);
            $weekly_request_stmt->bind_param("iss", $user_id, $week_start, $week_end);
            $weekly_request_stmt->execute();
            $weekly_request_result = $weekly_request_stmt->get_result();
            $weekly_request_data = $weekly_request_result->fetch_assoc();

            if ($weekly_request_data['weekly_count'] >= 3) {
                $error_message = "You have reached the limit of 3 requests for this week.";
            } else {
                // Proceed with request creation
                // Insert the service request
                $query = "INSERT INTO service_requests (user_id, category, description, priority, preferred_date, status) 
                          VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("issss", $user_id, $category, $description, $priority, $preferred_date);
                
                if ($stmt->execute()) {
                    // Get the new request ID
                    $request_id = $stmt->insert_id;
                    
                    // Add notification for admin users
                    $notificationQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, is_read) 
                                        SELECT user_id, ?, 'service_request', 'New service request submitted', FALSE 
                                        FROM users WHERE role = 'admin'";
                    $notifStmt = $conn->prepare($notificationQuery);
                    $notifStmt->bind_param("i", $request_id);
                    $notifStmt->execute();
                    
                    // Create notification for the user
                    $user_notification_message = "Your service request #" . $request_id . " has been submitted successfully.";
                    $userNotifQuery = "INSERT INTO notifications (user_id, related_id, related_type, message, is_read) 
                                    VALUES (?, ?, 'service_request', ?, FALSE)";
                    $userNotifStmt = $conn->prepare($userNotifQuery);
                    $userNotifStmt->bind_param("iis", $user_id, $request_id, $user_notification_message);
                    $userNotifStmt->execute();
                    
                    $success_message = "Request submitted successfully! You will be redirected shortly.";
                    header("refresh:2;url=view_requests.php");
                } else {
                    $error_message = "Error submitting request: " . $conn->error;
                }
            }
        }
    }
}

// Get user data for auto-fill
$user_id = $_SESSION['user_id'];
$user_query = "SELECT f_name, l_name, phone_number FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Service</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            font-family: 'Poppins', sans-serif;
            padding-top: 60px; /* Added padding to account for fixed header if present */
            color: #495057;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 100px auto 30px auto; /* Increased top margin from 30px to 100px */
            padding: 0 15px;
        }

        .page-title {
            text-align: center;
            margin: 0px 0 30px 0; /* Increased top margin from 30px to 80px */
            color: #212529;
            font-weight: 600;
            position: relative;
        }

        .page-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #3b82f6;
            margin: 10px auto;
            border-radius: 2px;
        }

        .page-title h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #2d3748;
        }

        .page-title p {
            font-size: 16px;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Card Styles */
        .card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 25px;
            border-bottom: none;
            text-align: center;
        }

        .card-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header i {
            margin-right: 12px;
            font-size: 24px;
        }

        .card-body {
            padding: 30px 40px;
            background: white;
        }

        /* Form Styles */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px 20px;
        }

        .form-group {
            margin-bottom: 20px;
            width: 100%;
        }

        .form-group-half {
            flex: 0 0 calc(50% - 20px);
            margin: 0 10px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            font-size: 15px;
            margin-bottom: 8px;
            color: #495057;
        }

        select, input, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background-color: #f8f9fa;
            color: #495057;
            font-size: 15px;
            transition: all 0.3s;
        }

        select:focus, input:focus, textarea:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
            background-color: #fff;
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-hint {
            display: block;
            font-size: 13px;
            margin-top: 6px;
            color: #6c757d;
        }

        .required {
            color: #dc3545;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }

        .btn-secondary {
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }

        .alert-success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }

        /* Info Card */
        .info-card {
            background-color: #f8f9fa;
            border-radius: 16px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .info-card-header {
            background-color: #e9ecef;
            padding: 18px 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            color: #495057;
        }

        .info-card-header i {
            margin-right: 10px;
            color: #3b82f6;
        }

        .info-card-body {
            padding: 20px 25px;
            color: #495057;
        }

        .info-card-body p {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .info-card-body a {
            color: #3b82f6;
            text-decoration: none;
        }

        .info-card-body a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }

            .form-group-half {
                flex: 0 0 calc(100% - 20px);
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-tools"></i> Request a Service</h1>
            <p>Fill out the form below to submit a service request to our maintenance team.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-tools"></i> Service Request Form</h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="service-form">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="name" class="form-label">Requester Name:</label>
                            <input type="text" id="name" value="<?php echo htmlspecialchars($user_data['f_name'] . ' ' . $user_data['l_name']); ?>" disabled>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="phone" class="form-label">Contact Number:</label>
                            <input type="text" id="phone" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? 'Not provided'); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="category" class="form-label">Service Category: <span class="required">*</span></label>
                            <select name="category" id="category" required>
                                <option value="">-- Select Category --</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                                <option value="HVAC">HVAC (Heating/Cooling)</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Pest Control">Pest Control</option>
                                <option value="Landscaping">Landscaping</option>
                                <option value="Security">Security</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="priority" class="form-label">Priority Level:</label>
                            <select name="priority" id="priority">
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_date" class="form-label">Preferred Service Date (Optional):</label>
                        <input type="date" name="preferred_date" id="preferred_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Detailed Description: <span class="required">*</span></label>
                        <textarea name="description" id="description" rows="5" placeholder="Please describe your issue in detail..." required></textarea>
                        <span class="form-hint">Include location details, specific problems, and any other relevant information.</span>
                    </div>
                    
                    <div class="form-actions">
                        <a href="view_requests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Requests</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-card-header">
                <i class="fas fa-info-circle"></i> Service Request Information
            </div>
            <div class="info-card-body">
                <p>For emergency situations requiring immediate attention, please call our emergency hotline at <strong>09672688658</strong>.</p>
                <p>Normal service requests are typically processed within 24-48 hours.</p>
                <p>You can view the status of your requests in the <a href="view_requests.php">My Requests</a> section.</p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        document.querySelector('.service-form').addEventListener('submit', function(e) {
            const description = document.getElementById('description').value.trim();
            const category = document.getElementById('category').value;
            
            if (!category || !description) {
                e.preventDefault();
                alert('Please fill out all required fields');
            }
        });
        
        // Show emergency notice when priority is set to Emergency
        document.getElementById('priority').addEventListener('change', function() {
            if (this.value === 'Emergency') {
                alert('For true emergencies, please also call our emergency hotline at 09672688658');
            }
        });
    });




                    /**
                 * Page Action Handler - Global utility for managing page actions and reloads
                 * Add this to all PHP files to ensure consistent user experience
                 */
 
    </script>
</body>
</html>
<?php include '../includes/footer.php'; ?>