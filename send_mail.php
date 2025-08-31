<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendGrievanceEmail($toEmail, $senderName, $messageText, $grievanceStatus, $grievanceId = null)
{
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Port = 2525;
        $mail->SMTPSecure = 'tls';
        $mail->Username = 'patilom421@gmail.com';
        $mail->Password = '';

        $mail->setFrom('patilom421@gmail.com', $senderName);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Grievance Status Update' . ($grievanceId ? " - #$grievanceId" : "");

        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px; }
                .status { padding: 8px 12px; background: #f5f5f5; display: inline-block; border-radius: 4px; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Grievance Update</h2>
                <p><strong>Status:</strong> <span class='status'>{$grievanceStatus}</span></p>
                " . ($grievanceId ? "<p><strong>Grievance ID:</strong> #{$grievanceId}</p>" : "") . "
                <p><strong>Message:</strong></p>
                <p>{$messageText}</p>
                <div class='footer'>
                    <p>This is an automated message from the Grievance Management System</p>
                    <p>Please do not reply to this email</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $emailBody;
        $mail->AltBody = "Grievance Status Update\n\nStatus: {$grievanceStatus}\n" .
            ($grievanceId ? "Grievance ID: #{$grievanceId}\n" : "") .
            "Message: {$messageText}\n\nThis is an automated message";

        if ($mail->send()) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}

// Test 01

$toEmail = "johndoe@gmail.com";
$senderName = "Admin";
$messageText = "Your grievance has been reviewed and is being processed.";
$grievanceStatus = "In Progress";
$grievanceId = "GRV-2025-001";

$result = sendGrievanceEmail($toEmail, $senderName, $messageText, $grievanceStatus, $grievanceId);
if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}