<?php
session_start();
include '../includes/config.php';
require '../send_email.php';

$error = "";
$success = "";
$otpSent = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if this is an OTP verification submission
    if (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp']);

        // Check if the OTP session exists
        if (isset($_SESSION['otp']) && isset($_SESSION['otp']['code'])) {
            $stored_otp = $_SESSION['otp']['code'];

            // Compare entered OTP with the stored OTP
            if ($entered_otp === $stored_otp) {
                // Proceed with user registration
                $registration_data = $_SESSION['registration_data'];
                $image_url = "/subdisystem/user/uploads/default_profile.jpg";
                if (isset($_SESSION['uploaded_image']) && !empty($_SESSION['uploaded_image'])) {
                    $image_url = $_SESSION['uploaded_image'];
                }

                $query = "INSERT INTO users (f_name, l_name, email, password_hash, phone_number, role, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param(
                    "sssssss",
                    $registration_data['f_name'],
                    $registration_data['l_name'],
                    $registration_data['email'],
                    $registration_data['password_hash'],
                    $registration_data['phone_number'],
                    $registration_data['role'],
                    $image_url
                );

                if ($stmt->execute()) {
                    // Clear session data
                    unset($_SESSION['otp']);
                    unset($_SESSION['registration_data']);
                    unset($_SESSION['uploaded_image']);
                    unset($_SESSION['last_otp_request']);

                    // Set a success flag to trigger the modal
                    $success = "Account created successfully! Redirecting to login...";
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 5000); // Redirect after 5 seconds
                        });
                    </script>";
                } else {
                    $error = "Failed to create account. Please try again.";
                }
            } else {
                $error = "Invalid OTP. Please try again.";
                error_log("Entered OTP: $entered_otp, Expected OTP: " . $_SESSION['otp']['code']); // Debugging log
                $otpSent = true; // Keep the modal open
            }
        } else {
            $error = "OTP session has expired or is missing. Please request a new OTP.";
            error_log("OTP Session Missing or Expired"); // Debugging log
        }
    }
    // Check if this is a request for a new OTP
    else if (isset($_POST['resend_otp'])) {
        $currentTime = time();
        $lastRequest = isset($_SESSION['last_otp_request']) ? $_SESSION['last_otp_request'] : 0;
        $timeDiff = $currentTime - $lastRequest;
        
        if ($timeDiff < 60) {
            $error = "Please wait " . (60 - $timeDiff) . " seconds before requesting a new OTP.";
            $otpSent = true; // Keep the modal open
        } else {
            $email = $_SESSION['registration_data']['email'];
            $otp = rand(100000, 999999); // Generate a new 6-digit OTP
            $_SESSION['otp'] = [
                'code' => (string)$otp, // Store OTP as a string
                'email' => $email
            ];
            $_SESSION['last_otp_request'] = time();
            
            if (sendOTPEmail($email, $otp)) {
                $success = "A new OTP has been sent to your email.";
                $otpSent = true; // Keep the modal open
            } else {
                $error = "Failed to send OTP. Please try again.";
                $otpSent = true; // Keep the modal open
            }
        }
    }
    // Normal signup form submission
    else {
        $f_name = trim(htmlspecialchars($_POST['f_name']));
        $l_name = trim(htmlspecialchars($_POST['l_name']));
        $email = trim(htmlspecialchars($_POST['email']));
        $password = $_POST['password'];
        $phone_number = trim(htmlspecialchars($_POST['phone_number']));
        $role = "resident";
        
        // Validate Password Strength
        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $error = "Password must be at least 8 characters, include 1 uppercase, 1 lowercase, 1 number, and 1 special character.";
        } else if (!isset($_POST['confirm_password']) || $password !== $_POST['confirm_password']) {
            $error = "Passwords do not match.";
        } else {
            // Check if the email already exists
            $query = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "This email is already registered. Please login or use a different email.";
            } else {
                // Handle profile picture upload
                $image_url = "/subdisystem/user/uploads/default_profile.jpg";
                if (isset($_FILES['uploads']) && $_FILES['uploads']['error'] == 0) {
                    $allowed = array('jpg', 'jpeg', 'png');
                    $filename = $_FILES['uploads']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (in_array(strtolower($ext), $allowed) && $_FILES['uploads']['size'] <= 2000000) {
                        $target_dir = "uploads/";
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $new_filename = uniqid() . "." . $ext;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['uploads']['tmp_name'], $target_file)) {
                            $image_url = "/subdisystem/user/" . $target_file;
                            $_SESSION['uploaded_image'] = $image_url;
                        } else {
                            $error = "Failed to upload image.";
                        }
                    } else {
                        $error = "Invalid file. Only JPG and PNG files under 2MB are allowed.";
                    }
                }
                
                if (empty($error)) {
                    // Generate OTP and store user data
                    $otp = rand(100000, 999999); // Generate a 6-digit OTP
                    $_SESSION['otp'] = [
                        'code' => (string)$otp, // Store OTP as a string
                        'email' => $email
                    ];
                    $_SESSION['last_otp_request'] = time();
                    $_SESSION['registration_data'] = [
                        'f_name' => $f_name,
                        'l_name' => $l_name,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'phone_number' => $phone_number,
                        'role' => $role,
                        'image_url' => $image_url
                    ];
                    
                    // Send OTP to user's email
                    if (sendOTPEmail($email, $otp)) {
                        $otpSent = true; // Show OTP modal
                        error_log("OTP sent to email: $otp"); // Debugging log
                    } else {
                        $error = "Failed to send OTP. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Subdivision Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

</head>
<body>
<!-- Add this at the top of your body tag -->
<video autoplay muted loop id="background-video">
    <source src="/subdisystem/dashboard image/background.mp4" type="video/mp4">
    Your browser does not support the video tag.
</video>

<div class="signup-card">
    <div class="signup-header">
        <h2><i class="bi bi-person-plus"></i> Subdivision Sign Up</h2>
    </div>
    
    <div class="signup-body">
        <?php if (!empty($error) && !$otpSent): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle"></i> <?= $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success) && !$otpSent): ?>
            <div class="success-message">
                <i class="bi bi-check-circle"></i> <?= $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <label for="f_name" class="form-label">First Name</label>
                        <div class="position-relative">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="f_name" id="f_name" class="form-control icon-input" required placeholder="Enter first name" value="<?= htmlspecialchars($_POST['f_name'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <label for="l_name" class="form-label">Last Name</label>
                        <div class="position-relative">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="l_name" id="l_name" class="form-control icon-input" required placeholder="Enter last name" value="<?= htmlspecialchars($_POST['l_name'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="position-relative">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" id="email" class="form-control icon-input" required placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="input-group">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="form-control icon-input" required placeholder="Create a password">
                    <i id="togglePassword" class="bi bi-eye-slash position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                </div>
                <div class="form-text">
                    Password must have at least 8 characters, 1 uppercase, 1 lowercase, 1 number, and 1 special character.
                </div>
            </div>

            <div class="input-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="position-relative">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control icon-input" required placeholder="Confirm your password">
                    <i id="toggleConfirmPassword" class="bi bi-eye-slash position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></i>
                </div>
            </div>

            <div class="input-group">
                <label for="phone_number" class="form-label">Phone Number</label>
                <div class="position-relative">
                    <i class="bi bi-telephone input-icon"></i>
                    <input type="text" name="phone_number" id="phone_number" class="form-control icon-input" placeholder="Enter phone number" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                </div>
            </div>

            <input type="hidden" name="role" value="resident">

            <div class="input-group">
                <label for="uploads" class="form-label">Profile Picture</label>
                <div class="position-relative">
                    <i class="bi bi-image input-icon"></i>
                    <input type="file" name="uploads" id="uploads" class="form-control icon-input" accept="image/*">
                </div>
                <div class="form-text">
                    Only JPG and PNG files are allowed (Max size: 2MB)
                </div>
            </div>

            <button type="submit" class="btn btn-signup">
                <i class="bi bi-check-circle"></i> Create Account
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Login
        </a>
    </div>
</div>

<!-- OTP Verification Modal -->
<div class="modal fade <?= $otpSent ? 'show' : '' ?>" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true" style="<?= $otpSent ? 'display: block;' : 'display: none;' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="otpModalLabel">Verify Your Email</h5>
                <?php if (empty($success) || $otpSent): ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php endif; ?>
            </div>
            <div class="modal-body">
                <?php if (!empty($error) && $otpSent): ?>
                    <div class="alert alert-danger">
                        <?= $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success) && $otpSent): ?>
                    <div class="alert alert-success">
                        <?= $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($success) || $otpSent): ?>
                <p>We've sent a 6-digit OTP to your email address. Please enter it below to verify your account.</p>
                <form method="POST">
                    <div class="mb-3">
                        <label for="otp" class="form-label">Enter OTP</label>
                        <input type="text" class="form-control" id="otp" name="otp" required>
                        <input type="hidden" name="verify_otp" value="1">
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">Verify</button>
                        <div class="resend-section">
                            <form method="POST" id="resendForm" class="d-inline">
                                <input type="hidden" name="resend_otp" value="1">
                                <button type="submit" id="resendBtn" class="btn btn-link">Resend OTP</button>
                            </form>
                            <span id="countdown" class="text-muted"></span>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Account Created</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Your account has been created successfully! You will be redirected to the login page shortly.</p>
            </div>
            <div class="modal-footer">
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Backdrop -->
<?php if ($otpSent): ?>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });

    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });

    // Resend OTP countdown logic
    let lastRequestTime = <?= isset($_SESSION['last_otp_request']) ? $_SESSION['last_otp_request'] : 0 ?>;
    let currentTime = Math.floor(Date.now() / 1000);
    let remainingTime = Math.max(0, (lastRequestTime + 60) - currentTime);
    const countdownEl = document.getElementById('countdown');
    const resendBtn = document.getElementById('resendBtn');
    
    if (remainingTime > 0) {
        resendBtn.disabled = true;
        updateCountdown();
        let countdownInterval = setInterval(updateCountdown, 1000);
        
        function updateCountdown() {
            if (remainingTime <= 0) {
                clearInterval(countdownInterval);
                resendBtn.disabled = false;
                countdownEl.textContent = '';
                return;
            }
            
            countdownEl.textContent = `(${remainingTime}s)`;
            remainingTime--;
        }
    }

    // Close modal functionality
    const closeModalButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.getElementById('otpModal');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) backdrop.remove();
        });
    });
});
</script>

</body>
</html>

<style>
        #background-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -2;
            object-fit: cover;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin: 0;
            position: relative;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(6px);
            z-index: -1;
        }
        
        .signup-card {
            width: 100%;
            max-width: 520px;
            background: rgba(30, 30, 30, 0.85);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            position: relative;
            margin: 20px auto;
        }
        
        .signup-header {
            background: rgba(23, 162, 184, 0.9);
            padding: 25px 30px;
            text-align: center;
            color: white;
        }
        
        .signup-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .signup-body {
            padding: 30px;
            color: #e1e1e1;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 15px;
            height: auto;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #17a2b8;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.25);
            color: white;
        }
        
        .form-control::placeholder, .form-select::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        
        .form-select option {
            background-color: #333;
            color: white;
        }
        
        .btn-signup {
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-signup:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .error-message {
            background: rgba(255, 82, 82, 0.2);
            color: #ff5252;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .success-message {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 15px;
        }
        
        .position-relative {
            position: relative;
            width: 100%;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1rem;
            z-index: 10;
        }
        
        .icon-input {
            padding-left: 45px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #17a2b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: white;
            text-decoration: underline;
        }
        
        .form-text {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            margin-top: -12px;
            margin-bottom: 15px;
        }
    
    /* Modal Styling */
    .modal-content {
        background: rgba(30, 30, 30, 0.95);
        color: white;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 20px;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-title {
        color: #17a2b8;
        font-weight: 600;
    }
    
    .btn-close {
        color: white;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
        opacity: 0.7;
    }
    
    .btn-close:hover {
        opacity: 1;
    }
    
    .resend-section {
        display: flex;
        align-items: center;
    }
    
    #countdown {
        margin-left: 5px;
        min-width: 40px;
    }
    
    .btn-link {
        color: #17a2b8;
        text-decoration: none;
        padding: 0.375rem 0.5rem;
    }
    
    .btn-link:hover {
        color: #0f7585;
        text-decoration: underline;
    }
    
    .btn-link:disabled {
        color: #6c757d;
        pointer-events: none;
    }
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.8);
    }
</style>