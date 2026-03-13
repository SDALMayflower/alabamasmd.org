<?php
/**
 * Alabama Mayflower Contact Form Handler
 * Simple SMTP email sender (like WP Mail SMTP)
 * 
 * CONFIGURATION REQUIRED:
 * Update the settings below with your SMTP credentials
 */

// ==========================================
// SMTP CONFIGURATION - UPDATE THESE VALUES
// ==========================================

// Your SMTP server settings
$smtp_host = 'smtp.gmail.com';                      // Gmail SMTP server
$smtp_port = 587;                                   // Port for TLS
$smtp_username = 'alabamamayflowercontact@gmail.com'; // Your Gmail account
$smtp_password = 'nhtpxdnrankymdps';                // App password (spaces removed)
$smtp_encryption = 'tls';                           // TLS encryption

// Where to send form submissions
$to_email = 'governor@alabamasmd.org';              // Recipient email
$from_email = 'alabamamayflowercontact@gmail.com';  // Sender email (same as SMTP)
$from_name = 'Alabama Mayflower Website';           // Display name

// Success/Error redirect pages
$success_url = 'contact-success.html';
$error_url = 'contact-error.html';

// ==========================================
// DO NOT EDIT BELOW THIS LINE
// ==========================================

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: contact_submit.html");
    exit;
}

// Get and sanitize form data
$name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$subject = isset($_POST['subject']) ? strip_tags(trim($_POST['subject'])) : 'Contact Form Submission';
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

// Validate required fields
if (empty($name) || empty($email) || empty($message)) {
    header("Location: " . $error_url . "?error=missing_fields");
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $error_url . "?error=invalid_email");
    exit;
}

// Try to use PHPMailer if available (recommended)
if (file_exists('PHPMailer/PHPMailer.php')) {
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
    require 'PHPMailer/Exception.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_encryption;
        $mail->Port = $smtp_port;
        
        // Email content
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);
        $mail->addReplyTo($email, $name);
        
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = "Name: $name\n";
        $mail->Body .= "Email: $email\n\n";
        $mail->Body .= "Message:\n$message\n";
        
        $mail->send();
        header("Location: " . $success_url);
        exit;
        
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        header("Location: " . $error_url . "?error=send_failed");
        exit;
    }
    
} else {
    // Fallback to basic PHP mail() function
    // NOTE: This may not work on all servers and doesn't use SMTP
    
    $email_subject = "Alabama Mayflower: " . $subject;
    $email_body = "Name: $name\n";
    $email_body .= "Email: $email\n\n";
    $email_body .= "Message:\n$message\n";
    
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (mail($to_email, $email_subject, $email_body, $headers)) {
        header("Location: " . $success_url);
        exit;
    } else {
        header("Location: " . $error_url . "?error=send_failed");
        exit;
    }
}
?>
