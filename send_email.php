<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Composer's autoloader
require  'includes/config.php';

function sendEmail($email, $subject, $message, $sendToAll = false) {
    global $conn;
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'subdisystem@gmail.com';  // Your Gmail
        $mail->Password   = 'tatu bnzw mgmg avgi'; // Use an App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Email Details
        $mail->setFrom('subdisystem@gmail.com', 'System Notification');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        if ($sendToAll) {
            // Fetch Gmail Users in Batches of 50
            $query = "SELECT email FROM users WHERE email LIKE '%@gmail.com' LIMIT 50";
            $result = $conn->query($query);
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $mail->addBCC($row['email']); // Use BCC for privacy
                }
                $mail->send();
                sleep(2); // Prevent being flagged as spam
            } else {
                throw new Exception("No Gmail users found.");
            }
        } else {
            // Fetch the user's email based on the provided email address
            $query = "SELECT email FROM users WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $mail->addAddress($row['email']);
                $mail->send();
            } else {
                throw new Exception("User not found.");
            }
        }

        return "Email(s) sent successfully!";
    } catch (Exception $e) {
        return "Error: " . $mail->ErrorInfo;
    }
}

function sendOTPEmail($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'subdisystem@gmail.com';  // Your Gmail
        $mail->Password   = 'tatu bnzw mgmg avgi'; // Use an App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Email Details
        $mail->setFrom('subdisystem@gmail.com', 'Subdivision Management System');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Registration';
        $mail->Body    = "Your OTP is: <b>$otp</b>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
