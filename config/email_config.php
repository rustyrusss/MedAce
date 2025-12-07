<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email Configuration with PHPMailer

// Choose email method: 'smtp' or 'mail'
if (!defined('EMAIL_METHOD')) {
    define('EMAIL_METHOD', 'smtp');
}

// SMTP Configuration (for Gmail)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'medacewebreviewer@gmail.com'); 
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'xrwj rftq skmo zbur'); // App Password
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'medacewebreviewer@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'MedAce Registration');

/**
 * Main email sender
 */
function sendEmail($to, $subject, $message) {
    if (EMAIL_METHOD === 'smtp') {
        return sendEmailSMTP($to, $subject, $message);
    } else {
        return sendEmailSimple($to, $subject, $message);
    }
}

/**
 * Send email using PHPMailer (SMTP)
 */
function sendEmailSMTP($to, $subject, $message) {

    // Correct autoload path for PHPMailer
    $vendorPath = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($vendorPath)) {
        error_log("PHPMailer not found at: $vendorPath");
        return false;
    }

    require_once $vendorPath;

    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // ✅ FIXED
        $mail->Port       = SMTP_PORT;

        // Disable SSL verification for localhost
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Fallback email using mail()
 */
function sendEmailSimple($to, $subject, $message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";

    try {
        $result = mail($to, $subject, $message, $headers);
        if (!$result) {
            error_log("Failed to send email to: $to");
        }
        return $result;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

/**
 * HTML: Approved Email
 */
function getApprovalEmailHTML($firstname, $lastname, $username) {
    $loginUrl = 'https://medace.transcend-enterprise.com/public/index.php';


    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial; line-height: 1.6; background-color: #f3f4f6; }
            .container { max-width: 600px; margin: auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #0d9488, #3b82f6); padding: 30px; color: white; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: linear-gradient(135deg, #0d9488, #3b82f6); padding: 12px 30px; color: white; border-radius: 8px; text-decoration: none; }
            .footer { text-align: center; color: #6b7280; margin-top: 20px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'><h1>🎉 Account Approved!</h1></div>
            <div class='content'>
                <h2>Hello {$firstname} {$lastname},</h2>
                <p>Your MedAce account has been approved.</p>
                <p><strong>Username:</strong> {$username}</p>
                <center><a href='{$loginUrl}' class='button'>Log In Now</a></center>
            </div>
            <div class='footer'>This is an automated message. Do not reply.</div>
        </div>
    </body>
    </html>";
}

/**
 * HTML: Pending Email
 */
function getPendingEmailHTML($firstname, $lastname, $role) {
    return "
    <!DOCTYPE html>
    <html>
    <body style='font-family: Arial; background:#f3f4f6; padding:20px;'>
        <div style='max-width:600px;margin:auto;background:white;padding:30px;border-radius:10px;'>
            <h2>⏳ Registration Received!</h2>
            <p>Hello {$firstname} {$lastname},</p>
            <p>Your account as <strong>{$role}</strong> is pending approval.</p>
            <p>You will receive another email once approved.</p>
        </div>
    </body>
    </html>";
}
?>
