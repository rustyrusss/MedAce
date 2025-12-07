<?php
// Test Email Script - Place in root directory
// Access via: http://localhost/MedAce/test_email.php

require_once 'config/email_config.php';

// Replace with your email for testing
$testEmail = 'arcayeraallain@gmail.com';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Test - MedAce</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 8px; color: #065f46; }
        .error { background: #fee2e2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px; color: #991b1b; }
        .info { background: #dbeafe; border: 2px solid #3b82f6; padding: 20px; border-radius: 8px; color: #1e40af; }
        h1 { color: #1f2937; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 MedAce Email Configuration Test</h1>";

// Test 1: Check if PHPMailer is installed
echo "<h2>Test 1: PHPMailer Installation</h2>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<div class='success'>✅ PHPMailer is installed correctly!</div>";
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo "<div class='error'>❌ PHPMailer not found. Run: composer require phpmailer/phpmailer</div>";
    exit;
}

// Test 2: Check email configuration
echo "<h2>Test 2: Email Configuration</h2>";
echo "<div class='info'>";
echo "<strong>Method:</strong> " . EMAIL_METHOD . "<br>";
echo "<strong>SMTP Host:</strong> " . SMTP_HOST . "<br>";
echo "<strong>SMTP Port:</strong> " . SMTP_PORT . "<br>";
echo "<strong>Username:</strong> " . SMTP_USERNAME . "<br>";
echo "<strong>From Email:</strong> " . SMTP_FROM_EMAIL . "<br>";
echo "<strong>Password Set:</strong> " . (SMTP_PASSWORD !== 'your-app-password' ? '✅ Yes' : '❌ Not configured') . "<br>";
echo "</div>";

// Test 3: Send test email
echo "<h2>Test 3: Send Test Email</h2>";
if (SMTP_PASSWORD === 'your-app-password') {
    echo "<div class='error'>❌ Please configure your Gmail credentials in email_config.php first!</div>";
} else {
    echo "<div class='info'>Sending test email to: <strong>{$testEmail}</strong></div>";
    
    $subject = "MedAce - Test Email";
    $message = getPendingEmailHTML("Test", "User", "Student");
    
    $result = sendEmail($testEmail, $subject, $message);
    
    if ($result) {
        echo "<div class='success'>
            <h3>✅ Email Sent Successfully!</h3>
            <p>Check your inbox at: <strong>{$testEmail}</strong></p>
            <p>If you don't see it, check your spam folder.</p>
        </div>";
    } else {
        echo "<div class='error'>
            <h3>❌ Email Failed to Send</h3>
            <p>Check the error log for details:</p>
            <pre>C:\\xampp\\apache\\logs\\error.log</pre>
            <p><strong>Common issues:</strong></p>
            <ul>
                <li>Wrong Gmail credentials</li>
                <li>App Password not generated</li>
                <li>2-Step Verification not enabled</li>
                <li>Firewall blocking port 587</li>
            </ul>
        </div>";
    }
}

echo "</body></html>";
?>