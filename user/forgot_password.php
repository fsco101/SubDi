<?php
// Start session
session_start();
include '../includes/config.php';
include '../send_email.php';

// Initialize variables
$email = "";
$error = "";
$success = "";

// Function to generate a secure token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    // Get email from form
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists in database
        $sql = "SELECT user_id, email FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Email exists, generate token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing reset requests for this email
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            
            // Create new password reset request
            $insert_sql = "INSERT INTO password_resets (email, token, expiry, created_at) VALUES (?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sss", $email, $token, $expiry);
            $insert_success = $insert_stmt->execute();
            
            // Send email
            if ($insert_success) {
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
                $subject = "Password Reset Request";
                $message = "
                <html>
                <head>
                    <title>Password Reset</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Please click the link below to reset your password:</p>
                    <p><a href='$reset_link'>Reset Password</a></p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                    <p>This link will expire in 1 hour.</p>
                </body>
                </html>
                ";
                
                if (sendEmail($email, $subject, $message)) {
                    $success = "Password reset link has been sent to your email.";
                    $email = ""; // Clear the form
                } else {
                    $error = "Failed to send reset email. Please try again later.";
                }
            } else {
                $error = "Failed to process your request. Please try again later.";
            }
        } else {
            // Don't reveal that email doesn't exist for security reasons
            // Add a small delay to prevent timing attacks
            sleep(1);
            $success = "If your email is registered, you will receive a password reset link.";
            $email = ""; // Clear the form
        }
    }
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF token validation failed
    $error = "Invalid form submission. Please try again.";
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Password recovery page">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .forgot-form {
            max-width: 450px;
            margin: 60px auto;
            background: #fff;
            padding: 35px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #375ad3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        .logo img {
            max-width: 130px;
            height: auto;
        }
        .form-control {
            height: 45px;
            border-radius: 4px;
        }
        .alert {
            border-radius: 4px;
        }
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #4e73df;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #375ad3;
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="forgot-form">
                    <div class="logo">
                        <img src="assets/img/logo.png" alt="Company Logo">
                    </div>
                    <h2 class="text-center mb-4">Reset Your Password</h2>
                    
                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your registered email" required>
                            <small class="form-text text-muted">We'll send a password reset link to this email</small>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
                        </div>
                        <div class="text-center">
                            <a href="login.php" class="back-link">Return to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-KyZXEAg3QhqLMpG8r+Knujsl5/1z8pXjG2y7Ik0n+Mr5p1iKdT5q7h3om6JEuoqi" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
    <script>
        // Auto dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $(".alert").alert('close');
            }, 5000);
        });
    </script>
</body>
</html>