<?php

/**
 * Send a grievance status update email to a student
 * 
 * @param string $email_id       Student's email address
 * @param string $sender_name    Name of the sender (admin/incharge)
 * @param string $text           Message content
 * @param string $grievance_status Current status of the grievance
 * @param string $grievance_id   Unique ID of the grievance
 * @param string $subject        Email subject (optional)
 * @return bool                  True if email sent successfully, false otherwise
 */
function send_grievance_email($email_id, $sender_name, $text, $grievance_status, $grievance_id, $subject = "Grievance Status Update")
{
    // Validate email
    if (!filter_var($email_id, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $email_id");
        return false;
    }

    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $sender_name <noreply@yourdomain.com>" . "\r\n";

    // Create email template with minimalist design
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                border-bottom: 1px solid #eeeeee;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .content {
                margin-bottom: 20px;
            }
            .status {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 4px;
                background-color: #f8f8f8;
                border: 1px solid #e0e0e0;
                font-size: 14px;
            }
            .footer {
                font-size: 12px;
                color: #777777;
                border-top: 1px solid #eeeeee;
                padding-top: 10px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Grievance Update</h2>
            <p>Grievance ID: <strong>' . htmlspecialchars($grievance_id) . '</strong></p>
        </div>
        
        <div class="content">
            <p>' . nl2br(htmlspecialchars($text)) . '</p>
            
            <p>Current Status: <span class="status">' . htmlspecialchars($grievance_status) . '</span></p>
        </div>
        
        <div class="footer">
            <p>This is an automated message from the Grievance Management System.</p>
            <p>Please do not reply to this email. If you need further assistance, please login to the Grievance Portal.</p>
        </div>
    </body>
    </html>
    ';

    // Send email
    $sent = mail($email_id, $subject, $message, $headers);

    // Log the result
    if ($sent) {
        error_log("Grievance email sent successfully to $email_id for grievance ID: $grievance_id");
    } else {
        error_log("Failed to send grievance email to $email_id for grievance ID: $grievance_id");
    }

    return $sent;
}

/**
 * Example usage
 */
/*
$result = send_grievance_email(
    'student@example.com',
    'Grievance Committee',
    'We have reviewed your grievance regarding the canteen facilities. Our team is currently working on resolving this issue.',
    'In Progress',
    'GRV-2025-0042',
    'Update on your Grievance #GRV-2025-0042'
);

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}
*/
