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
</head>
<body>
    <div class="container">
        <div class="page-header">
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
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="service-form">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="name">Requester Name:</label>
                            <input type="text" id="name" value="<?php echo htmlspecialchars($user_data['f_name'] . ' ' . $user_data['l_name']); ?>" disabled>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="phone">Contact Number:</label>
                            <input type="text" id="phone" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? 'Not provided'); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="category">Service Category: <span class="required">*</span></label>
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
                            <label for="priority">Priority Level:</label>
                            <select name="priority" id="priority">
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_date">Preferred Service Date (Optional):</label>
                        <input type="date" name="preferred_date" id="preferred_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="description" >Detailed Description: <span class="required">*</span></label>
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
                alert('For true emergencies, please also call our emergency hotline at (123) 456-7890');
            }
        });
    });
    </script>
</body>
</html>

<style>
:root {
    --primary-color: #17a2b8;
    --primary-hover: #138496;
    --secondary-color: #6c757d;
    --dark-bg: #2c2c2c;
    --card-bg: #333333;
    --text-color: #ffffff;
    --error-color: #dc3545;
    --success-color: #28a745;
    --border-radius: 8px;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: var(--text-color);
    background-color: white;
    line-height: 1.6;
}

.container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}



.page-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.page-header p {
    font-size: 1.1rem;
    opacity: 0.8;
}

/* Card Styles */
.card {
    background-color:white;
    border-radius: var(--border-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-body {
    padding: 2rem;
}

/* Forms */
.form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}
.form-group label{  
    margin-bottom: 1.5rem;
    width: 100%;
    color: white;
}
.form-group {
    margin-bottom: 1.5rem;
    width: 100%;
}

.form-group-half {
    flex: 0 0 calc(50% - 20px);
    margin: 0 10px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;

}

select, input, textarea {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--border-radius);
    background: var(--dark-bg);
    color: var(--text-color);
    font-size: 1rem;
    transition: all 0.3s ease;
}

select:focus, input:focus, textarea:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(23, 162, 184, 0.25);
    outline: none;
}

textarea {
    resize: vertical;
    min-height: 120px;
}

.form-hint {
    display: block;
    font-size: 0.85rem;
    margin-top: 0.5rem;
    opacity: 0.7;
}

.required {
    color: var(--error-color);
}

/* Buttons */
.form-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 2rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn i {
    margin-right: 0.5rem;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-hover);
    transform: translateY(-2px);
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.alert i {
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.2);
    border: 1px solid var(--success-color);
    color: #d4edda;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.2);
    border: 1px solid var(--error-color);
    color: #f8d7da;
}

/* Info Card */
.info-card {
    background-color: rgba(249, 249, 249, 0.1);
    border: 1px solid var(--primary-color);
    border-radius: var(--border-radius);
    margin-top: 2rem;
    color:black;
}

.info-card-header {
    background-color: rgba(240, 245, 246, 0.2);
    padding: 1rem;
    border-bottom: 1px solid rgba(23, 162, 184, 0.3);
    font-weight: 600;
    display: flex;
    align-items: center;
}

.info-card-header i {
    margin-right: 0.5rem;
    color: black;
}

.info-card-body {
    padding: 1rem;
}

.info-card-body p {
    margin-top: 0;
    margin-bottom: 0.75rem;
    color: black;
}

.info-card-body a {
    color: var(--primary-color);
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
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 2rem;
    }
}
</style>