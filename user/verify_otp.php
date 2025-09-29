<?php
session_start();
include '../includes/config.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp']);

    // Check if OTP exists in the session
    if (isset($_SESSION['otp']) && isset($_SESSION['otp']['code'])) {
        $stored_otp = $_SESSION['otp']['code'];

        // Compare entered OTP with the stored OTP
        if ($entered_otp === (string)$stored_otp) {
            if (isset($_SESSION['registration_data'])) {
                $registration_data = $_SESSION['registration_data']; // Retrieve user data from session

                // Insert user data into the database
                $query = "INSERT INTO users (f_name, l_name, email, password, phone_number, role, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param(
                    "sssssss",
                    $registration_data['f_name'],
                    $registration_data['l_name'],
                    $registration_data['email'],
                    $registration_data['password_hash'],
                    $registration_data['phone_number'],
                    $registration_data['role'],
                    $registration_data['image_url']
                );

                if ($stmt->execute()) {
                    $success = "Account created successfully! You can now log in.";
                    // Clear session data
                    unset($_SESSION['otp']);
                    unset($_SESSION['registration_data']);
                } else {
                    $error = "Failed to create account. Please try again.";
                }
            } else {
                $error = "Registration data is missing. Please try signing up again.";
            }
        } else {
            $error = "Invalid OTP. Please try again.";
            error_log("Entered OTP: $entered_otp, Expected OTP: $stored_otp"); // Debugging log
        }
    } else {
        $error = "OTP session has expired or is missing. Please request a new OTP.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Subdivision Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Verify OTP</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (empty($success)): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="otp" class="form-label">Enter OTP</label>
                <input type="text" name="otp" id="otp" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
