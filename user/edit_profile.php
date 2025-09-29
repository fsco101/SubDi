<?php
ob_start();
include '../includes/header.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user profile
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$error = "";
$success = "";

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $f_name = trim($_POST['f_name']) ?: $user['f_name'];
    $l_name = trim($_POST['l_name']) ?: $user['l_name'];
    $email = trim($_POST['email']) ?: $user['email'];
    $phone_number = trim($_POST['phone_number']) ?: $user['phone_number'];
    $image_url = $user['image_url']; // Default to existing image

    // Check if email is already taken by another user
    $checkEmailQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($checkEmailQuery);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "This email is already in use. Please choose another.";
    } else {
        // Handle Secure Profile Picture Upload
        if (!empty($_FILES['image_url']['name']) && $_FILES['image_url']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $fileType = mime_content_type($_FILES['image_url']['tmp_name']);
            $maxSize = 2 * 1024 * 1024; // 2MB limit

            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Only JPG and PNG are allowed.";
            } elseif ($_FILES['image_url']['size'] > $maxSize) {
                $error = "File too large. Maximum size is 2MB.";
            } else {
                $uploadDir = '../user/uploads/';
                $fileName = uniqid() . "_" . basename($_FILES['image_url']['name']);
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['image_url']['tmp_name'], $targetFile)) {
                    $image_url = '/subdisystem/user/uploads/' . $fileName;
                } else {
                    $error = "Error uploading file.";
                }
            }
        }

        // Update Profile in Database
        if (empty($error)) {
            $update_query = "UPDATE users SET f_name = ?, l_name = ?, email = ?, phone_number = ?, image_url = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssssi", $f_name, $l_name, $email, $phone_number, $image_url, $user_id);

            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
            } else {
                $error = "Error updating profile. Please try again.";
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_password_hash);
    $stmt->fetch();
    $stmt->close();

    // Validate old password
    if (!password_verify($old_password, $current_password_hash)) {
        $error = "Old password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $new_password)) {
        $error = "Password must be at least 8 characters, include 1 uppercase, 1 lowercase, 1 number, and 1 special character.";
    } else {
        // Hash new password and update in DB
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_password_hash, $user_id);

        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Error updating password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="./subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --primary-dark: #2d62aa;
            --secondary-color: #5d6c89;
            --accent-color: #00d2ff;
            --light-bg: #f8f9fa;
            --dark-text: #343a40;
            --light-text: #495057;
            --muted-text: #6c757d;
            --card-border: #dee2e6;
            --card-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        .profile-container {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .profile-card {
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--card-border);
            overflow: hidden;
            background-color: white;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 25px 30px;
            border-bottom: none;
            position: relative;
        }

        .card-header h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8rem;
        }

        .card-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.7rem;
            color: black;
            font-size: 0.95rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid var(--card-border);
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background-color: var(--light-bg);
            border-color: var(--card-border);
            color: var(--secondary-color);
            width: 45px;
            justify-content: center;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .btn {
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border: none;
        }

        .btn-secondary:hover {
            background-color: #4a5568;
            transform: translateY(-2px);
        }

        .section-divider {
            position: relative;
            text-align: center;
            margin: 40px 0;
        }

        .section-divider:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--card-border), transparent);
        }

        .section-divider span {
            position: relative;
            background: white;
            padding: 0 20px;
            color: var(--muted-text);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .profile-preview-container {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .profile-preview {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 8px 20px rgba(58, 123, 213, 0.2);
            margin: 0 auto;
            display: block;
            transition: transform 0.3s ease;
        }

        .profile-preview:hover {
            transform: scale(1.05);
        }

        .upload-overlay {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .profile-preview-container:hover .upload-overlay {
            opacity: 1;
            cursor: pointer;
        }

        .alert {
            border-radius: 10px;
            font-weight: 500;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success);
            border-left: 5px solid var(--success);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger);
            border-left: 5px solid var(--danger);
        }

        .text-muted {
            color: var(--muted-text) !important;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-footer {
            margin-top: 30px;
        }

        /* Custom file input styling */
        .custom-file-upload {
            position: relative;
            cursor: pointer;
        }

        .custom-file-upload input[type="file"] {
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Password strength indicator */
        .password-strength-meter {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-top: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .password-strength-meter div {
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        /* Animation for success message */
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
        }

        .fade-message {
            animation: fadeInOut 5s forwards;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-container {
                margin: 30px auto;
            }
            
            .card-header {
                padding: 20px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .profile-preview {
                width: 120px;
                height: 120px;
            }
            
            .upload-overlay {
                width: 120px;
                height: 120px;
            }
                                 }
            .password-toggle {
            border-radius: 0 10px 10px 0;
            background-color: var (--light-bg);
            border-color: var(--card-border);
            border-left: none;
            color: var(--secondary-color);
            }

            .input-group .form-control:not(:last-child) {
            border-radius: 0;
            }
    </style>
</head>
<body>

<div class="container profile-container">
    <div class="row">
        <div class="col-md-12">
            <div class="card profile-card">
                <div class="card-header text-center">
                    <h2><i class="fas fa-user-edit me-2"></i>Edit Profile</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success fade-message">
                            <i class="fas fa-check-circle me-2"></i><?= $success; ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-preview-container">
                        <?php if (!empty($user['image_url'])): ?>
                            <img src="<?= htmlspecialchars($user['image_url']); ?>" alt="Profile Picture" class="profile-preview" id="profile-preview">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/140" alt="Profile Picture" class="profile-preview" id="profile-preview">
                        <?php endif; ?>
                        <div class="upload-overlay" id="upload-trigger">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="profile-form">
                        <input type="hidden" name="update_profile">
                        <input type="file" name="image_url" id="image-upload" class="d-none" accept="image/*">

                        <div class="row form-section">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="f_name" class="form-control" value="<?= htmlspecialchars($user['f_name']); ?>" placeholder="Your first name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="l_name" class="form-control" value="<?= htmlspecialchars($user['l_name']); ?>" placeholder="Your last name">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" placeholder="Your email address">
                            </div>
                        </div>

                        <div class="form-section">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number']); ?>" placeholder="Your phone number">
                            </div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>

                    <div class="section-divider">
                        <span>Security Settings</span>
                    </div>

                    <form method="POST" id="password-form">
                        <div class="form-section">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="old_password" class="form-control" required placeholder="Enter your current password">
                                
                            </div>
                        </div>

                        <div class="form-section">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="Enter new password">
                                <button class="btn password-toggle" type="button" id="toggle-new-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength-meter mt-2">
                                <div id="password-strength-bar"></div>
                            </div>
                            <small class="text-muted">Must be at least 8 characters with 1 uppercase, 1 lowercase, 1 number, and 1 special character</small>
                        </div>

                        <div class="form-section">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Confirm new password">
                                <button class="btn password-toggle" type="button" id="toggle-confirm-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" name="change_password" class="btn btn-secondary w-100">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Image preview functionality
        const uploadTrigger = document.getElementById('upload-trigger');
        const imageUpload = document.getElementById('image-upload');
        const profilePreview = document.getElementById('profile-preview');
        
        uploadTrigger.addEventListener('click', function() {
            imageUpload.click();
        });
        
        imageUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Password toggle visibility functionality
        const toggleNewPassword = document.getElementById('toggle-new-password');
        const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        toggleNewPassword.addEventListener('click', function() {
            const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            newPasswordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
        
        // Password strength meter
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('password-strength-bar');
        const confirmInput = document.getElementById('confirm_password');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Calculate strength
            if (password.length >= 8) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 12.5;
            if (password.match(/[^A-Za-z0-9]/)) strength += 12.5;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Change color based on strength
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545'; // Red - weak
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#ffc107'; // Yellow - medium
            } else {
                strengthBar.style.backgroundColor = '#28a745'; // Green - strong
            }
        });
        
        // Password match validation
        confirmInput.addEventListener('input', function() {
            if (this.value === passwordInput.value) {
                this.style.borderColor = '#28a745';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });
        
        // Auto hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                successAlert.style.transition = 'opacity 1s';
            }, 5000);
        }
        
        // Initialize Bootstrap components
        const dropdownElements = document.querySelectorAll('.dropdown-toggle');
        dropdownElements.forEach(function (dropdown) {
            new bootstrap.Dropdown(dropdown);
        });
    });
</script>
</body>
</html>
<?php include '../includes/footer.php'; ?>