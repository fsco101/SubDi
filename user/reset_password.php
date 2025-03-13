<?php

session_start();
include '../includes/config.php';

// Initialize variables
$token = "";
$email = "";
$error = "";
$success = "";
$showForm = true;

// Function to log activity securely
function logActivity($email, $action, $conn) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_sql = "INSERT INTO security_logs (user_email, action, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("ssss", $email, $action, $ip, $user_agent);
    return $log_stmt->execute();
}

// CSRF protection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
        $showForm = false;
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Check if token and email are provided in URL
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['token']) && isset($_GET['email'])) {
    $token = trim($_GET['token']);
    $email = trim($_GET['email']);
    
    // Validate token and email in database
    $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = "Invalid or expired reset link. Please request a new one.";
        $showForm = false;
        logActivity($email, 'invalid_reset_attempt', $conn);
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    // Handle form submission
    $token = trim($_POST['token']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($password) || empty($confirm_password)) {
        $error = "Please enter and confirm your new password.";
    } elseif (strlen($password) < 10) {
        $error = "Password must be at least 10 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check token validity
        $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expiry > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Hash the new password with appropriate cost factor
            $password_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
            
            // Begin transaction
            $conn->begin_transaction();
            try {
                // Update user's password
                $update_sql = "UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE email = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $password_hash, $email);
                $update_result = $update_stmt->execute();
                
                if ($update_result) {
                    // Delete the used token
                    $delete_sql = "DELETE FROM password_resets WHERE email = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("s", $email);
                    $delete_stmt->execute();
                    
                    // Log the password reset
                    logActivity($email, 'password_reset_successful', $conn);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "Your password has been successfully reset. You can now login with your new password.";
                    $showForm = false;
                    
                    // Force logout from all devices (if using session tokens)
                    $invalidate_sql = "UPDATE user_sessions SET is_valid = 0 WHERE user_email = ?";
                    $invalidate_stmt = $conn->prepare($invalidate_sql);
                    $invalidate_stmt->bind_param("s", $email);
                    $invalidate_stmt->execute();
                } else {
                    throw new Exception("Failed to update password");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Failed to update password. Please try again.";
                logActivity($email, 'password_reset_failed', $conn);
            }
        } else {
            $error = "Invalid or expired reset link. Please request a new one.";
            $showForm = false;
            logActivity($email, 'invalid_reset_attempt', $conn);
        }
    }
} else {
    // No token or email provided
    $error = "Invalid request. Please use the reset link sent to your email.";
    $showForm = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Reset your password securely">
    <title>Reset Your Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 60px;
            color: #333;
        }
        .reset-form {
            max-width: 480px;
            margin: 0 auto 40px;
            background: #fff;
            padding: 35px 30px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        .form-group {
            margin-bottom: 24px;
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
            height: 48px;
            border-radius: 4px;
            border: 1px solid #ddd;
            padding-left: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .alert {
            border-radius: 4px;
            padding: 15px;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .requirement {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .requirement i {
            margin-right: 5px;
            font-size: 14px;
        }
        .valid {
            color: #28a745;
        }
        .invalid {
            color: #6c757d;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 14px;
            cursor: pointer;
            color: #6c757d;
        }
        .input-group {
            position: relative;
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
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="reset-form">
                    <div class="logo">
                        <img src="assets/img/logo.png" alt="Company Logo">
                    </div>
                    <h2 class="text-center mb-4">Reset Your Password</h2>
                    
                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($showForm): ?>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" autocomplete="new-password" required>
                                <span class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="password-requirements mt-2">
                                <div class="requirement" id="length">
                                    <i class="fas fa-circle"></i> At least 10 characters
                                </div>
                                <div class="requirement" id="uppercase">
                                    <i class="fas fa-circle"></i> At least one uppercase letter
                                </div>
                                <div class="requirement" id="lowercase">
                                    <i class="fas fa-circle"></i> At least one lowercase letter
                                </div>
                                <div class="requirement" id="number">
                                    <i class="fas fa-circle"></i> At least one number
                                </div>
                                <div class="requirement" id="special">
                                    <i class="fas fa-circle"></i> At least one special character
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" autocomplete="new-password" required>
                                <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div id="password-match" class="mt-2 text-muted">
                                <i class="fas fa-circle"></i> Passwords match
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-lock mr-2"></i> Reset Password
                            </button>
                        </div>
                    </form>
                    <?php elseif(empty($success)): ?>
                    <div class="text-center mt-4">
                        <a href="forgot_password.php" class="btn btn-primary">
                            <i class="fas fa-envelope mr-2"></i> Request New Reset Link
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-KyZXEAg3QhqLMpG8r+Knujsl5/1z8pXjG2y7Ik0n+Mr5p1iKdT5q7h3om6JEuoqi" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Real-time password validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Validate length
            if (password.length >= 10) {
                document.getElementById('length').classList.add('valid');
                document.getElementById('length').querySelector('i').classList.remove('fa-circle');
                document.getElementById('length').querySelector('i').classList.add('fa-check-circle');
            } else {
                document.getElementById('length').classList.remove('valid');
                document.getElementById('length').querySelector('i').classList.remove('fa-check-circle');
                document.getElementById('length').querySelector('i').classList.add('fa-circle');
            }
            
            // Validate uppercase
            if (/[A-Z]/.test(password)) {
                document.getElementById('uppercase').classList.add('valid');
                document.getElementById('uppercase').querySelector('i').classList.remove('fa-circle');
                document.getElementById('uppercase').querySelector('i').classList.add('fa-check-circle');
            } else {
                document.getElementById('uppercase').classList.remove('valid');
                document.getElementById('uppercase').querySelector('i').classList.remove('fa-check-circle');
                document.getElementById('uppercase').querySelector('i').classList.add('fa-circle');
            }
            
            // Validate lowercase
            if (/[a-z]/.test(password)) {
                document.getElementById('lowercase').classList.add('valid');
                document.getElementById('lowercase').querySelector('i').classList.remove('fa-circle');
                document.getElementById('lowercase').querySelector('i').classList.add('fa-check-circle');
            } else {
                document.getElementById('lowercase').classList.remove('valid');
                document.getElementById('lowercase').querySelector('i').classList.remove('fa-check-circle');
                document.getElementById('lowercase').querySelector('i').classList.add('fa-circle');
            }
            
            // Validate number
            if (/[0-9]/.test(password)) {
                document.getElementById('number').classList.add('valid');
                document.getElementById('number').querySelector('i').classList.remove('fa-circle');
                document.getElementById('number').querySelector('i').classList.add('fa-check-circle');
            } else {
                document.getElementById('number').classList.remove('valid');
                document.getElementById('number').querySelector('i').classList.remove('fa-check-circle');
                document.getElementById('number').querySelector('i').classList.add('fa-circle');
            }
            
            // Validate special character
            if (/[^A-Za-z0-9]/.test(password)) {
                document.getElementById('special').classList.add('valid');
                document.getElementById('special').querySelector('i').classList.remove('fa-circle');
                document.getElementById('special').querySelector('i').classList.add('fa-check-circle');
            } else {
                document.getElementById('special').classList.remove('valid');
                document.getElementById('special').querySelector('i').classList.remove('fa-check-circle');
                document.getElementById('special').querySelector('i').classList.add('fa-circle');
            }
            
            // Check password match
            checkPasswordMatch();
        });
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('password-match');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchElement.classList.add('valid');
                    matchElement.classList.remove('invalid', 'text-muted');
                    matchElement.querySelector('i').classList.remove('fa-circle');
                    matchElement.querySelector('i').classList.add('fa-check-circle');
                } else {
                    matchElement.classList.add('invalid');
                    matchElement.classList.remove('valid', 'text-muted');
                    matchElement.querySelector('i').classList.remove('fa-check-circle');
                    matchElement.querySelector('i').classList.add('fa-times-circle');
                }
            } else {
                matchElement.classList.remove('valid', 'invalid');
                matchElement.classList.add('text-muted');
                matchElement.querySelector('i').classList.remove('fa-check-circle', 'fa-times-circle');
                matchElement.querySelector('i').classList.add('fa-circle');
            }
        }
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Auto dismiss alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $(".alert").alert('close');
            }, 5000);
        });
    </script>
</body>
</html>